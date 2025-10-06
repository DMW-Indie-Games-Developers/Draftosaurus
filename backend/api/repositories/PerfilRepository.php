<?php
require_once __DIR__ . '/../config/Database.php';

class PerfilRepository {
    
    public function findById($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT
                id,
                username,
                email,
                nickname,
                avatar,
                puntuacion_total,
                partidas_jugadas,
                partidas_ganadas,
                created_at,
                updated_at
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function updateAvatar($userId, $avatarUrl) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $avatarUrl, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Método para obtener solo la puntuación
    public function getPuntuacionTotal($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT puntuacion_total FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (int)$result['puntuacion_total'] : 0;
    }

    // Método para actualizar puntuación
    public function actualizarPuntuacion($userId, $puntos) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE users 
            SET puntuacion_total = puntuacion_total + ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('ii', $puntos, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        
        if ($ok) {
            return $this->getPuntuacionTotal($userId);
        }
        return false;
    }

    // Método para obtener estadísticas completas
    public function getEstadisticasUsuario($userId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT 
                u.puntuacion_total,
                u.partidas_jugadas,
                u.partidas_ganadas,
                COALESCE(stats.promedio_puntos, 0) as promedio_puntos,
                COALESCE(stats.mejor_puntuacion, 0) as mejor_puntuacion
            FROM users u
            LEFT JOIN (
                SELECT 
                    CASE 
                        WHEN jugador1_id = ? THEN ?
                        WHEN jugador2_id = ? THEN ?
                    END as user_id,
                    AVG(CASE 
                        WHEN jugador1_id = ? THEN puntos_j1 
                        WHEN jugador2_id = ? THEN puntos_j2 
                    END) as promedio_puntos,
                    MAX(CASE 
                        WHEN jugador1_id = ? THEN puntos_j1 
                        WHEN jugador2_id = ? THEN puntos_j2 
                    END) as mejor_puntuacion
                FROM partidas 
                WHERE (jugador1_id = ? OR jugador2_id = ?) 
                AND estado_partida = 'finalizada'
                AND (puntos_j1 IS NOT NULL OR puntos_j2 IS NOT NULL)
                GROUP BY user_id
            ) stats ON stats.user_id = u.id
            WHERE u.id = ?
        ");
        
        // Bind todos los parámetros (11 veces el mismo userId)
        $stmt->bind_param('iiiiiiiiiii', 
            $userId, $userId, $userId, $userId, 
            $userId, $userId, $userId, $userId, 
            $userId, $userId, $userId
        );
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            return [
                'puntuacion_total' => 0,
                'partidas_jugadas' => 0,
                'partidas_ganadas' => 0,
                'promedio_puntos' => 0,
                'mejor_puntuacion' => 0
            ];
        }

        return [
            'puntuacion_total' => (int)$result['puntuacion_total'],
            'partidas_jugadas' => (int)$result['partidas_jugadas'],
            'partidas_ganadas' => (int)$result['partidas_ganadas'],
            'promedio_puntos' => round((float)$result['promedio_puntos'], 1),
            'mejor_puntuacion' => (int)$result['mejor_puntuacion']
        ];
    }

    public function updateNickname($userId, $nickname) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET nickname = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $nickname, $userId);
        return $stmt->execute();
    }

    public function nicknameExists($nickname, $excludeUserId) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE nickname = ? AND id != ?");
        $stmt->bind_param('si', $nickname, $excludeUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    public function getRanking($limit = 10) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT
                id,
                username,
                nickname,
                CASE WHEN nickname IS NOT NULL AND nickname != '' THEN nickname ELSE username END as display_name,
                avatar,
                puntuacion_total,
                partidas_jugadas,
                partidas_ganadas,
                CASE WHEN partidas_jugadas > 0 THEN ROUND((partidas_ganadas / partidas_jugadas) * 100, 1) ELSE 0 END as ratio_victorias
            FROM users
            WHERE estado = 'activo'
            ORDER BY puntuacion_total DESC, partidas_ganadas DESC
            LIMIT ?
        ");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $ranking = [];
        $position = 1;
        while ($row = $result->fetch_assoc()) {
            $row['position'] = $position++;
            $ranking[] = $row;
        }

        return $ranking;
    }

    public function getUserRanking($userId) {
        $db = Database::getInstance()->getConnection();

        // Obtener la posición del usuario
        $stmt = $db->prepare("
            SELECT
                COUNT(*) + 1 as position
            FROM users
            WHERE puntuacion_total > (
                SELECT puntuacion_total FROM users WHERE id = ?
            ) AND estado = 'activo'
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $userPosition = $result['position'];

        // Obtener total de jugadores activos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE estado = 'activo'");
        $stmt->execute();
        $totalPlayers = $stmt->get_result()->fetch_assoc()['total'];

        // Obtener datos del siguiente jugador (para calcular puntos necesarios)
        $stmt = $db->prepare("
            SELECT puntuacion_total
            FROM users
            WHERE puntuacion_total > (
                SELECT puntuacion_total FROM users WHERE id = ?
            ) AND estado = 'activo'
            ORDER BY puntuacion_total ASC
            LIMIT 1
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $nextResult = $stmt->get_result()->fetch_assoc();
        $nextPlayerPoints = $nextResult ? $nextResult['puntuacion_total'] : null;

        return [
            'position' => $userPosition,
            'total_players' => $totalPlayers,
            'next_player_points' => $nextPlayerPoints
        ];
    }
}