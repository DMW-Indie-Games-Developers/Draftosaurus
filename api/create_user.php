<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/Database.php';

$conn = Database::getInstance()->getConnection();
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["name"], $data["email"], $data["password"])) {
    echo json_encode(["error" => "Faltan datos"]);
    exit;
}

$nombre = $conn->real_escape_string($data["name"]);
$correo = $conn->real_escape_string($data["email"]);
$password = password_hash($data["password"], PASSWORD_BCRYPT);

$sql = "INSERT INTO usuarios (nombre, email, password, rol, estado) 
        VALUES ('$nombre', '$correo', '$password', 'jugador', 'activo')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["success" => "Usuario creado correctamente"]);
} else {
    echo json_encode(["error" => "Error al crear usuario: " . $conn->error]);
}
