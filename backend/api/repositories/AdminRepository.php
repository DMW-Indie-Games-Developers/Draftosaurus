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
        $stmt = $this->conn->prepare("
            SELECT id,
                   username AS name,
                   email,
                   estado AS status,
                   rol
            FROM   users
            ORDER BY id DESC
        ");

        if (!$stmt) {
            error_log("Error en findAllUsers: " . $this->conn->error);
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $users;
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

    // NUEVO: Método para buscar por username
    public function findUserByUsername(string $username): ?array
    {
        error_log("=== findUserByUsername: '$username' ===");
        
        $stmt = $this->conn->prepare("
            SELECT id, username, email, estado AS status, rol
            FROM users 
            WHERE username = ?
        ");
        
        if (!$stmt) {
            error_log("Error preparando findUserByUsername: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            error_log("Usuario encontrado: ID " . $user['id'] . ", Username: " . $user['username']);
        } else {
            error_log("Usuario NO encontrado");
        }
        
        return $user ?: null;
    }

    // NUEVO: Método para buscar por email
    public function findUserByEmail(string $email): ?array
    {
        error_log("=== findUserByEmail: '$email' ===");
        
        $stmt = $this->conn->prepare("
            SELECT id, username, email, estado AS status, rol
            FROM users 
            WHERE email = ?
        ");
        
        if (!$stmt) {
            error_log("Error preparando findUserByEmail: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            error_log("Email encontrado: ID " . $user['id'] . ", Email: " . $user['email']);
        } else {
            error_log("Email NO encontrado");
        }
        
        return $user ?: null;
    }

    public function insertUser(array $data): int
    {
        error_log("=== AdminRepository::insertUser - INICIO ===");
        error_log("Datos recibidos: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        error_log("Username a insertar: '" . $data['name'] . "'");
        error_log("Email a insertar: '" . $data['email'] . "'");
        
        // Verificación doble antes de insertar
        $existingUser = $this->findUserByUsername($data['name']);
        if ($existingUser) {
            error_log("STOP: Usuario ya existe antes de INSERT");
            throw new Exception('Nombre de usuario ya existe (ID: ' . $existingUser['id'] . ')');
        }
        
        $existingEmail = $this->findUserByEmail($data['email']);
        if ($existingEmail) {
            error_log("STOP: Email ya existe antes de INSERT");
            throw new Exception('El email ya está registrado (ID: ' . $existingEmail['id'] . ')');
        }
        
        error_log("Verificaciones completadas - Usuario y email disponibles");
        
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        error_log("Password hasheado correctamente");
        
        $stmt = $this->conn->prepare("
            INSERT INTO users (username, email, password, rol, estado, created_at)
            VALUES (?, ?, ?, 'usuario', 'activo', NOW())
        ");
        
        if (!$stmt) {
            error_log("Error preparando INSERT statement: " . $this->conn->error);
            throw new Exception("Error al preparar consulta de inserción");
        }
        
        error_log("Statement preparado correctamente");
        error_log("Ejecutando INSERT...");
        
        $stmt->bind_param('sss', $data['name'], $data['email'], $hash);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $errno = $stmt->errno;
            error_log("ERROR ejecutando INSERT: $error (errno: $errno)");
            
            $stmt->close();
            
            // 1062 = clave única duplicada
            if ($errno === 1062) {
                if (strpos($error, 'username') !== false) {
                    throw new Exception('El nombre de usuario ya está en uso');
                } elseif (strpos($error, 'email') !== false) {
                    throw new Exception('El email ya está registrado');
                } else {
                    throw new Exception('Ya existe un registro con estos datos');
                }
            }
            
            throw new Exception("Error al insertar usuario: $error");
        }
        
        $id = $this->conn->insert_id;
        $stmt->close();
        
        error_log("INSERT exitoso - Nuevo ID: $id");
        error_log("=== AdminRepository::insertUser - SUCCESS ===");
        
        return $id;
    }

    public function updateUser(int $id, array $data): int
    {
        error_log("=== AdminRepository::updateUser - INICIO ===");
        error_log("ID: $id, Datos: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        $sets = [];
        $types = '';
        $values = [];

        // Mapeo correcto de campos
        $fieldMap = [
            'name' => 'username', 
            'email' => 'email', 
            'password' => 'password'
        ];

        foreach ($fieldMap as $inputKey => $dbField) {
            if (isset($data[$inputKey]) && !empty($data[$inputKey])) {
                $sets[] = "$dbField = ?";
                $types .= 's';
                
                if ($inputKey === 'password') {
                    $values[] = password_hash($data[$inputKey], PASSWORD_BCRYPT);
                    error_log("Password será actualizado (hasheado)");
                } else {
                    $values[] = $data[$inputKey];
                    error_log("Campo $inputKey -> $dbField = '" . $data[$inputKey] . "'");
                }
            }
        }

        if (!$sets) {
            error_log("No hay campos para actualizar");
            return 0;
        }

        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE users SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        error_log("SQL a ejecutar: $sql");
        error_log("Valores: " . json_encode(array_slice($values, 0, -1), JSON_UNESCAPED_UNICODE) . " [ID: $id]");
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Error preparando updateUser: " . $this->conn->error);
            throw new Exception("Error al preparar consulta de actualización");
        }
        
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            error_log("Error ejecutando updateUser: $error");
            $stmt->close();
            throw new Exception("Error al actualizar usuario: $error");
        }
        
        $rows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("UPDATE completado - Filas afectadas: $rows");
        error_log("=== AdminRepository::updateUser - SUCCESS ===");
        
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
        $stmt = $this->conn->prepare("
            SELECT id,
                   nombre,
                   email,
                   asunto,
                   mensaje,
                   fecha_envio
            FROM   contacto
            ORDER  BY fecha_envio DESC
        ");

        if (!$stmt) {
            error_log("Error en findAllMessages: " . $this->conn->error);
            return [];
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $messages;
    }
}