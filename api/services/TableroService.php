<?php
require_once __DIR__ . '/../repositories/TableroRepository.php';

class TableroService {
    private TableroRepository $repo;

    public function __construct() {
        $this->repo = new TableroRepository();
    }

    public function crearPartida(string $jugador1, ?string $jugador2 = null): int {
        return $this->repo->crearPartida($jugador1, $jugador2);
    }

    public function validarJugada(array $data): array {
        $estadoActual = $this->repo->obtenerEstadoPartida($data['id_partida']);
        if (!$estadoActual) return ['success' => false, 'message' => 'Partida no encontrada'];

        $recinto = $data['recinto'];
        $dino = $data['dino'];
        $jugador = $data['jugador'];
        $restriccion = $data['restriccion'] ?? null;

        // Validar restricción del dado
        if ($jugador !== ($data['jugador_que_tiro'] ?? null)) {
            switch ($restriccion) {
                case 1:
                    if (!in_array($recinto, ['recinto-1', 'recinto-2', 'recinto-3'])) {
                        return ['success' => false, 'message' => 'Restricción: solo zona izquierda'];
                    }
                    break;
                case 2:
                    if (!in_array($recinto, ['recinto-4', 'recinto-5', 'recinto-7'])) {
                        return ['success' => false, 'message' => 'Restricción: solo zona derecha'];
                    }
                    break;
                case 3:
                    if (!in_array($recinto, ['recinto-1', 'recinto-2'])) {
                        return ['success' => false, 'message' => 'Restricción: solo zona boscosa'];
                    }
                    break;
                case 4:
                    if (!empty($estadoActual[$recinto])) {
                        return ['success' => false, 'message' => 'Restricción: solo recinto vacío'];
                    }
                    break;
                case 5:
                    if ($dino === 'trex') {
                        return ['success' => false, 'message' => 'Restricción: no se permite T-Rex'];
                    }
                    break;
            }
        }

        // Validar reglas del recinto
        $dinosEnRecinto = $estadoActual[$recinto] ?? [];

        switch ($recinto) {
            case 'recinto-1':
                if (!empty($dinosEnRecinto) && ($dinosEnRecinto[0]['especie'] ?? '') !== $dino) {
                    return ['success' => false, 'message' => 'Solo dinosaurios de la misma especie'];
                }
                break;
            case 'recinto-2':
                if (count($dinosEnRecinto) >= 3) {
                    return ['success' => false, 'message' => 'El recinto ya tiene 3 dinosaurios'];
                }
                break;
            case 'recinto-4':
                if (!empty($dinosEnRecinto)) {
                    return ['success' => false, 'message' => 'Solo 1 dinosaurio permitido'];
                }
                if ($dino !== 'trex') {
                    return ['success' => false, 'message' => 'Solo T-Rex permitido'];
                }
                break;
            case 'recinto-7':
                if (!empty($dinosEnRecinto)) {
                    return ['success' => false, 'message' => 'Solo 1 dinosaurio permitido'];
                }
                break;
            case 'recinto-5':
                $especies = array_column($dinosEnRecinto, 'especie');
                if (in_array($dino, $especies)) {
                    return ['success' => false, 'message' => 'Solo dinosaurios de especies distintas'];
                }
                break;
        }

        return ['success' => true, 'message' => 'Jugada válida'];
    }

    public function guardarJugada(array $data): void {
        $this->repo->guardarJugada(
            $data['id_partida'],
            $data['jugador'],
            $data['ronda'],
            $data['turno'],
            $data['recinto'],
            $data['dino']
        );
    }

    public function finalizarPartida(array $data): void {
        $this->repo->finalizarPartida(
            $data['id_partida'],
            $data['ganador'],
            $data['puntos_j1'],
            $data['puntos_j2'],
            $data['estado']
        );
    }

    public function obtenerPartidas(string $jugador): array {
        return $this->repo->obtenerPartidasPorJugador($jugador);
    }

    public function eliminarPartida(int $id): void {
        $this->repo->eliminarPartida($id);
    }
}