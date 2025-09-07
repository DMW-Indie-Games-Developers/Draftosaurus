<?php
require_once __DIR__ . '/../services/AdminService.php';

class AdminController
{
    private AdminService $service;

    public function __construct()
    {
        $this->service = new AdminService();
    }

    public function listUsers(): void
    {
        try {
            $users = $this->service->getAllUsers();
            http_response_code(200);
            echo json_encode($users);
        } catch (Exception $e) {
            error_log("Error en listUsers: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener usuarios']);
        }
    }

    public function getUser(int $id): void
    {
        try {
            $user = $this->service->getUserById($id);
            if ($user) {
                http_response_code(200);
                echo json_encode($user);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
            }
        } catch (Exception $e) {
            error_log("Error en getUser: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener usuario']);
        }
    }

    public function createUser(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                return;
            }
            
            $result = $this->service->createUser($data);
            http_response_code($result['success'] ? 201 : 400);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error en createUser: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }

    public function updateUser(int $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                return;
            }
            
            $result = $this->service->updateUser($id, $data);
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error en updateUser: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }

    public function toggleUserStatus(int $id): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['status'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Status requerido']);
                return;
            }
            
            $result = $this->service->toggleUserStatus($id, $data['status']);
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Error en toggleUserStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }

    public function listMessages(): void
    {
        try {
            $messages = $this->service->getAllMessages();
            http_response_code(200);
            echo json_encode($messages);
        } catch (Exception $e) {
            error_log("Error en listMessages: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener mensajes']);
        }
    }
}