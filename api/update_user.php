<?php
header("Content-Type: application/json");
require_once __DIR__ . '/config/Database.php';
$conn = Database::getInstance()->getConnection();

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$id){ echo json_encode(["error"=>"Faltan datos"]); exit; }

// Cambiar estado vía PATCH
if(isset($_GET['action']) && $_GET['action']==='status'){
    if(!isset($data['estado'])){
        echo json_encode(["error"=>"Faltan datos"]);
        exit;
    }
    $estado = $conn->real_escape_string($data['estado']);
    $sql = "UPDATE usuarios SET estado='$estado' WHERE id=$id";
    if($conn->query($sql)===TRUE) echo json_encode(["success"=>"Estado actualizado"]);
    else echo json_encode(["error"=>"Error al actualizar: ".$conn->error]);
    exit;
}

// Actualización normal de usuario
$updates = [];
if(isset($data['name'])) $updates[] = "nombre='".$conn->real_escape_string($data['name'])."'";
if(isset($data['email'])) $updates[] = "email='".$conn->real_escape_string($data['email'])."'";
if(isset($data['password'])) $updates[] = "password='".password_hash($data['password'], PASSWORD_BCRYPT)."'";

if(count($updates)){
    $sql = "UPDATE usuarios SET ".implode(',', $updates)." WHERE id=$id";
    if($conn->query($sql)===TRUE) echo json_encode(["success"=>"Usuario actualizado"]);
    else echo json_encode(["error"=>"Error al actualizar: ".$conn->error]);
}else{
    echo json_encode(["error"=>"No hay campos para actualizar"]);
}
