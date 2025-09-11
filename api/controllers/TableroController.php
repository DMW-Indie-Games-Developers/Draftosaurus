<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/TableroService.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

class TableroController
{
    private TableroService $service;

    public function __construct()
    {
        $this->service = new TableroService();
    }

    /* ----------  auxiliar  ---------- */
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
        echo json_encode($response);
    }

    /* ----------  endpoints  ---------- */

    public function crearPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $data = $this->getInput();
            
            error_log("Creando partida para usuario: " . json_encode($user));
            error_log("Datos recibidos: " . json_encode($data));
            
            $config = [
                'nombre_jugador1' => $data['nombre_jugador1'] ?? $user['username'],
                'nombre_jugador2' => $data['nombre_jugador2'] ?? 'Rival',
                'total_rondas' => $data['total_rondas'] ?? 4,
            ];

            $id = $this->service->crearPartida(
                $user['username'],
                $data['jugador2'] ?? null,
                $config
            );

            $this->sendResponse([
                'success' => true,
                'id' => $id,
                'message' => 'Partida creada exitosamente',
            ]);
            
        } catch (Throwable $e) {
            error_log("Error al crear partida: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al crear partida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function guardarEstadoPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $data = $this->getInput();
            
            error_log("Guardando estado partida para usuario: " . json_encode($user));
            error_log("Datos recibidos: " . json_encode($data));

            // Validar que tengamos datos mÃ­nimos
            if (empty($data)) {
                $this->sendResponse([
                    'success' => false,
                    'message' => 'No se recibieron datos para guardar'
                ], 400);
                return;
            }

            $idPartida = (int)($data['id'] ?? 0);
            
            // Si es partida nueva (id === 0 o no existe) creamos una nueva
            if ($idPartida === 0) {
                error_log("Creando nueva partida minimal");
                
                $datosPartida = [
                    'jugador1' => $user['username'],
                    'jugador2' => $data['jugador2'] ?? 'CPU',
                    'jugadorActivo' => (int)($data['jugadorActivo'] ?? 1),
                    'ronda' => (int)($data['ronda'] ?? 1),
                    'turno' => (int)($data['turno'] ?? 1),
                    'jugadorQueTiroDado' => (int)($data['jugadorQueTiroDado'] ?? 0),
                    'restriccion' => $data['restriccion'] ?? null,
                    'ultimo_jugador' => $data['ultimo_jugador'] ?? null,
                    'mano1' => $data['mano1'] ?? [],
                    'mano2' => $data['mano2'] ?? [],
                    'estado' => $data['colocaciones'] ?? []
                ];
                
                $nuevoId = $this->service->crearPartidaMinimal($datosPartida);
                
                $this->sendResponse([
                    'success' => true,
                    'id' => $nuevoId,
                    'message' => 'Partida guardada exitosamente',
                ]);
                
            } else {
                error_log("Actualizando partida existente con ID: $idPartida");
                
                // Validar acceso a la partida
                if (!$this->service->validarAccesoPartida($idPartida, $user['username'])) {
                    $this->sendResponse([
                        'success' => false,
                        'message' => 'No tienes acceso a esta partida'
                    ], 403);
                    return;
                }
                
                $exito = $this->service->guardarEstadoPartida($data);
                
                $this->sendResponse([
                    'success' => $exito,
                    'message' => $exito ? 'Partida actualizada exitosamente' : 'Error al actualizar partida',
                ]);
            }
            
        } catch (Throwable $e) {
            error_log("Error al guardar estado partida: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al guardar estado: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cargarPartida(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $idPartida = (int)($_GET['id'] ?? 0);

            error_log("Cargando partida ID: $idPartida para usuario: " . $user['username']);

            if (!$idPartida) {
                $this->sendResponse([
                    'success' => false,
                    'message' => 'ID de partida requerido'
                ], 400);
                return;
            }

            if (!$this->service->validarAccesoPartida($idPartida, $user['username'])) {
                $this->sendResponse([
                    'success' => false,
                    'message' => 'No tienes acceso a esta partida'
                ], 403);
                return;
            }

            $partida = $this->service->cargarPartida($idPartida);

            if ($partida) {
                $this->sendResponse([
                    'success' => true,
                    'data' => $partida
                ]);
            } else {
                $this->sendResponse([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }
            
        } catch (Throwable $e) {
            error_log("Error al cargar partida: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al cargar partida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function obtenerPartidasEnProgreso(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $partidas = $this->service->obtenerPartidasEnProgreso($user['username']);

            error_log("Partidas encontradas para " . $user['username'] . ": " . count($partidas));

            $this->sendResponse([
                'success' => true,
                'data' => $partidas
            ]);
            
        } catch (Throwable $e) {
            error_log("Error al obtener partidas: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al obtener partidas: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function obtenerMisPartidas(): void
    {
        try {
            $user = AuthHelper::requireActiveUser();
            $lista = $this->service->obtenerPartidasEnProgreso($user['username']);

            $this->sendResponse([
                'success' => true,
                'data' => $lista
            ]);
            
        } catch (Throwable $e) {
            error_log("Error al listar partidas: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al listar partidas: ' . $e->getMessage()
            ], 500);
        }
    }

    /* ----------  validar jugada  ---------- */
    public function validarJugada(): void
    {
        try {
            $data = $this->getInput();
            $res = $this->service->validarJugada($data);

            $this->sendResponse($res);
            
        } catch (Throwable $e) {
            error_log("Error al validar jugada: " . $e->getMessage());
            $this->sendResponse([
                'success' => false,
                'message' => 'Error al validar jugada: ' . $e->getMessage(),
            ], 500);
        }
    }
}