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
            
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
            error_log("=== AdminController::toggleUserStatus - FIN ===");
            
        } catch (Exception $e) {
            error_log("ERROR EXCEPTION en toggleUserStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }

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
}