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
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Faltan campos requeridos'];
        }
        
        try {
            $id = $this->repo->insertUser($data);
            return ['success' => true, 'id' => $id];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error al crear usuario: ' . $e->getMessage()];
        }
    }

    public function updateUser(int $id, array $data): array
    {
        try {
            $rows = $this->repo->updateUser($id, $data);
            return ['success' => $rows > 0, 'message' => $rows > 0 ? 'Usuario actualizado' : 'No se pudo actualizar'];
        } catch (Exception $e) {
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