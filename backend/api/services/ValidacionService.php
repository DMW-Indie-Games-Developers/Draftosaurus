<?php
declare(strict_types=1);

/**
 * ValidacionService - Validaciones de seguridad del juego en backend
 *
 * Este servicio implementa todas las validaciones de reglas del juego
 * para prevenir trampas y manipulaciones desde el frontend.
 */
class ValidacionService
{
    // Configuración de restricciones del dado (6 caras reales: 1-6)
    // NOTA: El jugador que TIRA el dado juega SIN restricción (ve dado6.png)
    // El OTRO jugador debe seguir la restricción del dado
    private const RESTRICCIONES = [
        1 => 'zona_izquierda',    // Zona izquierda
        2 => 'zona_derecha',      // Zona derecha
        3 => 'zona_boscosa',      // Zona boscosa (ambiente boscoso)
        4 => 'recinto_vacio',     // Recinto vacío
        5 => 'sin_trex',          // Recinto sin T-Rex (del jugador activo)
        6 => 'zona_rocosa'        // Zona rocosa (ambiente rocoso)
    ];

    // Configuración de recintos
    private const CONFIG_RECINTOS = [
        'bosque-semejanza' => [
            'nombre' => 'El Bosque de la Semejanza',
            'tipo' => 'semejanza',
            'ambiente' => 'boscoso',
            'zona' => 'izquierda',
            'capacidad_max' => null, // Sin límite
            'regla_especial' => 'solo_misma_especie'
        ],
        'prado-diferencia' => [
            'nombre' => 'El Prado de la Diferencia',
            'tipo' => 'diferencia',
            'ambiente' => 'rocoso',
            'zona' => 'derecha',
            'capacidad_max' => 6,
            'regla_especial' => 'solo_especies_diferentes'
        ],
        'pradera-amor' => [
            'nombre' => 'La Pradera del Amor',
            'tipo' => 'parejas',
            'ambiente' => 'rocoso',
            'zona' => 'izquierda',
            'capacidad_max' => null,
            'regla_especial' => 'solo_parejas'
        ],
        'trio-frondoso' => [
            'nombre' => 'El Trío Frondoso',
            'tipo' => 'trio',
            'ambiente' => 'boscoso',
            'zona' => 'izquierda',
            'capacidad_max' => 3,
            'regla_especial' => 'exactamente_tres'
        ],
        'rey-selva' => [
            'nombre' => 'El Rey de la Selva',
            'tipo' => 'rey',
            'ambiente' => 'boscoso',
            'zona' => 'derecha',
            'capacidad_max' => 1,
            'regla_especial' => 'solo_uno'
        ],
        'isla-solitario' => [
            'nombre' => 'La Isla Solitaria',
            'tipo' => 'solitario',
            'ambiente' => 'rocoso',
            'zona' => 'derecha',
            'capacidad_max' => 1,
            'regla_especial' => 'solo_uno'
        ],
        'rio' => [
            'nombre' => 'El Río',
            'tipo' => 'rio',
            'ambiente' => 'neutral',
            'zona' => 'centro',
            'capacidad_max' => null,
            'regla_especial' => 'siempre_disponible'
        ]
    ];

    /**
     * Valida una colocación completa de dinosaurio
     *
     * @param array $datos Datos de la colocación
     * @param array $estadoPartida Estado actual de la partida
     * @return array ['valida' => bool, 'razon' => string]
     */
    public function validarColocacion(array $datos, array $estadoPartida): array
    {
        error_log("=== VALIDACIÓN DE COLOCACIÓN ===");
        error_log("Datos recibidos: " . json_encode($datos));
        error_log("Estado partida: " . json_encode([
            'jugadorActivo' => $estadoPartida['jugadorActivo'] ?? null,
            'jugadorQueTiroDado' => $estadoPartida['jugadorQueTiroDado'] ?? null,
            'restriccion' => $estadoPartida['restriccion'] ?? null
        ]));

        // 1. Validar que los datos necesarios existen
        $validacionDatos = $this->validarDatosCompletos($datos, $estadoPartida);
        if (!$validacionDatos['valida']) {
            return $validacionDatos;
        }

        $recintoId = $datos['recintoId'];
        $especie = $datos['especie'];
        $jugadorActivo = $estadoPartida['jugadorActivo'];
        $jugadorQueTiroDado = $estadoPartida['jugadorQueTiroDado'];
        $restriccion = $estadoPartida['restriccion'];
        $recintos = $estadoPartida['recintos'] ?? [];

        // 2. Validar que el jugador tiene el dinosaurio en su mano
        $validacionMano = $this->validarDinosaurioEnMano($especie, $jugadorActivo, $estadoPartida);
        if (!$validacionMano['valida']) {
            return $validacionMano;
        }

        // 3. Validar restricción del dado (solo para el jugador que NO tiró)
        if ($jugadorActivo !== $jugadorQueTiroDado) {
            $validacionRestriccion = $this->validarRestriccionDado(
                $recintoId,
                $restriccion,
                $recintos,
                $especie,
                $jugadorActivo
            );
            if (!$validacionRestriccion['valida']) {
                return $validacionRestriccion;
            }
        } else {
            error_log("✅ Jugador que tiró el dado - SIN restricción aplicada");
        }

        // 4. Validar reglas específicas del recinto
        $validacionRecinto = $this->validarReglasRecinto(
            $recintoId,
            $especie,
            $jugadorActivo,
            $recintos
        );
        if (!$validacionRecinto['valida']) {
            return $validacionRecinto;
        }

        error_log("✅ COLOCACIÓN VÁLIDA - Todas las validaciones pasadas");
        return [
            'valida' => true,
            'razon' => 'Colocación válida'
        ];
    }

