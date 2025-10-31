<?php
/**
 * FRONTEND - Servidor de archivos estáticos
 * Puerto: 3000
 * Ejecutar: php -S localhost:3000 -t frontend frontend/server.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Rutas HTML principales
$routes = [
    '/' => 'views/index.html',
    '/home' => 'views/index.html',
    '/login' => 'views/login.html',
    '/perfil' => 'views/perfil.html',
    '/tablero' => 'views/tablero.html',
    '/tracking' => 'views/tracking.html',
    '/ranking' => 'views/ranking.html',
    '/admin' => 'views/admin.html',
];

// Si la ruta está en el mapa de rutas, servir el HTML correspondiente
if (isset($routes[$uri])) {
    $file = __DIR__ . '/' . $routes[$uri];
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// Servir archivos estáticos (CSS, JS, imágenes)
$file = __DIR__ . $uri;

if (file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }

    readfile($file);
    exit;
}

// 404 - Archivo no encontrado
http_response_code(404);
echo '<!DOCTYPE html>
<html>
<head>
    <title>404 - No encontrado</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; }
        h1 { color: #e74c3c; }
    </style>
</head>
<body>
    <h1>404 - Página no encontrada</h1>
    <p>La ruta solicitada no existe.</p>
    <a href="/">Volver al inicio</a>
</body>
</html>';
