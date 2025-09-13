<?php
/**
 * Propósito:
 *  - Centraliza la creación y gestión de la conexión a la base de datos MySQL.
 *  - Implementa el patrón Singleton para asegurar que exista una única conexión
 *    compartida durante el ciclo de vida de la aplicación (evita múltiples conexiones innecesarias).
 *
 * Conceptos clave:
 *  - Patrón Singleton (constructor privado + método estático getInstance()).
 *  - Uso de la extensión mysqli para conectarse a MySQL.
 *  - Manejo básico de errores de conexión y configuración de charset (utf8).
 */
class Database {
    /**
     * Instancia única de la clase (Singleton).
     * - ?Database: puede ser Database o null antes de inicializarse.
     */
    private static ?Database $instance = null; // instancia única

    /** Configuración de conexión (ajusta a tu entorno local). */
    private string $host = "localhost";   // Host del servidor MySQL
    private string $user = "root";        // Usuario de la base de datos
    private string $password = "mysql";  // Contraseña del usuario
    private string $dbname = "draftosaurus"; // Nombre de la base de datos

    /**
     * Recurso de conexión de mysqli ya abierto y listo para usarse por el resto de la aplicación.
     */
    private mysqli $conn;

    /**
     * Constructor privado: impide instanciar la clase desde fuera.
     * Aquí se establece la conexión a la base de datos.
     * Si hay un error de conexión, se lanza una Exception con un mensaje claro.
     */
    private function __construct() {
        // Crea la conexión usando credenciales definidas arriba
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);

        // Si falla la conexión, detenemos con una excepción descriptiva
        if ($this->conn->connect_error) {
            throw new Exception('Error de conexión a la base de datos: ' . $this->conn->connect_error);
        }

        // Asegura que las comunicaciones usen UTF-8 (evita problemas con acentos/ñ)
        $this->conn->set_charset("utf8");
    }

    /**
     * Punto de acceso global a la instancia Singleton de Database.
     * - Si la instancia no existe, la crea.
     * - Si ya existe, devuelve la misma.
     */
    public static function getInstance(): ?Database
    {
        // Si no existe una instancia, la crea
        if (self::$instance === null) {
            self::$instance = new self();
        }
        // en caso contrario, retorna la instancia existente
        return self::$instance;
    }

    /**
     * Devuelve la conexión mysqli activa para ejecutar consultas.
     */
    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    /**
     * Cierra la conexión y libera la instancia Singleton (opcionalmente útil
     * para pruebas o para reinicializar la conexión).
     */
    public function close(): void
    {
        $this->conn->close();
        self::$instance = null; // Permite recrear la instancia si se llama nuevamente a getInstance()
    }
}
