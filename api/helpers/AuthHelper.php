<?php
require_once __DIR__ . '/../services/AuthService.php';

class AuthHelper {
    public static function iniciarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    /**
     * ACTUALIZADO: Ahora también valida el estado del usuario
     */
    public static function requireLogin(): void {
        self::iniciarSesion();
        
        if (!isset($_SESSION['userId'])) {
            error_log("requireLogin: No hay userId en sesión");
            header('Location: /login');
            exit;
        }

        // NUEVA VALIDACIÓN: Verificar si el usuario sigue activo
        $userId = $_SESSION['userId'];
        $authService = AuthService::getInstance();
        $validation = $authService->validateUserStatus($userId);

        if (!$validation['valid']) {
            error_log("requireLogin: Usuario $userId no es válido - Razón: " . $validation['reason']);
            
            // Destruir la sesión
            self::destroySession();
            
            // Redirigir con mensaje específico
            if ($validation['reason'] === 'ACCOUNT_SUSPENDED') {
                header('Location: /login?error=suspended');
            } else {
                header('Location: /login?error=invalid_session');
            }
            exit;
        }

        error_log("requireLogin: Usuario $userId validado correctamente");
    }

    /**
     * ACTUALIZADO: Validar también el estado para usuarios ya logueados
     */
    public static function redirectIfLogged(): void {
        self::iniciarSesion();
        
        if (isset($_SESSION['userId'])) {
            $userId = $_SESSION['userId'];
            $authService = AuthService::getInstance();
            $validation = $authService->validateUserStatus($userId);

            if (!$validation['valid']) {
                error_log("redirectIfLogged: Usuario $userId no válido, destruyendo sesión");
                // Si el usuario está suspendido, destruir la sesión
                self::destroySession();
                return; // No redirigir, permitir que vea el login con mensaje
            }

            // Usuario válido, redirigir según corresponda
            if ($_SESSION['rol'] === 'admin') {
                header('Location: /admin');
            } else {
                header('Location: /perfil');
            }
            exit;
        }
    }

    public static function usuario(): ?array {
        self::iniciarSesion();
        return $_SESSION['userId'] ?? null;
    }

    /**
     * NUEVO: Método para obtener información completa del usuario actual
     */
    public static function usuarioCompleto(): ?array {
        self::iniciarSesion();
        
        if (!isset($_SESSION['userId'])) {
            return null;
        }

        $userId = $_SESSION['userId'];
        $authService = AuthService::getInstance();
        $validation = $authService->validateUserStatus($userId);

        if (!$validation['valid']) {
            error_log("usuarioCompleto: Usuario $userId no válido");
            self::destroySession();
            return null;
        }

        return $validation['user'];
    }

    /**
     * NUEVO: Método para destruir sesión completamente
     */
    public static function destroySession(): void {
        self::iniciarSesion();
        
        // Limpiar todas las variables de sesión
        $_SESSION = [];
        
        // Destruir la cookie de sesión si existe
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        // Destruir la sesión
        session_destroy();
        
        error_log("Sesión destruida completamente");
    }

    /**
     * NUEVO: Validar sesión sin redirigir (para APIs)
     */
    public static function validateSession(): array {
        self::iniciarSesion();
        
        if (!isset($_SESSION['userId'])) {
            return ['valid' => false, 'reason' => 'NO_SESSION'];
        }

        $userId = $_SESSION['userId'];
        $authService = AuthService::getInstance();
        $validation = $authService->validateUserStatus($userId);

        if (!$validation['valid']) {
            // Destruir sesión inválida
            self::destroySession();
        }

        return $validation;
    }

    /**
     * NUEVO: Middleware para APIs que requieren usuario activo
     */
    public static function requireActiveUser(): array {
        $validation = self::validateSession();
        
        if (!$validation['valid']) {
            http_response_code(401);
            
            $message = match($validation['reason']) {
                'NO_SESSION' => 'Sesión requerida',
                'USER_NOT_FOUND' => 'Usuario no encontrado',
                'ACCOUNT_SUSPENDED' => 'Cuenta suspendida',
                default => 'Sesión inválida'
            };
            
            echo json_encode([
                'error' => $message,
                'code' => $validation['reason']
            ]);
            exit;
        }

        return $validation['user'];
    }
}