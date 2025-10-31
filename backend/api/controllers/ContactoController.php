<?php

require_once __DIR__ . '/../services/ContactoService.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/RateLimiter.php';
require_once __DIR__ . '/../helpers/AuditLogger.php';

class ContactoController {
    private $contactoService;

    public function __construct() {
        $this->contactoService = new ContactoService();
    }

    /**
     * Crear un nuevo mensaje de contacto
     * POST /api/models/contacto
     */
    public function crear() {
        try {
            // SEGURIDAD: Rate limiting para prevenir spam (5 mensajes por hora)
            RateLimiter::requireLimit('contact', 5, 3600);

            // Verificar que sea POST
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode([
                    'success' => false,
                    'message' => 'Método no permitido'
                ]);
                return;
            }

            // Obtener los datos JSON del cuerpo de la petición
            $input = file_get_contents('php://input');
            $datos = json_decode($input, true);

            // Verificar que se recibieron datos válidos
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Datos JSON inválidos'
                ]);
                return;
            }

            // Procesar el mensaje
            $resultado = $this->contactoService->crearMensaje($datos);

            // Establecer código de respuesta apropiado
            if ($resultado['success']) {
                http_response_code(201); // Creado

                // AUDIT LOG: Mensaje de contacto enviado
                AuditLogger::log(
                    AuditLogger::ACTION_CONTACT_SUBMITTED,
                    null,
                    "Mensaje de contacto enviado desde: " . ($datos['email'] ?? 'email no proporcionado'),
                    ['nombre' => $datos['nombre'] ?? 'N/A', 'asunto' => $datos['asunto'] ?? 'N/A']
                );
            } else {
                http_response_code(400); // Error de validación
            }

            echo json_encode($resultado);

        } catch (Exception $e) {
            error_log("Error en ContactoController::crear: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Obtener todos los mensajes (solo administradores)
     * GET /api/contacto
     */
    public function listar() {
        try {
            // SEGURIDAD: Verificar que el usuario sea administrador
            AuthHelper::requireAdmin();

            // Obtener parámetros de paginación
            $pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
            $porPagina = isset($_GET['porPagina']) ? min(100, max(1, intval($_GET['porPagina']))) : 20;

            $resultado = $this->contactoService->obtenerMensajes($pagina, $porPagina);

            if ($resultado['success']) {
                http_response_code(200);
            } else {
                http_response_code(500);
            }

            echo json_encode($resultado);

        } catch (Exception $e) {
            error_log("Error en ContactoController::listar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Obtener un mensaje específico (solo administradores)
     * GET /api/contacto/{id}
     */
    public function obtener($id) {
        try {
            // SEGURIDAD: Verificar que el usuario sea administrador
            AuthHelper::requireAdmin();

            // Validar ID
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
                return;
            }

            $resultado = $this->contactoService->obtenerMensaje((int)$id);

            if ($resultado['success']) {
                http_response_code(200);
            } else {
                http_response_code(404);
            }

            echo json_encode($resultado);

        } catch (Exception $e) {
            error_log("Error en ContactoController::obtener: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Eliminar un mensaje (solo administradores)
     * DELETE /api/contacto/{id}
     */
    public function eliminar($id) {
        try {
            // SEGURIDAD: Verificar que el usuario sea administrador
            AuthHelper::requireAdmin();

            // Validar ID
            if (!is_numeric($id) || $id <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
                return;
            }

            $resultado = $this->contactoService->eliminarMensaje((int)$id);

            if ($resultado['success']) {
                http_response_code(200);
            } else {
                http_response_code(404);
            }

            echo json_encode($resultado);

        } catch (Exception $e) {
            error_log("Error en ContactoController::eliminar: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor'
            ]);
        }
    }

    /**
     * Obtener estadísticas (solo administradores)
     * GET /api/contacto/estadisticas
     */
    public function estadisticas() {
        try {
            // SEGURIDAD: Verificar que el usuario sea administrador
            AuthHelper::requireAdmin();

            $resultado = $this->contactoService->obtenerEstadisticas();

            if ($resultado['success']) {
                http_response_code(200);
            } else {
                http_response_code(500);
            }

            echo json_encode($resultado);

        } catch (Exception $e) {
            error_log("Error en ContactoController::estadisticas: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error interno del servidor'
            ]);
        }
    }
}