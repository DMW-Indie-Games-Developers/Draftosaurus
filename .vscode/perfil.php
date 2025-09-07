<?php
require_once __DIR__.'/api/helpers/AuthHelper.php';
AuthHelper::requireLogin();   // ¿Sin login? → login.html
readfile(__DIR__.'/perfil.html');