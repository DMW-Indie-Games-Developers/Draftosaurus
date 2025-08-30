<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/Database.php';

$conn = Database::getInstance()->getConnection();

// Seleccionamos todos los campos correctos
$sql = "SELECT id, nombre, email, asunto, mensaje, fecha_envio FROM contacto";
$res = $conn->query($sql);

if(!$res){
    echo json_encode(["error" => "Error en la consulta: " . $conn->error]);
    exit;
}

$msgs = [];
while($row = $res->fetch_assoc()){
    $msgs[] = [
        "id" => $row["id"],
        "nombre" => $row["nombre"],
        "email" => $row["email"],
        "asunto" => $row["asunto"],
        "mensaje" => $row["mensaje"],
        "fecha_envio" => $row["fecha_envio"]
    ];
}

echo json_encode($msgs);
