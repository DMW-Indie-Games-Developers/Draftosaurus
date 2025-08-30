<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/Database.php';

$conn = Database::getInstance()->getConnection();

// Seleccionamos todos los campos necesarios
$sql = "SELECT id, nombre, email, rol, estado FROM usuarios";
$result = $conn->query($sql);

if(!$result){
    echo json_encode(["error" => "Error en la consulta: " . $conn->error]);
    exit;
}

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = [
        "id" => $row["id"],
        "nombre" => $row["nombre"],
        "email" => $row["email"],
        "rol" => $row["rol"],
        "estado" => $row["estado"]
    ];
}

echo json_encode($usuarios);
/* Prueba de Jona: obtención de lista de usuarios desde la base de datos */
