<?php

require_once __DIR__ . '/../../usermodel/Dinosaurio.php';

class FisicaService {

    // Coordenadas de los recintos en el tablero (en metros)
    // Tablero: 6 recintos en disposición 3x2
    const COORDENADAS_RECINTOS = [
        'bosque-semejanza' => ['x' => 1.0, 'y' => 1.0],
        'trio-frondoso' => ['x' => 3.0, 'y' => 1.0],
        'pradera-amor' => ['x' => 5.0, 'y' => 1.0],
        'rey-selva' => ['x' => 1.0, 'y' => 3.0],
        'prado-diferencia' => ['x' => 3.0, 'y' => 3.0],
        'rio' => ['x' => 2.0, 'y' => 2.0],     // Centro del tablero
        'isla-solitario' => ['x' => 5.0, 'y' => 3.0]
    ];

    // Centro del tablero (eje de rotación)
    const CENTRO_TABLERO = ['x' => 3.0, 'y' => 2.0];

    /**
     * Calcula el centro de masa del tablero de un jugador
     */
    public function calcularCentroMasa($recintos, $jugador) {
        $masa_total = 0;
        $momento_x = 0;
        $momento_y = 0;

        foreach ($recintos as $tipo_recinto => $recinto_data) {
            if (!isset(self::COORDENADAS_RECINTOS[$tipo_recinto])) continue;

            $coord = self::COORDENADAS_RECINTOS[$tipo_recinto];
            $dinosaurios = $recinto_data['dinosaurios'] ?? [];

            foreach ($dinosaurios as $dino_data) {
                if ($dino_data['jugador'] == $jugador) {
                    $masa = Dinosaurio::obtenerMasa($dino_data['especie']);

                    $masa_total += $masa;
                    $momento_x += $masa * $coord['x'];
                    $momento_y += $masa * $coord['y'];
                }
            }
        }

        if ($masa_total == 0) {
            return ['x' => 0, 'y' => 0, 'masa_total' => 0];
        }

        return [
            'x' => $momento_x / $masa_total,
            'y' => $momento_y / $masa_total,
            'masa_total' => $masa_total
        ];
    }

    /**
     * Calcula el momento de inercia total del tablero para un jugador
     * I = Σ(m × r²) donde r es la distancia al eje de rotación (centro del tablero)
     */
    public function calcularMomentoInercia($recintos, $jugador) {
        $momento_total = 0;
        $centro = self::CENTRO_TABLERO;

        error_log("=== CÁLCULO MOMENTO DE INERCIA JUGADOR $jugador ===");

        foreach ($recintos as $tipo_recinto => $recinto_data) {
            if (!isset(self::COORDENADAS_RECINTOS[$tipo_recinto])) continue;

            $coord = self::COORDENADAS_RECINTOS[$tipo_recinto];
            $dinosaurios = $recinto_data['dinosaurios'] ?? [];

            foreach ($dinosaurios as $dino_data) {
                if ($dino_data['jugador'] == $jugador) {
                    $dinosaurio = new Dinosaurio($dino_data['especie'], $jugador);

                    // Calcular distancia al centro del tablero
                    $distancia = sqrt(
                        pow($coord['x'] - $centro['x'], 2) +
                        pow($coord['y'] - $centro['y'], 2)
                    );

                    // Calcular momento de inercia del dinosaurio
                    $momento_dino = $dinosaurio->calcularMomentoInercia($distancia);
                    $momento_total += $momento_dino;

                    error_log("Dino {$dino_data['especie']} en $tipo_recinto: masa={$dinosaurio->getMasa()}kg, distancia={$distancia}m, momento={$momento_dino}");
                }
            }
        }

        error_log("Momento total jugador $jugador: {$momento_total} kg⋅m²");
        return $momento_total;
    }

    /**
     * Calcula el bonus de puntuación basado en momento de inercia
     * Tableros con distribución más equilibrada (menor momento) obtienen bonus
     */
    public function calcularBonusInercia($momento_inercia, $masa_total) {
        if ($masa_total == 0) return 0;

        // Normalizar por masa total para comparar distribuciones
        $inercia_normalizada = $momento_inercia / $masa_total;

        error_log("Inercia normalizada: {$inercia_normalizada}");

        // Rangos de bonus basados en distribución
        if ($inercia_normalizada < 5.0) {
            error_log("Bonus: +8 puntos (distribución muy concentrada)");
            return 8; // Distribución muy concentrada - muy estable
        } elseif ($inercia_normalizada < 10.0) {
            error_log("Bonus: +5 puntos (distribución concentrada)");
            return 5; // Distribución concentrada - estable
        } elseif ($inercia_normalizada < 15.0) {
            error_log("Bonus: +2 puntos (distribución equilibrada)");
            return 2; // Distribución equilibrada
        } elseif ($inercia_normalizada < 20.0) {
            error_log("Bonus: 0 puntos (distribución dispersa)");
            return 0; // Distribución dispersa
        } else {
            error_log("Penalización: -3 puntos (distribución muy dispersa)");
            return -3; // Distribución muy dispersa - inestable
        }
    }

    /**
     * Calcula el análisis físico completo para ambos jugadores
     */
    public function analizarFisicaCompleta($recintos) {
        $resultado = [
            'jugador1' => $this->analizarJugador($recintos, 1),
            'jugador2' => $this->analizarJugador($recintos, 2)
        ];

        error_log("=== ANÁLISIS FÍSICO COMPLETO ===");
        error_log("J1: Momento={$resultado['jugador1']['momento_inercia']}, Bonus={$resultado['jugador1']['bonus']}");
        error_log("J2: Momento={$resultado['jugador2']['momento_inercia']}, Bonus={$resultado['jugador2']['bonus']}");

        return $resultado;
    }

    /**
     * Análisis físico para un jugador específico
     */
    private function analizarJugador($recintos, $jugador) {
        $centro_masa = $this->calcularCentroMasa($recintos, $jugador);
        $momento_inercia = $this->calcularMomentoInercia($recintos, $jugador);
        $bonus = $this->calcularBonusInercia($momento_inercia, $centro_masa['masa_total']);

        return [
            'centro_masa' => $centro_masa,
            'momento_inercia' => $momento_inercia,
            'bonus_fisica' => $bonus,
            'bonus' => $bonus, // Alias para compatibilidad
            'masa_total' => $centro_masa['masa_total']
        ];
    }

    /**
     * Obtener información de coordenadas para debugging
     */
    public static function obtenerCoordenadasRecintos() {
        return self::COORDENADAS_RECINTOS;
    }

    /**
     * Obtener centro del tablero
     */
    public static function obtenerCentroTablero() {
        return self::CENTRO_TABLERO;
    }
}