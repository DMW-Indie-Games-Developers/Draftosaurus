<?php
declare(strict_types=1);

/**
 * Servicio especializado en cálculos de puntuación por recinto
 * Separado para mejor testing y mantenimiento
 */
class RecintoCalculatorService
{
    /**
     * Calcula puntos de un recinto específico para un jugador
     */
    public function calcularPuntosRecinto(string $tipoRecinto, array $config, array $especiesJugador, array $todosLosRecintos, int $jugador): int
    {
        if (empty($especiesJugador)) {
            return 0;
        }

        $puntos = 0;
        $cuentaEspecies = array_count_values($especiesJugador);

        switch ($tipoRecinto) {
            case 'bosque-semejanza':
                $puntos = $this->calcularBosqueSemejanza($config, $cuentaEspecies);
                break;
                
            case 'prado-diferencia':
                $puntos = $this->calcularPradoDiferencia($config, $cuentaEspecies);
                break;
                
            case 'pradera-amor':
                $puntos = $this->calcularPraderaAmor($config, $cuentaEspecies);
                break;
                
            case 'trio-frondoso':
                $puntos = $this->calcularTrioFrondoso($config, $especiesJugador);
                break;
                
            case 'rey-selva':
                $puntos = $this->calcularReySelva($config, $especiesJugador, $todosLosRecintos, $jugador);
                break;
                
            case 'isla-solitario':
                $puntos = $this->calcularIslaSolitario($config, $especiesJugador, $todosLosRecintos, $jugador);
                break;
                
            case 'rio':
                $puntos = $this->calcularRio($config, $especiesJugador);
                break;
                
            default:
                error_log("Tipo de recinto desconocido: $tipoRecinto");
                return 0;
        }

        // Bonus T-Rex (excepto en el río)
        if ($tipoRecinto !== 'rio' && in_array('trex', $especiesJugador)) {
            $puntos += 1;
            error_log("Bonus T-Rex aplicado (+1) en recinto $tipoRecinto para jugador $jugador");
        }

        error_log("Recinto $tipoRecinto - Jugador $jugador: {$puntos} puntos (especies: " . json_encode($especiesJugador) . ")");
        return $puntos;
    }

    /**
     * Bosque de la Semejanza: Solo misma especie
     * Puntuación según cantidad de dinosaurios de la especie mayoritaria
     */
    private function calcularBosqueSemejanza(array $config, array $cuentaEspecies): int
    {
        $maxCantidad = max($cuentaEspecies);
        $puntos = $config['puntos_por_dino'][$maxCantidad] ?? 0;
        
        error_log("Bosque Semejanza: max cantidad $maxCantidad = $puntos puntos");
        return $puntos;
    }

    /**
     * Prado de la Diferencia: Solo especies distintas
     * Puntuación según cantidad de especies diferentes
     */
    private function calcularPradoDiferencia(array $config, array $cuentaEspecies): int
    {
        $especiesDiferentes = count($cuentaEspecies);
        $puntos = $config['puntos_por_dino'][$especiesDiferentes] ?? 0;
        
        error_log("Prado Diferencia: $especiesDiferentes especies = $puntos puntos");
        return $puntos;
    }

    /**
     * Pradera del Amor: Puntos por parejas
     * 5 puntos por cada pareja de la misma especie
     */
    private function calcularPraderaAmor(array $config, array $cuentaEspecies): int
    {
        $puntos = 0;
        $detalles = [];
        
        foreach ($cuentaEspecies as $especie => $cantidad) {
            $parejas = floor($cantidad / 2);
            $puntosPareja = $parejas * $config['puntos_por_pareja'];
            $puntos += $puntosPareja;
            
            if ($parejas > 0) {
                $detalles[] = "$especie: $parejas parejas = $puntosPareja pts";
            }
        }
        
        error_log("Pradera Amor: " . implode(', ', $detalles) . " = $puntos puntos total");
        return $puntos;
    }

    /**
     * Trío Frondoso: Exactamente 3 dinosaurios
     */
    private function calcularTrioFrondoso(array $config, array $especies): int
    {
        $cantidad = count($especies);
        $puntos = ($cantidad === 3) ? $config['puntos_por_trio'] : 0;
        
        error_log("Trío Frondoso: $cantidad dinosaurios = $puntos puntos");
        return $puntos;
    }

