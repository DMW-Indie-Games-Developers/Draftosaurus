<?php
require_once __DIR__ . '/../config/Database.php';

class RankingRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene los mejores jugadores ordenados por puntuación.
     * @param int $limit El número de jugadores a obtener.
     * @return array Lista de jugadores.
     */
    public function getTopPlayers(int $limit = 10): array {
        try {
            // Asumimos que la tabla se llama 'users' y tiene las columnas 'username' y 'puntuacion_total'
            $stmt = $this->db->prepare(
                "SELECT username, puntuacion_total
                 FROM users
                 ORDER BY puntuacion_total DESC
                 LIMIT :limit"
            );
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error en RankingRepository: ' . $e->getMessage());
            return [];
        }
    }
}