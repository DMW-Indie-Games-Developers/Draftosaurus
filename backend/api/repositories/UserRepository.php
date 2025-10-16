<?php
/**
 * Responsabilidad:
 * - Encapsular el acceso a la base de datos para la entidad "usuarios".
 * - Proveer métodos de consulta e inserción usando consultas preparadas (prepared statements) para evitar SQL Injection.
 *
 * Diseño:
 * - Singleton: comparte la misma conexión (mysqli) provista por Database.
 */

require_once __DIR__ . '/../../usermodel/User.php';

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
     * Retorna un objeto User o null si no existe.
     */
    public function findByEmail(string $email): ?User
    {
        // Se usa * para traer todos los campos de la tabla usuarios
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        if ($data) {
            return $this->createUserFromArray($data);
        }
        return null;
    }

    /**
     * NUEVO: Busca un usuario por ID.
     * Retorna un objeto User o null si no se encuentra.
     */
    public function findById(int $id): ?User
    {
        error_log("=== UserRepository::findById($id) ===");

        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparando findById: " . $this->conn->error);
            return null;
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        if ($data) {
            error_log("Usuario ID $id encontrado: " . $data['username'] . " (estado: " . $data['estado'] . ")");
            return $this->createUserFromArray($data);
        } else {
            error_log("Usuario ID $id NO encontrado");
        }

        return null;
    }

    /**
     * Busca un usuario por su username o email.
     * Retorna un objeto User o null si no se encuentra.
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $query = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        if ($data) {
            return $this->createUserFromArray($data);
        }
        return null;
    }

    /**
     * Crea un nuevo usuario y devuelve el objeto User creado.
     */
    public function createUser(User $user): User|false
    {
        $query = "INSERT INTO users (username, email, password, nickname) VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return false;
        }

        // Asignar a variables para evitar el error "Only variables should be passed by reference"
        $username = $user->getUsername();
        $email = $user->getEmail();
        $password = $user->getPassword();
        $nickname = $user->getNickname();

        $stmt->bind_param("ssss", $username, $email, $password, $nickname);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error al crear usuario: $error");
        }

        $insertId = $stmt->insert_id;
        $stmt->close();

        $user->setId($insertId);
        return $user;
    }

    /**
     * Método auxiliar para crear objeto User desde array de BD
     */
    private function createUserFromArray(array $data): User
    {
        $user = new User();
        $user->setId($data['id']);
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setNickname($data['nickname'] ?? null);
        $user->setAvatar($data['avatar']);
        $user->setPuntuacionTotal($data['puntuacion_total']);
        $user->setPartidasJugadas($data['partidas_jugadas']);
        $user->setPartidasGanadas($data['partidas_ganadas']);
        $user->setRol($data['rol']);
        $user->setEstado($data['estado']);
        $user->setCreatedAt($data['created_at']);
        $user->setUpdatedAt($data['updated_at']);
        return $user;
    }

    /**
     * Actualiza el nickname de un usuario
     */
    public function updateNickname(int $userId, ?string $nickname): bool
    {
        $query = "UPDATE users SET nickname = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("si", $nickname, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}