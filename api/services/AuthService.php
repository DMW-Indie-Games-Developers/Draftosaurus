<?php
/**
 * Responsabilidad:
 * - Implementar la lógica de negocio para autenticación: registro y login.
 * - Coordinar validaciones de negocio y llamar al repositorio de usuarios.
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
     */
    private function verifyCredentials(string $identifier, string $plainPassword): array|false
    {
        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        if (!$user) {
            return false;
        }

        if (!password_verify($plainPassword, $user['password'])) {
            return false;
        }

        // Importante: removemos el hash de la contraseña antes de devolver los datos.
        unset($user['password']);
        
        // Convertimos los tipos de datos numéricos
        $user['id'] = (int)$user['id'];
        $user['partidas_jugadas'] = (int)$user['partidas_jugadas'];
        $user['partidas_ganadas'] = (int)$user['partidas_ganadas'];
        $user['puntuacion_total'] = (int)$user['puntuacion_total'];

        return $user;
    }

    /**
     * Autenticación (login) usando email o username.
     */
    public function login(string $identifier, string $password): array
    {
        $user = $this->verifyCredentials($identifier, $password);
        if ($user === false) {
            return ['success' => false, 'message' => 'Credenciales incorrectas.'];
        }

        return [
            'success' => true,
            'message' => 'Login exitoso.',
            'user' => $user, // Devolvemos todos los datos del usuario
        ];
    }
}
