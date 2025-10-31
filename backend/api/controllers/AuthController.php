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
        $username = isset($data['username']) ? trim((string) $data['username']) : '';
        $email = isset($data['email']) ? trim((string) $data['email']) : '';
        $password = isset($data['password']) ? (string) $data['password'] : '';

        // Validación mínima de presencia de campos
        if ($username === '' || $email === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username, email y contraseña son requeridos.']);
            return;
        }

        // SEGURIDAD: Validación de formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El email no tiene un formato válido.']);
            return;
        }

        // SEGURIDAD: Validación de username
        // - Entre 3 y 30 caracteres
        // - Solo alfanuméricos, guiones y guiones bajos
        // - No permite XSS
        if (strlen($username) < 3 || strlen($username) > 30) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El username debe tener entre 3 y 30 caracteres.']);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El username solo puede contener letras, números, guiones y guiones bajos.']);
            return;
        }

        // SEGURIDAD: Validación de contraseña
        // - Mínimo 6 caracteres (se puede ajustar según necesidad)
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
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
            // AUDIT LOG: Usuario registrado exitosamente
            AuditLogger::log(
                AuditLogger::ACTION_REGISTER,
                $result['user']['id'],
                "Nuevo usuario registrado: $username",
                ['email' => $email]
            );
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
     *  - 429 Too Many Requests si está bloqueado por intentos fallidos.
     *  - 500 Internal Server Error para errores inesperados.
     */
    public function login()
    {
        // Asegurar que la sesión esté iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // SEGURIDAD: Verificar bloqueo progresivo ANTES de procesar el login
        ProgressiveRateLimiter::requireNotBlocked();

        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
            return;
        }

        $identifier = $data['identifier'] ?? ($data['email'] ?? ($data['username'] ?? null));
        $password = $data['password'] ?? null;

        if (!$identifier || !$password) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Identificador y contraseña son requeridos.']);
            return;
        }

        $identifier = trim((string) $identifier);
        $password = (string) $password;

        // SEGURIDAD: Solo loguear el identifier, NUNCA la contraseña
        error_log("Login intentado para: $identifier");

        if ($identifier === '' || $password === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Identificador y contraseña no pueden estar vacíos.']);
            return;
        }

        $result = $this->authService->login($identifier, $password);

        if (!is_array($result) || !array_key_exists('success', $result)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
            return;
        }

        // ✅ Guardar en sesión PHP (sin session_start() duplicado)
        if ($result['success']) {
            $_SESSION['userId'] = $result['user']['id'];
            $_SESSION['rol'] = $result['user']['rol'];

            // SEGURIDAD: Resetear bloqueo progresivo después de login exitoso
            ProgressiveRateLimiter::reset();

            // AUDIT LOG: Login exitoso
            AuditLogger::log(
                AuditLogger::ACTION_LOGIN_SUCCESS,
                $result['user']['id'],
                "Login exitoso",
                ['username' => $result['user']['username']]
            );

            // SESSION TRACKING: Registrar inicio de sesión
            SessionTracker::registerLogin($result['user']['id']);

            http_response_code(200);
        } else {
            // SEGURIDAD: Registrar intento fallido para bloqueo progresivo
            ProgressiveRateLimiter::recordFailedAttempt($identifier);

            // AUDIT LOG: Login fallido
            AuditLogger::log(
                AuditLogger::ACTION_LOGIN_FAILED,
                null,
                "Intento de login fallido para: $identifier",
                ['identifier' => $identifier]
            );

            http_response_code(401);
        }

        echo json_encode($result);
    }

    /**
     * Verificar credenciales sin cambiar sesión (para jugador 2)
     */
    public function verifyCredentials($identifier, $password)
    {
        return $this->authService->checkUserCredentials($identifier, $password);
    }
}