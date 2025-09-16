<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/TableroService.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../config/Database.php';

class TableroController
{
    private TableroService $service;

    public function __construct()
    {
        $this->service = new TableroService();
    }

    /* ===== MÉTODOS AUXILIARES ===== */

    private function getInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error decodificando JSON: " . json_last_error_msg());
            error_log("Datos recibidos: " . $raw);
            return [];
        }

        return $data ?? [];
    }

    private function sendResponse(array $response, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    /* ===== CREAR PARTIDA ===== */
    public function crearPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $input = $this->getInput();

            $jugador1_id = $user['id'];
            $esInvitado = $input['esInvitado'] ?? false;
            $nombreJugador2 = $input['nombre_jugador2'] ?? 'Invitado';

            $jugador2_id = null;
            $name_invitado = null;

            if ($esInvitado) {
                $name_invitado = $nombreJugador2;
            } else {
                // Si no es invitado, buscar el ID del usuario por nombre
                $jugador2_id = $this->buscarUsuarioPorNombre($nombreJugador2);
                if (!$jugador2_id) {
                    throw new Exception('Usuario no encontrado: ' . $nombreJugador2);
                }
            }

            $partidaId = $this->service->crearPartida($jugador1_id, $jugador2_id, $name_invitado);

            $this->sendResponse([
                'success' => true,
                'id' => $partidaId,
                'partidaId' => $partidaId,
                'message' => 'Partida creada con éxito'
            ]);

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===== GUARDAR ESTADO PARTIDA ===== */
    public function guardarEstadoPartida(): void
    {
        error_log("=== DEBUG GUARDAR ESTADO PARTIDA ===");
        error_log("Método HTTP: " . $_SERVER['REQUEST_METHOD']);
        error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'no definido'));
        
        try {
            $user = AuthHelper::requireActiveUser();
            error_log("✅ Usuario autenticado: ID={$user['id']}, Username={$user['username']}");
            
            $raw = file_get_contents('php://input');
            error_log("Raw input recibido (length=" . strlen($raw) . "): " . substr($raw, 0, 500));
            
            $datos = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON inválido: ' . json_last_error_msg());
            }
            
            error_log("✅ JSON decodificado correctamente");
            error_log("Datos principales: " . json_encode([
                'id' => $datos['id'] ?? 'no_definido',
                'partidaId' => $datos['partidaId'] ?? 'no_definido',
                'ronda' => $datos['ronda'] ?? 'no_definido',
                'colocaciones_count' => count($datos['colocaciones'] ?? [])
            ]));

            $partidaId = (int) ($datos['id'] ?? $datos['partidaId'] ?? 0);
            
            if (!$partidaId || $partidaId <= 0) {
                error_log("❌ ID de partida inválido: " . var_export($partidaId, true));
                throw new Exception('ID de partida no válido: ' . $partidaId);
            }
            
            error_log("✅ ID de partida válido: $partidaId");

            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                error_log("❌ Acceso denegado: usuario {$user['id']} no puede acceder a partida $partidaId");
                throw new Exception('No tienes permiso para guardar esta partida');
            }
            
            error_log("✅ Acceso validado correctamente");

            // ✅ VALIDACIONES CRÍTICAS ANTES DE PROCESAR
            $ronda = $datos['ronda'] ?? 1;
            $turno = $datos['turno'] ?? 1;
            $jugadorActivo = $datos['jugadorActivo'] ?? 1;
            $restriccion = $datos['restriccion'] ?? null;

            // Validar ronda (1-4)
            if ($ronda < 1 || $ronda > 4) {
                error_log("❌ Ronda inválida: $ronda (debe estar entre 1-4)");
                throw new Exception("Ronda inválida: $ronda. Debe estar entre 1 y 4.");
            }

            // Validar turno (1-3)  
            if ($turno < 1 || $turno > 3) {
                error_log("❌ Turno inválido: $turno (debe estar entre 1-3)");
                throw new Exception("Turno inválido: $turno. Debe estar entre 1 y 3.");
            }

            // Validar jugador activo (1-2)
            if ($jugadorActivo < 1 || $jugadorActivo > 2) {
                error_log("❌ Jugador activo inválido: $jugadorActivo");
                throw new Exception("Jugador activo inválido: $jugadorActivo. Debe ser 1 o 2.");
            }

            // Validar restricción (1-6 o null)
            if ($restriccion !== null && ($restriccion < 1 || $restriccion > 6)) {
                error_log("❌ Restricción inválida: $restriccion");
                throw new Exception("Restricción inválida: $restriccion. Debe estar entre 1 y 6 o ser null.");
            }

            error_log("✅ Validaciones pasadas: ronda=$ronda, turno=$turno, jugadorActivo=$jugadorActivo");

            // Formatear datos correctamente
            $estadoFormateado = [
                'recintos' => $this->formatearColocaciones($datos['colocaciones'] ?? []),
                'ronda_actual' => $ronda,
                'turno_actual' => $turno,
                'jugador_activo' => $jugadorActivo,
                'jugador_que_tiro_dado' => $datos['jugadorQueTiroDado'] ?? 1,
                'restriccion_actual' => $restriccion,
                'mano_jugador1' => $datos['mano1'] ?? [],
                'mano_jugador2' => $datos['mano2'] ?? [],
                'ultimo_jugador' => $datos['jugadorActivo'] ?? 1
            ];
            
            error_log("Estado formateado para BD: " . json_encode([
                'recintos_count' => count($estadoFormateado['recintos']),
                'ronda_actual' => $estadoFormateado['ronda_actual'],
                'jugador_activo' => $estadoFormateado['jugador_activo']
            ]));

            $ok = $this->service->guardarEstadoPartida($partidaId, $estadoFormateado);
            
            if ($ok) {
                error_log("✅ Partida guardada exitosamente en BD");
                $this->sendResponse(['success' => true, 'message' => 'Partida guardada']);
            } else {
                error_log("❌ Falló el guardado en base de datos");
                throw new Exception('No se pudo guardar la partida en base de datos');
            }

        } catch (Exception $e) {
            error_log("❌ ERROR COMPLETO: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->sendResponse([
                'success' => false, 
                'error' => $e->getMessage(),
                'debug' => [
                    'partidaId' => $partidaId ?? 'no_definido',
                    'userId' => isset($user) ? $user['id'] : 'no_definido',
                    'input_length' => strlen(file_get_contents('php://input'))
                ]
            ], 500);
        }
    }

    /* ===== CARGAR PARTIDA ===== */
    public function cargarPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();

            // Intentar BODY primero (POST/PUT)
            $datos = $this->getInput();
            $partidaId = (int) ($datos['partidaId'] ?? 0);

            // Si no hay ID, probar query-string (GET)
            if (!$partidaId) {
                $partidaId = (int) ($_GET['id'] ?? 0);
            }

            if (!$partidaId) {
                throw new Exception('ID de partida no proporcionado');
            }

            // Validar acceso
            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                throw new Exception('No tienes permiso para ver esta partida');
            }

            // Cargar partida
            $partida = $this->service->cargarPartida($partidaId);
            if (!$partida) {
                throw new Exception('Partida no encontrada');
            }

            // Formatear colocaciones para el frontend
            $colocaciones = $this->extraerColocacionesDeRecintos($partida['recintos'] ?? []);
            $partida['colocaciones'] = $colocaciones;

            $this->sendResponse([
                'success' => true,
                'data' => $partida
            ]);

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'message' => $e->getMessage()], 404);
        }
    }

    /* ===== OBTENER PARTIDAS EN PROGRESO ===== */
    public function obtenerPartidasEnProgreso(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $partidas = $this->service->obtenerPartidasEnProgreso($user['id']);

            // Formatear partidas con nombres correctos
            $partidasFormateadas = [];
            foreach ($partidas as $partida) {
                $partidasFormateadas[] = [
                    'id' => $partida['id'],
                    'jugador1' => $user['username'], // El usuario actual
                    'jugador2' => $partida['name_invitado'] ?? 'Invitado',
                    'name_invitado' => $partida['name_invitado'],
                    'ronda_actual' => $partida['ronda'],
                    'turno_actual' => $partida['turno'],
                    'updated_at' => $partida['updated_at'],
                    'estado_partida' => $partida['estado_partida']
                ];
            }

            $this->sendResponse(['success' => true, 'partidas' => $partidasFormateadas]);
        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===== FINALIZAR PARTIDA ===== */
    public function finalizarPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $datos = $this->getInput();
            $partidaId = (int) ($datos['partidaId'] ?? 0);

            if (!$partidaId) {
                throw new Exception('ID de partida no proporcionado');
            }

            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                throw new Exception('No tienes permiso para finalizar esta partida');
            }

            // Log inicial
            error_log("=== INICIANDO FINALIZACION DE PARTIDA ===");
            error_log("Usuario: {$user['id']} ({$user['username']})");
            error_log("Partida ID: $partidaId");
            error_log("Datos recibidos: " . json_encode($datos));

            $resultado = $this->service->finalizarPartidaSimple($partidaId, $user['id']);

            // Log del resultado
            error_log("=== RESULTADO DE FINALIZACION ===");
            error_log("Ganador: " . ($resultado['ganador'] ?? 'null'));
            error_log("Puntos: " . json_encode($resultado['puntos'] ?? [0, 0]));
            error_log("Nombre ganador: " . ($resultado['nombreGanador'] ?? 'null'));

            // Preparar respuesta estructurada
            $respuesta = [
                'success' => true,
                'ganador' => $resultado['ganador'] ?? 0,
                'puntos' => $resultado['puntos'] ?? [0, 0],
                'nombreGanador' => $resultado['nombreGanador'] ?? null,
                'nombreJ1' => $resultado['nombreJ1'] ?? 'Jugador 1',
                'nombreJ2' => $resultado['nombreJ2'] ?? 'Jugador 2',
                'totalDinosaurios' => $resultado['totalDinosaurios'] ?? 0,
                'message' => 'Partida finalizada correctamente'
            ];

            error_log("Respuesta enviada: " . json_encode($respuesta));
            $this->sendResponse($respuesta);

        } catch (Exception $e) {
            error_log("=== ERROR EN FINALIZACION DE PARTIDA ===");
            error_log("Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            $this->sendResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'partidaId' => $partidaId ?? 'no_definido',
                    'userId' => $user['id'] ?? 'no_definido'
                ]
            ], 500);
        }
    }

    /* ===== ELIMINAR PARTIDA ===== */
    public function eliminarPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $datos = $this->getInput();
            $partidaId = (int) ($datos['partidaId'] ?? 0);

            if (!$partidaId) {
                throw new Exception('ID de partida no proporcionado');
            }

            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                throw new Exception('No tienes permiso para eliminar esta partida');
            }

            // Verificar que la partida no esté finalizada
            $partida = $this->service->cargarPartida($partidaId);
            if ($partida && isset($partida['estado_partida']) && $partida['estado_partida'] === 'finalizada') {
                throw new Exception('No se pueden eliminar partidas finalizadas');
            }

            $ok = $this->service->eliminarPartida($partidaId);

            if ($ok) {
                $this->sendResponse(['success' => true, 'message' => 'Partida eliminada correctamente']);
            } else {
                throw new Exception('No se pudo eliminar la partida');
            }

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===== OBTENER PUNTUACIONES ===== */
    public function obtenerPuntuaciones(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $datos = $this->getInput();
            $partidaId = (int) ($datos['partidaId'] ?? 0);

            if (!$partidaId) {
                throw new Exception('Falta partidaId');
            }

            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                throw new Exception('Sin permisos');
            }

            $partida = $this->service->cargarPartida($partidaId);
            if (!$partida) {
                throw new Exception('Partida no encontrada');
            }

            $recintos = $partida['recintos'] ?? [];
            $puntos = $this->service->calcularPuntuacionesFinales($recintos);

            $this->sendResponse([
                'success' => true,
                'puntos' => [
                    'jugador1' => $puntos[0],
                    'jugador2' => $puntos[1]
                ]
            ]);

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===== VALIDAR JUGADA ===== */
    public function validarJugada(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $datos = $this->getInput();
            $partidaId = (int) ($datos['partidaId'] ?? 0);

            if (!$this->service->validarAccesoPartida($partidaId, $user['id'])) {
                throw new Exception('No tienes permiso para jugar en esta partida');
            }

            $partida = $this->service->cargarPartida($partidaId);
            if (!$partida) {
                throw new Exception('La partida no existe');
            }

            $esValida = $this->service->validarJugada($partida, $datos['jugada'] ?? []);

            $this->sendResponse([
                'success' => true,
                'esValida' => $esValida
            ]);

        } catch (Exception $e) {
            $this->sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ===== OBTENER REGLAS ===== */
    public function obtenerReglas(): void
    {
        $reglas = [
            'objetivo' => 'Crear el parque de dinosaurios con más puntos de victoria.',
            'rondas' => 4,
            'turnos_por_ronda' => 6,
            'final_de_partida' => 'La partida termina después de 4 rondas. El jugador con más puntos gana.'
        ];

        $this->sendResponse([
            'success' => true,
            'reglas' => $reglas
        ]);
    }

    /* ===== MÉTODOS AUXILIARES PRIVADOS ===== */

    private function buscarUsuarioPorNombre(string $nombre): ?int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND estado = 'activo'");
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            return $result ? (int) $result['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function formatearColocaciones(array $colocaciones): array
    {
        $recintos = [];

        foreach ($colocaciones as $colocacion) {
            $recintoId = $colocacion['recinto'];
            if (!isset($recintos[$recintoId])) {
                $recintos[$recintoId] = ['dinosaurios' => []];
            }

            $recintos[$recintoId]['dinosaurios'][] = [
                'especie' => $colocacion['especie'],
                'jugador' => (int) $colocacion['jugador']
            ];
        }

        return $recintos;
    }

    private function extraerColocacionesDeRecintos(array $recintos): array
    {
        $colocaciones = [];

        foreach ($recintos as $recintoId => $recintoData) {
            if (isset($recintoData['dinosaurios'])) {
                foreach ($recintoData['dinosaurios'] as $dino) {
                    $colocaciones[] = [
                        'recinto' => $recintoId,
                        'especie' => $dino['especie'],
                        'jugador' => (int) $dino['jugador']
                    ];
                }
            }
        }

        return $colocaciones;
    }
}