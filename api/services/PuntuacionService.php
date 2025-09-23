<?php
declare(strict_types=1);

require_once __DIR__ . '/RecintoCalculatorService.php';

class PuntuacionService
{
    private RecintoCalculatorService $calculadora;
    
    private const CONFIG_RECINTOS = [
        'bosque-semejanza' => [
            'nombre' => 'El Bosque de la Semejanza',
            'tipo' => 'bosque-semejanza',
            'ambiente' => 'boscoso',
            'zona' => 'izquierda',
            'capacidad_max' => null,
            'puntos_por_dino' => [1 => 1, 2 => 3, 3 => 6, 4 => 10, 5 => 15, 6 => 21]
        ],
        'prado-diferencia' => [
            'nombre' => 'El Prado de la Diferencia',
            'tipo' => 'prado-diferencia',
            'ambiente' => 'rocoso',
            'zona' => 'derecha',
            'capacidad_max' => 6,
            'puntos_por_dino' => [1 => 1, 2 => 3, 3 => 6, 4 => 10, 5 => 15, 6 => 21]
        ],
        'pradera-amor' => [
            'nombre' => 'La Pradera del Amor',
            'tipo' => 'pradera-amor',
            'ambiente' => 'rocoso',
            'zona' => 'izquierda',
            'capacidad_max' => null,
            'puntos_por_pareja' => 5
        ],
        'trio-frondoso' => [
            'nombre' => 'El Trío Frondoso',
            'tipo' => 'trio-frondoso',
            'ambiente' => 'boscoso',
            'zona' => 'izquierda',
            'capacidad_max' => 3,
            'puntos_por_trio' => 7
        ],
        'rey-selva' => [
            'nombre' => 'El Rey de la Selva',
            'tipo' => 'rey-selva',
            'ambiente' => 'boscoso',
            'zona' => 'derecha',
            'capacidad_max' => 1,
            'puntos_ganador' => 7
        ],
        'isla-solitario' => [
            'nombre' => 'La Isla Solitaria',
            'tipo' => 'isla-solitario',
            'ambiente' => 'rocoso',
            'zona' => 'derecha',
            'capacidad_max' => 1,
            'puntos_solitario' => 7
        ],
        'rio' => [
            'nombre' => 'El Río',
            'tipo' => 'rio',
            'ambiente' => 'neutral',
            'zona' => 'centro',
            'capacidad_max' => null,
            'puntos_por_dino' => 1
        ]
    ];

    public function __construct()
    {
        $this->calculadora = new RecintoCalculatorService();
    }

    /**
     * Calcula las puntuaciones finales de ambos jugadores con logging detallado
     */
    public function calcularPuntuacionesFinales(array $recintos): array
    {
        error_log("=== INICIO CÁLCULO DE PUNTUACIONES ===");
        error_log("Recintos recibidos: " . json_encode($recintos, JSON_PRETTY_PRINT));
        
        $puntos = [1 => 0, 2 => 0];
        $detallePuntos = [1 => [], 2 => []];

        foreach ($recintos as $recintoId => $recintoData) {
            if (!isset(self::CONFIG_RECINTOS[$recintoId])) {
                error_log("ADVERTENCIA: Recinto desconocido: $recintoId");
                continue;
            }

            $config = self::CONFIG_RECINTOS[$recintoId];
            $dinos = $recintoData['dinosaurios'] ?? [];
            
            error_log("--- Procesando recinto: $recintoId ---");
            error_log("Configuración: " . json_encode($config));
            error_log("Dinosaurios: " . json_encode($dinos));

            if (empty($dinos)) {
                error_log("Recinto vacío, saltando...");
                continue;
            }

            // Agrupar dinosaurios por jugador
            $dinosPorJugador = $this->agruparDinosaurios($dinos);
            error_log("Dinosaurios por jugador: " . json_encode($dinosPorJugador));

            // Calcular puntos para cada jugador en este recinto
            foreach ([1, 2] as $jugador) {
                $especiesJugador = $dinosPorJugador[$jugador] ?? [];
                
                if (!empty($especiesJugador)) {
                    $puntosRecinto = $this->calculadora->calcularPuntosRecinto(
                        $config['tipo'],
                        $config,
                        $especiesJugador,
                        $recintos,
                        $jugador
                    );
                    
                    $puntos[$jugador] += $puntosRecinto;
                    $detallePuntos[$jugador][$recintoId] = $puntosRecinto;
                    
                    error_log("Jugador $jugador en $recintoId: $puntosRecinto puntos");
                }
            }
        }

        error_log("=== RESUMEN FINAL ===");
        error_log("Jugador 1: {$puntos[1]} puntos total");
        error_log("Detalle J1: " . json_encode($detallePuntos[1]));
        error_log("Jugador 2: {$puntos[2]} puntos total");
        error_log("Detalle J2: " . json_encode($detallePuntos[2]));
        error_log("=== FIN CÁLCULO DE PUNTUACIONES ===");

        return [$puntos[1], $puntos[2]];
    }

