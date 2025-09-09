<?php
// upload-avatar.php
// Recibe multipart/form-data con 'avatar' (file) y 'userId' (optional)
// Valida el archivo, lo guarda en uploads/avatars/ y devuelve JSON { success, avatarUrl }

header('Content-Type: application/json');

// Límite de tamaño (3 MB)
$maxSize = 3 * 1024 * 1024;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    }

    if (!isset($_FILES['avatar'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se recibió el archivo avatar.']);
        exit;
    }

    $file = $_FILES['avatar'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Error al subir el archivo.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El archivo excede el tamaño máximo de 3 MB.']);
        exit;
    }

    // Validar tipo MIME real con fallbacks (evita dependencia estricta de la extensión fileinfo)
    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    } else {
        $imgInfo = @getimagesize($file['tmp_name']);
        $mime = $imgInfo['mime'] ?? null;
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    if (!array_key_exists($mime, $allowed)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido. Solo imágenes.']);
        exit;
    }

    // UserId opcional (para construir nombre de archivo)
    $userId = isset($_POST['userId']) ? preg_replace('/[^0-9]/', '', $_POST['userId']) : null;

    // Crear carpeta si no existe
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'No se pudo crear carpeta de subida.']);
            exit;
        }
    }

    // Generar nombre único
    $ext = $allowed[$mime];
    $base = $userId ? ('user_' . $userId) : 'avatar';
    $filename = $base . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo.']);
        exit;
    }

    // Opcional: establecer permisos
    @chmod($target, 0644);

    // URL accesible desde la web (ruta relativa) - coincide con la carpeta donde guardamos
    $avatarUrl = '/uploads/avatars/' . $filename;

    echo json_encode(['success' => true, 'avatarUrl' => $avatarUrl]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno.', 'detail' => $e->getMessage()]);
    exit;
}