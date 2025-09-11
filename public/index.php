<?php
/* ---------- 1.  Silenciar CUALQUIER output ---------- */
ob_start();          // bufferiza todo
ini_set('display_errors', 0);   // no imprime warnings al browser

if ($_SERVER['REQUEST_URI'] === '/tablero.html') {
    header('Location: /tablero');
    exit;
}

// Agregar esta redirección para index.html
if ($_SERVER['REQUEST_URI'] === '/index.html') {
    header('Location: /home');
    exit;
}

// ✅ Redirigir /home.php → /home
if ($_SERVER['REQUEST_URI'] === '/home.php') {
    header('Location: /home', true, 301);
    exit;
}

/**
 * Punto de entrada de la API (Front Controller):
 *  - Carga las dependencias principales.
 *  - Configura cabeceras comunes (JSON, CORS básico).
 *  - Resuelve la ruta solicitada y delega la ejecución al controlador correspondiente.
 *
 * Cómo ejecutar localmente (modo embebido de PHP):
 *  - Desde el directorio del proyecto: php -S localhost:8000 -t public
 *  - Luego puedes probar con el cliente simple index.html o Postman, cURL, etc.
 */
require_once __DIR__ . '/../api/controllers/AdminController.php';
require_once __DIR__ . '/../api/repositories/AdminRepository.php';
require_once __DIR__ . '/../api/services/AdminService.php';
require_once __DIR__ . '/../api/helpers/AuthHelper.php';
require_once __DIR__ . '/../api/config/Database.php';
require_once __DIR__ . '/../api/repositories/UserRepository.php';
require_once __DIR__ . '/../api/services/AuthService.php';
require_once __DIR__ . '/../api/controllers/AuthController.php';
require_once __DIR__ . '/../api/services/TableroService.php';
require_once __DIR__ . '/../api/controllers/TableroController.php';
require_once __DIR__ . '/../api/repositories/TableroRepository.php';
require_once __DIR__ . '/../api/controllers/PerfilController.php';
require_once __DIR__ . '/../api/repositories/PerfilRepository.php';
require_once __DIR__ . '/../api/services/PerfilService.php';
require_once __DIR__ . '/../api/repositories/RankingRepository.php';
require_once __DIR__ . '/../api/services/RankingService.php';
require_once __DIR__ . '/../api/controllers/RankingController.php';

AuthHelper::iniciarSesion();

