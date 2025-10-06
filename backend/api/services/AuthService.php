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

        // 3. Crear usuario y hashear contraseña
        $user = new User($username, $email, $password);
        $user->hashPassword();

        $newUser = $this->userRepository->createUser($user);
        if (!$newUser) {
            return ['success' => false, 'message' => 'No se pudo completar el registro. Inténtalo de nuevo.'];
        }

        return [
            'success' => true,
            'message' => 'Registro exitoso.',
            'user' => $newUser->toArray()
        ];
    }

    /**
     * Verifica las credenciales del usuario.
     * Si son correctas, devuelve todos los datos del usuario (excepto la contraseña).
     * Si no, devuelve false.
     * NUEVA: También valida el estado del usuario.
     */
    private function verifyCredentials(string $identifier, string $plainPassword): User|false
    {
        error_log("=== INICIO verifyCredentials ===");
        error_log("Identifier: $identifier");

        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$user) {
            error_log("Usuario NO encontrado");
            error_log("=== FIN verifyCredentials (usuario no existe) ===");
            return false;
        }

        error_log("Usuario encontrado: " . $user->getUsername());
        error_log("Estado del usuario: " . $user->getEstado());

        // NUEVA VALIDACIÓN: Verificar si el usuario está suspendido
        if (!$user->isActive()) {
            error_log("ACCESO DENEGADO: Usuario suspendido");
            error_log("=== FIN verifyCredentials (usuario suspendido) ===");
            return false;
        }

        error_log("Usuario activo, verificando contraseña...");

        if (!$user->verifyPassword($plainPassword)) {
            error_log("Password verify: FAIL");
            error_log("=== FIN verifyCredentials (password incorrecta) ===");
            return false;
        }

        error_log("Password verify: OK");
        error_log("Usuario estado final: " . $user->getEstado());
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

            if (!$userExists->isActive()) {
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

        error_log("Login exitoso para usuario ID: " . $user->getId());
        error_log("=== FIN AuthService::login (success) ===");

        return [
            'success' => true,
            'message' => 'Login exitoso.',
            'user' => [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email'    => $user->getEmail(),
                'rol'      => $user->getRol(),
                'estado'   => $user->getEstado(),
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

        if (!$user->isActive()) {
            error_log("Usuario $userId está suspendido");
            return ['valid' => false, 'reason' => 'ACCOUNT_SUSPENDED'];
        }

        error_log("Usuario $userId está activo");
        return ['valid' => true, 'user' => $user->toArray()];
    }

    /**
     * Verificar credenciales para jugador 2 sin cambiar sesión
     */
    public function checkUserCredentials($identifier, $password)
    {
        error_log("=== Verificando credenciales para jugador 2: $identifier ===");

        // Usar el método privado existente
        $user = $this->verifyCredentials($identifier, $password);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ];
        }

        error_log("Credenciales verificadas correctamente para jugador 2: $identifier");
        return [
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'nickname' => $user->getNickname(),
                'display_name' => $user->getDisplayName()
            ]
        ];
    }
}