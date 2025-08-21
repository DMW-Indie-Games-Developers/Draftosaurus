<?php
/**
 * Responsabilidad:
 *  - Recibir las solicitudes HTTP relacionadas con autenticación (registro y login).
 *  - Validar el input básico del cliente.
 *  - Delegar la lógica de negocio al AuthService.
 *  - Devolver respuestas HTTP con códigos de estado coherentes.
 *  - Un Controller nunca debe contener lógica de negocio ni de acceso a datos.
 */

class AuthController
{
    /** Servicio de autenticación que encapsula la lógica de negocio. */
    private $authService;

    public function __construct()
    {
        // Obtenemos la instancia única del servicio (patrón Singleton en AuthService)
        $this->authService = AuthService::getInstance();
    }

    /**
     * Endpoint: POST /register
     * Tarea: Valida el cuerpo JSON y delega el registro de un nuevo usuario.
     * Respuestas esperadas:
     *  - 201 Created si se crea correctamente.
     *  - 400 Bad Request si faltan campos o hay datos inválidos.
     *  - 409 Conflict si ya existe el username o email.
     *  - 500 Internal Server Error para errores inesperados.
     */
    public function register()
    {
        // Lee el cuerpo crudo de la petición y lo intenta parsear como JSON
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        // Si no es un objeto/array JSON válido, respondemos 400
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
            return;
        }

        // Sanitiza/normaliza datos (trim para quitar espacios a los extremos)
        $username = isset($data['username']) ? trim((string)$data['username']) : '';
        $email = isset($data['email']) ? trim((string)$data['email']) : '';
        $password = isset($data['password']) ? (string)$data['password'] : '';

        // Validación mínima de presencia de campos
        if ($username === '' || $email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username, email y contraseña son requeridos.']);
            return;
        }

        // Delegamos la creación al servicio
        $result = $this->authService->register($username, $email, $password);

        // Si el servicio no retornó un array válido, lo consideramos error interno
        if (!is_array($result) || !array_key_exists('success', $result)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
            return;
        }

        // Mapeo de códigos de negocio a códigos HTTP
        if ($result['success'] === true) {
            http_response_code(201); // Creado
        } else {
            $code = isset($result['code']) ? $result['code'] : 'error';
            if ($code === 'invalid') {
                http_response_code(400); // Datos inválidos
            } elseif ($code === 'duplicate') {
                http_response_code(409); // Conflicto: duplicado
            } else {
                http_response_code(500); // Error genérico del servidor
            }
        }

        echo json_encode($result);
    }

    /**
     * Endpoint: POST /login
     * Tarea: Permite autenticación usando un identificador (email o username) más contraseña.
     * Respuestas esperadas:
     *  - 200 OK si las credenciales son correctas.
     *  - 400 Bad Request si faltan campos o están vacíos.
     *  - 401 Unauthorized si las credenciales no son válidas.
     *  - 500 Internal Server Error para errores inesperados.
     */
    public function login()
    {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
            return;
        }

        // Puede venir como "identifier" o por separado "email" / "username"
        $identifier = $data['identifier'] ?? ($data['email'] ?? ($data['username'] ?? null));
        $password = $data['password'] ?? null;

        if (!$identifier || !$password) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Identificador (email o username) y contraseña son requeridos.'
            ]);
            return;
        }

        // Normalizamos tipos y removemos espacios laterales
        $identifier = trim((string)$identifier);
        $password = (string)$password;

        if ($identifier === '' || $password === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Identificador y contraseña no pueden estar vacíos.'
            ]);
            return;
        }

        // Delegamos autenticación al servicio
        $result = $this->authService->login($identifier, $password);

        if (!is_array($result) || !array_key_exists('success', $result)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
            return;
        }

        // 200 si ok; 401 si credenciales incorrectas
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(401);
        }

        echo json_encode($result);
    }
}
