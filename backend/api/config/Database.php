<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Carga el archivo .env ubicado en /var/www/Draftosaurus/backend/
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

class Database
{
    private static ?Database $instance = null;

    private string $host;
    private string $user;
    private string $password;
    private string $dbname;

    private mysqli $conn;

    private function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->user = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->dbname = $_ENV['DB_NAME'] ?? 'draftosaurus';
	
	$this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            throw new Exception('Error de conexión a la base de datos: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8");
    }

    public static function getInstance(): ?Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->conn;
    }

    public function close(): void
    {
        $this->conn->close();
        self::$instance = null;
    }
}
