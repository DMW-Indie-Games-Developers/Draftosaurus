<?php

/**
 * BACKEND API - SOLO RESPONDE JSON
 * Puerto: 4000
 * Ejecutar: php -S localhost:4000 -t backend/public
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

// Cargar dependencias
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
require_once __DIR__ . '/../api/controllers/ContactoController.php';
require_once __DIR__ . '/../api/repositories/ContactoRepository.php';
require_once __DIR__ . '/../api/services/ContactoService.php';
require_once __DIR__ . '/../usermodel/Contacto.php';

AuthHelper::iniciarSesion();

// Cabeceras CORS y JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = explode('/', trim((string) $uri, '/'));
    $method = strtoupper($_SERVER['REQUEST_METHOD']);

    $resource = $uri[0] ?? '';
    $param = $uri[1] ?? '';

    $controller = new AuthController();

    switch ($resource) {
        /* ---------- AUTH ---------- */
        case 'login':
            if ($method === 'POST') {
                $controller->login();
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        case 'register':
            if ($method === 'POST') {
                $controller->register();
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

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

        case 'verify-user':
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                $identifier = trim($data['identifier'] ?? '');
                $password = $data['password'] ?? '';

                if (!$identifier || !$password) {
                    echo json_encode(['success' => false, 'message' => 'Faltan credenciales']);
                    exit;
                }

                $result = $controller->verifyCredentials($identifier, $password);
                echo json_encode($result);
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        /* ---------- PERFIL ---------- */
        case 'perfil':
            $controller = new PerfilController();

            // GET /perfil/me
            if ($method === 'GET' && ($uri[1] ?? '') === 'me') {
                $user = AuthHelper::requireActiveUser();
                echo json_encode([
                    'success' => true,
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'nickname' => $user['nickname'] ?? null,
                    'avatar' => $user['avatar'] ?? 'img/isotipoOficial.png',
                    'puntuacion_total' => $user['puntuacion_total'] ?? 0,
                    'partidas_jugadas' => $user['partidas_jugadas'] ?? 0,
                    'partidas_ganadas' => $user['partidas_ganadas'] ?? 0,
                    'created_at' => $user['created_at']
                ]);
                exit;
            }

            // GET /perfil/{id}
            if ($method === 'GET' && isset($uri[1]) && is_numeric($uri[1])) {
                $userId = (int)$uri[1];
                echo json_encode($controller->getPerfil($userId));
                exit;
            }

            // POST /perfil/avatar
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

            // POST /perfil/nickname
            if ($method === 'POST' && ($uri[1] ?? '') === 'nickname') {
                $user = AuthHelper::requireActiveUser();
                $raw = file_get_contents('php://input');
                $data = json_decode($raw, true);
                $nickname = $data['nickname'] ?? null;
                echo json_encode($controller->updateNickname($user['id'], $nickname));
                exit;
            }

            // GET /perfil/ranking
            if ($method === 'GET' && ($uri[1] ?? '') === 'ranking') {
                $limit = $_GET['limit'] ?? 10;
                $service = new PerfilService();
                $ranking = $service->getRanking($limit);
                echo json_encode(['success' => true, 'ranking' => $ranking]);
                exit;
            }

            // GET /perfil/user-ranking
            if ($method === 'GET' && ($uri[1] ?? '') === 'user-ranking') {
                $user = AuthHelper::requireActiveUser();
                $service = new PerfilService();
                $userRanking = $service->getUserRanking($user['id']);
                echo json_encode(['success' => true] + $userRanking);
                exit;
            }

            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        /* ---------- PARTIDAS ---------- */
        case 'misPartidas':
            try {
                $user = AuthHelper::requireActiveUser();
                $controller = new TableroController();
                $controller->obtenerPartidasEnProgreso();
                exit;
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'No autorizado']);
                exit;
            }

        case 'mi-perfil':
            try {
                $user = AuthHelper::requireActiveUser();
                $controller = new PerfilController();
                echo json_encode($controller->getPerfil($user['id']));
            } catch (Exception $e) {
                // requireActiveUser ya respondió
            }
            break;

        /* ---------- HEALTH ---------- */
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
                ['method' => 'GET',  'path' => '/health', 'description' => 'Estado de la aplicación'],
                ['method' => 'POST', 'path' => '/login', 'description' => 'Login con email o username'],
                ['method' => 'POST', 'path' => '/register', 'description' => 'Crear nuevo usuario'],
                ['method' => 'GET',  'path' => '/perfil/me', 'description' => 'Obtener perfil actual'],
            ];
            http_response_code(200);
            echo json_encode(['success' => true, 'status' => $status, 'timestamp' => date('c'), 'services' => $services, 'endpoints' => $endpoints]);
            break;

        /* ---------- API ---------- */
        case 'api':
            $sub = $uri[1] ?? '';

            /* TABLERO */
            if ($sub === 'crearPartida' && $method === 'POST') {
                $controller = new TableroController();
                $controller->crearPartida();
                exit;
            }

            if ($sub === 'guardarEstadoPartida' && $method === 'POST') {
                $controller = new TableroController();
                $controller->guardarEstadoPartida();
                exit;
            }

            if ($sub === 'cargarPartida' && $method === 'GET') {
                $controller = new TableroController();
                $controller->cargarPartida();
                exit;
            }

            if ($sub === 'finalizarPartida' && $method === 'POST') {
                $controller = new TableroController();
                $controller->finalizarPartida();
                exit;
            }

            if ($sub === 'obtenerPuntuaciones' && $method === 'POST') {
                $controller = new TableroController();
                $controller->obtenerPuntuaciones();
                exit;
            }

            /* UPLOAD AVATAR */
            if ($sub === 'upload_avatar.php' && $method === 'POST') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $userId = $user['id'];

                    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('No se recibió ningún archivo válido');
                    }

                    $file = $_FILES['avatar'];

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        throw new Exception('Formato no soportado');
                    }

                    $maxSize = 3 * 1024 * 1024;
                    if ($file['size'] > $maxSize) {
                        throw new Exception('Archivo muy grande');
                    }

                    $uploadDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                        throw new Exception('Error al guardar');
                    }

                    $avatarUrl = 'http://localhost:4000/uploads/avatars/' . $filename;
                    $controller = new PerfilController();
                    $result = $controller->updateAvatar($userId, $avatarUrl);

                    if ($result['success']) {
                        echo json_encode(['success' => true, 'message' => 'Avatar actualizado', 'avatarUrl' => $avatarUrl]);
                    } else {
                        if (file_exists($filepath)) unlink($filepath);
                        echo json_encode($result);
                    }

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
            }

            /* CONTACTO */
            if ($sub === 'models' && isset($uri[2]) && $uri[2] === 'contacto') {
                $controller = new ContactoController();

                if ($method === 'POST') {
                    $controller->crear();
                    exit;
                }

                if ($method === 'GET') {
                    if (isset($uri[3]) && is_numeric($uri[3])) {
                        $controller->obtener((int)$uri[3]);
                        exit;
                    } elseif (isset($uri[3]) && $uri[3] === 'estadisticas') {
                        $controller->estadisticas();
                        exit;
                    } else {
                        $controller->listar();
                        exit;
                    }
                }

                if ($method === 'DELETE' && isset($uri[3]) && is_numeric($uri[3])) {
                    $controller->eliminar((int)$uri[3]);
                    exit;
                }

                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Endpoint no encontrado']);
                exit;
            }

            /* RANKING */
            if ($sub === 'ranking' && $method === 'GET') {
                try {
                    $controller = new RankingController();
                    $controller->showRanking();
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Error al obtener el ranking']);
                }
                exit;
            }

            /* TABLERO (anidado) */
            if ($sub === 'tablero') {
                $controller = new TableroController();
                $action = $uri[2] ?? '';

                switch ($action) {
                    case 'guardarEstadoPartida':
                        if ($method === 'POST') {
                            $controller->guardarEstadoPartida();
                            exit;
                        }
                        break;

                    case 'cargarPartida':
                        if ($method === 'GET') {
                            $controller->cargarPartida();
                            exit;
                        }
                        break;

                    case 'crearPartida':
                        if ($method === 'POST') {
                            $controller->crearPartida();
                            exit;
                        }
                        break;

                    case 'finalizarPartida':
                        if ($method === 'POST') {
                            $controller->finalizarPartida();
                            exit;
                        }
                        break;

                    case 'obtenerPartidasEnProgreso':
                        if ($method === 'GET') {
                            $controller->obtenerPartidasEnProgreso();
                            exit;
                        }
                        break;

                    case 'validarJugada':
                        if ($method === 'POST') {
                            $controller->validarJugada();
                            exit;
                        }
                        break;

                    case 'obtenerPuntuaciones':
                        if ($method === 'POST') {
                            $controller->obtenerPuntuaciones();
                            exit;
                        }
                        break;

                    case 'validarColocacionDino':
                        if ($method === 'POST') {
                            $controller->validarColocacionDino();
                            exit;
                        }
                        break;
                }

                http_response_code(404);
                echo json_encode(['error' => 'Endpoint tablero no encontrado']);
                exit;
            }

            /* ADMIN */
            if ($sub === 'admin') {
                $controller = new AdminController();
                $op = $uri[2] ?? '';
                $id = (int)($uri[3] ?? 0);
                $extra = $uri[4] ?? '';

                switch ($method) {
                    case 'GET':
                        if ($op === 'users' && $id === 0) {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->listUsers();
                            } catch (Exception $e) {}
                            exit;
                        } elseif ($op === 'users' && $id > 0) {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->getUser($id);
                            } catch (Exception $e) {}
                            exit;
                        } elseif ($op === 'messages') {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->listMessages();
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'POST':
                        if ($op === 'users') {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->createUser();
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'PUT':
                        if ($op === 'users' && $id > 0) {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->updateUser($id);
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;

                    case 'PATCH':
                        if ($op === 'users' && $id > 0 && $extra === 'status') {
                            try {
                                AuthHelper::requireActiveUser();
                                $controller->toggleUserStatus($id);
                            } catch (Exception $e) {}
                            exit;
                        }
                        break;
                }

                http_response_code(404);
                echo json_encode(['error' => 'Ruta admin no encontrada']);
                exit;
            }

            http_response_code(404);
            echo json_encode(['error' => 'API endpoint no encontrado']);
            break;

        /* SERVIR AVATARES */
        case 'uploads':
            if (isset($uri[1]) && $uri[1] === 'avatars' && isset($uri[2])) {
                $filename = $uri[2];
                $filepath = __DIR__ . '/uploads/avatars/' . $filename;

                if (file_exists($filepath) && strpos(realpath($filepath), realpath(__DIR__ . '/uploads/avatars/')) === 0) {
                    $mimeType = mime_content_type($filepath);
                    header('Access-Control-Allow-Origin: http://localhost:3000');
                    header('Access-Control-Allow-Credentials: true');
                    header('Content-Type: ' . $mimeType);
                    header('Content-Length: ' . filesize($filepath));
                    header('Cache-Control: public, max-age=31536000');
                    readfile($filepath);
                    exit;
                }
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
            break;

        /* UPLOAD AVATAR (ruta directa) */
        case 'upload_avatar':
            if ($method === 'POST') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $userId = $user['id'];

                    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('No se recibió ningún archivo válido');
                    }

                    $file = $_FILES['avatar'];

                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    if (!in_array($file['type'], $allowedTypes)) {
                        throw new Exception('Formato no soportado');
                    }

                    $maxSize = 3 * 1024 * 1024;
                    if ($file['size'] > $maxSize) {
                        throw new Exception('Archivo muy grande');
                    }

                    $uploadDir = __DIR__ . '/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                        throw new Exception('Error al guardar');
                    }

                    $avatarUrl = 'http://localhost:4000/uploads/avatars/' . $filename;
                    $controller = new PerfilController();
                    $result = $controller->updateAvatar($userId, $avatarUrl);

                    if ($result['success']) {
                        echo json_encode(['success' => true, 'message' => 'Avatar actualizado', 'avatarUrl' => $avatarUrl]);
                    } else {
                        if (file_exists($filepath)) unlink($filepath);
                        echo json_encode($result);
                    }

                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Recurso no encontrado']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

ob_end_flush();
