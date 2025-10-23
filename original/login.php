<?php
require_once __DIR__.'/api/helpers/AuthHelper.php';
AuthHelper::redirectIfLogged(); // si ya está logueado → /perfil
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__.'/views/login.html');