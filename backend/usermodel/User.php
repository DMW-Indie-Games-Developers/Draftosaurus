<?php

class User {
    private $id;
    private $username;
    private $email;
    private $password;
    private $nickname;
    private $avatar;
    private $puntuacion_total;
    private $partidas_jugadas;
    private $partidas_ganadas;
    private $rol;
    private $estado;
    private $created_at;
    private $updated_at;

    public function __construct($username = null, $email = null, $password = null) {
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->avatar = 'img/isotipoOficial.png';
        $this->puntuacion_total = 0;
        $this->partidas_jugadas = 0;
        $this->partidas_ganadas = 0;
        $this->rol = 'usuario';
        $this->estado = 'activo';
    }

    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getPassword() { return $this->password; }
    public function getNickname() { return $this->nickname; }
    public function getAvatar() { return $this->avatar; }
    public function getPuntuacionTotal() { return $this->puntuacion_total; }
    public function getPartidasJugadas() { return $this->partidas_jugadas; }
    public function getPartidasGanadas() { return $this->partidas_ganadas; }
    public function getRol() { return $this->rol; }
    public function getEstado() { return $this->estado; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }

    // Setters
    public function setId($id) { $this->id = $id; }
    public function setUsername($username) { $this->username = $username; }
    public function setEmail($email) { $this->email = $email; }
    public function setPassword($password) { $this->password = $password; }
    public function setNickname($nickname) { $this->nickname = $nickname; }
    public function setAvatar($avatar) { $this->avatar = $avatar; }
    public function setPuntuacionTotal($puntuacion_total) { $this->puntuacion_total = $puntuacion_total; }
    public function setPartidasJugadas($partidas_jugadas) { $this->partidas_jugadas = $partidas_jugadas; }
    public function setPartidasGanadas($partidas_ganadas) { $this->partidas_ganadas = $partidas_ganadas; }
    public function setRol($rol) { $this->rol = $rol; }
    public function setEstado($estado) { $this->estado = $estado; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
    public function setUpdatedAt($updated_at) { $this->updated_at = $updated_at; }

    // Validación
    public function validar() {
        $errores = [];

        if (empty($this->username)) {
            $errores[] = 'El nombre de usuario es obligatorio';
        } elseif (strlen($this->username) > 50) {
            $errores[] = 'El nombre de usuario no puede tener más de 50 caracteres';
        }

        if (empty($this->email)) {
            $errores[] = 'El email es obligatorio';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El email no tiene un formato válido';
        } elseif (strlen($this->email) > 100) {
            $errores[] = 'El email no puede tener más de 100 caracteres';
        }

        if (empty($this->password)) {
            $errores[] = 'La contraseña es obligatoria';
        } elseif (strlen($this->password) < 6) {
            $errores[] = 'La contraseña debe tener al menos 6 caracteres';
        }

        if (!in_array($this->rol, ['admin', 'usuario'])) {
            $errores[] = 'El rol debe ser admin o usuario';
        }

        if (!in_array($this->estado, ['activo', 'suspendido'])) {
            $errores[] = 'El estado debe ser activo o suspendido';
        }

        return $errores;
    }

    // Métodos de utilidad
    public function isAdmin() {
        return $this->rol === 'admin';
    }

    public function isActive() {
        return $this->estado === 'activo';
    }

    public function hashPassword() {
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
    }

    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }

    // Convertir a array
    /**
     * Obtiene el nombre a mostrar (nickname si existe, si no username)
     */
    public function getDisplayName() {
        return !empty($this->nickname) ? $this->nickname : $this->username;
    }

    public function toArray() {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'nickname' => $this->nickname,
            'display_name' => $this->getDisplayName(),
            'avatar' => $this->avatar,
            'puntuacion_total' => $this->puntuacion_total,
            'partidas_jugadas' => $this->partidas_jugadas,
            'partidas_ganadas' => $this->partidas_ganadas,
            'rol' => $this->rol,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}