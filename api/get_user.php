<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/Database.php';

$conn = Database::getInstance()->getConnection();

if(!isset($_GET['id'])){
    echo json_encode(['error'=>'Faltan datos']);
    exit;
}

$id = (int)$_GET['id'];

$sql = "SELECT id, nombre, email, rol, estado FROM usuarios WHERE id=$id";
$res = $conn->query($sql);

if($res && $res->num_rows > 0){
    echo json_encode($res->fetch_assoc());
} else {
    echo json_encode(['error'=>'Usuario no encontrado']);
}
/* Prueba de Jona obtención de datos de un usuario específico */