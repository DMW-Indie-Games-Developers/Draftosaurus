<?php
require_once __DIR__ . '/../services/PerfilService.php';

class PerfilController {
    private $service;

    public function __construct() {
        $this->service = new PerfilService();
    }

    public function getPerfil($userId) {
        $result = $this->service->getPerfil($userId);
        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }
        return ['success' => true] + $result;
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

    // ✅ NUEVO: Endpoint para obtener estadísticas del usuario
    public function getEstadisticasUsuario($userId) {
        try {
            $estadisticas = $this->service->getEstadisticasUsuario($userId);
            return [
                'success' => true,
                'total_partidas' => $estadisticas['total_partidas'] ?? 0,
                'partidas_ganadas' => $estadisticas['partidas_ganadas'] ?? 0,
                'promedio_puntos' => $estadisticas['promedio_puntos'] ?? 0,
                'mejor_puntuacion' => $estadisticas['mejor_puntuacion'] ?? 0,
                'puntuacion_actual' => $estadisticas['puntuacion_total'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }

    // ✅ NUEVO: Endpoint específico para obtener solo la puntuación
    public function getPuntuacionUsuario($userId) {
        try {
            $puntuacion = $this->service->getPuntuacionUsuario($userId);
            return [
                'success' => true,
                'puntuacion_actual' => $puntuacion
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener puntuación: ' . $e->getMessage(),
                'puntuacion_actual' => 0
            ];
        }
    }

    // ✅ NUEVO: Endpoint para actualizar puntuación
    public function actualizarPuntuacion($userId, $puntos) {
        try {
            $resultado = $this->service->actualizarPuntuacion($userId, $puntos);
            return [
                'success' => true,
                'nueva_puntuacion' => $resultado,
                'message' => 'Puntuación actualizada correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar puntuación: ' . $e->getMessage()
            ];
        }
    }
}