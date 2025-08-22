<?php
require_once __DIR__ . '/../services/PerfilService.php';

class PerfilController {
    private $service;

    public function __construct() {
        $this->service = new PerfilService();
    }

    public function getPerfil($userId) {
        return $this->service->getPerfil($userId);
    }

    // Endpoint para actualizar el avatar
    public function updateAvatar($userId, $avatarUrl) {
        $ok = $this->service->updateAvatar($userId, $avatarUrl);
        if ($ok) {
            return ['success' => true, 'message' => 'Avatar actualizado'];
        } else {
            return ['success' => false, 'message' => 'No se pudo actualizar el avatar'];
        }
    }
}
