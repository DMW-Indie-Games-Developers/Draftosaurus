<?php
require_once __DIR__ . '/helpers/AuthHelper.php';
require_once __DIR__ . '/controllers/PerfilController.php';

AuthHelper::iniciarSesion();

// Headers para upload
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Verificar que el usuario esté autenticado
    $user = AuthHelper::requireActiveUser();
    $userId = $user['id'];
    
    // Verificar que se subió un archivo
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió ningún archivo válido');
    }
    
    $file = $_FILES['avatar'];
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Formato de archivo no soportado. Use JPEG, PNG, WEBP o GIF.');
    }
    
    // Validar tamaño (3MB máximo)
    $maxSize = 3 * 1024 * 1024; // 3MB
    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande. Máximo 3MB.');
    }
    
    // Crear directorio si no existe
    $uploadDir = __DIR__ . '/../public/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de avatares');
        }
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // URL pública del avatar
    $avatarUrl = '/uploads/avatars/' . $filename;
    
    // Actualizar en base de datos
    $controller = new PerfilController();
    $result = $controller->updateAvatar($userId, $avatarUrl);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Avatar actualizado correctamente',
            'avatarUrl' => $avatarUrl
        ]);
    } else {
        // Eliminar archivo si no se pudo guardar en BD
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>