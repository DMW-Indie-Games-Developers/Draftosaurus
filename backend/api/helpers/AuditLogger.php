<?php
/**
 * AuditLogger - Sistema de Logs de Auditoría
 *
 * Propósito:
 * - Registrar todas las acciones importantes del sistema
 * - Rastrear quién hizo qué y cuándo
 * - Detectar actividad sospechosa
 * - Cumplimiento y auditoría
 */

class AuditLogger
{
    // Constantes de acciones
    const ACTION_LOGIN_SUCCESS = 'login_success';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_LOGOUT = 'logout';
    const ACTION_REGISTER = 'register';

    const ACTION_USER_CREATED = 'user_created';
    const ACTION_USER_UPDATED = 'user_updated';
    const ACTION_USER_STATUS_CHANGED = 'user_status_changed';
    const ACTION_USER_ROLE_CHANGED = 'user_role_changed';

    const ACTION_AVATAR_UPLOADED = 'avatar_uploaded';
    const ACTION_PROFILE_UPDATED = 'profile_updated';

    const ACTION_ADMIN_ACCESS = 'admin_panel_access';
    const ACTION_ADMIN_USERS_VIEW = 'admin_users_viewed';
    const ACTION_ADMIN_MESSAGES_VIEW = 'admin_messages_viewed';

    const ACTION_GAME_CREATED = 'game_created';
    const ACTION_GAME_FINISHED = 'game_finished';

    const ACTION_CONTACT_SUBMITTED = 'contact_submitted';

    const ACTION_CSRF_VIOLATION = 'csrf_violation';
    const ACTION_RATE_LIMIT_HIT = 'rate_limit_hit';
    const ACTION_UNAUTHORIZED_ACCESS = 'unauthorized_access';

    /**
     * Registra una acción en el sistema
     *
     * @param string $action Tipo de acción (usar constantes ACTION_*)
     * @param int|null $userId ID del usuario que realiza la acción (null para sistema)
     * @param string|null $description Descripción legible de la acción
     * @param array $metadata Datos adicionales en formato JSON
     */
    public static function log(
        string $action,
        ?int $userId = null,
        ?string $description = null,
        array $metadata = []
    ): void {
        try {
            $db = Database::getInstance()->getConnection();

            // Obtener información del usuario si existe
            $username = null;
            if ($userId !== null) {
                $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $username = $row['username'];
                }
                $stmt->close();
            }

            // Obtener información de la petición
            $ipAddress = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Limitar longitud del user agent
            if ($userAgent && strlen($userAgent) > 255) {
                $userAgent = substr($userAgent, 0, 255);
            }

            // Convertir metadata a JSON
            $metadataJson = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

            // Insertar log
            $stmt = $db->prepare("
                INSERT INTO audit_logs
                (user_id, username, action, description, ip_address, user_agent, metadata, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                'issssss',
                $userId,
                $username,
                $action,
                $description,
                $ipAddress,
                $userAgent,
                $metadataJson
            );

            $stmt->execute();
            $stmt->close();

            // Log en archivo también para debugging
            error_log("AUDIT: [$action] User: " . ($username ?? 'SYSTEM') . " - " . ($description ?? ''));

        } catch (Exception $e) {
            // No fallar si el log falla, solo registrar el error
            error_log("Error en AuditLogger: " . $e->getMessage());
        }
    }

