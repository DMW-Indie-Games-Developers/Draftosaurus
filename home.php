<?php
// 1. No responder nada si es AJAX
if (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    strpos($_SERVER['REQUEST_URI'], '/api') === 0
) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not Found']);
    exit;
}

// 2. Si ya está logueado → redirigir
require_once __DIR__.'/api/helpers/AuthHelper.php';
AuthHelper::redirectIfLogged(); // <-- hace header + exit si hay sesión

// 3. Si llegó acá → mostrar landing
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__.'/views/index.html');