<?php
class AuthHelper {
    public static function iniciarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }

    public static function requireLogin(): void {
        self::iniciarSesion();
        if (!isset($_SESSION['userId'])) {
            header('Location: /login.html');
            exit;
        }
    }

    public static function redirectIfLogged(): void {
        self::iniciarSesion();
        if (isset($_SESSION['userId'])) {
            header('Location: /perfil');
            exit;
        }
    }

    public static function usuario(): ?array {
        self::iniciarSesion();
        return $_SESSION['userId'] ?? null;
    }
}