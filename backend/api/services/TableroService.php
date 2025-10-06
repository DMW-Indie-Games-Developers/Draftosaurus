<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/TableroRepository.php';
require_once __DIR__ . '/../services/PuntuacionService.php';
require_once __DIR__ . '/../config/Database.php';

class TableroService
{
    private TableroRepository $repository;
    private PuntuacionService $puntuacionService;
    private mysqli $conn;

    private const TOTAL_RONDAS = 4;
    private const ESPECIES = ['dino1', 'dino2', 'dino3', 'dino4', 'dino5', 'trex'];

    public function __construct()
    {
        $this->repository = new TableroRepository();
        $this->puntuacionService = new PuntuacionService();
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    /* ===== CREAR PARTIDA ===== */
    public function crearPartida(int $jugador1, ?int $jugador2, ?string $name_invitado): int
    {
        $mano1 = $this->generarManoInicial();
        $mano2 = $this->generarManoInicial();

        $datos = [
            'jugador1' => $jugador1,
            'jugador2' => $jugador2,
            'name_invitado' => $name_invitado,
            'jugadorActivo' => rand(1, 2),
            'jugadorQueTiroDado' => 0,
            'ronda' => 1,
            'turno' => 1,
            'mano1' => $mano1,
            'mano2' => $mano2,
            'restriccion' => null,
            'estado' => $this->inicializarRecintos()
        ];

        return $this->repository->crearPartidaMinimal($datos);
    }

    /* ===== CARGAR PARTIDA ===== */
    public function cargarPartida(int $partidaId): ?array
    {
        $raw = $this->repository->obtenerEstadoCompletoPartida($partidaId);
        if (!$raw) return null;

        $nombreJ1 = $raw['jugador1'] ?? 'Jugador 1';
        $nombreJ2 = $raw['jugador2'] ?? $raw['name_invitado'] ?? 'Invitado';

        return [
            'id'                     => $raw['id'],
            'jugador1'               => $nombreJ1,
            'jugador2'               => $nombreJ2,
            'name_invitado'          => $raw['name_invitado'],
            'ronda'                  => $raw['ronda_actual'] ?? 1,
            'turno'                  => $raw['turno_actual'] ?? 1,
            'jugadorActivo'          => $raw['jugador_activo'] ?? 1,
            'jugadorQueTiroDado'     => $raw['jugador_que_tiro_dado'] ?? 0,
            'restriccion'            => $raw['restriccion_actual'] ?? null,
            'colocadosEnTurno'       => 0,
            'mano1'                  => $raw['mano_jugador1'] ?? [],
            'mano2'                  => $raw['mano_jugador2'] ?? [],
            'recintos'               => $raw['recintos'] ?? [],
            'estado_partida'         => $raw['estado_partida'] ?? 'activa',
        ];
    }

    /* ===== GUARDAR ESTADO PARTIDA ===== */
    public function guardarEstadoPartida(int $partidaId, array $datos): bool
    {
        return $this->repository->guardarEstadoPartida($partidaId, $datos);
    }

    /* ===== OBTENER PARTIDAS EN PROGRESO ===== */
    public function obtenerPartidasEnProgreso(int $userId): array
    {
        return $this->repository->obtenerPartidasEnProgreso($userId);
    }

    /* ===== VALIDAR ACCESO ===== */
    public function validarAccesoPartida(int $partidaId, int $userId): bool
    {
        return $this->repository->validarAccesoPartida($partidaId, $userId);
    }

    /* ===== ELIMINAR PARTIDA ===== */
    public function eliminarPartida(int $partidaId): bool
    {
        return $this->repository->eliminarPartida($partidaId);
    }

    /* ===== FINALIZAR PARTIDA SIMPLE ===== */
    public function finalizarPartidaSimple(int $partidaId, int $userId): array
    {
        error_log("Iniciando finalización simple de partida $partidaId para usuario $userId");
        
        $this->conn->begin_transaction();
        try {
            // 1. Cargar partida
            $partida = $this->cargarPartida($partidaId);
            if (!$partida) {
                throw new Exception('Partida no encontrada');
            }
            error_log("Partida cargada: " . json_encode([
                'id' => $partida['id'],
                'jugador1' => $partida['jugador1'],
                'jugador2' => $partida['jugador2'],
                'recintos_count' => count($partida['recintos'])
            ]));

            // 2. Validar acceso
            if (!$this->validarAccesoPartida($partidaId, $userId)) {
                throw new Exception('No tienes permiso para finalizar esta partida');
            }
            error_log("Acceso validado");

            // 3. Verificar que hay dinosaurios colocados
            $recintos = $partida['recintos'] ?? [];
            $totalDinosaurios = 0;
            foreach ($recintos as $recintoData) {
                if (isset($recintoData['dinosaurios'])) {
                    $totalDinosaurios += count($recintoData['dinosaurios']);
                }
            }
            
            error_log("Total de dinosaurios en la partida: $totalDinosaurios");
            
            if ($totalDinosaurios === 0) {
                error_log("ADVERTENCIA: No hay dinosaurios colocados en la partida");
                // Continuar con la finalización pero con puntos 0-0
            }

            // 4. Calcular puntuaciones usando el nuevo servicio
            error_log("Iniciando cálculo de puntuaciones...");
            error_log("Estructura de recintos: " . json_encode($recintos, JSON_PRETTY_PRINT));
            
            $puntos = $this->puntuacionService->calcularPuntuacionesFinales($recintos);
            error_log("Puntos calculados por el servicio: " . json_encode($puntos));

            // 5. Validar que los puntos son válidos
            if (!is_array($puntos) || count($puntos) < 2) {
                error_log("ERROR: Puntos inválidos, usando valores por defecto");
                $puntos = [0, 0];
            }

            // 6. Determinar ganador
            $ganador = $this->puntuacionService->determinarGanador($puntos);
            error_log("Ganador determinado: $ganador");

            // 7. Obtener nombres de jugadores
            $nombreJ1 = $partida['jugador1'] ?? 'Jugador 1';
            $nombreJ2 = $partida['jugador2'] ?? 'Invitado';
            
            $nombreGanador = null;
            if ($ganador === 1) {
                $nombreGanador = $nombreJ1;
            } elseif ($ganador === 2) {
                $nombreGanador = $nombreJ2;
            }
            
            error_log("Nombre del ganador: " . ($nombreGanador ?? 'Empate'));

            // 8. Finalizar partida en BD
            $stmt = $this->conn->prepare("
                UPDATE partidas
                SET estado_partida = 'finalizada',
                    ganador = ?,
                    puntos_j1 = ?,
                    puntos_j2 = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("iiii", $ganador, $puntos[0], $puntos[1], $partidaId);
            $resultadoFinalizar = $stmt->execute();

            if (!$resultadoFinalizar) {
                throw new Exception('Error al finalizar partida en base de datos: ' . $stmt->error);
            }

            $filasAfectadas = $stmt->affected_rows;
            if ($filasAfectadas === 0) {
                throw new Exception("No se pudo actualizar la partida. Verifique que la partida existe.");
            }

            error_log("Partida finalizada en BD con ganador: $ganador, puntos: [{$puntos[0]}, {$puntos[1]}]");

            // 9. Obtener información completa del jugador
            $partidaCompleta = $this->repository->obtenerEstadoCompletoPartida($partidaId);
            
            // 10. Actualizar estadísticas del jugador 1
            if ($partidaCompleta['jugador1_id']) {
                $this->actualizarEstadisticasJugador($partidaCompleta['jugador1_id'], $puntos[0], $ganador === 1);
                error_log("Estadísticas actualizadas para jugador 1 (ID: {$partidaCompleta['jugador1_id']})");
            }

            // 11. Actualizar estadísticas del jugador 2 si es usuario registrado
            if ($partidaCompleta['jugador2_id']) {
                $this->actualizarEstadisticasJugador($partidaCompleta['jugador2_id'], $puntos[1], $ganador === 2);
                error_log("Estadísticas actualizadas para jugador 2 (ID: {$partidaCompleta['jugador2_id']})");
            }

            $this->conn->commit();
            error_log("Transacción completada exitosamente");
            
            // 12. Preparar respuesta completa
            $respuesta = [
                'ganador' => $ganador, 
                'puntos' => $puntos, 
                'nombreGanador' => $nombreGanador,
                'nombreJ1' => $nombreJ1,
                'nombreJ2' => $nombreJ2,
                'partida' => $partida,
                'totalDinosaurios' => $totalDinosaurios
            ];
            
            error_log("Respuesta final: " . json_encode([
                'ganador' => $respuesta['ganador'],
                'puntos' => $respuesta['puntos'],
                'nombreGanador' => $respuesta['nombreGanador'],
                'totalDinosaurios' => $respuesta['totalDinosaurios']
            ]));
            
            return $respuesta;

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error en finalización: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /* ===== CALCULAR PUNTUACIONES (DELEGACIÓN) ===== */
    public function calcularPuntuacionesFinales(array $recintos): array
    {
        return $this->puntuacionService->calcularPuntuacionesFinales($recintos);
    }

    public function determinarGanador(array $puntosCalculados): int
    {
        return $this->puntuacionService->determinarGanador($puntosCalculados);
    }

    /* ===== MÉTODOS PRIVADOS ===== */
    private function inicializarRecintos(): array
    {
        $recintos = [];
        $configRecintos = $this->puntuacionService->getAllConfigRecintos();
        
        foreach ($configRecintos as $id => $config) {
            $recintos[$id] = ['dinosaurios' => []];
        }
        return $recintos;
    }

    private function generarManoInicial(): array
    {
        $mano = [];
        for ($i = 0; $i < 6; $i++) {
            $mano[] = self::ESPECIES[array_rand(self::ESPECIES)];
        }
        return $mano;
    }

    private function actualizarEstadisticasJugador(int $jugadorId, int $puntos, bool $esGanador): void
    {
        $stmt = $this->conn->prepare("
            UPDATE users 
            SET puntuacion_total = puntuacion_total + ?,
                partidas_ganadas = partidas_ganadas + ?,
                partidas_jugadas = partidas_jugadas + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $ganadas = $esGanador ? 1 : 0;
        $stmt->bind_param("iii", $puntos, $ganadas, $jugadorId);
        $stmt->execute();
        
        error_log("Estadísticas actualizadas para jugador ID: $jugadorId");
    }

    // Métodos adicionales requeridos por el controlador
    public function getPartida(int $partidaId): ?array
    {
        return $this->cargarPartida($partidaId);
    }

    public function guardarPartida(int $partidaId, array $datos): bool
    {
        return $this->guardarEstadoPartida($partidaId, $datos);
    }

    public function validarFinDeJuego(array $partida): bool
    {
        return isset($partida['ronda']) && $partida['ronda'] > self::TOTAL_RONDAS;
    }

    public function validarJugada(array $partida, array $jugada): bool
    {
        return true; // Implementar lógica de validación si es necesaria
    }

    public function calcularPuntuacionRecinto(string $recintoId, array $dinosaurios): int
    {
        $config = $this->puntuacionService->getConfigRecinto($recintoId);
        if (!$config) return 0;

        // Crear estructura de recintos para el cálculo
        $recintos = [$recintoId => ['dinosaurios' => $dinosaurios]];
        
        // Calcular puntos para cada jugador por separado
        $totalPuntos = 0;
        
        for ($jugador = 1; $jugador <= 2; $jugador++) {
            $puntosJugador = $this->puntuacionService->calcularPuntosRecintoEspecifico(
                $recintoId, 
                $jugador, 
                $recintos
            );
            $totalPuntos += $puntosJugador;
        }
        
        return $totalPuntos;
    }

    private function agruparDinosauriosPorJugador(array $dinosaurios): array
    {
        $dinosPorJugador = [1 => [], 2 => []];
        
        foreach ($dinosaurios as $dino) {
            $jugador = (int)$dino['jugador'];
            if (isset($dinosPorJugador[$jugador])) {
                $dinosPorJugador[$jugador][] = $dino['especie'];
            }
        }
        
        return $dinosPorJugador;
    }
}