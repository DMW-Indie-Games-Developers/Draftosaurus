<?php
// upload-avatar.php
// Guarda la imagen subida y devuelve la URL

$targetDir = __DIR__ . '/img/avatars/';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$response = ['success' => false];

if (isset($_FILES['avatar']) && isset($_POST['userId'])) {
    $userId = intval($_POST['userId']);
    $file = $_FILES['avatar'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    $targetFile = $targetDir . $fileName;
    $url = 'img/avatars/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $response['success'] = true;
        $response['avatarUrl'] = $url;
    } else {
        $response['message'] = 'No se pudo guardar la imagen.';
    }
} else {
    $response['message'] = 'Faltan datos.';
}

header('Content-Type: application/json');
echo json_encode($response);
