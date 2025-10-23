<?php

require_once __DIR__ . '/../repositories/ContactoRepository.php';
require_once __DIR__ . '/../../usermodel/Contacto.php';

class ContactoService {
    private $contactoRepository;

    public function __construct() {
        $this->contactoRepository = new ContactoRepository();
    }

    /**
     * Procesar un nuevo mensaje de contacto
     */
    public function crearMensaje($datos) {
        try {
            // Validar que lleguen los datos necesarios
            if (!isset($datos['nombre']) || !isset($datos['email']) || 
                !isset($datos['asunto']) || !isset($datos['mensaje'])) {
                return [
                    'success' => false,
                    'message' => 'Todos los campos son obligatorios'
                ];
            }

            // Limpiar y sanitizar los datos
            $nombre = trim($datos['nombre']);
            $email = trim($datos['email']);
            $asunto = trim($datos['asunto']);
            $mensaje = trim($datos['mensaje']);

            // Crear el objeto contacto
            $contacto = new Contacto($nombre, $email, $asunto, $mensaje);

            // Validar los datos
            $errores = $contacto->validar();
            if (!empty($errores)) {
                return [
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $errores
                ];
            }

            // Validaciones adicionales de seguridad
            if ($this->contienePalabrasSospechosas($mensaje) || 
                $this->contienePalabrasSospechosas($asunto)) {
                return [
                    'success' => false,
                    'message' => 'El mensaje contiene contenido no permitido'
                ];
            }

            // Guardar en la base de datos
            $resultado = $this->contactoRepository->crear($contacto);
            
            if ($resultado) {
                // Log para administradores
                error_log("Nuevo mensaje de contacto recibido de: " . $email);
                
                return [
                    'success' => true,
                    'message' => 'Mensaje enviado correctamente. Te responderemos pronto.',
                    'id' => $resultado->getId()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Error al guardar el mensaje. Por favor, inténtalo de nuevo.'
                ];
            }

        } catch (Exception $e) {
            error_log("Error en ContactoService::crearMensaje: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error interno del servidor'
            ];
        }
    }

    /**
     * Obtener todos los mensajes (para administradores)
     */
    public function obtenerMensajes($pagina = 1, $porPagina = 20) {
        try {
            $offset = ($pagina - 1) * $porPagina;
            $mensajes = $this->contactoRepository->obtenerTodos($porPagina, $offset);
            $total = $this->contactoRepository->contarTotal();
            
            return [
                'success' => true,
                'mensajes' => $mensajes,
                'total' => $total,
                'pagina' => $pagina,
                'porPagina' => $porPagina,
                'totalPaginas' => ceil($total / $porPagina)
            ];
        } catch (Exception $e) {
            error_log("Error en ContactoService::obtenerMensajes: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener los mensajes'
            ];
        }
    }

    /**
     * Obtener un mensaje específico
     */
    public function obtenerMensaje($id) {
        try {
            $contacto = $this->contactoRepository->obtenerPorId($id);
            
            if ($contacto) {
                return [
                    'success' => true,
                    'mensaje' => $contacto->toArray()
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Mensaje no encontrado'
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ContactoService::obtenerMensaje: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener el mensaje'
            ];
        }
    }

    /**
     * Eliminar un mensaje
     */
    public function eliminarMensaje($id) {
        try {
            $resultado = $this->contactoRepository->eliminar($id);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Mensaje eliminado correctamente'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No se pudo eliminar el mensaje'
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ContactoService::eliminarMensaje: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar el mensaje'
            ];
        }
    }

    /**
     * Filtro básico para palabras sospechosas
     */
    private function contienePalabrasSospechosas($texto) {
        $palabrasSospechosas = [
            'script', '<script', 'javascript:', 'onclick', 'onload',
            'eval(', 'document.', 'window.', 'alert(',
            'http://', 'https://', 'www.', '.com', '.net', '.org'
        ];

        $textoMinuscula = strtolower($texto);
        
        foreach ($palabrasSospechosas as $palabra) {
            if (strpos($textoMinuscula, $palabra) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtener estadísticas de mensajes
     */
    public function obtenerEstadisticas() {
        try {
            $total = $this->contactoRepository->contarTotal();
            
            return [
                'success' => true,
                'estadisticas' => [
                    'total_mensajes' => $total,
                    'fecha_consulta' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            error_log("Error en ContactoService::obtenerEstadisticas: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ];
        }
    }
}