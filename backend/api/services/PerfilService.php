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
            'nickname' => $user['nickname'] ?? null,
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

    public function updateNickname($userId, $nickname) {
        // Validar nickname
        if ($nickname !== null && $nickname !== '') {
            // Limpiar espacios y validar longitud
            $nickname = trim($nickname);
            if (strlen($nickname) > 50) {
                return ['success' => false, 'message' => 'El nickname no puede tener más de 50 caracteres'];
            }
            if (strlen($nickname) < 2) {
                return ['success' => false, 'message' => 'El nickname debe tener al menos 2 caracteres'];
            }

            // Validar que el nickname no sea igual al username
            $user = $this->repository->findById($userId);
            if ($user && $nickname === $user['username']) {
                return ['success' => false, 'message' => 'El nickname no puede ser igual a tu nombre de usuario'];
            }

            // Validar que el nickname sea único
            if ($this->repository->nicknameExists($nickname, $userId)) {
                return ['success' => false, 'message' => 'Este nickname ya está en uso por otro usuario'];
            }
        } else {
            $nickname = null; // Permitir nickname vacío
        }

        $result = $this->repository->updateNickname($userId, $nickname);
        if ($result) {
            return ['success' => true, 'message' => 'Nickname actualizado correctamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar el nickname'];
        }
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

    // Método para obtener ranking general
    public function getRanking($limit = 10) {
        return $this->repository->getRanking($limit);
    }

    // Método para obtener posición del usuario en el ranking
    public function getUserRanking($userId) {
        return $this->repository->getUserRanking($userId);
    }
}