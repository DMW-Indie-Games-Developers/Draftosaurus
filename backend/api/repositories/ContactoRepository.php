<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../../usermodel/Contacto.php';

class ContactoRepository {
    private $db;

    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Crear un nuevo mensaje de contacto
     */
    public function crear(Contacto $contacto) {
        try {
            $sql = "INSERT INTO contacto (nombre, email, asunto, mensaje, fecha_envio)
                    VALUES (?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);

            // bind_param requiere variables, no puede recibir resultados de funciones directamente
            $nombre = $contacto->getNombre();
            $email = $contacto->getEmail();
            $asunto = $contacto->getAsunto();
            $mensaje = $contacto->getMensaje();

            $stmt->bind_param("ssss", $nombre, $email, $asunto, $mensaje);

            if ($stmt->execute()) {
                $contacto->setId($this->db->insert_id);
                return $contacto;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error en ContactoRepository::crear: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los mensajes de contacto
     */
    public function obtenerTodos($limite = 50, $offset = 0) {
        try {
            $sql = "SELECT id, nombre, email, asunto, mensaje, fecha_envio 
                    FROM contacto 
                    ORDER BY fecha_envio DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("ii", $limite, $offset);
            $stmt->execute();
            
            $resultado = $stmt->get_result();
            $mensajes = [];
            
            while ($fila = $resultado->fetch_assoc()) {
                $contacto = new Contacto();
                $contacto->setId($fila['id']);
                $contacto->setNombre($fila['nombre']);
                $contacto->setEmail($fila['email']);
                $contacto->setAsunto($fila['asunto']);
                $contacto->setMensaje($fila['mensaje']);
                $contacto->setFechaEnvio($fila['fecha_envio']);
                
                $mensajes[] = $contacto->toArray();
            }
            
            return $mensajes;
        } catch (Exception $e) {
            error_log("Error en ContactoRepository::obtenerTodos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener un mensaje por ID
     */
    public function obtenerPorId($id) {
        try {
            $sql = "SELECT id, nombre, email, asunto, mensaje, fecha_envio 
                    FROM contacto 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $resultado = $stmt->get_result();
            $fila = $resultado->fetch_assoc();
            
            if ($fila) {
                $contacto = new Contacto();
                $contacto->setId($fila['id']);
                $contacto->setNombre($fila['nombre']);
                $contacto->setEmail($fila['email']);
                $contacto->setAsunto($fila['asunto']);
                $contacto->setMensaje($fila['mensaje']);
                $contacto->setFechaEnvio($fila['fecha_envio']);
                
                return $contacto;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error en ContactoRepository::obtenerPorId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Contar total de mensajes
     */
    public function contarTotal() {
        try {
            $sql = "SELECT COUNT(*) as total FROM contacto";
            $resultado = $this->db->query($sql);
            $fila = $resultado->fetch_assoc();
            return $fila['total'];
        } catch (Exception $e) {
            error_log("Error en ContactoRepository::contarTotal: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Eliminar un mensaje por ID
     */
    public function eliminar($id) {
        try {
            $sql = "DELETE FROM contacto WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("i", $id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error en ContactoRepository::eliminar: " . $e->getMessage());
            return false;
        }
    }
}