    /**
     * Rey de la Selva: Mayoría de esa especie en todo el parque
     */
    private function calcularReySelva(array $config, array $especies, array $todosLosRecintos, int $jugador): int
    {
        if (count($especies) !== 1) {
            error_log("Rey Selva: No hay exactamente 1 dinosaurio");
            return 0;
        }

        $especie = $especies[0];
        $totalJ1 = $this->contarEspecieEnTodosLosRecintos($todosLosRecintos, $especie, 1);
        $totalJ2 = $this->contarEspecieEnTodosLosRecintos($todosLosRecintos, $especie, 2);
        
        error_log("Rey Selva - Especie $especie: J1=$totalJ1, J2=$totalJ2");
        
        if ($jugador === 1 && $totalJ1 >= $totalJ2) {
            error_log("Rey Selva: Jugador 1 gana con $especie");
            return $config['puntos_ganador'];
        } elseif ($jugador === 2 && $totalJ2 >= $totalJ1) {
            error_log("Rey Selva: Jugador 2 gana con $especie");
            return $config['puntos_ganador'];
        }
        
        error_log("Rey Selva: Jugador $jugador no tiene mayoría de $especie");
        return 0;
    }

    /**
     * Isla Solitaria: Único de su especie en todo el parque del jugador
     */
    private function calcularIslaSolitario(array $config, array $especies, array $todosLosRecintos, int $jugador): int
    {
        if (count($especies) !== 1) {
            error_log("Isla Solitaria: No hay exactamente 1 dinosaurio");
            return 0;
        }

        $especie = $especies[0];
        $totalJugador = $this->contarEspecieEnTodosLosRecintos($todosLosRecintos, $especie, $jugador);
        
        error_log("Isla Solitaria - Especie $especie: Jugador $jugador tiene $totalJugador en total");
        
        if ($totalJugador === 1) {
            error_log("Isla Solitaria: $especie es único para jugador $jugador");
            return $config['puntos_solitario'];
        }
        
        return 0;
    }

    /**
     * Río: 1 punto por dinosaurio
     */
    private function calcularRio(array $config, array $especies): int
    {
        $cantidad = count($especies);
        $puntos = $cantidad * $config['puntos_por_dino'];
        
        error_log("Río: $cantidad dinosaurios = $puntos puntos");
        return $puntos;
    }

    /**
     * Cuenta cuántos dinosaurios de una especie tiene un jugador en TODOS los recintos
     */
    private function contarEspecieEnTodosLosRecintos(array $todosLosRecintos, string $especie, int $jugador): int
    {
        $count = 0;
        
        foreach ($todosLosRecintos as $recintoId => $recintoData) {
            $dinos = $recintoData['dinosaurios'] ?? [];
            foreach ($dinos as $dino) {
                if ((int)$dino['jugador'] === $jugador && $dino['especie'] === $especie) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Valida la configuración de un recinto
     */
    public function validarConfigRecinto(array $config, string $tipoRecinto): array
    {
        $errores = [];
        
        switch ($tipoRecinto) {
            case 'bosque-semejanza':
            case 'prado-diferencia':
                if (!isset($config['puntos_por_dino']) || !is_array($config['puntos_por_dino'])) {
                    $errores[] = "Falta configuración puntos_por_dino para $tipoRecinto";
                }
                break;
                
            case 'pradera-amor':
                if (!isset($config['puntos_por_pareja'])) {
                    $errores[] = "Falta configuración puntos_por_pareja para $tipoRecinto";
                }
                break;
                
            case 'trio-frondoso':
                if (!isset($config['puntos_por_trio'])) {
                    $errores[] = "Falta configuración puntos_por_trio para $tipoRecinto";
                }
                break;
                
            case 'rey-selva':
                if (!isset($config['puntos_ganador'])) {
                    $errores[] = "Falta configuración puntos_ganador para $tipoRecinto";
                }
                break;
                
            case 'isla-solitario':
                if (!isset($config['puntos_solitario'])) {
                    $errores[] = "Falta configuración puntos_solitario para $tipoRecinto";
                }
                break;
                
            case 'rio':
                if (!isset($config['puntos_por_dino'])) {
                    $errores[] = "Falta configuración puntos_por_dino para río";
                }
                break;
        }
        
        return $errores;
    }
}