    /**
     * Calcula los puntos de un recinto específico para un jugador
     */
    public function calcularPuntosRecintoEspecifico(string $recintoId, int $jugador, array $todosLosRecintos): int
    {
        if (!isset(self::CONFIG_RECINTOS[$recintoId])) {
            error_log("Recinto desconocido: $recintoId");
            return 0;
        }

        $config = self::CONFIG_RECINTOS[$recintoId];
        $recintoData = $todosLosRecintos[$recintoId] ?? ['dinosaurios' => []];
        $dinos = $recintoData['dinosaurios'] ?? [];
        
        if (empty($dinos)) {
            return 0;
        }

        $dinosPorJugador = $this->agruparDinosaurios($dinos);
        $especiesJugador = $dinosPorJugador[$jugador] ?? [];
        
        if (empty($especiesJugador)) {
            return 0;
        }

        return $this->calculadora->calcularPuntosRecinto(
            $config['tipo'],
            $config,
            $especiesJugador,
            $todosLosRecintos,
            $jugador
        );
    }

    /**
     * Determina el ganador basándose en los puntos
     */
    public function determinarGanador(array $puntosCalculados): int
    {
        if (empty($puntosCalculados) || count($puntosCalculados) < 2) {
            error_log("ADVERTENCIA: Datos de puntos incompletos: " . json_encode($puntosCalculados));
            return 0;
        }
        
        $puntosJ1 = $puntosCalculados[0];
        $puntosJ2 = $puntosCalculados[1];
        
        error_log("Determinando ganador: J1=$puntosJ1, J2=$puntosJ2");
        
        if ($puntosJ1 > $puntosJ2) {
            error_log("Ganador: Jugador 1");
            return 1;
        }
        if ($puntosJ2 > $puntosJ1) {
            error_log("Ganador: Jugador 2");
            return 2;
        }
        
        error_log("Empate detectado");
        return 0; // Empate
    }

    /**
     * Obtiene la configuración de un recinto
     */
    public function getConfigRecinto(string $recintoId): ?array
    {
        return self::CONFIG_RECINTOS[$recintoId] ?? null;
    }

    /**
     * Obtiene todas las configuraciones de recintos
     */
    public function getAllConfigRecintos(): array
    {
        return self::CONFIG_RECINTOS;
    }

    /**
     * Valida que todos los recintos tengan configuración válida
     */
    public function validarConfiguraciones(): array
    {
        $errores = [];
        
        foreach (self::CONFIG_RECINTOS as $recintoId => $config) {
            $tipo = $config['tipo'] ?? $recintoId;
            $erroresRecinto = $this->calculadora->validarConfigRecinto($config, $tipo);
            
            if (!empty($erroresRecinto)) {
                $errores[$recintoId] = $erroresRecinto;
            }
        }
        
        return $errores;
    }

    /**
     * Agrupa los dinosaurios por jugador
     */
    private function agruparDinosaurios(array $dinos): array
    {
        $dinosPorJugador = [1 => [], 2 => []];
        
        foreach ($dinos as $dino) {
            $jugador = (int)$dino['jugador'];
            $especie = $dino['especie'];
            
            if (isset($dinosPorJugador[$jugador])) {
                $dinosPorJugador[$jugador][] = $especie;
            } else {
                error_log("ADVERTENCIA: Jugador inválido: $jugador");
            }
        }
        
        return $dinosPorJugador;
    }

    /**
     * Obtiene un resumen de puntuación por recintos
     */
    public function obtenerResumenPuntuacion(array $recintos): array
    {
        $resumen = [
            'jugador1' => ['total' => 0, 'recintos' => []],
            'jugador2' => ['total' => 0, 'recintos' => []]
        ];

        foreach ($recintos as $recintoId => $recintoData) {
            if (!isset(self::CONFIG_RECINTOS[$recintoId])) {
                continue;
            }

            $puntosJ1 = $this->calcularPuntosRecintoEspecifico($recintoId, 1, $recintos);
            $puntosJ2 = $this->calcularPuntosRecintoEspecifico($recintoId, 2, $recintos);

            $resumen['jugador1']['recintos'][$recintoId] = $puntosJ1;
            $resumen['jugador1']['total'] += $puntosJ1;
            
            $resumen['jugador2']['recintos'][$recintoId] = $puntosJ2;
            $resumen['jugador2']['total'] += $puntosJ2;
        }

        return $resumen;
    }

}