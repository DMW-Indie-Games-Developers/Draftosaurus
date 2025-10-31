<?php
/**
 * CsrfHelper - Protección contra Cross-Site Request Forgery
 *
 * Propósito:
 * - Generar tokens CSRF únicos por sesión
 * - Validar tokens en requests sensibles
 * - Prevenir ataques CSRF
 */

class CsrfHelper
{
    /**
     * Genera o retorna el token CSRF de la sesión actual
     */
    public static function generateToken(): string
    {
        AuthHelper::iniciarSesion();

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            error_log("CSRF: Nuevo token generado para sesión");
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Valida un token CSRF contra el almacenado en sesión
     */
    public static function validateToken(?string $token): bool
    {
        AuthHelper::iniciarSesion();

        if (!isset($_SESSION['csrf_token']) || $token === null) {
            return false;
        }

        // Usar hash_equals para prevenir timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Middleware: Requiere un token CSRF válido o termina la ejecución
     */
    public static function requireValidToken(): void
    {
        $token = self::getTokenFromRequest();

        if (!self::validateToken($token)) {
            error_log("CSRF: Token inválido o faltante - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            http_response_code(403);
            echo json_encode([
                'error' => 'Token de seguridad inválido o faltante',
                'code' => 'CSRF_TOKEN_INVALID'
            ]);
            exit;
        }

        error_log("CSRF: Token válido");
    }

    /**
     * Obtiene el token CSRF del request (header o body)
     */
    private static function getTokenFromRequest(): ?string
    {
        // Opción 1: Header HTTP (recomendado para APIs)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Opción 2: Body JSON
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (isset($data['csrf_token'])) {
                return $data['csrf_token'];
            }
        }

        // Opción 3: POST data (para formularios tradicionales)
        if (isset($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        return null;
    }

    /**
     * Regenera el token CSRF (útil después de login/logout)
     */
    public static function regenerateToken(): string
    {
        AuthHelper::iniciarSesion();
        unset($_SESSION['csrf_token']);
        return self::generateToken();
    }

    /**
     * NUEVO: Middleware global para proteger automáticamente métodos de modificación
     *
     * @param string $method - Método HTTP (GET, POST, PUT, etc.)
     * @param string $path - Ruta del request
     * @param array $excludedPaths - Rutas excluidas de protección CSRF
     */
    public static function protectModifyingMethods(string $method, string $path, array $excludedPaths = []): void
    {
        // Solo proteger métodos que modifican datos
        $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array($method, $protectedMethods)) {
            return; // GET, HEAD, OPTIONS no necesitan protección CSRF
        }

        // Verificar si la ruta está excluida
        foreach ($excludedPaths as $excludedPath) {
            if (self::pathMatches($path, $excludedPath)) {
                error_log("CSRF: Ruta excluida de protección: $path");
                return;
            }
        }

        // Aplicar protección CSRF
        error_log("CSRF: Protegiendo $method $path");
        self::requireValidToken();
    }

    /**
     * Verifica si una ruta coincide con un patrón
     */
    private static function pathMatches(string $path, string $pattern): bool
    {
        // Convertir patrón con wildcards a regex
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '#^' . $pattern . '$#';

        return preg_match($pattern, $path) === 1;
    }

    /**
     * NUEVO: Obtiene el token actual sin generar uno nuevo
     */
    public static function getToken(): ?string
    {
        AuthHelper::iniciarSesion();
        return $_SESSION['csrf_token'] ?? null;
    }
}
