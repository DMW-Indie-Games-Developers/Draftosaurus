<?php
require_once __DIR__ . '/../config/Database.php';

class AdminRepository
{
    private static ?AdminRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ---------- USUARIOS ---------- */
    public function findAllUsers(): array
    {
        $res = $this->conn->query("
            SELECT id, 
                   username AS name, 
                   email, 
                   estado AS status,
                   rol
            FROM   users
            ORDER BY id DESC
        ");
        
        if (!$res) {
            error_log("Error en findAllUsers: " . $this->conn->error);
            return [];
        }
        
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    public function findUser(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT id, 
                   username AS name, 
                   email, 
                   rol, 
                   estado AS status
            FROM   users
            WHERE  id = ?
        ");
        
        if (!$stmt) {
            error_log("Error preparando findUser: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    public function insertUser(array $data): int
    {
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, email, password, rol, estado, created_at)
            VALUES (?, ?, ?, 'usuario', 'activo', NOW())
        ");
        
        if (!$stmt) {
            error_log("Error preparando insertUser: " . $this->conn->error);
            throw new Exception("Error al preparar consulta");
        }
        
        $stmt->bind_param('sss', $data['name'], $data['email'], $hash);
        
        if (!$stmt->execute()) {
            // 1062 = clave única duplicada
            if ($stmt->errno === 1062) {
                $stmt->close();
                throw new Exception('Nombre de usuario o email ya existe');
            }
            error_log("Error ejecutando insertUser: " . $stmt->error);
            throw new Exception("Error al insertar usuario");
        }
        
        $id = $this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateUser(int $id, array $data): int
    {
        $sets = [];
        $types = '';
        $values = [];

        foreach (['name' => 'username', 'email' => 'email', 'password' => 'password'] as $k => $db) {
            if (isset($data[$k]) && !empty($data[$k])) {
                $sets[] = "$db = ?";
                $types .= 's';
                $values[] = $k === 'password' ? password_hash($data[$k], PASSWORD_BCRYPT) : $data[$k];
            }
        }

        if (!$sets) return 0;

        $values[] = $id;
        $types .= 'i';

        $stmt = $this->conn->prepare("UPDATE users SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?");
        
        if (!$stmt) {
            error_log("Error preparando updateUser: " . $this->conn->error);
            throw new Exception("Error al preparar consulta de actualización");
        }
        
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            error_log("Error ejecutando updateUser: " . $stmt->error);
            throw new Exception("Error al actualizar usuario");
        }
        
        $rows = $stmt->affected_rows;
        $stmt->close();
        return $rows;
    }

    public function updateUserStatus(int $id, string $status): int
    {
        $stmt = $this->conn->prepare("UPDATE users SET estado = ? WHERE id = ?");
        if (!$stmt) {
            error_log("Error preparando updateUserStatus: " . $this->conn->error);
            throw new Exception("Error al preparar consulta");
        }
        
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $rows = $stmt->affected_rows;
        $stmt->close();
        return $rows;
    }

    /* ---------- MENSAJES ---------- */
    public function findAllMessages(): array
    {
        $res = $this->conn->query("
            SELECT id, 
                   nombre, 
                   email, 
                   asunto, 
                   mensaje, 
                   fecha_envio
            FROM   contacto
            ORDER  BY fecha_envio DESC
        ");
        
        if (!$res) {
            error_log("Error en findAllMessages: " . $this->conn->error);
            return [];
        }
        
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}