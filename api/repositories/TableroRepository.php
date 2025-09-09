<?php
require_once __DIR__ . '/../config/Database.php';

class TableroRepository {
    private mysqli $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function crearPartida(string $jugador1, ?string $jugador2 = null): int {
        $estadoInicial = json_encode([
            'recinto-1' => [],
            'recinto-2' => [],
            'recinto-3' => [],
            'recinto-4' => [],
            'recinto-5' => [],
            'recinto-6' => [],
            'recinto-7' => [],
        ]);
        $stmt = $this->conn->prepare("INSERT INTO partidas (jugador1, jugador2, estado) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $jugador1, $jugador2, $estadoInicial);
        $stmt->execute();
        return $stmt->insert_id;
    }

    public function guardarJugada(int $idPartida, int $jugador, int $ronda, int $turno, string $recinto, string $dino): void {
        $stmt = $this->conn->prepare("INSERT INTO jugadas (id_partida, jugador, ronda, turno, recinto, dino) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiss", $idPartida, $jugador, $ronda, $turno, $recinto, $dino);
        $stmt->execute();
    }

    public function finalizarPartida(int $idPartida, int $ganador, int $puntosJ1, int $puntosJ2, array $estado): void {
        $estadoJson = json_encode($estado);
        $stmt = $this->conn->prepare("UPDATE partidas SET ganador = ?, puntos_j1 = ?, puntos_j2 = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("iiisi", $ganador, $puntosJ1, $puntosJ2, $estadoJson, $idPartida);
        $stmt->execute();
    }

    public function obtenerEstadoPartida(int $idPartida): array {
        $stmt = $this->conn->prepare("SELECT estado FROM partidas WHERE id = ?");
        $stmt->bind_param("i", $idPartida);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return json_decode($row['estado'] ?? '{}', true);
    }

    public function obtenerPartidasPorJugador(string $jugador): array {
        $stmt = $this->conn->prepare("SELECT * FROM partidas WHERE jugador1 = ? OR jugador2 = ? ORDER BY fecha DESC");
        $stmt->bind_param("ss", $jugador, $jugador);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function eliminarPartida(int $idPartida): void {
        $stmt = $this->conn->prepare("DELETE FROM partidas WHERE id = ?");
        $stmt->bind_param("i", $idPartida);
        $stmt->execute();
    }
}