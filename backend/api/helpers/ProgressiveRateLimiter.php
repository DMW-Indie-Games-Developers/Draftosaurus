<?php
/**
 * ProgressiveRateLimiter - Sistema de bloqueo progresivo para prevenir ataques de fuerza bruta
 *
 * Propósito:
 * - Bloquear intentos de login fallidos con tiempos de espera crecientes
 * - Prevenir ataques de fuerza bruta mediante penalización progresiva
 * - Resetear automáticamente después de un login exitoso
 *
 * Estrategia de bloqueo:
 * - 1er intento fallido: 0 segundos (advertencia)
 * - 2do intento fallido: 30 segundos
 * - 3er intento fallido: 2 minutos
 * - 4to intento fallido: 5 minutos
 * - 5to intento fallido: 15 minutos
 * - 6to+ intento fallido: 30 minutos
 */

class ProgressiveRateLimiter
{
    // Configuración de tiempos de bloqueo progresivos (en segundos)
    private static $blockDurations = [
        1 => 0,      // 1er intento: sin bloqueo
        2 => 30,     // 2do intento: 30 segundos
        3 => 120,    // 3er intento: 2 minutos
        4 => 300,    // 4to intento: 5 minutos
        5 => 900,    // 5to intento: 15 minutos
        6 => 1800    // 6to+ intento: 30 minutos
    ];

    /**
     * Obtiene la IP del cliente
     */
    private static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Registra un intento fallido de login
     *
     * @param string $identifier Email o username del intento
     */
    public static function recordFailedAttempt(string $identifier): void
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = 'login_progressive_' . $ip;
        $now = time();

        if (!isset($_SESSION['progressive_limit'])) {
            $_SESSION['progressive_limit'] = [];
        }

        if (!isset($_SESSION['progressive_limit'][$key])) {
            $_SESSION['progressive_limit'][$key] = [
                'attempts' => [],
                'identifier' => $identifier
            ];
        }

        // Agregar el nuevo intento fallido
        $_SESSION['progressive_limit'][$key]['attempts'][] = $now;
        $_SESSION['progressive_limit'][$key]['identifier'] = $identifier;

        $attemptCount = count($_SESSION['progressive_limit'][$key]['attempts']);

