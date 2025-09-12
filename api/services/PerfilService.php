<?php
require_once __DIR__ . '/../repositories/PerfilRepository.php';

class PerfilService {
    private $repository;

    public function __construct() {
        $this->repository = new PerfilRepository();
    }

    public function getPerfil($userId) {
        $user = $this->repository->findById($userId);
        if (!$user) {
            return ['error' => 'Usuario no encontrado'];
        }
        // AquÃ­ puedes agregar lÃ³gica extra si lo necesitas
        return $user;
    }

    public function updateAvatar($userId, $avatarUrl) {
        return $this->repository->updateAvatar($userId, $avatarUrl);
    }
}