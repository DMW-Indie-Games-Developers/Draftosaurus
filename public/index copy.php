<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------- 1.  Silenciar CUALQUIER output ---------- */
ob_start();
ini_set('display_errors', 0);

// Redirecciones
if ($_SERVER['REQUEST_URI'] === '/tablero.html') {
    header('Location: /tablero');
    exit;
}

if ($_SERVER['REQUEST_URI'] === '/index.html') {
    header('Location: /home');
    exit;
}

if ($_SERVER['REQUEST_URI'] === '/home.php') {
    header('Location: /home', true, 301);
    exit;
}

if ($_SERVER['REQUEST_URI'] === '/ranking.php') {
    header('Location: /ranking', true, 301);
    exit;
}

// Incluir archivos necesarios
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
require_once __DIR__ . '/../api/models/Contacto.php';

AuthHelper::iniciarSesion();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        case '':
        case 'home':
            require_once __DIR__ . '/../home.php';
            exit;

        case 'perfil':
            $controller = new PerfilController();

            if ($method === 'GET' && !isset($uri[1])) {
                require_once __DIR__ . '/../perfil.php';
                exit;
            }

            // ✅ CORREGIDO: Endpoint /perfil/me que devuelve datos completos desde users
            if ($method === 'GET' && ($uri[1] ?? '') === 'me') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $perfil = $controller->getPerfil($user['id']);
                    
                    if ($perfil['success']) {
                        header('Content-Type: application/json');
                        echo json_encode($perfil);
                    } else {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => $perfil['error'] ?? 'Usuario no encontrado']);
                    }
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'No autorizado']);
                }
                exit;
            }

            // ✅ NUEVO: Endpoint para estadísticas
            if ($method === 'GET' && ($uri[1] ?? '') === 'estadisticas') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $userId = $_GET['userId'] ?? $user['id'];
                    
                    // Verificar que el usuario puede acceder a estas estadísticas
                    if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'No autorizado']);
                        exit;
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode($controller->getEstadisticasUsuario($userId));
                    exit;
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'No autorizado']);
                    exit;
                }
            }

            // ✅ NUEVO: Endpoint para puntuación específica
            if ($method === 'GET' && ($uri[1] ?? '') === 'puntuacion') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $userId = $_GET['userId'] ?? $user['id'];
                    
                    // Verificar que el usuario puede acceder a esta puntuación
                    if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'No autorizado']);
                        exit;
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode($controller->getPuntuacionUsuario($userId));
                    exit;
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'No autorizado']);
                    exit;
                }
            }

            // ✅ NUEVO: Endpoint para actualizar puntuación
            if ($method === 'POST' && ($uri[1] ?? '') === 'actualizarPuntuacion') {
                try {
                    $user = AuthHelper::requireActiveUser();
                    $raw = file_get_contents('php://input');
                    $data = json_decode($raw, true);
                    
                    $userId = $data['userId'] ?? $user['id'];
                    $puntos = $data['puntos'] ?? 0;
                    
                    // Verificar permisos
                    if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                        http_response_code(403);
                        echo json_encode(['success' => false, 'message' => 'No autorizado']);
                        exit;
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode($controller->actualizarPuntuacion($userId, $puntos));
                    exit;
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
            }

            if ($method === 'GET' && isset($uri[1]) && is_numeric($uri[1])) {
                $userId = (int)$uri[1];
                echo json_encode($controller->getPerfil($userId));
                exit;
            }

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
                require_once __DIR__ . '/../login.php';
                exit;
            }
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            break;

        case 'misPartidas':
            try {
                $user = AuthHelper::requireActiveUser();
                $controller = new TableroController();
                $controller->obtenerPartidasEnProgreso();
                exit;
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'No autorizado'
                ]);
                exit;
            }

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
            header('Content-Type: text/html; charset=utf-8');
            if (!isset($_SESSION['userId']) || ($_SESSION['rol'] ?? '') !== 'admin') {
                header('Location: /login');
                exit;
            }
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
                $services['database']['status'] = ($db && $db->query('SELECT 1')->num_rows === 1) ? 'up' : 'down';
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
                ['method' => 'GET',  'path' => '/perfil/estadisticas', 'description' => 'Obtener estadísticas de usuario'],
                ['method' => 'GET',  'path' => '/perfil/puntuacion', 'description' => 'Obtener puntuación de usuario'],
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

        case 'ranking':
            require_once __DIR__ . '/../ranking.php';
            exit;

        case 'uploads':
            if (isset($uri[1]) && $uri[1] === 'avatars' && isset($uri[2])) {
                $filename = $uri[2];
                $filepath = __DIR__ . '/../public/uploads/avatars/' . $filename;

                if (file_exists($filepath) && strpos(realpath($filepath), realpath(__DIR__ . '/../public/uploads/avatars/')) === 0) {
                    $mimeType = mime_content_type($filepath);
                    header('Content-Type: ' . $mimeType);
                    header('Content-Length: ' . filesize($filepath));
                    readfile($filepath);
                    exit;
                }
            }
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
            break;

        case 'api':
            $sub = $uri[1] ?? '';

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
                        throw new Exception('Formato de archivo no soportado. Use JPEG, PNG, WEBP o GIF.');
                    }

                    $maxSize = 3 * 1024 * 1024;
                    if ($file['size'] > $maxSize) {
                        throw new Exception('El archivo es demasiado grande. Máximo 3MB.');
                    }

                    $uploadDir = __DIR__ . '/../public/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            throw new Exception('No se pudo crear el directorio de avatares');
                        }
                    }

                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
                    $filepath = $uploadDir . $filename;

                    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                        throw new Exception('Error al guardar el archivo');
                    }

                    $avatarUrl = '/uploads/avatars/' . $filename;
                    $controller = new PerfilController();
                    $result = $controller->updateAvatar($userId, $avatarUrl);

                    if ($result['success']) {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Avatar actualizado correctamente',
                            'avatarUrl' => $avatarUrl
                        ]);
                    } else {
                        if (file_exists($filepath)) {
                            unlink($filepath);
                        }
                        echo json_encode($result);
                    }
                } catch (Exception $e) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                exit;
            }
            
            /* ---------- NUEVO: Endpoint para Ranking ---------- */
            if ($sub === 'ranking' && $method === 'GET') {
                try {
                    $controller = new RankingController();
                    $controller->showRanking();
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Error al obtener el ranking: ' . $e->getMessage()
                    ]);
                }
                exit;
            }

            // ✅ NUEVO: Endpoints para usuario
            if ($sub === 'usuario') {
                $controller = new PerfilController();
                $action = $uri[2] ?? '';

                // Endpoint: /api/usuario/puntuacion
                if ($action === 'puntuacion' && $method === 'GET') {
                    try {
                        $user = AuthHelper::requireActiveUser();
                        $userId = $_GET['userId'] ?? $user['id'];
                        
                        // Verificar permisos
                        if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'message' => 'No autorizado']);
                            exit;
                        }
                        
                        echo json_encode($controller->getPuntuacionUsuario($userId));
                        exit;
                    } catch (Exception $e) {
                        http_response_code(401);
                        echo json_encode(['success' => false, 'message' => 'No autorizado']);
                        exit;
                    }
                }

                // Endpoint: /api/usuario/estadisticas
                if ($action === 'estadisticas' && $method === 'GET') {
                    try {
                        $user = AuthHelper::requireActiveUser();
                        $userId = $_GET['userId'] ?? $user['id'];
                        
                        // Verificar permisos
                        if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'message' => 'No autorizado']);
                            exit;
                        }
                        
                        echo json_encode($controller->getEstadisticasUsuario($userId));
                        exit;
                    } catch (Exception $e) {
                        http_response_code(401);
                        echo json_encode(['success' => false, 'message' => 'No autorizado']);
                        exit;
                    }
                }

                // Endpoint: /api/usuario/actualizarPuntuacion
                if ($action === 'actualizarPuntuacion' && $method === 'POST') {
                    try {
                        $user = AuthHelper::requireActiveUser();
                        $raw = file_get_contents('php://input');
                        $data = json_decode($raw, true);
                        
                        $userId = $data['userId'] ?? $user['id'];
                        $puntos = $data['puntos'] ?? 0;
                        
                        // Solo admin o el mismo usuario pueden actualizar puntuación
                        if ($userId != $user['id'] && ($user['rol'] ?? '') !== 'admin') {
                            http_response_code(403);
                            echo json_encode(['success' => false, 'message' => 'No autorizado']);
                            exit;
                        }
                        
                        echo json_encode($controller->actualizarPuntuacion($userId, $puntos));
                        exit;
                    } catch (Exception $e) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                        exit;
                    }
                }

                http_response_code(404);
                echo json_encode(['error' => 'Endpoint usuario no encontrado']);
                exit;
            }

            if ($sub === 'tablero') {
                $controller = new TableroController();
                $action = $uri[2] ?? '';

                switch ($action) {
                    case 'guardarEstadoPartida':
                        if ($method === 'POST') {
                            $controller->guardarPartida();
                            exit;
                        }
                        break;

                    case 'cargarPartida':
                        if ($method === 'POST' || $method === 'GET') {
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

                    case 'finalizarPartida':
                        if ($method === 'POST') {
                            $controller->finalizarPartida();
                            exit;
                        }
                        break;

                    case 'eliminarPartida':
                        if ($method === 'POST') {
                            $controller->eliminarPartida();
                            exit;
                        }
                        break;

                    case 'obtenerReglas':
                        if ($method === 'GET') {
                            $controller->obtenerReglas();
                            exit;
                        }
                        break;

                    case 'obtenerPuntuaciones':
                        if ($method === 'POST') {
                            $controller->obtenerPuntuaciones();
                            exit;
                        }
                        break;

                    case 'validarIntegridad':
                        if ($method === 'GET') {
                            try {
                                $user = AuthHelper::requireActiveUser();
                                $partidaId = (int)($_GET['partidaId'] ?? 0);
                                if (!$partidaId) {
                                    http_response_code(400);
                                    echo json_encode(['success' => false, 'message' => 'ID de partida requerido']);
                                    exit;
                                }
                                $repository = new TableroRepository();
                                $validacion = $repository->validarIntegridadPartida($partidaId);
                                echo json_encode([
                                    'success' => true,
                                    'validacion' => $validacion
                                ]);
                            } catch (Exception $e) {
                                http_response_code(500);
                                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                            }
                            exit;
                        }
                        break;
                }

                http_response_code(404);
                echo json_encode(['error' => 'Endpoint tablero no encontrado']);
                exit;
            }

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

        case 'reglas':
            require_once __DIR__ . '/../reglas.php';
            exit;

        case 'estadisticas':
            try {
                AuthHelper::requireActiveUser();
                require_once __DIR__ . '/../estadisticas.php';
            } catch (Exception $e) {
                header('Location: /login');
                exit;
            }
            exit;

        case 'jugar':
            header('Location: /tablero', true, 301);
            exit;

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