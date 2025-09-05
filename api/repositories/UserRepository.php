<?php
/**
 * Responsabilidad:
 * - Encapsular el acceso a la base de datos para la entidad "usuarios".
 * - Proveer métodos de consulta e inserción usando consultas preparadas (prepared statements) para evitar SQL Injection.
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
        // Usamos la clase de conexión que definimos en las respuestas anteriores
        $this->conn = Config::getConnection();
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
     * Busca un usuario por ID.
     * Retorna un array asociativo con las columnas solicitadas o null si no existe.
     */
    public function findById(int $id)
    {
        $query = "SELECT id, username, email, avatar FROM usuarios WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $id); // "i" indica integer
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Busca un usuario por email.
     */
    public function findByEmail(string $email)
    {
        $query = "SELECT id, username, email, password, avatar FROM usuarios WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Busca un usuario por username.
     */
    public function findByUsername(string $username)
    {
        $query = "SELECT id, username, email, password, avatar FROM usuarios WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Busca un usuario por username o email.
     */
    public function findByUsernameOrEmail(string $identifier)
    {
        $query = "SELECT id, username, email, password, avatar FROM usuarios WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) {
            $result->free();
        }
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Crea un nuevo usuario y devuelve datos básicos del registro insertado.
     * Importante: la contraseña debe llegar ya hasheada a este método.
     */
    public function createUser(string $username, string $email, string $hashedPassword)
    {
        // Consulta corregida para la tabla 'usuarios'
        $query = "INSERT INTO usuarios (username, email, password, avatar) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $defaultAvatar = 'img/isotipoOficial.png';
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $defaultAvatar);
        $ok = $stmt->execute();
        
        if (!$ok) {
            $stmt->close();
            return false;
        }
        $insertId = $stmt->insert_id;
        $stmt->close();

        return [
            'id' => (int)$insertId,
            'username' => $username,
            'email' => $email,
            'avatar' => $defaultAvatar,
        ];
    }

    /**
     * Actualiza la URL del avatar de un usuario.
     */
    public function updateAvatar(int $userId, string $avatarUrl)
    {
        $query = "UPDATE usuarios SET avatar = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("si", $avatarUrl, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
