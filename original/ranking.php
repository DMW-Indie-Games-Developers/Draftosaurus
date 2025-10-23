<?php
require_once __DIR__ . '/api/helpers/AuthHelper.php';

// Verificar que el usuario esté logueado y activo
$user = AuthHelper::requireActiveUser();

// Establecer las cabeceras correctas para HTML
header('Content-Type: text/html; charset=utf-8');

// Leer y mostrar el contenido HTML del ranking
readfile(__DIR__ . '/views/ranking.html');
?>