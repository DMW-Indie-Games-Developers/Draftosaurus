<?php
/**
 * Responsabilidad:
 *  - Encapsular el acceso a la base de datos para la entidad "users".
 *  - Proveer métodos de consulta e inserción usando consultas preparadas (prepared statements) para evitar SQL Injection.
 *
 * Diseño:
 *  - Singleton: comparte la misma conexión (mysqli) provista por Database.
 */

class UserRepository
{
    /** Instancia única del repositorio. */
    private static ?UserRepository $instance = null; // instancia única

    /** Conexión activa a MySQL (mysqli). */
    private mysqli $conn;

    /**
     * Constructor privado: obtiene la conexión desde Database (Singleton) para reutilizarla.
     */
    private function __construct()
    {
        // Obtenemos la conexión de la capa Database
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
     * Retorna un array asociativo con las columnas solicitadas o null si no existe.
     */
    public function findByEmail(string $email)
    {
        $query = "SELECT id, username, email, password FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null; // si falla la preparación, devolvemos null (no revelamos detalles de DB)
        }

        $stmt->bind_param("s", $email); // "s" indica string
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null; // fetch_assoc devuelve array asociativo

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
        $query = "SELECT id, username, email, password FROM users WHERE username = ?";
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
     * Busca un usuario por username o email (cualquiera que coincida).
     */
    public function findByUsernameOrEmail(string $identifier)
    {
        $query = "SELECT id, username, email, password FROM users WHERE username = ? OR email = ?";
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
        $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false; // si no se pudo preparar, devolvemos false (fallo genérico)
        }

        $stmt->bind_param("sss", $username, $email, $hashedPassword);
        $ok = $stmt->execute();
        if (!$ok) {
            $stmt->close();
            return false;
        }

        $insertId = $stmt->insert_id; // id autoincrement generado
        $stmt->close();

        return [
            'id' => (int)$insertId,
            'username' => $username,
            'email' => $email,
        ];
    }
}
