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
            error_log("Partida cargada");

            // 2. Validar acceso
            if (!$this->validarAccesoPartida($partidaId, $userId)) {
                throw new Exception('No tienes permiso para finalizar esta partida');
            }
            error_log("Acceso validado");

            // 3. Calcular puntuaciones usando el nuevo servicio
            $recintos = $partida['recintos'] ?? [];
            $puntos = $this->puntuacionService->calcularPuntuacionesFinales($recintos);
            error_log("Puntos calculados: " . json_encode($puntos));

            // 4. Determinar ganador
            $ganador = $this->puntuacionService->determinarGanador($puntos);
            error_log("Ganador determinado: $ganador");

            // 5. Finalizar partida en BD
            $stmt = $this->conn->prepare("
                UPDATE partidas 
                SET estado_partida = 'finalizada',
                    ganador = ?,
                    puntos_j1 = ?,
                    puntos_j2 = ?,
                    fecha_finalizacion = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("iiii", $ganador, $puntos[0], $puntos[1], $partidaId);
            $resultadoFinalizar = $stmt->execute();
            
            if (!$resultadoFinalizar) {
                throw new Exception('Error al finalizar partida en base de datos: ' . $stmt->error);
            }
            error_log("Partida finalizada en BD con ganador: $ganador");

            // 6. Actualizar estadísticas del jugador 1
            $this->actualizarEstadisticasJugador($userId, $puntos[0], $ganador === 1);

            // 7. Actualizar estadísticas del jugador 2 si es usuario registrado
            $partidaCompleta = $this->repository->obtenerEstadoCompletoPartida($partidaId);
            if ($partidaCompleta['jugador2_id']) {
                $this->actualizarEstadisticasJugador($partidaCompleta['jugador2_id'], $puntos[1], $ganador === 2);
            }

            $this->conn->commit();
            error_log("Transacción completada exitosamente");
            
            // Preparar respuesta
            $nombreJ1 = $partida['jugador1'] ?? 'Jugador 1';
            $nombreJ2 = $partida['jugador2'] ?? 'Invitado';

            $nombreGanador = null;
            if ($ganador === 1) {
                $nombreGanador = $nombreJ1;
            } elseif ($ganador === 2) {
                $nombreGanador = $nombreJ2;
            }

            return [
                'ganador' => $ganador, 
                'puntos' => $puntos, 
                'nombreGanador' => $nombreGanador,
                'partida' => $partida
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error en finalización: " . $e->getMessage());
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

        $recintos = [$recintoId => ['dinosaurios' => $dinosaurios]];
        $dinosPorJugador = $this->agruparDinosauriosPorJugador($dinosaurios);
        
        $puntosRecinto = $this->puntuacionService->calcularPuntosRecinto($config, $dinosPorJugador, $recintos);
        
        return array_sum($puntosRecinto);
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