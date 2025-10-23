<?php

class Contacto {
    private $id;
    private $nombre;
    private $email;
    private $asunto;
    private $mensaje;
    private $fecha_envio;

    public function __construct($nombre = null, $email = null, $asunto = null, $mensaje = null) {
        $this->nombre = $nombre;
        $this->email = $email;
        $this->asunto = $asunto;
        $this->mensaje = $mensaje;
    }

    // Getters
    public function getId() { return $this->id; }
    public function getNombre() { return $this->nombre; }
    public function getEmail() { return $this->email; }
    public function getAsunto() { return $this->asunto; }
    public function getMensaje() { return $this->mensaje; }
    public function getFechaEnvio() { return $this->fecha_envio; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setNombre($nombre) { $this->nombre = $nombre; }
    public function setEmail($email) { $this->email = $email; }
    public function setAsunto($asunto) { $this->asunto = $asunto; }
    public function setMensaje($mensaje) { $this->mensaje = $mensaje; }
    public function setFechaEnvio($fecha_envio) { $this->fecha_envio = $fecha_envio; }

    // Validación
    public function validar() {
        $errores = [];

        if (empty($this->nombre)) {
            $errores[] = 'El nombre es obligatorio';
        } elseif (strlen($this->nombre) > 100) {
            $errores[] = 'El nombre no puede tener más de 100 caracteres';
        }

        if (empty($this->email)) {
            $errores[] = 'El email es obligatorio';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido';
        } elseif (strlen($this->email) > 255) {
            $errores[] = 'El email no puede tener más de 255 caracteres';
        }

        if (empty($this->asunto)) {
            $errores[] = 'El asunto es obligatorio';
        } elseif (strlen($this->asunto) > 255) {
            $errores[] = 'El asunto no puede tener más de 255 caracteres';
        }

        if (empty($this->mensaje)) {
            $errores[] = 'El mensaje es obligatorio';
        } elseif (strlen($this->mensaje) > 5000) {
            $errores[] = 'El mensaje no puede tener más de 5000 caracteres';
        }

        return $errores;
    }

    // Convertir a array
    public function toArray() {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'asunto' => $this->asunto,
            'mensaje' => $this->mensaje,
            'fecha_envio' => $this->fecha_envio
        ];
    }
}