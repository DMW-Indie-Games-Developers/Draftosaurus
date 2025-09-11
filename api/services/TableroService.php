<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/TableroRepository.php';

class TableroService
{
    private TableroRepository $repository;

    public function __construct()
    {
        $this->repository = new TableroRepository();
    }

    /* ----------  CREAR PARTIDA COMPLETA  ---------- */
    public function crearPartida(string $jugador1, ?string $jugador2, array $config): int
    {
        $datosPartida = [
            'jugador1'          => $jugador1,
            'jugador2'          => $jugador2 ?? 'CPU',
            'jugadorActivo'     => 1,
            'ronda'             => 1,
            'turno'             => 1,
            'jugadorQueTiroDado'=> 0,
            'restriccion'       => null,
            'mano1'             => $this->generarManoInicial(),
            'mano2'             => $this->generarManoInicial(),
            'estado'            => $this->inicializarRecintos()
        ];

        return $this->repository->crearPartidaMinimal($datosPartida);
    }

    /* ----------  CREAR PARTIDA MINIMAL  ---------- */
    public function crearPartidaMinimal(array $datos): int
    {
        $this->validarDatosPartida($datos);

        // Garantizar que los JSON siempre sean arrays
        foreach (['mano1', 'mano2', 'estado'] as $k) {
            if (!isset($datos[$k]) || !is_array($datos[$k])) {
                $datos[$k] = [];
            }
        }

        return $this->repository->crearPartidaMinimal($datos);
    }

    /* ----------  GUARDAR ESTADO  ---------- */
    public function guardarEstadoPartida(array $datos): bool
    {
        $idPartida = (int)($datos['id'] ?? 0);
        if ($idPartida <= 0) {
            throw new InvalidArgumentException("ID de partida inválido");
        }

        // Preparar el array que le enviaremos al repository
        $estadoCompleto = [
            'recintos'              => $datos['colocaciones'] ?? $datos['recintos'] ?? $datos['estado'] ?? [],
            'ronda_actual'          => (int)($datos['ronda']          ?? 1),
            'turno_actual'          => (int)($datos['turno']          ?? 1),
            'jugador_activo'        => (int)($datos['jugadorActivo']  ?? 1),
            'jugador_que_tiro_dado' => (int)($datos['jugadorQueTiroDado'] ?? 0),
            'restriccion_actual'    => $datos['restriccion'] ?? null,
            'mano_jugador1'         => $datos['mano1'] ?? [],
            'mano_jugador2'         => $datos['mano2'] ?? [],
            'ultimo_jugador'        => $datos['ultimo_jugador'] ?? null
        ];

        return $this->repository->guardarEstadoPartida($idPartida, $estadoCompleto);
    }

    /* ----------  CARGAR PARTIDA  ---------- */
    public function cargarPartida(int $idPartida): ?array
    {
        if ($idPartida <= 0) return null;

        $partida = $this->repository->obtenerEstadoCompletoPartida($idPartida);
        if (!$partida) return null;

        // Normalizar las manos para el formato que espera el frontend
        $mano1 = $this->normalizarMano($partida['mano_jugador1']);
        $mano2 = $this->normalizarMano($partida['mano_jugador2']);

        // Traducir al formato que espera el frontend
        return [
            'id'                    => $partida['id'],
            'jugador1'              => $partida['jugador1'],
            'jugador2'              => $partida['jugador2'],
            'jugadorActivo'         => $partida['jugador_activo'],
            'ronda'                 => $partida['ronda_actual'],
            'turno'                 => $partida['turno_actual'],
            'jugadorQueTiroDado'    => $partida['jugador_que_tiro_dado'],
            'restriccion'           => $partida['restriccion_actual'],
            'colocaciones'          => $partida['recintos'],   // nombre frontend
            'mano1'                 => $mano1,
            'mano2'                 => $mano2,
            'ultimo_jugador'        => $partida['ultimo_jugador'],
            'created_at'            => $partida['created_at'],
            'updated_at'            => $partida['updated_at']
        ];
    }

    /* ----------  OBTENER PARTIDAS EN PROGRESO  ---------- */
    public function obtenerPartidasEnProgreso(string $jugador): array
    {
        return $this->repository->obtenerPartidasEnProgreso($jugador);
    }

    /* ----------  VALIDAR ACCESO  ---------- */
    public function validarAccesoPartida(int $idPartida, string $jugador): bool
    {
        return $this->repository->validarAccesoPartida($idPartida, $jugador);
    }

    /* ----------  VALIDAR JUGADA  ---------- */
    public function validarJugada(array $datos): array
    {
        $errors = [];

        if (empty($datos['recinto']))   $errors[] = "Debe seleccionar un recinto";
        if (empty($datos['dinosaurio']))$errors[] = "Debe seleccionar un dinosaurio";

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'data'   => $datos
        ];
    }

    /* ----------  FINALIZAR PARTIDA  ---------- */
    public function finalizarPartida(int $idPartida, array $resultadoFinal): bool
    {
        return $this->repository->finalizarPartida($idPartida, $resultadoFinal);
    }

    /* -------------------------------------------------------------- */
    /*  MÉTODOS AUXILIARES PRIVADOS                                   */
    /* -------------------------------------------------------------- */
    private function validarDatosPartida(array $datos): void
    {
        $required = ['jugador1', 'jugador2', 'jugadorActivo', 'ronda', 'turno'];
        foreach ($required as $f) {
            if (!isset($datos[$f])) {
                throw new InvalidArgumentException("Campo requerido faltante: $f");
            }
        }

        if (!in_array($datos['jugadorActivo'], [1, 2], true)) {
            throw new InvalidArgumentException("jugadorActivo debe ser 1 o 2");
        }
        $dado = $datos['jugadorQueTiroDado'] ?? 0;
        if (!in_array($dado, [0, 1, 2], true)) {
            throw new InvalidArgumentException("jugadorQueTiroDado debe ser 0, 1 o 2");
        }
    }

    private function generarManoInicial(): array
    {
        return [
            'dinosaurios'      => [],
            'cartas_especiales'=> []
        ];
    }

    private function inicializarRecintos(): array
    {
        return [
            'recinto1' => [],
            'recinto2' => [],
            'recinto3' => [],
            'recinto4' => []
        ];
    }

    /**
     * Normaliza el formato de una mano para que sea compatible con el frontend
     * Si recibe un array simple, lo convierte al formato esperado
     * Si ya tiene el formato correcto, lo devuelve tal como está
     */
    private function normalizarMano(array $mano): array
    {
        // Si la mano ya tiene el formato esperado (con claves 'dinosaurios' y 'cartas_especiales')
        if (isset($mano['dinosaurios']) || isset($mano['cartas_especiales'])) {
            return [
                'dinosaurios'       => $mano['dinosaurios'] ?? [],
                'cartas_especiales' => $mano['cartas_especiales'] ?? []
            ];
        }

        // Si es un array simple (formato antiguo), lo convertimos
        if (is_array($mano) && !empty($mano) && !isset($mano['dinosaurios'])) {
            return [
                'dinosaurios'       => $mano,
                'cartas_especiales' => []
            ];
        }

        // Si está vacío o es null, devolvemos estructura vacía
        return [
            'dinosaurios'       => [],
            'cartas_especiales' => []
        ];
    }
}