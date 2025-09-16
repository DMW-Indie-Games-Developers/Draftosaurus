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

        // Asegurarse de que los valores numéricos estén correctamente formateados
        return [
            'success' => true,
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'avatar' => $user['avatar'],
            'puntuacion_total' => (int)($user['puntuacion_total'] ?? 0),
            'partidas_jugadas' => (int)($user['partidas_jugadas'] ?? 0),
            'partidas_ganadas' => (int)($user['partidas_ganadas'] ?? 0),
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];
    }

    public function updateAvatar($userId, $avatarUrl) {
        return $this->repository->updateAvatar($userId, $avatarUrl);
    }

    // Método para obtener solo la puntuación
    public function getPuntuacionUsuario($userId) {
        return $this->repository->getPuntuacionTotal($userId);
    }

    // Método para actualizar puntuación
    public function actualizarPuntuacion($userId, $puntos) {
        return $this->repository->actualizarPuntuacion($userId, $puntos);
    }

    // Método para obtener estadísticas completas
    public function getEstadisticasUsuario($userId) {
        return $this->repository->getEstadisticasUsuario($userId);
    }
}