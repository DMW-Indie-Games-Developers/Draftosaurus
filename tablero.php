<?php
/* tablero.php */
require_once __DIR__.'/api/config/Database.php';   // o tu bootstrap
if (empty($_SESSION['userId'])) {
    header('Location: /login.html');
    exit;
}
/* ---------- devolvés el HTML ---------- */
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__.'/view/tablero.html');