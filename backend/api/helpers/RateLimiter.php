<?php
/**
 * RateLimiter - Limitador de velocidad de requests
 *
 * Propósito:
 * - Prevenir fuerza bruta en login
 * - Prevenir spam en formularios
 * - Prevenir abuso de uploads
 * - Mitigar ataques DoS simples
 */

class RateLimiter
{
    /**
     * Obtiene la IP del cliente
     */
    private static function getClientIp(): string
    {
        // Intentar obtener la IP real si está detrás de un proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Verifica si el cliente ha excedido el límite de requests
     *
     * @param string $action Identificador de la acción (ej: 'login', 'contact', 'upload')
     * @param int $maxAttempts Número máximo de intentos permitidos
     * @param int $windowSeconds Ventana de tiempo en segundos
     * @return bool true si está dentro del límite, false si lo excedió
     */
    public static function check(string $action, int $maxAttempts, int $windowSeconds): bool
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = $action . '_' . $ip;
        $now = time();

        // Inicializar array de rate limit si no existe
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }

        // Limpiar intentos fuera de la ventana de tiempo
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            fn($timestamp) => ($now - $timestamp) < $windowSeconds
        );

        // Verificar si excede el límite
        $currentAttempts = count($_SESSION['rate_limit'][$key]);

        if ($currentAttempts >= $maxAttempts) {
            $oldestAttempt = min($_SESSION['rate_limit'][$key]);
            $timeRemaining = $windowSeconds - ($now - $oldestAttempt);

            error_log("RATE LIMIT: IP $ip excedió límite en '$action' ($currentAttempts/$maxAttempts). Tiempo restante: {$timeRemaining}s");
            return false;
        }

        // Registrar este intento
        $_SESSION['rate_limit'][$key][] = $now;

        return true;
    }

    /**
     * Middleware: Requiere que el cliente esté dentro del límite o termina la ejecución
     *
     * @param string $action Identificador de la acción
     * @param int $maxAttempts Número máximo de intentos
     * @param int $windowSeconds Ventana de tiempo en segundos
     */
    public static function requireLimit(string $action, int $maxAttempts, int $windowSeconds): void
    {
        if (!self::check($action, $maxAttempts, $windowSeconds)) {
            $minutesRemaining = ceil($windowSeconds / 60);

            http_response_code(429); // Too Many Requests
            echo json_encode([
                'error' => "Demasiados intentos. Por favor, espera $minutesRemaining minutos antes de intentar nuevamente.",
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $windowSeconds
            ]);
            exit;
        }
    }

    /**
     * Limpia el contador para una acción específica (útil después de éxito)
     *
     * @param string $action Identificador de la acción
     */
    public static function reset(string $action): void
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = $action . '_' . $ip;

        if (isset($_SESSION['rate_limit'][$key])) {
            unset($_SESSION['rate_limit'][$key]);
            error_log("RATE LIMIT: Reset para '$action' - IP: $ip");
        }
    }

    /**
     * Limpia todos los contadores de rate limiting
     */
    public static function clearAll(): void
    {
        AuthHelper::iniciarSesion();

        if (isset($_SESSION['rate_limit'])) {
            unset($_SESSION['rate_limit']);
        }
    }
}
