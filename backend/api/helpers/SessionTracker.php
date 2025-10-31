<?php
/**
 * SessionTracker - Rastreador de sesiones de usuarios
 *
 * Propósito:
 * - Registrar cuando un usuario inicia sesión
 * - Rastrear actividad de usuarios
 * - Determinar usuarios online
 * - Estadísticas de sesiones
 */

class SessionTracker
{
    /**
     * Registra un nuevo inicio de sesión
     */
    public static function registerLogin(int $userId): void
    {
        try {
            $db = Database::getInstance()->getConnection();

            $sessionId = session_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, login_time, is_active)
                    VALUES (?, ?, ?, ?, NOW(), 1)";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("isss", $userId, $sessionId, $ipAddress, $userAgent);
            $stmt->execute();

            error_log("SessionTracker: Sesión registrada para usuario $userId desde IP $ipAddress");

        } catch (Exception $e) {
            error_log("Error en SessionTracker::registerLogin: " . $e->getMessage());
        }
    }

    /**
     * Actualiza la última actividad del usuario
     */
    public static function updateActivity(int $userId): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sessionId = session_id();

            $sql = "UPDATE user_sessions
                    SET last_activity = NOW()
                    WHERE user_id = ? AND session_id = ? AND is_active = 1";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("is", $userId, $sessionId);
            $stmt->execute();

        } catch (Exception $e) {
            error_log("Error en SessionTracker::updateActivity: " . $e->getMessage());
        }
    }

    /**
     * Marca una sesión como finalizada (logout)
     */
    public static function registerLogout(int $userId): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sessionId = session_id();

            $sql = "UPDATE user_sessions
                    SET logout_time = NOW(), is_active = 0
                    WHERE user_id = ? AND session_id = ? AND is_active = 1";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("is", $userId, $sessionId);
            $stmt->execute();

            error_log("SessionTracker: Logout registrado para usuario $userId");

        } catch (Exception $e) {
            error_log("Error en SessionTracker::registerLogout: " . $e->getMessage());
        }
    }

    /**
     * Limpia sesiones inactivas (más de 30 minutos sin actividad)
     */
    public static function cleanInactiveSessions(): void
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Marcar como inactivas las sesiones sin actividad en 30 minutos
            $sql = "UPDATE user_sessions
                    SET is_active = 0, logout_time = last_activity
                    WHERE is_active = 1
                    AND last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)";

            $db->query($sql);

        } catch (Exception $e) {
            error_log("Error en SessionTracker::cleanInactiveSessions: " . $e->getMessage());
        }
    }

    /**
     * Obtiene usuarios actualmente online (últimos 5 minutos de actividad)
     */
    public static function getOnlineUsers(): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Primero limpiar sesiones inactivas
            self::cleanInactiveSessions();

            $sql = "SELECT DISTINCT
                        us.user_id,
                        u.name as username,
                        u.email,
                        u.rol,
                        us.ip_address,
                        us.login_time,
                        us.last_activity,
                        TIMESTAMPDIFF(MINUTE, us.last_activity, NOW()) as minutes_idle
                    FROM user_sessions us
                    INNER JOIN users u ON us.user_id = u.id
                    WHERE us.is_active = 1
                    AND us.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    ORDER BY us.last_activity DESC";

            $result = $db->query($sql);
            $users = [];

            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }

            return $users;

        } catch (Exception $e) {
            error_log("Error en SessionTracker::getOnlineUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas de sesiones
     */
    public static function getSessionStats(): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Usuarios online ahora
            $onlineNow = count(self::getOnlineUsers());

            // Total de sesiones hoy
            $sql = "SELECT COUNT(*) as total
                    FROM user_sessions
                    WHERE DATE(login_time) = CURDATE()";
            $result = $db->query($sql);
            $todaySessions = $result->fetch_assoc()['total'];

            // Total de sesiones esta semana
            $sql = "SELECT COUNT(*) as total
                    FROM user_sessions
                    WHERE login_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $result = $db->query($sql);
            $weekSessions = $result->fetch_assoc()['total'];

            // Usuarios únicos hoy
            $sql = "SELECT COUNT(DISTINCT user_id) as total
                    FROM user_sessions
                    WHERE DATE(login_time) = CURDATE()";
            $result = $db->query($sql);
            $uniqueToday = $result->fetch_assoc()['total'];

            // Duración promedio de sesión (en minutos)
            $sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, login_time, logout_time)) as avg_duration
                    FROM user_sessions
                    WHERE logout_time IS NOT NULL
                    AND DATE(login_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $result = $db->query($sql);
            $avgDuration = round($result->fetch_assoc()['avg_duration'] ?? 0, 1);

            // Sesiones activas totales
            $sql = "SELECT COUNT(*) as total
                    FROM user_sessions
                    WHERE is_active = 1";
            $result = $db->query($sql);
            $activeSessions = $result->fetch_assoc()['total'];

            return [
                'online_now' => $onlineNow,
                'sessions_today' => $todaySessions,
                'sessions_week' => $weekSessions,
                'unique_users_today' => $uniqueToday,
                'avg_session_duration' => $avgDuration,
                'active_sessions' => $activeSessions
            ];

        } catch (Exception $e) {
            error_log("Error en SessionTracker::getSessionStats: " . $e->getMessage());
            return [
                'online_now' => 0,
                'sessions_today' => 0,
                'sessions_week' => 0,
                'unique_users_today' => 0,
                'avg_session_duration' => 0,
                'active_sessions' => 0
            ];
        }
    }

    /**
     * Obtiene el historial de sesiones recientes
     */
    public static function getRecentSessions(int $limit = 20): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $sql = "SELECT
                        us.id,
                        us.user_id,
                        u.name as username,
                        u.email,
                        us.ip_address,
                        us.login_time,
                        us.logout_time,
                        us.last_activity,
                        us.is_active,
                        TIMESTAMPDIFF(MINUTE, us.login_time, COALESCE(us.logout_time, NOW())) as duration_minutes
                    FROM user_sessions us
                    INNER JOIN users u ON us.user_id = u.id
                    ORDER BY us.login_time DESC
                    LIMIT ?";

            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();

            $result = $stmt->get_result();
            $sessions = [];

            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }

            return $sessions;

        } catch (Exception $e) {
            error_log("Error en SessionTracker::getRecentSessions: " . $e->getMessage());
            return [];
        }
    }
}
