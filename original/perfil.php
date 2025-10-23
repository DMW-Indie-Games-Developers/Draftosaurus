<?php
require_once __DIR__.'/api/helpers/AuthHelper.php';
AuthHelper::iniciarSesion();
AuthHelper::requireLogin();          // ¿sin sesión? → /login
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/views/perfil.html');