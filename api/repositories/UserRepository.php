<?php
/**
 * Responsabilidad:
 * - Encapsular el acceso a la base de datos para la entidad "usuarios".
 * - Proveer métodos de consulta e inserción usando consultas preparadas (prepared statements) para evitar SQL Injection.
 *
 * Diseño:
 * - Singleton: comparte la misma conexión (mysqli) provista por Database.
 */

class UserRepository
{
    /** Instancia única del repositorio. */
    private static ?UserRepository $instance = null;

    /** Conexión activa a MySQL (mysqli). */
    private mysqli $conn;

    /**
     * Constructor privado: obtiene la conexión desde Database (Singleton) para reutilizarla.
     */
    private function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Acceso global a la instancia única del repositorio.
     */
    public static function getInstance(): ?UserRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Busca un usuario por email.
     * Retorna un array asociativo con los datos del usuario o null si no existe.
     */
    public function findByEmail(string $email): ?array
    {
        // Se usa * para traer todos los campos de la tabla usuarios
        $query = "SELECT * FROM usuarios WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        $stmt->close();
        return $user;
    }

    /**
     * Busca un usuario por su username o email.
     * Retorna un array asociativo con todos los datos del usuario o null si no se encuentra.
     */
    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $query = "SELECT * FROM usuarios WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return $user;
    }

    /**
     * Crea un nuevo usuario y devuelve datos básicos del registro insertado.
     * La contraseña debe llegar ya hasheada.
     */
    public function createUser(string $username, string $email, string $hashedPassword): array|false
    {
        $query = "INSERT INTO usuarios (username, email, password) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sss", $username, $email, $hashedPassword);
        $ok = $stmt->execute();
        if (!$ok) {
            $stmt->close();
            // Podríamos verificar el código de error para duplicados (1062)
            return false;
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        return [
            'id' => (int)$insertId,
            'username' => $username,
            'email' => $email
        ];
    }
}