    /**
     * Valida que todos los datos necesarios estén presentes
     */
    private function validarDatosCompletos(array $datos, array $estadoPartida): array
    {
        if (empty($datos['recintoId'])) {
            error_log("❌ VALIDACIÓN FALLIDA: recintoId faltante");
            return [
                'valida' => false,
                'razon' => 'ID de recinto no proporcionado'
            ];
        }

        if (empty($datos['especie'])) {
            error_log("❌ VALIDACIÓN FALLIDA: especie faltante");
            return [
                'valida' => false,
                'razon' => 'Especie de dinosaurio no proporcionada'
            ];
        }

        if (!isset($estadoPartida['jugadorActivo'])) {
            error_log("❌ VALIDACIÓN FALLIDA: jugadorActivo faltante en estado");
            return [
                'valida' => false,
                'razon' => 'Estado de partida incompleto (jugadorActivo)'
            ];
        }

        if (!isset($estadoPartida['jugadorQueTiroDado'])) {
            error_log("❌ VALIDACIÓN FALLIDA: jugadorQueTiroDado faltante en estado");
            return [
                'valida' => false,
                'razon' => 'Estado de partida incompleto (jugadorQueTiroDado)'
            ];
        }

        if (!isset($estadoPartida['restriccion'])) {
            error_log("❌ VALIDACIÓN FALLIDA: restriccion faltante en estado");
            return [
                'valida' => false,
                'razon' => 'Estado de partida incompleto (restriccion)'
            ];
        }

        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Valida que el dinosaurio esté en la mano del jugador
     */
    private function validarDinosaurioEnMano(string $especie, int $jugador, array $estadoPartida): array
    {
        $manoKey = "mano{$jugador}";
        $mano = $estadoPartida[$manoKey] ?? [];

        // Convertir mano a array si es string JSON
        if (is_string($mano)) {
            $mano = json_decode($mano, true) ?? [];
        }

        error_log("Validando mano del jugador $jugador: " . json_encode($mano));

        if (!in_array($especie, $mano, true)) {
            error_log("❌ TRAMPA DETECTADA: Jugador $jugador intenta colocar '$especie' que NO está en su mano");
            error_log("Mano actual: " . json_encode($mano));
            return [
                'valida' => false,
                'razon' => "🚫 TRAMPA: El dinosaurio '$especie' no está en tu mano"
            ];
        }

        error_log("✅ Dinosaurio '$especie' encontrado en la mano del jugador $jugador");
        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Valida la restricción del dado para el jugador que NO lo tiró
     */
    private function validarRestriccionDado(
        string $recintoId,
        ?int $restriccion,
        array $recintos,
        string $especie,
        int $jugadorActivo
    ): array {
        // El río SIEMPRE está disponible para cualquier restricción
        if ($recintoId === 'rio') {
            error_log("✅ Río - siempre disponible, restricción ignorada");
            return ['valida' => true, 'razon' => ''];
        }

        if (!isset(self::CONFIG_RECINTOS[$recintoId])) {
            error_log("❌ Recinto desconocido: $recintoId");
            return [
                'valida' => false,
                'razon' => "Recinto '$recintoId' no reconocido"
            ];
        }

        $config = self::CONFIG_RECINTOS[$recintoId];
        $zona = $config['zona'];
        $ambiente = $config['ambiente'];

        error_log("Validando restricción $restriccion para recinto $recintoId (zona=$zona, ambiente=$ambiente)");

        switch ($restriccion) {
            case 1: // Zona izquierda
                if ($zona !== 'izquierda') {
                    error_log("❌ Restricción 1 (Zona izquierda): zona debe ser 'izquierda', es '$zona'");
                    return [
                        'valida' => false,
                        'razon' => '🎲 Restricción del dado: Solo puedes colocar en la ZONA IZQUIERDA'
                    ];
                }
                break;

            case 2: // Zona derecha
                if ($zona !== 'derecha') {
                    error_log("❌ Restricción 2 (Zona derecha): zona debe ser 'derecha', es '$zona'");
                    return [
                        'valida' => false,
                        'razon' => '🎲 Restricción del dado: Solo puedes colocar en la ZONA DERECHA'
                    ];
                }
                break;

            case 3: // Zona boscosa (ambiente boscoso)
                if ($ambiente !== 'boscoso') {
                    error_log("❌ Restricción 3 (Zona boscosa): ambiente debe ser 'boscoso', es '$ambiente'");
                    return [
                        'valida' => false,
                        'razon' => '🎲 Restricción del dado: Solo puedes colocar en la ZONA BOSCOSA'
                    ];
                }
                break;

            case 4: // Recinto vacío
                $recintoData = $recintos[$recintoId] ?? ['dinosaurios' => []];
                $dinosaurios = $recintoData['dinosaurios'] ?? [];

                if (!empty($dinosaurios)) {
                    error_log("❌ Restricción 4 (Recinto vacío): recinto tiene " . count($dinosaurios) . " dinosaurios");
                    return [
                        'valida' => false,
                        'razon' => '🎲 Restricción del dado: Solo puedes colocar en recintos VACÍOS'
                    ];
                }
                break;

            case 5: // Recinto sin T-Rex (del jugador activo)
                $recintoData = $recintos[$recintoId] ?? ['dinosaurios' => []];
                $dinosaurios = $recintoData['dinosaurios'] ?? [];

                // 🔧 Solo verificar T-Rex del jugador que está colocando, no del oponente
                foreach ($dinosaurios as $dino) {
                    if ($dino['especie'] === 'trex' && (int)$dino['jugador'] === $jugadorActivo) {
                        error_log("❌ Restricción 5 (Sin T-Rex): el jugador $jugadorActivo ya tiene un T-Rex en este recinto");
                        return [
                            'valida' => false,
                            'razon' => '🎲 Restricción del dado: No puedes colocar en recintos donde YA TENGAS un T-REX'
                        ];
                    }
                }
                break;

            case 6: // Zona rocosa (ambiente rocoso)
                if ($ambiente !== 'rocoso') {
                    error_log("❌ Restricción 6 (Zona rocosa): ambiente debe ser 'rocoso', es '$ambiente'");
                    return [
                        'valida' => false,
                        'razon' => '🎲 Restricción del dado: Solo puedes colocar en la ZONA ROCOSA'
                    ];
                }
                break;

            default:
                error_log("⚠️ Restricción desconocida: $restriccion");
                return [
                    'valida' => false,
                    'razon' => "Restricción del dado inválida: $restriccion"
                ];
        }

        error_log("✅ Restricción del dado validada correctamente");
        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Valida las reglas específicas de cada tipo de recinto
     */
    private function validarReglasRecinto(
        string $recintoId,
        string $especie,
        int $jugador,
        array $recintos
    ): array {
        if (!isset(self::CONFIG_RECINTOS[$recintoId])) {
            return [
                'valida' => false,
                'razon' => "Recinto '$recintoId' no reconocido"
            ];
        }

        $config = self::CONFIG_RECINTOS[$recintoId];
        $recintoData = $recintos[$recintoId] ?? ['dinosaurios' => []];
        $dinosaurios = $recintoData['dinosaurios'] ?? [];

        error_log("Validando reglas del recinto '$recintoId' (tipo: {$config['tipo']})");
        error_log("Dinosaurios actuales en recinto: " . json_encode($dinosaurios));

        // Obtener solo los dinosaurios del jugador actual en este recinto
        $dinosJugador = array_filter($dinosaurios, function($d) use ($jugador) {
            return (int)$d['jugador'] === $jugador;
        });

        // 1. Validar capacidad máxima
        if ($config['capacidad_max'] !== null) {
            $cantidadActual = count($dinosJugador);
            if ($cantidadActual >= $config['capacidad_max']) {
                error_log("❌ Capacidad máxima alcanzada: $cantidadActual/{$config['capacidad_max']}");
                return [
                    'valida' => false,
                    'razon' => "🚫 Recinto lleno: máximo {$config['capacidad_max']} dinosaurios por jugador"
                ];
            }
        }

        // 2. Validar reglas específicas por tipo de recinto
        switch ($config['tipo']) {
            case 'semejanza': // Bosque de la Semejanza
                return $this->validarBosqueSemejanza($dinosJugador, $especie);

            case 'diferencia': // Prado de la Diferencia
                return $this->validarPradoDiferencia($dinosJugador, $especie);

            case 'parejas': // Pradera del Amor
                return $this->validarPraderaAmor($dinosJugador, $especie);

            case 'trio': // Trío Frondoso
                return $this->validarTrioFrondoso($dinosJugador, $especie);

            case 'rey': // Rey de la Selva
                return $this->validarReySelva($dinosJugador, $especie);

            case 'solitario': // Isla Solitaria
                // Ya validado por capacidad_max = 1
                return ['valida' => true, 'razon' => ''];

            case 'rio': // Río
                // Sin restricciones especiales
                return ['valida' => true, 'razon' => ''];

            default:
                error_log("⚠️ Tipo de recinto desconocido: {$config['tipo']}");
                return ['valida' => true, 'razon' => ''];
        }
    }

    /**
     * Bosque de la Semejanza: Solo dinosaurios de la misma especie
     */
    private function validarBosqueSemejanza(array $dinosJugador, string $especieNueva): array
    {
        if (empty($dinosJugador)) {
            // Primer dinosaurio, permitir
            return ['valida' => true, 'razon' => ''];
        }

        // Todos deben ser de la misma especie
        $especieExistente = $dinosJugador[0]['especie'] ?? null;

        if ($especieExistente && $especieNueva !== $especieExistente) {
            error_log("❌ Bosque Semejanza: especie '$especieNueva' diferente a '$especieExistente'");
            return [
                'valida' => false,
                'razon' => "🌳 Bosque de la Semejanza: Solo puedes colocar dinosaurios de la MISMA especie ($especieExistente)"
            ];
        }

        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Prado de la Diferencia: Solo dinosaurios de especies diferentes
     */
    private function validarPradoDiferencia(array $dinosJugador, string $especieNueva): array
    {
        foreach ($dinosJugador as $dino) {
            if ($dino['especie'] === $especieNueva) {
                error_log("❌ Prado Diferencia: especie '$especieNueva' ya existe");
                return [
                    'valida' => false,
                    'razon' => "🌾 Prado de la Diferencia: Ya tienes un '$especieNueva'. Todas deben ser DIFERENTES"
                ];
            }
        }

        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Pradera del Amor: Solo permite colocar en parejas (2 de la misma especie)
     */
    private function validarPraderaAmor(array $dinosJugador, string $especieNueva): array
    {
        // Contar especies actuales
        $contadorEspecies = [];
        foreach ($dinosJugador as $dino) {
            $esp = $dino['especie'];
            $contadorEspecies[$esp] = ($contadorEspecies[$esp] ?? 0) + 1;
        }

        // Si ya hay uno de esta especie, permitir (completar pareja)
        if (isset($contadorEspecies[$especieNueva]) && $contadorEspecies[$especieNueva] === 1) {
            return ['valida' => true, 'razon' => ''];
        }

        // Si no hay ninguno de esta especie, permitir (iniciar pareja)
        if (!isset($contadorEspecies[$especieNueva])) {
            return ['valida' => true, 'razon' => ''];
        }

        // Si ya hay 2 o más de esta especie, no permitir
        if ($contadorEspecies[$especieNueva] >= 2) {
            error_log("❌ Pradera Amor: Ya hay {$contadorEspecies[$especieNueva]} de '$especieNueva'");
            return [
                'valida' => false,
                'razon' => "💕 Pradera del Amor: Ya tienes una PAREJA completa de '$especieNueva' (máximo 2)"
            ];
        }

        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Trío Frondoso: Máximo 3 dinosaurios (ya validado por capacidad_max)
     */
    private function validarTrioFrondoso(array $dinosJugador, string $especieNueva): array
    {
        // La capacidad máxima ya se valida en validarReglasRecinto
        return ['valida' => true, 'razon' => ''];
    }

    /**
     * Rey de la Selva: Solo acepta T-Rex (máximo 1)
     */
    private function validarReySelva(array $dinosJugador, string $especieNueva): array
    {
        // Solo se permiten T-Rex
        if ($especieNueva !== 'trex') {
            error_log("❌ Rey de la Selva: Solo se permiten T-Rex, intento de colocar '$especieNueva'");
            return [
                'valida' => false,
                'razon' => "👑 Rey de la Selva: Solo se permiten T-REX en este recinto"
            ];
        }

        return ['valida' => true, 'razon' => ''];
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
     * Obtiene todas las restricciones del dado
     */
    public function getAllRestricciones(): array
    {
        return self::RESTRICCIONES;
    }
}