    /**
     * Obtiene los logs más recientes
     *
     * @param int $limit Número de logs a obtener
     * @param string|null $action Filtrar por tipo de acción
     * @param int|null $userId Filtrar por usuario
     * @return array Lista de logs
     */
    public static function getRecentLogs(
        int $limit = 100,
        ?string $action = null,
        ?int $userId = null
    ): array {
        try {
            $db = Database::getInstance()->getConnection();

            $sql = "SELECT * FROM audit_logs WHERE 1=1";
            $params = [];
            $types = '';

            if ($action !== null) {
                $sql .= " AND action = ?";
                $params[] = $action;
                $types .= 's';
            }

            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
                $types .= 'i';
            }

            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            $types .= 'i';

            $stmt = $db->prepare($sql);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $logs = [];
            while ($row = $result->fetch_assoc()) {
                // Decodificar metadata JSON
                if ($row['metadata']) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                $logs[] = $row;
            }

            $stmt->close();

            return $logs;

        } catch (Exception $e) {
            error_log("Error obteniendo logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas de logs
     *
     * @return array Estadísticas
     */
    public static function getStats(): array {
        try {
            $db = Database::getInstance()->getConnection();

            $stats = [];

            // Total de logs
            $result = $db->query("SELECT COUNT(*) as total FROM audit_logs");
            $stats['total_logs'] = $result->fetch_assoc()['total'];

            // Logins exitosos hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'login_success'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['logins_today'] = $result->fetch_assoc()['count'];

            // Logins fallidos hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'login_failed'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['failed_logins_today'] = $result->fetch_assoc()['count'];

            // Usuarios creados hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'user_created'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['users_created_today'] = $result->fetch_assoc()['count'];

            // Violaciones CSRF hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'csrf_violation'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['csrf_violations_today'] = $result->fetch_assoc()['count'];

            // Rate limit hits hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'rate_limit_hit'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['rate_limit_hits_today'] = $result->fetch_assoc()['count'];

            // Accesos no autorizados hoy
            $result = $db->query("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE action = 'unauthorized_access'
                AND DATE(created_at) = CURDATE()
            ");
            $stats['unauthorized_access_today'] = $result->fetch_assoc()['count'];

            // Usuarios únicos activos hoy
            $result = $db->query("
                SELECT COUNT(DISTINCT user_id) as count
                FROM audit_logs
                WHERE user_id IS NOT NULL
                AND DATE(created_at) = CURDATE()
            ");
            $stats['unique_users_today'] = $result->fetch_assoc()['count'];

            return $stats;

        } catch (Exception $e) {
            error_log("Error obteniendo stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la IP real del cliente
     */
    private static function getClientIp(): string {
        // Verificar si está detrás de un proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Obtiene descripción amigable de una acción
     */
    public static function getActionDescription(string $action): string {
        $descriptions = [
            self::ACTION_LOGIN_SUCCESS => 'Inicio de sesión exitoso',
            self::ACTION_LOGIN_FAILED => 'Intento de inicio de sesión fallido',
            self::ACTION_LOGOUT => 'Cierre de sesión',
            self::ACTION_REGISTER => 'Registro de nuevo usuario',

            self::ACTION_USER_CREATED => 'Usuario creado',
            self::ACTION_USER_UPDATED => 'Usuario actualizado',
            self::ACTION_USER_STATUS_CHANGED => 'Estado de usuario cambiado',
            self::ACTION_USER_ROLE_CHANGED => 'Rol de usuario cambiado',

            self::ACTION_AVATAR_UPLOADED => 'Avatar actualizado',
            self::ACTION_PROFILE_UPDATED => 'Perfil actualizado',

            self::ACTION_ADMIN_ACCESS => 'Acceso al panel de administración',
            self::ACTION_ADMIN_USERS_VIEW => 'Visualización de usuarios (admin)',
            self::ACTION_ADMIN_MESSAGES_VIEW => 'Visualización de mensajes (admin)',

            self::ACTION_GAME_CREATED => 'Partida creada',
            self::ACTION_GAME_FINISHED => 'Partida finalizada',

            self::ACTION_CONTACT_SUBMITTED => 'Mensaje de contacto enviado',

            self::ACTION_CSRF_VIOLATION => '⚠️ Violación de token CSRF',
            self::ACTION_RATE_LIMIT_HIT => '⚠️ Límite de velocidad alcanzado',
            self::ACTION_UNAUTHORIZED_ACCESS => '⚠️ Intento de acceso no autorizado',
        ];

        return $descriptions[$action] ?? $action;
    }
}