        error_log("PROGRESSIVE RATE LIMIT: Intento fallido #$attemptCount para '$identifier' desde IP $ip");
    }

    /**
     * Verifica si el usuario/IP está bloqueado y por cuánto tiempo
     *
     * @return array ['blocked' => bool, 'wait_seconds' => int, 'attempt_number' => int]
     */
    public static function checkBlock(): array
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = 'login_progressive_' . $ip;
        $now = time();

        if (!isset($_SESSION['progressive_limit'][$key])) {
            return ['blocked' => false, 'wait_seconds' => 0, 'attempt_number' => 0];
        }

        $data = $_SESSION['progressive_limit'][$key];
        $attempts = $data['attempts'];
        $attemptCount = count($attempts);

        if ($attemptCount === 0) {
            return ['blocked' => false, 'wait_seconds' => 0, 'attempt_number' => 0];
        }

        // Determinar el tiempo de bloqueo según el número de intentos
        $blockDuration = self::$blockDurations[$attemptCount] ?? self::$blockDurations[6];

        // Obtener el timestamp del último intento fallido
        $lastAttempt = end($attempts);
        $timeSinceLastAttempt = $now - $lastAttempt;

        // Si aún está dentro del período de bloqueo
        if ($timeSinceLastAttempt < $blockDuration) {
            $waitSeconds = $blockDuration - $timeSinceLastAttempt;

            error_log("PROGRESSIVE RATE LIMIT: IP $ip bloqueada. Intento #$attemptCount. Esperar $waitSeconds segundos.");

            return [
                'blocked' => true,
                'wait_seconds' => $waitSeconds,
                'attempt_number' => $attemptCount
            ];
        }

        // El tiempo de bloqueo ha expirado, limpiar intentos antiguos
        self::cleanExpiredAttempts();

        return ['blocked' => false, 'wait_seconds' => 0, 'attempt_number' => $attemptCount];
    }

    /**
     * Middleware: Requiere que el usuario no esté bloqueado o termina la ejecución
     */
    public static function requireNotBlocked(): void
    {
        $status = self::checkBlock();

        if ($status['blocked']) {
            $minutes = ceil($status['wait_seconds'] / 60);
            $seconds = $status['wait_seconds'] % 60;
            $attemptNumber = $status['attempt_number'];

            $timeMessage = $minutes > 0
                ? "$minutes minuto(s) y $seconds segundo(s)"
                : "$seconds segundo(s)";

            http_response_code(429); // Too Many Requests
            echo json_encode([
                'success' => false,
                'error' => "Demasiados intentos fallidos de inicio de sesión. Por favor, espera $timeMessage antes de intentar nuevamente.",
                'code' => 'LOGIN_BLOCKED',
                'retry_after' => $status['wait_seconds'],
                'attempt_number' => $attemptNumber
            ]);
            exit;
        }
    }

    /**
     * Resetea el contador de intentos fallidos (llamar después de login exitoso)
     */
    public static function reset(): void
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = 'login_progressive_' . $ip;

        if (isset($_SESSION['progressive_limit'][$key])) {
            $attemptCount = count($_SESSION['progressive_limit'][$key]['attempts']);
            unset($_SESSION['progressive_limit'][$key]);
            error_log("PROGRESSIVE RATE LIMIT: Reset exitoso para IP $ip después de $attemptCount intentos fallidos");
        }
    }

    /**
     * Limpia intentos que ya expiraron (más de 1 hora)
     */
    private static function cleanExpiredAttempts(): void
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = 'login_progressive_' . $ip;
        $now = time();
        $expirationTime = 3600; // 1 hora

        if (isset($_SESSION['progressive_limit'][$key])) {
            $attempts = $_SESSION['progressive_limit'][$key]['attempts'];

            // Filtrar intentos que tienen más de 1 hora
            $validAttempts = array_filter($attempts, function($timestamp) use ($now, $expirationTime) {
                return ($now - $timestamp) < $expirationTime;
            });

            if (count($validAttempts) === 0) {
                // Si no quedan intentos válidos, eliminar completamente
                unset($_SESSION['progressive_limit'][$key]);
            } else {
                // Actualizar con solo los intentos válidos
                $_SESSION['progressive_limit'][$key]['attempts'] = array_values($validAttempts);
            }
        }
    }

    /**
     * Obtiene información del estado actual de bloqueo
     *
     * @return array Información detallada del estado
     */
    public static function getStatus(): array
    {
        AuthHelper::iniciarSesion();

        $ip = self::getClientIp();
        $key = 'login_progressive_' . $ip;

        if (!isset($_SESSION['progressive_limit'][$key])) {
            return [
                'attempt_count' => 0,
                'is_blocked' => false,
                'next_block_duration' => self::$blockDurations[1]
            ];
        }

        $data = $_SESSION['progressive_limit'][$key];
        $attemptCount = count($data['attempts']);
        $status = self::checkBlock();

        $nextAttemptNumber = $attemptCount + 1;
        $nextBlockDuration = self::$blockDurations[$nextAttemptNumber] ?? self::$blockDurations[6];

        return [
            'attempt_count' => $attemptCount,
            'is_blocked' => $status['blocked'],
            'wait_seconds' => $status['wait_seconds'],
            'next_block_duration' => $nextBlockDuration,
            'identifier' => $data['identifier'] ?? 'unknown'
        ];
    }

    /**
     * Limpia todos los contadores de bloqueo progresivo
     */
    public static function clearAll(): void
    {
        AuthHelper::iniciarSesion();

        if (isset($_SESSION['progressive_limit'])) {
            unset($_SESSION['progressive_limit']);
        }
    }
}
