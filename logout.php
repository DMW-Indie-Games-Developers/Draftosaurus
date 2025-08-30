<?php
session_start();
session_destroy();
header("Location: login.php");
exit;
?>
/* Prueba de Jona cierre de sesión y redirección al login