<?php
/**
 * Responsabilidad:
 * - Implementar la lógica de negocio para autenticación: registro y login.
 * - Coordinar validaciones de negocio y llamar al repositorio de usuarios.
 * - NUEVA: Validar estado del usuario (activo/suspendido)
 *
 * Diseño:
 * - Singleton: una sola instancia del servicio durante la ejecución.
 * - El servicio NO conoce detalles de HTTP, retorna arrays que luego el controlador traduce.
 */

class AuthService
{
    private static ?AuthService $instance = null;
    private ?UserRepository $userRepository;

    private function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
    }

    public static function getInstance(): ?AuthService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un nuevo usuario.
     */
    public function register(string $username, string $email, string $password): array
    {
        // 1. Validaciones básicas
        if (empty(trim($username)) || empty(trim($email)) || empty($password)) {
            return ['success' => false, 'message' => 'Todos los campos son requeridos.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'El formato del email no es válido.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        // 2. Verificar duplicados
        if ($this->userRepository->findByUsernameOrEmail($username)) {
            return ['success' => false, 'message' => 'El nombre de usuario ya está en uso.'];
        }
        if ($this->userRepository->findByUsernameOrEmail($email)) {
            return ['success' => false, 'message' => 'El email ya está registrado.'];
        }

        // 3. Hashear contraseña
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 4. Crear usuario
        $newUser = $this->userRepository->createUser($username, $email, $hashedPassword);
        if (!$newUser) {
            return ['success' => false, 'message' => 'No se pudo completar el registro. Inténtalo de nuevo.'];
        }

        return [
            'success' => true,
            'message' => 'Registro exitoso.',
            'user' => $newUser
        ];
    }

    /**
     * Verifica las credenciales del usuario.
     * Si son correctas, devuelve todos los datos del usuario (excepto la contraseña).
     * Si no, devuelve false.
     * NUEVA: También valida el estado del usuario.
     */
    private function verifyCredentials(string $identifier, string $plainPassword): array|false
    {
        error_log("=== INICIO verifyCredentials ===");
        error_log("Identifier: $identifier");
        
        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$user) {
            error_log("Usuario NO encontrado");
            error_log("=== FIN verifyCredentials (usuario no existe) ===");
            return false;
        }

        error_log("Usuario encontrado: " . ($user['username'] ?? 'sin username'));
        error_log("Estado del usuario: " . ($user['estado'] ?? 'sin estado'));

        // NUEVA VALIDACIÓN: Verificar si el usuario está suspendido
        if (isset($user['estado']) && $user['estado'] === 'suspendido') {
            error_log("ACCESO DENEGADO: Usuario suspendido");
            error_log("=== FIN verifyCredentials (usuario suspendido) ===");
            return false;
        }

        error_log("Usuario activo, verificando contraseña...");
        error_log("Hash en BD: " . ($user['password'] ?? 'sin password'));

        if (!password_verify($plainPassword, $user['password'])) {
            error_log("Password verify: FAIL");
            error_log("=== FIN verifyCredentials (password incorrecta) ===");
            return false;
        }

        // Importante: removemos el hash de la contraseña antes de devolver los datos.
        unset($user['password']);

        // Convertimos los tipos de datos numéricos
        $user['id'] = (int) $user['id'];
        $user['partidas_jugadas'] = (int) ($user['partidas_jugadas'] ?? 0);
        $user['partidas_ganadas'] = (int) ($user['partidas_ganadas'] ?? 0);
        $user['puntuacion_total'] = (int) ($user['puntuacion_total'] ?? 0);

        error_log("Password verify: OK");
        error_log("Usuario estado final: " . $user['estado']);
        error_log("=== FIN verifyCredentials (success) ===");

        return $user;
    }

    /**
     * Autenticación (login) usando email o username.
     * ACTUALIZADO: Incluye validación de estado del usuario.
     */
    public function login(string $identifier, string $password): array
    {
        error_log("=== INICIO AuthService::login ===");
        error_log("Intentando login para: $identifier");
        
        $user = $this->verifyCredentials($identifier, $password);
        
        if ($user === false) {
            // Necesitamos ser más específicos sobre por qué falló
            $userExists = $this->userRepository->findByUsernameOrEmail($identifier);
            
            if (!$userExists) {
                error_log("Login fallido: Usuario no existe");
                return ['success' => false, 'message' => 'Credenciales incorrectas.'];
            }
            
            if (isset($userExists['estado']) && $userExists['estado'] === 'suspendido') {
                error_log("Login fallido: Usuario suspendido");
                return [
                    'success' => false, 
                    'message' => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
                    'code' => 'ACCOUNT_SUSPENDED'
                ];
            }
            
            error_log("Login fallido: Password incorrecta");
            return ['success' => false, 'message' => 'Credenciales incorrectas.'];
        }

        error_log("Login exitoso para usuario ID: " . $user['id']);
        error_log("=== FIN AuthService::login (success) ===");

        return [
            'success' => true,
            'message' => 'Login exitoso.',
            'user' => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
                'estado'   => $user['estado'], // Incluir estado en respuesta
            ]
        ];
    }

    /**
     * NUEVO: Método para verificar si un usuario activo sigue estando activo
     * Útil para validar sesiones existentes
     */
    public function validateUserStatus(int $userId): array
    {
        error_log("=== Validando estado de usuario ID: $userId ===");
        
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            error_log("Usuario $userId no encontrado");
            return ['valid' => false, 'reason' => 'USER_NOT_FOUND'];
        }
        
        if ($user['estado'] === 'suspendido') {
            error_log("Usuario $userId está suspendido");
            return ['valid' => false, 'reason' => 'ACCOUNT_SUSPENDED'];
        }
        
        error_log("Usuario $userId está activo");
        return ['valid' => true, 'user' => $user];
    }
}