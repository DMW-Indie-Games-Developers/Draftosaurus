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

    /* ===== CREAR PARTIDA ===== */
    public function crearPartidaMinimal(array $data): int
    {
        $stmt = $this->conn->prepare("
            INSERT INTO partidas
            (jugador1_id, jugador2_id, jugadorActivo, ronda, turno,
            mano1, mano2, jugadorQueTiroDado, restriccion, recintos,
            created_at, updated_at, name_invitado, estado_partida)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, 'activa')
        ");

        $mano1 = json_encode($data['mano1']);
        $mano2 = json_encode($data['mano2']);
        $recintos = json_encode($data['estado']);

        $jugador2_id = $data['jugador2'] ?? null;
        $restriccion = $data['restriccion'] ?? null;
        $name_invitado = $data['name_invitado'] ?? null;

        $stmt->bind_param(
            "iiiisssisss",
            $data['jugador1'],
            $jugador2_id,
            $data['jugadorActivo'],
            $data['ronda'],
            $data['turno'],
            $mano1,
            $mano2,
            $data['jugadorQueTiroDado'],
            $restriccion,
            $recintos,
            $name_invitado
        );

        if (!$stmt->execute()) {
            throw new Exception("Error al crear partida: " . $stmt->error);
        }

        return $this->conn->insert_id;
    }

    /* ===== ACTUALIZAR ESTADO ===== */
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
                updated_at         = CURRENT_TIMESTAMP,
                estado_partida     = 'activa'
            WHERE id = ?
        ");

        $recintos = json_encode($estadoCompleto['recintos']);
        $mano1 = json_encode($estadoCompleto['mano_jugador1']);
        $mano2 = json_encode($estadoCompleto['mano_jugador2']);

        $stmt->bind_param(
            "siiiiissii",
            $recintos,
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

    /* ===== CARGAR PARTIDA ===== */
    public function obtenerEstadoCompletoPartida(int $idPartida): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                p.id,
                p.jugador1_id,
                p.jugador2_id,
                p.name_invitado,
                u1.username AS j1_username,
                u2.username AS j2_username,
                p.recintos,
                p.ronda,
                p.turno,
                p.jugadorActivo,
                p.jugadorQueTiroDado,
                p.restriccion,
                p.mano1,
                p.mano2,
                p.ultimo_jugador,
                p.estado_partida,
                p.created_at,
                p.updated_at,
                p.ganador,
                p.puntos_j1,
                p.puntos_j2
            FROM partidas p
            JOIN users u1 ON p.jugador1_id = u1.id
            LEFT JOIN users u2 ON p.jugador2_id = u2.id
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $idPartida);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;

        $nombreJ1 = $row['j1_username'];
        $nombreJ2 = $row['jugador2_id']
            ? ($row['j2_username'] ?? 'Usuario')
            : ($row['name_invitado'] ?: 'Invitado');

        return [
            'id'                    => (int)$row['id'],
            'jugador1'              => $nombreJ1,
            'jugador2'              => $nombreJ2,
            'jugador1_id'           => (int)$row['jugador1_id'],
            'jugador2_id'           => (int)$row['jugador2_id'],
            'name_invitado'         => $row['name_invitado'],
            'recintos'              => json_decode($row['recintos'], true) ?: [],
            'ronda_actual'          => (int)$row['ronda'],
            'turno_actual'          => (int)$row['turno'],
            'jugador_activo'        => (int)$row['jugadorActivo'],
            'jugador_que_tiro_dado' => (int)$row['jugadorQueTiroDado'],
            'restriccion_actual'    => $row['restriccion'],
            'mano_jugador1'         => json_decode($row['mano1'], true) ?: [],
            'mano_jugador2'         => json_decode($row['mano2'], true) ?: [],
            'ultimo_jugador'        => $row['ultimo_jugador'] === null ? null : (int)$row['ultimo_jugador'],
            'estado_partida'        => $row['estado_partida'],
            'ganador'               => $row['ganador'],
            'puntos_j1'             => $row['puntos_j1'] !== null ? (int)$row['puntos_j1'] : null,
            'puntos_j2'             => $row['puntos_j2'] !== null ? (int)$row['puntos_j2'] : null,
            'created_at'            => $row['created_at'],
            'updated_at'            => $row['updated_at'],
        ];
    }

    /* ===== PARTIDAS EN PROGRESO ===== */
    public function obtenerPartidasEnProgreso($jugador): array
    {
        if (is_string($jugador)) {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.id, p.jugador1_id, p.jugador2_id, p.ronda, p.turno, 
                    p.created_at, p.updated_at, p.name_invitado, p.estado_partida
                FROM partidas p
                JOIN users u1 ON p.jugador1_id = u1.id
                LEFT JOIN users u2 ON p.jugador2_id = u2.id
                WHERE (u1.username = ? OR u2.username = ?)
                  AND (p.estado_partida IS NULL OR p.estado_partida = 'activa')
                ORDER BY p.updated_at DESC
            ");
            $stmt->bind_param("ss", $jugador, $jugador);
        } else {
            $stmt = $this->conn->prepare("
                SELECT 
                    id, jugador1_id, jugador2_id, ronda, turno, 
                    created_at, updated_at, name_invitado, estado_partida
                FROM partidas
                WHERE (jugador1_id = ? OR jugador2_id = ?)
                  AND (estado_partida IS NULL OR estado_partida = 'activa')
                ORDER BY updated_at DESC
            ");
            $stmt->bind_param("ii", $jugador, $jugador);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($result as &$p) {
            $p['id'] = (int)$p['id'];
            $p['ronda'] = (int)$p['ronda'];
            $p['turno'] = (int)$p['turno'];
            $p['jugador1_id'] = (int)$p['jugador1_id'];
            $p['jugador2_id'] = (int)$p['jugador2_id'];
            $p['name_invitado'] = $p['name_invitado'] ?? null;
        }

        return $result;
    }

    /* ===== VALIDAR ACCESO ===== */
    public function validarAccesoPartida(int $idPartida, $jugador): bool
    {
        if (is_string($jugador)) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as tiene_acceso
                FROM partidas p
                JOIN users u1 ON p.jugador1_id = u1.id
                LEFT JOIN users u2 ON p.jugador2_id = u2.id
                WHERE p.id = ? AND (u1.username = ? OR u2.username = ?)
            ");
            $stmt->bind_param("iss", $idPartida, $jugador, $jugador);
        } else {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as tiene_acceso
                FROM partidas 
                WHERE id = ? AND (jugador1_id = ? OR jugador2_id = ?)
            ");
            $stmt->bind_param("iii", $idPartida, $jugador, $jugador);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return ($result['tiene_acceso'] ?? 0) > 0;
    }

    /* ===== FINALIZAR PARTIDA ===== */
    public function finalizarPartida(int $idPartida, array $resultadoFinal): bool
    {
        $stmt = $this->conn->prepare("
            UPDATE partidas 
            SET estado_partida = 'finalizada',
                ganador        = ?,
                puntos_j1      = ?,
                puntos_j2      = ?,
                tipo_victoria  = ?,
                fecha_finalizacion = CURRENT_TIMESTAMP,
                updated_at     = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $ganador = $resultadoFinal['ganador'] ?? null;
        $puntosJ1 = $resultadoFinal['puntos_j1'] ?? 0;
        $puntosJ2 = $resultadoFinal['puntos_j2'] ?? 0;
        $tipoVictoria = $resultadoFinal['tipo_victoria'] ?? 'puntos';

        $stmt->bind_param("siisi", $ganador, $puntosJ1, $puntosJ2, $tipoVictoria, $idPartida);

        $success = $stmt->execute();
        if ($success) {
            error_log("Partida $idPartida finalizada: $ganador ($puntosJ1 vs $puntosJ2)");
        } else {
            error_log("Error finalizando partida $idPartida: " . $stmt->error);
        }

        return $success;
    }

    /* ===== ELIMINAR PARTIDA ===== */
    public function eliminarPartida(int $idPartida): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM partidas WHERE id = ?");
        $stmt->bind_param("i", $idPartida);
        return $stmt->execute();
    }

    /* ===== ESTADÍSTICAS ===== */
    public function obtenerEstadisticasJugador(int $jugadorId): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_partidas,
                SUM(CASE 
                    WHEN estado_partida = 'finalizada' AND ganador = (SELECT username FROM users WHERE id = ?) 
                    THEN 1 ELSE 0 
                END) as partidas_ganadas,
                SUM(CASE 
                    WHEN estado_partida = 'finalizada' AND ganador IS NULL 
                    THEN 1 ELSE 0 
                END) as empates,
                AVG(CASE 
                    WHEN estado_partida = 'finalizada' AND jugador1_id = ? 
                    THEN puntos_j1 
                    WHEN estado_partida = 'finalizada' AND jugador2_id = ? 
                    THEN puntos_j2 
                    ELSE NULL 
                END) as promedio_puntos,
                MAX(CASE 
                    WHEN estado_partida = 'finalizada' AND jugador1_id = ? 
                    THEN puntos_j1 
                    WHEN estado_partida = 'finalizada' AND jugador2_id = ? 
                    THEN puntos_j2 
                    ELSE 0 
                END) as mejor_puntuacion,
                COUNT(CASE 
                    WHEN estado_partida = 'activa' 
                    THEN 1 ELSE NULL 
                END) as partidas_en_curso
            FROM partidas 
            WHERE jugador1_id = ? OR jugador2_id = ?
        ");
        
        $stmt->bind_param("iiiiiii", 
            $jugadorId, $jugadorId, $jugadorId, 
            $jugadorId, $jugadorId, $jugadorId, $jugadorId
        );
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return [
            'total_partidas' => (int)($result['total_partidas'] ?? 0),
            'partidas_ganadas' => (int)($result['partidas_ganadas'] ?? 0),
            'empates' => (int)($result['empates'] ?? 0),
            'partidas_perdidas' => (int)($result['total_partidas'] ?? 0) - 
                                  (int)($result['partidas_ganadas'] ?? 0) - 
                                  (int)($result['empates'] ?? 0),
            'promedio_puntos' => round((float)($result['promedio_puntos'] ?? 0), 1),
            'mejor_puntuacion' => (int)($result['mejor_puntuacion'] ?? 0),
            'partidas_en_curso' => (int)($result['partidas_en_curso'] ?? 0),
            'porcentaje_victorias' => $result['total_partidas'] > 0 ? 
                round(((int)($result['partidas_ganadas'] ?? 0) / (int)$result['total_partidas']) * 100, 1) : 0
        ];
    }

    /* ===== HISTORIAL DE PARTIDAS ===== */
    public function obtenerHistorialPartidas(int $jugadorId, int $limit = 10): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                p.id,
                p.ganador,
                p.puntos_j1,
                p.puntos_j2,
                p.tipo_victoria,
                p.fecha_finalizacion,
                p.created_at,
                u1.username as jugador1_nombre,
                CASE 
                    WHEN p.jugador2_id IS NULL OR p.jugador2_id = 0 
                    THEN COALESCE(p.name_invitado, 'Invitado')
                    ELSE u2.username 
                END as jugador2_nombre,
                CASE 
                    WHEN p.jugador1_id = ? THEN 1
                    ELSE 2 
                END as posicion_jugador
            FROM partidas p
            JOIN users u1 ON p.jugador1_id = u1.id
            LEFT JOIN users u2 ON p.jugador2_id = u2.id
            WHERE (p.jugador1_id = ? OR p.jugador2_id = ?)
              AND p.estado_partida = 'finalizada'
            ORDER BY p.fecha_finalizacion DESC
            LIMIT ?
        ");
        
        $stmt->bind_param("iiii", $jugadorId, $jugadorId, $jugadorId, $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($result as &$partida) {
            $partida['id'] = (int)$partida['id'];
            $partida['puntos_j1'] = (int)($partida['puntos_j1'] ?? 0);
            $partida['puntos_j2'] = (int)($partida['puntos_j2'] ?? 0);
            $partida['posicion_jugador'] = (int)$partida['posicion_jugador'];
            
            if ($partida['ganador'] === null) {
                $partida['resultado_para_jugador'] = 'empate';
            } else {
                $nombreJugador = $partida['posicion_jugador'] === 1 ? 
                    $partida['jugador1_nombre'] : $partida['jugador2_nombre'];
                $partida['resultado_para_jugador'] = 
                    $partida['ganador'] === $nombreJugador ? 'victoria' : 'derrota';
            }
        }

        return $result;
    }

    /* ===== RANKING GLOBAL ===== */
    public function obtenerRankingGlobal(int $limit = 50): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                u.id,
                u.username,
                u.avatar,
                COUNT(p.id) as total_partidas,
                SUM(CASE 
                    WHEN p.estado_partida = 'finalizada' AND p.ganador = u.username 
                    THEN 1 ELSE 0 
                END) as victorias,
                SUM(CASE 
                    WHEN p.estado_partida = 'finalizada' AND p.ganador IS NULL 
                    THEN 1 ELSE 0 
                END) as empates,
                AVG(CASE 
                    WHEN p.estado_partida = 'finalizada' AND p.jugador1_id = u.id 
                    THEN p.puntos_j1 
                    WHEN p.estado_partida = 'finalizada' AND p.jugador2_id = u.id 
                    THEN p.puntos_j2 
                    ELSE NULL 
                END) as promedio_puntos,
                MAX(CASE 
                    WHEN p.estado_partida = 'finalizada' AND p.jugador1_id = u.id 
                    THEN p.puntos_j1 
                    WHEN p.estado_partida = 'finalizada' AND p.jugador2_id = u.id 
                    THEN p.puntos_j2 
                    ELSE 0 
                END) as mejor_puntuacion
            FROM users u
            LEFT JOIN partidas p ON (p.jugador1_id = u.id OR p.jugador2_id = u.id)
            WHERE u.estado = 'activo'
            GROUP BY u.id, u.username, u.avatar
            HAVING total_partidas > 0
            ORDER BY victorias DESC, promedio_puntos DESC, total_partidas DESC
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($result as $index => &$jugador) {
            $jugador['posicion'] = $index + 1;
            $jugador['id'] = (int)$jugador['id'];
            $jugador['total_partidas'] = (int)($jugador['total_partidas'] ?? 0);
            $jugador['victorias'] = (int)($jugador['victorias'] ?? 0);
            $jugador['empates'] = (int)($jugador['empates'] ?? 0);
            $jugador['derrotas'] = $jugador['total_partidas'] - $jugador['victorias'] - $jugador['empates'];
            $jugador['promedio_puntos'] = round((float)($jugador['promedio_puntos'] ?? 0), 1);
            $jugador['mejor_puntuacion'] = (int)($jugador['mejor_puntuacion'] ?? 0);
            $jugador['porcentaje_victorias'] = $jugador['total_partidas'] > 0 ? 
                round(($jugador['victorias'] / $jugador['total_partidas']) * 100, 1) : 0;
        }

        return $result;
    }

    /* ===== ESTADÍSTICAS GENERALES (CORREGIDO) ===== */
    public function obtenerEstadisticasGenerales(): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_partidas,
                COUNT(CASE WHEN estado_partida = 'activa' THEN 1 END) as partidas_activas,
                COUNT(CASE WHEN estado_partida = 'finalizada' THEN 1 END) as partidas_finalizadas,
                AVG(CASE WHEN estado_partida = 'finalizada' THEN puntos_j1 + puntos_j2 END) as promedio_puntos_totales,
                COUNT(DISTINCT jugador1_id) + COUNT(DISTINCT jugador2_id) as jugadores_unicos,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, 
                    CASE WHEN fecha_finalizacion IS NOT NULL 
                         THEN fecha_finalizacion 
                         ELSE updated_at 
                    END)) as duracion_promedio_minutos
            FROM partidas
        ");
        
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return [
            'total_partidas' => (int)($result['total_partidas'] ?? 0),
            'partidas_activas' => (int)($result['partidas_activas'] ?? 0),
            'partidas_finalizadas' => (int)($result['partidas_finalizadas'] ?? 0),
            'promedio_puntos_totales' => round((float)($result['promedio_puntos_totales'] ?? 0), 1),
            'jugadores_activos' => (int)($result['jugadores_unicos'] ?? 0),
            'duracion_promedio_horas' => round((float)($result['duracion_promedio_minutos'] ?? 0) / 60, 1)
        ];
    }

    /* ===== MANTENIMIENTO ===== */
    public function limpiarPartidasAbandonadas(int $diasAbandonada = 7): int
    {
        $stmt = $this->conn->prepare("
            UPDATE partidas 
            SET estado_partida = 'abandonada',
                updated_at = CURRENT_TIMESTAMP
            WHERE estado_partida = 'activa' 
              AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->bind_param("i", $diasAbandonada);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }

    public function eliminarPartidasAntiguas(int $diasEliminar = 30): int
    {
        $stmt = $this->conn->prepare("
            DELETE FROM partidas 
            WHERE estado_partida = 'abandonada' 
              AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->bind_param("i", $diasEliminar);
        $stmt->execute();
        
        return $stmt->affected_rows;
    }

    /* ===== VALIDACIÓN DE INTEGRIDAD ===== */
    public function validarIntegridadPartida(int $idPartida): array
    {
        $partida = $this->obtenerEstadoCompletoPartida($idPartida);
        if (!$partida) {
            return ['valida' => false, 'errores' => ['Partida no encontrada']];
        }

        $errores = [];

        if (!in_array($partida['jugador_activo'], [1, 2])) {
            $errores[] = "jugador_activo inválido: " . $partida['jugador_activo'];
        }

        if ($partida['ronda_actual'] < 1 || $partida['ronda_actual'] > 4) {
            $errores[] = "ronda_actual inválida: " . $partida['ronda_actual'];
        }

        if ($partida['turno_actual'] < 1 || $partida['turno_actual'] > 3) {
            $errores[] = "turno_actual inválido: " . $partida['turno_actual'];
        }

        $mano1 = $partida['mano_jugador1'];
        $mano2 = $partida['mano_jugador2'];

        if (!is_array($mano1) || count($mano1) > 6) {
            $errores[] = "mano_jugador1 inválida";
        }

        if (!is_array($mano2) || count($mano2) > 6) {
            $errores[] = "mano_jugador2 inválida";
        }

        $colocaciones = $partida['recintos'];
        if (!is_array($colocaciones)) {
            $errores[] = "colocaciones inválidas";
        } else {
            $especiesValidas = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];
            $recintosValidos = ['bosque-semejanza', 'prado-diferencia', 'pradera-amor', 'trio-frondoso', 'rey-selva', 'isla-solitaria', 'rio'];

            foreach ($colocaciones as $colocacion) {
                if (!isset($colocacion['recinto']) || !in_array($colocacion['recinto'], $recintosValidos)) {
                    $errores[] = "Recinto inválido en colocación: " . ($colocacion['recinto'] ?? 'null');
                }

                if (!isset($colocacion['especie']) || !in_array($colocacion['especie'], $especiesValidas)) {
                    $errores[] = "Especie inválida en colocación: " . ($colocacion['especie'] ?? 'null');
                }

                if (!isset($colocacion['jugador']) || !in_array((int)$colocacion['jugador'], [1, 2])) {
                    $errores[] = "Jugador inválido en colocación: " . ($colocacion['jugador'] ?? 'null');
                }
            }
        }

        return [
            'valida' => empty($errores),
            'errores' => $errores,
            'partida_id' => $idPartida,
            'estado_partida' => $partida['estado_partida']
        ];
    }
}