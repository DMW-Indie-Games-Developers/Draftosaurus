<?php
require_once __DIR__ . '/../services/AdminService.php';

class AdminController
{
    private AdminService $service;

    public function __construct()
    {
        $this->service = new AdminService();
    }

    public function listUsers(): void
    {
        try {
            error_log("=== AdminController::listUsers - INICIO ===");
            $users = $this->service->getAllUsers();
            error_log("Usuarios obtenidos: " . count($users));
            
            http_response_code(200);
            echo json_encode($users);
            error_log("=== AdminController::listUsers - SUCCESS ===");
        } catch (Exception $e) {
            error_log("ERROR en listUsers: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener usuarios']);
        }
    }

    public function getUser(int $id): void
    {
        try {
            error_log("=== AdminController::getUser($id) - INICIO ===");
            $user = $this->service->getUserById($id);
            if ($user) {
                http_response_code(200);
                echo json_encode($user);
                error_log("Usuario $id encontrado y enviado");
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
                error_log("Usuario $id NO encontrado");
            }
            error_log("=== AdminController::getUser - FIN ===");
        } catch (Exception $e) {
            error_log("ERROR en getUser: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener usuario']);
        }
    }

    public function createUser(): void
    {
        try {
            error_log("=== AdminController::createUser - INICIO ===");

            // SEGURIDAD: Protección CSRF para operaciones sensibles
            CsrfHelper::requireValidToken();

            // Leer y debugear el input raw
            $rawInput = file_get_contents('php://input');
            error_log("Raw input recibido: " . $rawInput);
            
            $data = json_decode($rawInput, true);
            
            if (!$data) {
                error_log("ERROR: JSON inválido o vacío");
                error_log("JSON decode error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
                return;
            }
            
            error_log("Datos JSON parseados: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            
            // Validar campos requeridos
            $requiredFields = ['name', 'email', 'password'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                error_log("ERROR: Campos faltantes: " . implode(', ', $missingFields));
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Faltan campos requeridos: ' . implode(', ', $missingFields)
                ]);
                return;
            }
            
            // Sanitizar datos
            $data['name'] = trim($data['name']);
            $data['email'] = trim($data['email']);
            // No trimear password para preservar espacios si son intencionales
            
            error_log("Datos sanitizados - name: '" . $data['name'] . "', email: '" . $data['email'] . "'");
            
            // Delegar al service
            $result = $this->service->createUser($data);

            error_log("Resultado del service: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            // AUDIT LOG: Usuario creado por administrador
            if ($result['success']) {
                $adminUser = AuthHelper::getCurrentUser();
                AuditLogger::log(
                    AuditLogger::ACTION_USER_CREATED,
                    $adminUser['id'],
                    "Administrador creó nuevo usuario: " . $data['name'],
                    [
                        'created_user_id' => $result['user']['id'] ?? null,
                        'created_username' => $data['name'],
                        'created_email' => $data['email']
                    ]
                );
            }

            // Responder con el código HTTP apropiado
            $httpCode = $result['success'] ? 201 : 400;
            http_response_code($httpCode);
            echo json_encode($result);
            
            error_log("Respuesta enviada con código: $httpCode");
            error_log("=== AdminController::createUser - FIN ===");
            
        } catch (Exception $e) {
            error_log("=== ERROR EXCEPTION en AdminController::createUser ===");
            error_log("Exception message: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ]);
        }
    }

    public function updateUser(int $id): void
    {
        try {
            error_log("=== AdminController::updateUser($id) - INICIO ===");

            // SEGURIDAD: Protección CSRF
            CsrfHelper::requireValidToken();

            $rawInput = file_get_contents('php://input');
            error_log("Raw input para update: " . $rawInput);
            
            $data = json_decode($rawInput, true);
            if (!$data) {
                error_log("ERROR: JSON inválido en update");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos JSON inválidos']);
                return;
            }
            
            error_log("Datos para actualizar ID $id: " . json_encode($data, JSON_UNESCAPED_UNICODE));

            $result = $this->service->updateUser($id, $data);

            error_log("Resultado update: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            // AUDIT LOG: Usuario actualizado
            if ($result['success']) {
                $adminUser = AuthHelper::getCurrentUser();
                AuditLogger::log(
                    AuditLogger::ACTION_USER_UPDATED,
                    $adminUser['id'],
                    "Administrador actualizó usuario ID: $id",
                    [
                        'updated_user_id' => $id,
                        'fields_updated' => array_keys($data)
                    ]
                );
            }

            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
            error_log("=== AdminController::updateUser - FIN ===");
            
        } catch (Exception $e) {
            error_log("ERROR EXCEPTION en updateUser: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
        }
    }

    public function toggleUserStatus(int $id): void
    {
        try {
            error_log("=== AdminController::toggleUserStatus($id) - INICIO ===");

            // SEGURIDAD: Protección CSRF
            CsrfHelper::requireValidToken();

            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!$data || !isset($data['status'])) {
                error_log("ERROR: Status no proporcionado");
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Status requerido']);
                return;
            }

            error_log("Cambiando status de usuario $id a: " . $data['status']);

            $result = $this->service->toggleUserStatus($id, $data['status']);

            error_log("Resultado cambio status: " . json_encode($result, JSON_UNESCAPED_UNICODE));

            // AUDIT LOG: Estado de usuario cambiado
            if ($result['success']) {
                $adminUser = AuthHelper::getCurrentUser();
                AuditLogger::log(
                    AuditLogger::ACTION_USER_STATUS_CHANGED,
                    $adminUser['id'],
                    "Administrador cambió estado de usuario ID: $id a {$data['status']}",
                    [
                        'affected_user_id' => $id,
                        'new_status' => $data['status']
                    ]
                );
            }

            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
            error_log("=== AdminController::toggleUserStatus - FIN ===");

        } catch (Exception $e) {
            error_log("ERROR EXCEPTION en toggleUserStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }

    /**
     * SEGURIDAD: Método de cambio de rol ELIMINADO
     *
     * Los cambios de rol son operaciones críticas que deben realizarse
     * directamente en la base de datos por razones de seguridad.
     *
     * Para cambiar el rol de un usuario, ejecutar en MySQL:
     * UPDATE users SET rol = 'admin' WHERE id = X;
     *
     * Este método fue eliminado para prevenir:
     * - Auto-demotion (admin se quita a sí mismo el rol)
     * - Eliminación accidental del último admin
     * - Escalación de privilegios por vulnerabilidades web
     */

    public function listMessages(): void
    {
        try {
            error_log("=== AdminController::listMessages - INICIO ===");
            $messages = $this->service->getAllMessages();
            error_log("Mensajes obtenidos: " . count($messages));

            http_response_code(200);
            echo json_encode($messages);
            error_log("=== AdminController::listMessages - SUCCESS ===");
        } catch (Exception $e) {
            error_log("ERROR en listMessages: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener mensajes']);
        }
    }

    /**
     * NUEVO: Obtener logs de auditoría
     */
    public function getAuditLogs(): void
    {
        try {
            error_log("=== AdminController::getAuditLogs - INICIO ===");

            // AUDIT LOG: Acceso a logs de auditoría
            $adminUser = AuthHelper::getCurrentUser();
            if ($adminUser) {
                AuditLogger::log(
                    AuditLogger::ACTION_ADMIN_ACCESS,
                    $adminUser['id'],
                    "Administrador accedió a los logs de auditoría"
                );
            }

            // Parámetros de filtrado
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $action = $_GET['action'] ?? null;
            $userId = isset($_GET['userId']) ? (int)$_GET['userId'] : null;

            // Limitar el máximo de resultados
            if ($limit > 1000) {
                $limit = 1000;
            }

            $logs = AuditLogger::getRecentLogs($limit, $action, $userId);

            error_log("Logs de auditoría obtenidos: " . count($logs));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getAuditLogs: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener logs de auditoría'
            ]);
        }
    }

    /**
     * NUEVO: Obtener estadísticas de auditoría
     */
    public function getAuditStats(): void
    {
        try {
            error_log("=== AdminController::getAuditStats - INICIO ===");

            $stats = AuditLogger::getStats();

            error_log("Estadísticas de auditoría obtenidas");

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getAuditStats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener estadísticas'
            ]);
        }
    }

    /**
     * NUEVO: Obtener usuarios actualmente online
     */
    public function getOnlineUsers(): void
    {
        try {
            error_log("=== AdminController::getOnlineUsers - INICIO ===");

            $onlineUsers = SessionTracker::getOnlineUsers();

            error_log("Usuarios online obtenidos: " . count($onlineUsers));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'online_users' => $onlineUsers,
                'count' => count($onlineUsers)
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getOnlineUsers: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener usuarios online'
            ]);
        }
    }

    /**
     * NUEVO: Obtener estadísticas de sesiones
     */
    public function getSessionStats(): void
    {
        try {
            error_log("=== AdminController::getSessionStats - INICIO ===");

            $stats = SessionTracker::getSessionStats();

            error_log("Estadísticas de sesiones obtenidas");

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getSessionStats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener estadísticas de sesiones'
            ]);
        }
    }

    /**
     * NUEVO: Obtener historial de sesiones recientes
     */
    public function getRecentSessions(): void
    {
        try {
            error_log("=== AdminController::getRecentSessions - INICIO ===");

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            if ($limit > 200) $limit = 200; // Máximo 200

            $sessions = SessionTracker::getRecentSessions($limit);

            error_log("Sesiones recientes obtenidas: " . count($sessions));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'sessions' => $sessions,
                'count' => count($sessions)
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getRecentSessions: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener sesiones recientes'
            ]);
        }
    }

    /**
     * NUEVO: Obtener estadísticas generales del sistema
     */
    public function getSystemStats(): void
    {
        try {
            error_log("=== AdminController::getSystemStats - INICIO ===");

            // Estadísticas de usuarios
            $db = Database::getInstance()->getConnection();

            $totalUsers = $db->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
            $activeUsers = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'activo'")->fetch_assoc()['total'];
            $adminUsers = $db->query("SELECT COUNT(*) as total FROM users WHERE rol = 'admin'")->fetch_assoc()['total'];

            // Estadísticas de partidas
            $totalGames = $db->query("SELECT COUNT(*) as total FROM partidas")->fetch_assoc()['total'];
            $activeGames = $db->query("SELECT COUNT(*) as total FROM partidas WHERE finalizada = 0")->fetch_assoc()['total'];
            $finishedGames = $db->query("SELECT COUNT(*) as total FROM partidas WHERE finalizada = 1")->fetch_assoc()['total'];

            // Estadísticas de contacto
            $totalMessages = $db->query("SELECT COUNT(*) as total FROM contacto")->fetch_assoc()['total'];
            $messagesToday = $db->query("SELECT COUNT(*) as total FROM contacto WHERE DATE(fecha_envio) = CURDATE()")->fetch_assoc()['total'];

            // Estadísticas de sesiones
            $sessionStats = SessionTracker::getSessionStats();

            $stats = [
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                    'admins' => $adminUsers,
                    'suspended' => $totalUsers - $activeUsers
                ],
                'games' => [
                    'total' => $totalGames,
                    'active' => $activeGames,
                    'finished' => $finishedGames
                ],
                'messages' => [
                    'total' => $totalMessages,
                    'today' => $messagesToday
                ],
                'sessions' => $sessionStats
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            error_log("ERROR en getSystemStats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener estadísticas del sistema'
            ]);
        }
    }
}