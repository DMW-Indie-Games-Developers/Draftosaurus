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
class Database
{
    /**
     * Instancia única de la clase (Singleton).
     * - ?Database: puede ser Database o null antes de inicializarse.
     */
    private static ?Database $instance = null; // instancia única

    /**
     * Configuración de conexión usando variables de entorno.
     * Las credenciales se cargan desde el archivo .env en la raíz del proyecto.
     */
    private string $host;
    private string $user;
    private string $password;
    private string $dbname;

    /**
     * Recurso de conexión de mysqli ya abierto y listo para usarse por el resto de la aplicación.
     */
    private mysqli $conn;

    /**
     * Constructor privado: impide instanciar la clase desde fuera.
     * Aquí se establece la conexión a la base de datos.
     * Si hay un error de conexión, se lanza una Exception con un mensaje claro.
     */
    private function __construct()
    {
        // Cargar variables de entorno desde el archivo .env
        $this->loadEnv();

        // Obtener credenciales desde variables de entorno
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'draftosaurus';

        // Crea la conexión usando credenciales desde variables de entorno
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);

        // Si falla la conexión, detenemos con una excepción descriptiva
        if ($this->conn->connect_error) {
            throw new Exception('Error de conexión a la base de datos: ' . $this->conn->connect_error);
        }

        // Asegura que las comunicaciones usen UTF-8 (evita problemas con acentos/ñ)
        $this->conn->set_charset("utf8");
    }

    /**
     * Carga las variables de entorno desde el archivo .env
     * Busca el archivo .env en el directorio raíz del proyecto
     */
    private function loadEnv(): void
    {
        // Buscar el archivo .env en la raíz del proyecto (3 niveles arriba desde /backend/api/config/)
        $envPath = __DIR__ . '/../../../.env';

        if (!file_exists($envPath)) {
            // Si no existe .env, intentar usar valores por defecto o lanzar error
            error_log("⚠️ Archivo .env no encontrado en: " . realpath(__DIR__ . '/../../../'));
            return;
        }

        // Leer el archivo .env línea por línea
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentarios (líneas que empiezan con #)
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Separar clave=valor
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Establecer la variable de entorno
                if (!empty($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
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
