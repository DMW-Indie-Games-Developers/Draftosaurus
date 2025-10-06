<?php

class Partida {
    private $id;
    private $jugador1_id;
    private $jugador2_id;
    private $jugadorActivo;
    private $ronda;
    private $turno;
    private $mano1;
    private $mano2;
    private $jugadorQueTiroDado;
    private $restriccion;
    private $recintos;
    private $ganador;
    private $puntos_j1;
    private $puntos_j2;
    private $ultimo_jugador;
    private $created_at;
    private $updated_at;
    private $estado_partida;
    private $name_invitado;

    public function __construct($jugador1_id = null, $jugador2_id = null) {
        $this->jugador1_id = $jugador1_id;
        $this->jugador2_id = $jugador2_id;
        $this->jugadorActivo = 1;
        $this->ronda = 1;
        $this->turno = 1;
        $this->mano1 = json_encode([]);
        $this->mano2 = json_encode([]);
        $this->puntos_j1 = 0;
        $this->puntos_j2 = 0;
        $this->ultimo_jugador = 1;
        $this->estado_partida = 'activa';
    }

    // Getters
    public function getId() { return $this->id; }
    public function getJugador1Id() { return $this->jugador1_id; }
    public function getJugador2Id() { return $this->jugador2_id; }
    public function getJugadorActivo() { return $this->jugadorActivo; }
    public function getRonda() { return $this->ronda; }
    public function getTurno() { return $this->turno; }
    public function getMano1() { return $this->mano1; }
    public function getMano2() { return $this->mano2; }
    public function getJugadorQueTiroDado() { return $this->jugadorQueTiroDado; }
    public function getRestriccion() { return $this->restriccion; }
    public function getRecintos() { return $this->recintos; }
    public function getGanador() { return $this->ganador; }
    public function getPuntosJ1() { return $this->puntos_j1; }
    public function getPuntosJ2() { return $this->puntos_j2; }
    public function getUltimoJugador() { return $this->ultimo_jugador; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getEstadoPartida() { return $this->estado_partida; }
    public function getNameInvitado() { return $this->name_invitado; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setJugador1Id($jugador1_id) { $this->jugador1_id = $jugador1_id; }
    public function setJugador2Id($jugador2_id) { $this->jugador2_id = $jugador2_id; }
    public function setJugadorActivo($jugadorActivo) { $this->jugadorActivo = $jugadorActivo; }
    public function setRonda($ronda) { $this->ronda = $ronda; }
    public function setTurno($turno) { $this->turno = $turno; }
    public function setMano1($mano1) { $this->mano1 = $mano1; }
    public function setMano2($mano2) { $this->mano2 = $mano2; }
    public function setJugadorQueTiroDado($jugadorQueTiroDado) { $this->jugadorQueTiroDado = $jugadorQueTiroDado; }
    public function setRestriccion($restriccion) { $this->restriccion = $restriccion; }
    public function setRecintos($recintos) { $this->recintos = $recintos; }
    public function setGanador($ganador) { $this->ganador = $ganador; }
    public function setPuntosJ1($puntos_j1) { $this->puntos_j1 = $puntos_j1; }
    public function setPuntosJ2($puntos_j2) { $this->puntos_j2 = $puntos_j2; }
    public function setUltimoJugador($ultimo_jugador) { $this->ultimo_jugador = $ultimo_jugador; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
    public function setUpdatedAt($updated_at) { $this->updated_at = $updated_at; }
    public function setEstadoPartida($estado_partida) { $this->estado_partida = $estado_partida; }
    public function setNameInvitado($name_invitado) { $this->name_invitado = $name_invitado; }

    // Validación
    public function validar() {
        $errores = [];

        if ($this->jugadorActivo !== null && !in_array($this->jugadorActivo, [1, 2])) {
            $errores[] = 'El jugador activo debe ser 1 o 2';
        }

        if ($this->ronda < 1 || $this->ronda > 5) {
            $errores[] = 'La ronda debe estar entre 1 y 5';
        }

        if ($this->turno < 1 || $this->turno > 3) {
            $errores[] = 'El turno debe estar entre 1 y 3';
        }

        if ($this->restriccion !== null && ($this->restriccion < 1 || $this->restriccion > 6)) {
            $errores[] = 'La restricción debe estar entre 1 y 6';
        }

        return $errores;
    }

    // Métodos de utilidad
    public function isActive() {
        return $this->estado_partida === 'activa';
    }

    public function isFinished() {
        return $this->ganador !== null;
    }

    public function getManoJugador($jugador) {
        if ($jugador == 1) {
            return json_decode($this->mano1, true);
        } elseif ($jugador == 2) {
            return json_decode($this->mano2, true);
        }
        return [];
    }

    public function setManoJugador($jugador, $mano) {
        if ($jugador == 1) {
            $this->mano1 = json_encode($mano);
        } elseif ($jugador == 2) {
            $this->mano2 = json_encode($mano);
        }
    }

    // Convertir a array
    public function toArray() {
        return [
            'id' => $this->id,
            'jugador1_id' => $this->jugador1_id,
            'jugador2_id' => $this->jugador2_id,
            'jugadorActivo' => $this->jugadorActivo,
            'ronda' => $this->ronda,
            'turno' => $this->turno,
            'mano1' => $this->mano1,
            'mano2' => $this->mano2,
            'jugadorQueTiroDado' => $this->jugadorQueTiroDado,
            'restriccion' => $this->restriccion,
            'recintos' => $this->recintos,
            'ganador' => $this->ganador,
            'puntos_j1' => $this->puntos_j1,
            'puntos_j2' => $this->puntos_j2,
            'ultimo_jugador' => $this->ultimo_jugador,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'estado_partida' => $this->estado_partida,
            'name_invitado' => $this->name_invitado
        ];
    }
}