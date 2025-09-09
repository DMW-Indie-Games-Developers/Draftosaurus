<?php
require_once __DIR__ . '/../services/TableroService.php';

class TableroController {
    private TableroService $service;

    public function __construct() {
        $this->service = new TableroService();
    }

    private function getInput(): array {
        return json_decode(file_get_contents("php://input"), true) ?? [];
    }

    public function crearPartida(): void {
        $data = $this->getInput();
        $id = $this->service->crearPartida($data['jugador1'], $data['jugador2'] ?? null);
        echo json_encode(['success' => true, 'id_partida' => $id]);
    }

    public function validarJugada(): array {
        $data = $this->getInput();
        return $this->service->validarJugada($data);
    }

    public function guardarJugada(): void {
        $data = $this->getInput();
        $this->service->guardarJugada($data);
        echo json_encode(['success' => true]);
    }

    public function finalizarPartida(): void {
        $data = $this->getInput();
        $this->service->finalizarPartida($data);
        echo json_encode(['success' => true]);
    }

    public function obtenerPartidas(): void {
        $jugador = $_GET['jugador'] ?? '';
        $partidas = $this->service->obtenerPartidas($jugador);
        echo json_encode(['success' => true, 'data' => $partidas]);
    }

    public function eliminarPartida(): void {
        $data = $this->getInput();
        $this->service->eliminarPartida($data['id_partida']);
        echo json_encode(['success' => true]);
    }
}