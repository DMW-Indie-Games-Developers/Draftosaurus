<?php
require_once __DIR__ . '/../repositories/AdminRepository.php';

class AdminService
{
    private AdminRepository $repo;

    public function __construct()
    {
        $this->repo = AdminRepository::getInstance();
    }

    // Métodos para usuarios
    public function getAllUsers(): array
    {
        return $this->repo->findAllUsers();
    }

    public function getUserById(int $id): ?array
    {
        return $this->repo->findUser($id);
    }

    public function createUser(array $data): array
    {
        error_log("=== AdminService::createUser - INICIO ===");
        error_log("Datos recibidos: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            error_log("ERROR: Faltan campos requeridos");
            error_log("name: '" . ($data['name'] ?? 'NULL') . "'");
            error_log("email: '" . ($data['email'] ?? 'NULL') . "'");
            error_log("password: " . (isset($data['password']) ? '[SET]' : 'NULL'));
            return ['success' => false, 'message' => 'Faltan campos requeridos'];
        }
        
        // Verificar si el usuario ya existe ANTES de intentar crearlo
        // $existingUser = $this->repo->findUserByUsername($data['name']); //
        if ($existingUser) {
            error_log("ERROR: Usuario ya existe - ID: " . $existingUser['id']);
            return ['success' => false, 'message' => 'El nombre de usuario ya está en uso'];
        }
        
        $existingEmail = $this->repo->findUserByEmail($data['email']);
        if ($existingEmail) {
            error_log("ERROR: Email ya existe - ID: " . $existingEmail['id']);
            return ['success' => false, 'message' => 'El email ya está registrado'];
        }
        
        try {
            error_log("Procediendo a insertar usuario: " . $data['name']);
            $id = $this->repo->insertUser($data); // 
            error_log("Usuario creado exitosamente con ID: " . $id);
            error_log("=== AdminService::createUser - SUCCESS ===");
            return ['success' => true, 'id' => $id, 'message' => 'Usuario creado exitosamente'];
        } catch (Exception $e) {
            error_log("ERROR EXCEPTION en createUser: " . $e->getMessage());
            error_log("=== AdminService::createUser - ERROR ===");
            return ['success' => false, 'message' => 'Error al crear usuario: ' . $e->getMessage()];
        }
    }

    public function updateUser(int $id, array $data): array
    {
        error_log("=== AdminService::updateUser - INICIO ===");
        error_log("ID: $id, Datos: " . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        try {
            $rows = $this->repo->updateUser($id, $data);
            error_log("Filas afectadas: $rows");
            error_log("=== AdminService::updateUser - SUCCESS ===");
            return ['success' => $rows > 0, 'message' => $rows > 0 ? 'Usuario actualizado' : 'No se pudo actualizar'];
        } catch (Exception $e) {
            error_log("ERROR EXCEPTION en updateUser: " . $e->getMessage());
            error_log("=== AdminService::updateUser - ERROR ===");
            return ['success' => false, 'message' => 'Error al actualizar usuario: ' . $e->getMessage()];
        }
    }

    public function toggleUserStatus(int $id, ?string $newStatus): array
    {
        if (!$newStatus) {
            return ['success' => false, 'message' => 'Falta status'];
        }

        if (!in_array($newStatus, ['activo', 'suspendido'])) {
            return ['success' => false, 'message' => 'Status inválido'];
        }

        try {
            $rows = $this->repo->updateUserStatus($id, $newStatus);
            return ['success' => $rows > 0, 'message' => $rows > 0 ? 'Estado actualizado' : 'No se pudo actualizar el estado'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()];
        }
    }

    // Métodos para mensajes
    public function getAllMessages(): array
    {
        return $this->repo->findAllMessages();
    }
}