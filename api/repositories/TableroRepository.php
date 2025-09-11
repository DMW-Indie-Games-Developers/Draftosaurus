<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

class TableroRepository
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /* ----------  INSERTAR PARTIDA NUEVA  ---------- */
    public function crearPartidaMinimal(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO partidas
            (jugador1_id, jugador2_id, jugadorActivo, ronda, turno,
             mano1, mano2, jugadorQueTiroDado, restriccion, recintos,
             created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $mano1    = json_encode($data['mano1']);
        $mano2    = json_encode($data['mano2']);
        $recintos = json_encode($data['estado']); // Cambiado: ahora guarda en 'recintos'

        $stmt->bind_param(
            "ssiiississ",
            $data['jugador1'],
            $data['jugador2'],
            $data['jugadorActivo'],
            $data['ronda'],
            $data['turno'],
            $mano1,
            $mano2,
            $data['jugadorQueTiroDado'],
            $data['restriccion'],
            $recintos  // Cambiado: guarda en columna 'recintos'
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al crear partida: " . $stmt->error);
        }

        return $this->conn->insert_id;
    }

    /* ----------  ACTUALIZAR ESTADO  ---------- */
    public function guardarEstadoPartida(int $idPartida, array $estadoCompleto): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE partidas
            SET recintos           = ?,
                ronda              = ?,
                turno              = ?,
                jugadorActivo      = ?,
                jugadorQueTiroDado = ?,
                restriccion        = ?,
                mano1              = ?,
                mano2              = ?,
                ultimo_jugador     = ?,
                updated_at         = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $recintos = json_encode($estadoCompleto['recintos']);
        $mano1    = json_encode($estadoCompleto['mano_jugador1']);
        $mano2    = json_encode($estadoCompleto['mano_jugador2']);

        $stmt->bind_param(
            "siiiiissii",
            $recintos,  // Cambiado: usa 'recintos' en lugar de 'estado'
            $estadoCompleto['ronda_actual'],
            $estadoCompleto['turno_actual'],
            $estadoCompleto['jugador_activo'],
            $estadoCompleto['jugador_que_tiro_dado'],
            $estadoCompleto['restriccion_actual'],
            $mano1,
            $mano2,
            $estadoCompleto['ultimo_jugador'],
            $idPartida
        );

        $result = $stmt->execute();
        if (!$result) {
            error_log("Error al guardar estado partida: " . $stmt->error);
        }

        return $result;
    }

    /* ----------  CARGAR PARTIDA  ---------- */
    public function obtenerEstadoCompletoPartida(int $idPartida): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM partidas WHERE id = ?");
        $stmt->bind_param("i", $idPartida);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;

        return [
            'id'                    => (int)$row['id'],
            'jugador1'              => $row['jugador1_id'],
            'jugador2'              => $row['jugador2_id'],
            'recintos'              => json_decode($row['recintos'], true) ?: [], // Cambiado: lee de 'recintos'
            'ronda_actual'          => (int)$row['ronda'],
            'turno_actual'          => (int)$row['turno'],
            'jugador_activo'        => (int)$row['jugadorActivo'],
            'jugador_que_tiro_dado' => (int)$row['jugadorQueTiroDado'],
            'restriccion_actual'    => $row['restriccion'],
            'mano_jugador1'         => json_decode($row['mano1'], true) ?: [],
            'mano_jugador2'         => json_decode($row['mano2'], true) ?: [],
            'ultimo_jugador'        => $row['ultimo_jugador'] === null ? null : (int)$row['ultimo_jugador'],
            'created_at'            => $row['created_at'],
            'updated_at'            => $row['updated_at'],
        ];
    }

    /* ----------  PARTIDAS EN PROGRESO  ---------- */
    public function obtenerPartidasEnProgreso(string $jugador): array
    {
        $stmt = $this->conn->prepare("
            SELECT id, jugador1_id, jugador2_id, ronda, turno, created_at, updated_at
            FROM partidas
            WHERE (jugador1_id = ? OR jugador2_id = ?)
              AND (estado_partida = 'activa' OR estado_partida IS NULL)
            ORDER BY updated_at DESC
        ");
        $stmt->bind_param("ss", $jugador, $jugador);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($result as &$p) {
            $p['id']   = (int)$p['id'];
            $p['ronda']= (int)$p['ronda'];
            $p['turno']= (int)$p['turno'];
        }

        return $result;
    }

    /* ----------  GUARDAR JUGADA (opcional)  ---------- */
    public function guardarJugada(int $idPartida, int $jugador, int $ronda, int $turno, string $recinto, string $dino): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO jugadas (id_partida, jugador, ronda, turno, recinto, dino, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("iiiiss", $idPartida, $jugador, $ronda, $turno, $recinto, $dino);

        if (!$stmt->execute()) {
            error_log("Error al guardar jugada: " . $stmt->error);
        }
    }

    /* ----------  BORRAR PARTIDA  ---------- */
    public function eliminarPartida(int $idPartida): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM partidas WHERE id = ?");
        $stmt->bind_param("i", $idPartida);
        return $stmt->execute();
    }

    /* ----------  VALIDAR ACCESO  ---------- */
    public function validarAccesoPartida(int $idPartida, string $jugador): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as tiene_acceso
            FROM partidas 
            WHERE id = ? AND (jugador1_id = ? OR jugador2_id = ?)
        ");
        $stmt->bind_param("iss", $idPartida, $jugador, $jugador);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return ($result['tiene_acceso'] ?? 0) > 0;
    }

    /* ----------  MARCAR PARTIDA COMO FINALIZADA  ---------- */
    public function finalizarPartida(int $idPartida, array $resultadoFinal): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE partidas 
            SET estado_partida = 'finalizada',
                ganador        = ?,
                puntos_j1      = ?,
                puntos_j2      = ?,
                updated_at     = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        // Variables obligatorias para bind_param
        $ganador  = $resultadoFinal['ganador']  ?? null;
        $puntosJ1 = $resultadoFinal['puntos_j1'] ?? 0;
        $puntosJ2 = $resultadoFinal['puntos_j2'] ?? 0;

        $stmt->bind_param("siii", $ganador, $puntosJ1, $puntosJ2, $idPartida);

        return $stmt->execute();
    }
}