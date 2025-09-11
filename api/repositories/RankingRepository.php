<?php
require_once __DIR__ . '/../config/Database.php';

class RankingRepository {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getTopPlayers(int $limit = 10): array {
        // Primero, verifica si la conexión es válida
        if ($this->conn === null || $this->conn->connect_error) {
            error_log('Error de conexión MySQLi en RankingRepository.');
            return [];
        }

        try {
            // En MySQLi, los parámetros se marcan con '?'
            $sql = "SELECT username, puntuacion_total FROM users ORDER BY puntuacion_total DESC LIMIT ?";
            $stmt = $this->conn->prepare($sql);

            // Se especifica el tipo de dato ('i' para entero) y se pasa la variable
            $stmt->bind_param('i', $limit);

            $stmt->execute();

            // Se obtiene el resultado y se convierte a un array asociativo
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);

            $stmt->close();
            return $data;

        } catch (Exception $e) {
            error_log('Error en RankingRepository (MySQLi): ' . $e->getMessage());
            return [];
        }
    }
}