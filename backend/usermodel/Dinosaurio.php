<?php

class Dinosaurio {
    private $especie;
    private $jugador;
    private $masa; // kg
    private $radio; // m (radio del dinosaurio para momento de inercia)

    // Propiedades físicas por especie (basadas en dinosaurios reales)
    const PROPIEDADES_FISICAS = [
        'dino1' => [
            'nombre' => 'Compsognathus',
            'masa' => 2.5,      // kg
            'radio' => 0.3      // m
        ],
        'dino2' => [
            'nombre' => 'Velociraptor',
            'masa' => 15.0,     // kg
            'radio' => 0.6      // m
        ],
        'dino3' => [
            'nombre' => 'Parasaurolophus',
            'masa' => 3500.0,   // kg
            'radio' => 1.5      // m
        ],
        'dino4' => [
            'nombre' => 'Triceratops',
            'masa' => 6000.0,   // kg
            'radio' => 2.0      // m
        ],
        'dino5' => [
            'nombre' => 'Brontosaurus',
            'masa' => 15000.0,  // kg
            'radio' => 3.0      // m
        ],
        'trex' => [
            'nombre' => 'Tyrannosaurus Rex',
            'masa' => 7000.0,   // kg
            'radio' => 2.5      // m
        ]
    ];

    public function __construct($especie = null, $jugador = null) {
        $this->especie = $especie;
        $this->jugador = $jugador;

        if ($especie && isset(self::PROPIEDADES_FISICAS[$especie])) {
            $props = self::PROPIEDADES_FISICAS[$especie];
            $this->masa = $props['masa'];
            $this->radio = $props['radio'];
        }
    }

    // Getters
    public function getEspecie() { return $this->especie; }
    public function getJugador() { return $this->jugador; }
    public function getMasa() { return $this->masa; }
    public function getRadio() { return $this->radio; }
    public function getNombre() {
        return self::PROPIEDADES_FISICAS[$this->especie]['nombre'] ?? $this->especie;
    }

    // Setters
    public function setEspecie($especie) {
        $this->especie = $especie;
        if (isset(self::PROPIEDADES_FISICAS[$especie])) {
            $props = self::PROPIEDADES_FISICAS[$especie];
            $this->masa = $props['masa'];
            $this->radio = $props['radio'];
        }
    }
    public function setJugador($jugador) { $this->jugador = $jugador; }

    /**
     * Calcula el momento de inercia del dinosaurio respecto a un punto
     * I = m * r²
     * donde r es la distancia al eje de rotación
     */
    public function calcularMomentoInercia($distancia_al_eje) {
        // Para un dinosaurio, usamos el modelo de una esfera sólida
        // I_esfera = (2/5) * m * R² + m * d²
        // donde R es el radio del dinosaurio y d es la distancia al eje

        $momento_propio = (2/5) * $this->masa * pow($this->radio, 2);
        $momento_traslacion = $this->masa * pow($distancia_al_eje, 2);

        return $momento_propio + $momento_traslacion;
    }

    /**
     * Validación
     */
    public function validar() {
        $errores = [];

        if (empty($this->especie)) {
            $errores[] = 'La especie es obligatoria';
        }

        if (!isset(self::PROPIEDADES_FISICAS[$this->especie])) {
            $errores[] = 'Especie no válida';
        }

        if ($this->jugador !== null && !in_array($this->jugador, [1, 2])) {
            $errores[] = 'El jugador debe ser 1 o 2';
        }

        return $errores;
    }

    /**
     * Convertir a array
     */
    public function toArray() {
        return [
            'especie' => $this->especie,
            'jugador' => $this->jugador,
            'masa' => $this->masa,
            'radio' => $this->radio,
            'nombre' => $this->getNombre()
        ];
    }

    /**
     * Métodos estáticos de utilidad
     */
    public static function obtenerMasa($especie) {
        return self::PROPIEDADES_FISICAS[$especie]['masa'] ?? 0;
    }

    public static function obtenerRadio($especie) {
        return self::PROPIEDADES_FISICAS[$especie]['radio'] ?? 0;
    }

    public static function obtenerTodasLasEspecies() {
        return array_keys(self::PROPIEDADES_FISICAS);
    }
}