// Cabeceras comunes para JSON y CORS (ajusta según tu necesidad real de seguridad)
header('Content-Type: application/json'); // El cliente interpreta la respuesta como JSON
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true'); // Permite cualquier origen (en producción conviene restringir)
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar la solicitud OPTIONS antes de cualquier otra lógica de enrutamiento.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Parseo de la URL solicitada; ejemplo: /register -> ['register']
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // Ruta sin host
    $uri = explode('/', trim((string) $uri, '/')); // Partes de la ruta
    $method = strtoupper($_SERVER['REQUEST_METHOD']); // Método HTTP en mayúsculas

    // Recurso principal (primer segmento) y un posible parámetro (segundo segmento)
    $resource = $uri[0] ?? '';
    $param = $uri[1] ?? '';

    // En este proyecto, un solo controlador maneja login/registro/health
    $controller = new AuthController();

    switch ($resource) {
        /* ---------- HOME / LANDING ---------- */
        case '':
        case 'home':
            require_once __DIR__ . '/../home.php';
            exit;

        /* ---------- Rutas API/JSON ---------- */
        case 'perfil':
            $controller = new PerfilController();

            /* 1) HTML propio (logueado) */
            if ($method === 'GET' && !isset($uri[1])) {
                require_once __DIR__ . '/../perfil.php';   // sólo valida sesión y sirve HTML
                exit;
            }

            /* 2) JSON propio (logueado) – /perfil/me */
if ($method === 'GET' && ($uri[1] ?? '') === 'me') {
    $user = AuthHelper::requireActiveUser(); // ya devuelve el usuario logueado
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'avatar' => $user['avatar'] ?? 'img/isotipoOficial.png',
        'puntuacion_total' => $user['puntuacion_total'] ?? 0,
        'partidas_jugadas' => $user['partidas_jugadas'] ?? 0,
        'partidas_ganadas' => $user['partidas_ganadas'] ?? 0,
        'created_at' => $user['created_at']
    ]);
    exit;
}

            /* 3) JSON ajeno (opcional, público) */
            if ($method === 'GET' && isset($uri[1]) && is_numeric($uri[1])) {
                $userId = (int)$uri[1];
                echo json_encode($controller->getPerfil($userId));
                exit;
            }

            /* 4) Update avatar */
            if ($method === 'POST' && ($uri[1] ?? '') === 'avatar') {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                $userId = $data['userId'] ?? null;
                $avatarUrl = $data['avatarUrl'] ?? null;
                if ($userId && $avatarUrl) {
                    echo json_encode($controller->updateAvatar($userId, $avatarUrl));
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
                }
                exit;
            }

            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        case 'login':
            if ($method === 'POST') {
                $controller->login();
                break;
            }
            if ($method === 'GET') {
                // Mostrar formulario de login
                require_once __DIR__ . '/../login.php';
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        case 'register':
            if ($method === 'POST') {
                $controller->register();
                break;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            break;

        case 'mi-perfil':
            try {
                $user = AuthHelper::requireActiveUser();
                $controller = new PerfilController();
                echo json_encode($controller->getPerfil($user['id']));
            } catch (Exception $e) {
                // requireActiveUser ya respondió
            }
            break;

        case 'admin':
            // Cambiar el header Content-Type antes de verificar la sesión
            header('Content-Type: text/html; charset=utf-8');
            
            // Verificar sesión y rol
            if (!isset($_SESSION['userId']) || $_SESSION['rol'] !== 'admin') {
                // Redirigir a login si no está autorizado
                header('Location: /login');
                exit;
            }
            
            // Incluir el archivo admin.php 
            require_once __DIR__ . '/../admin.php';
            exit;

        case 'logout':
            $_SESSION = [];
            session_destroy();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            http_response_code(200);
            echo json_encode(['success' => true]);
            break;

        case 'health':
            $status = 'OK';
            $services = ['database' => ['status' => 'unknown']];
            try {
                $db = Database::getInstance()->getConnection();
                $services['database']['status'] = ($db && $db->ping()) ? 'up' : 'down';
                if ($services['database']['status'] === 'down') $status = 'DEGRADED';
            } catch (Exception $e) {
                $services['database']['status'] = 'down';
                $status = 'DEGRADED';
            }
            $endpoints = [
                ['method' => 'GET',  'path' => '/health',        'description' => 'Estado de la aplicación y servicios'],
                ['method' => 'POST', 'path' => '/login',         'description' => 'Login con email o username'],
                ['method' => 'POST', 'path' => '/register',      'description' => 'Crear nuevo usuario'],
                ['method' => 'GET',  'path' => '/perfil',        'description' => 'Obtener perfil de usuario por id'],
                ['method' => 'POST', 'path' => '/perfil/avatar', 'description' => 'Actualizar avatar de usuario'],
            ];
            http_response_code(200);
            echo json_encode(['success' => true, 'status' => $status, 'timestamp' => date('c'), 'services' => $services, 'endpoints' => $endpoints]);
            break;

        case 'tablero':
            require_once __DIR__ . '/../tablero.php';
            exit;
        case 'debug-session':
            session_start();
            echo json_encode([
                'session' => $_SESSION,
                'cookies' => $_COOKIE,
                'userId' => $_SESSION['userId'] ?? null
            ]);
            exit;
             // --- AÑADIR ESTE NUEVO CASE PARA LA PÁGINA DEL RANKING ---
        case 'ranking':
            require_once __DIR__ . '/../ranking.php';
            exit;
        // --------------------------------------------------------
        // --- AÑADIR ESTE IF PARA EL ENDPOINT DE LA API ---
            if ($sub === 'ranking' && $method === 'GET') {
                $controller = new RankingController();
                $controller->showRanking();
                exit;
            }
            // ------------------------------------------------
            
        case 'api':
            $sub = $uri[1] ?? '';
            if ($sub === 'admin') {
                $controller = new AdminController();
                $op    = $uri[2] ?? '';      // users | messages
                $id    = (int)($uri[3] ?? 0);
                $extra = $uri[4] ?? '';      // status

                error_log("=== API ADMIN ROUTE ===");
                error_log("Method: $method, Op: $op, ID: $id, Extra: $extra");
                error_log("URI completa: " . implode('/', $uri));

                switch ($method) {
                    case 'GET':
                        if ($op === 'users' && $id === 0) {
                            // GET /api/admin/users
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->listUsers();
                            } catch (Exception $e) {}
                            exit;
                        } elseif ($op === 'users' && $id > 0) {
                            // GET /api/admin/users/{id}
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->getUser($id);
                            } catch (Exception $e) {}
                            exit;
                        } elseif ($op === 'messages') {
                            // GET /api/admin/messages
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->listMessages();
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'POST':
                        if ($op === 'users') {
                            // POST /api/admin/users
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->createUser();
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'PUT':
                        if ($op === 'users' && $id > 0) {
                            // PUT /api/admin/users/{id}
                            error_log("Ejecutando updateUser para ID: $id");
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->updateUser($id);
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'PATCH':
                        if ($op === 'users' && $id > 0 && $extra === 'status') {
                            // PATCH /api/admin/users/{id}/status
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->toggleUserStatus($id);
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;
                }
                
                error_log("ERROR: Ruta API admin no encontrada - $method $op");
                http_response_code(404);
                echo json_encode(['error' => 'Ruta admin no encontrada']);
                exit;
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe el recurso.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}

ob_end_flush();