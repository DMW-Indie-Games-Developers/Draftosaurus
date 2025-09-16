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
}