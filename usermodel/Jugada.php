<?php

class Jugada {
    private $id;
    private $id_partida;
    private $jugador;
    private $ronda;
    private $turno;
    private $recinto;
    private $dino;

    public function __construct($id_partida = null, $jugador = null, $ronda = null, $turno = null) {
        $this->id_partida = $id_partida;
        $this->jugador = $jugador;
        $this->ronda = $ronda;
        $this->turno = $turno;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getIdPartida() { return $this->id_partida; }
    public function getJugador() { return $this->jugador; }
    public function getRonda() { return $this->ronda; }
    public function getTurno() { return $this->turno; }
    public function getRecinto() { return $this->recinto; }
    public function getDino() { return $this->dino; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setIdPartida($id_partida) { $this->id_partida = $id_partida; }
    public function setJugador($jugador) { $this->jugador = $jugador; }
    public function setRonda($ronda) { $this->ronda = $ronda; }
    public function setTurno($turno) { $this->turno = $turno; }
    public function setRecinto($recinto) { $this->recinto = $recinto; }
    public function setDino($dino) { $this->dino = $dino; }

    // Validación
    public function validar() {
        $errores = [];

        if (empty($this->id_partida)) {
            $errores[] = 'El ID de partida es obligatorio';
        }

        if (empty($this->jugador)) {
            $errores[] = 'El jugador es obligatorio';
        } elseif (!in_array($this->jugador, [1, 2])) {
            $errores[] = 'El jugador debe ser 1 o 2';
        }

        if (empty($this->ronda)) {
            $errores[] = 'La ronda es obligatoria';
        } elseif ($this->ronda < 1 || $this->ronda > 5) {
            $errores[] = 'La ronda debe estar entre 1 y 5';
        }

        if (empty($this->turno)) {
            $errores[] = 'El turno es obligatorio';
        } elseif ($this->turno < 1 || $this->turno > 3) {
            $errores[] = 'El turno debe estar entre 1 y 3';
        }

        return $errores;
    }

    // Convertir a array
    public function toArray() {
        return [
            'id' => $this->id,
            'id_partida' => $this->id_partida,
            'jugador' => $this->jugador,
            'ronda' => $this->ronda,
            'turno' => $this->turno,
            'recinto' => $this->recinto,
            'dino' => $this->dino
        ];
    }
}