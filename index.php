<?php
/**
 * Punto de entrada de la API (Front Controller):
 *  - Carga las dependencias principales.
 *  - Configura cabeceras comunes (JSON, CORS básico).
 *  - Resuelve la ruta solicitada y delega la ejecución al controlador correspondiente.
 *
 * Cómo ejecutar localmente (modo embebido de PHP):
 *  - Desde el directorio del proyecto: php -S localhost:8000
 *  - Luego puedes probar con el cliente simple index.html o Postman, cURL, etc.
 */

require_once 'api/config/Database.php';
require_once 'api/repositories/UserRepository.php';
require_once 'api/services/AuthService.php';
require_once 'api/controllers/AuthController.php';
require_once 'api/services/PerfilService.php';
require_once 'api/controllers/PerfilController.php';

// Cabeceras comunes para JSON y CORS (ajusta según tu necesidad real de seguridad)
header('Content-Type: application/json'); // El cliente interpreta la respuesta como JSON
header('Access-Control-Allow-Origin: *'); // Permite cualquier origen (en producción conviene restringir)
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 💡 CAMBIO NECESARIO: Manejar la solicitud OPTIONS antes de cualquier otra lógica de enrutamiento.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Parseo de la URL solicitada; ejemplo: /register -> ['register']
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // Ruta sin host
    $uri = explode('/', trim((string)$uri, '/')); // Partes de la ruta
    $method = strtoupper($_SERVER['REQUEST_METHOD']); // Método HTTP en mayúsculas

    // Recurso principal (primer segmento) y un posible parámetro (segundo segmento)
    $resource = $uri[0] ?? '';
    $param = $uri[1] ?? '';

    // En este proyecto, un solo controlador maneja login/registro/health
    $controller = new AuthController();

    /**
     * Rutas de la API disponibles en este punto del proyecto:
     *  - POST /login     -> Autenticación con email o username
     *  - POST /register  -> Crear nuevo usuario
     *  - GET  /health    -> Estado de la API y servicios dependientes
     */

    switch ($resource) { // Enrutamiento muy simple basado en el primer segmento
        case 'perfil':
            $controller = new PerfilController();
            if ($method === 'GET') {
                $userId = $_GET['id'] ?? null;
                echo json_encode($controller->getPerfil($userId));
                break;
            }
            if ($method === 'POST' && isset($uri[1]) && $uri[1] === 'avatar') {
                // Espera JSON con userId y avatarUrl
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                $userId = $data['userId'] ?? null;
                $avatarUrl = $data['avatarUrl'] ?? null;
                if ($userId && $avatarUrl) {
                    echo json_encode($controller->updateAvatar($userId, $avatarUrl));
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan datos para actualizar el avatar.']);
                }
                break;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;
        case 'login':
            if ($method === 'POST') {
                $controller->login();
                break;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;

        case 'register':
            if ($method === 'POST') {
                $controller->register();
                break;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;

        case 'health':
            // Estado general de la API y verificación básica de la base de datos
            $status = 'OK';
            $services = [
                'database' => ['status' => 'unknown'],
            ];

            // Intentamos hacer ping a la DB para verificar que responde
            try {
                $db = Database::getInstance()->getConnection();
                if ($db && $db->ping()) {
                    $services['database']['status'] = 'up';
                } else {
                    $services['database']['status'] = 'down';
                    $status = 'DEGRADED';
                }
            } catch (Exception $e) {
                $services['database']['status'] = 'down';
                $status = 'DEGRADED';
            }

            // Catálogo de endpoints expuestos (útil como documentación rápida)
            $endpoints = [
                ['method' => 'GET', 'path' => '/health', 'description' => 'Estado de la aplicación y servicios'],
                ['method' => 'POST', 'path' => '/login', 'description' => 'Login con email o username'],
                ['method' => 'POST', 'path' => '/register', 'description' => 'Crear nuevo usuario'],
                ['method' => 'GET', 'path' => '/perfil?id={id}', 'description' => 'Obtener perfil de usuario por id'],
                ['method' => 'POST', 'path' => '/perfil/avatar', 'description' => 'Actualizar avatar de usuario'],
                // Agrega aquí nuevas rutas futuras...
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'status' => $status,
                'timestamp' => date('c'), // ISO-8601
                'services' => $services,
                'endpoints' => $endpoints,
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe el recurso.']);
            break;
    }
} catch (Exception $e) {
    // Captura de errores no controlados
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
        // 'detail' => $e->getMessage(), // Evita exponer detalles en producción
    ]);
}
