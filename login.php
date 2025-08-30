<?php
session_start();
require 'api/config/Database.php';

// Habilitar errores para debug mientras desarrollamos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = "";

// Procesar el formulario
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if($email && $password){
        try {
            // Obtener la conexión desde el singleton
            $db = Database::getInstance();
            $conn = $db->getConnection();

            // Preparar consulta segura
            $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if($stmt->num_rows > 0){
                // Vincular resultados
                $stmt->bind_result($id, $nombre, $passwordHash, $rol);
                $stmt->fetch();

                // Verificar contraseña usando password_verify
                if(password_verify($password, $passwordHash)){
                    // Guardar datos en sesión
                    $_SESSION['id'] = $id;
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['rol'] = $rol;

                    // Redirigir según rol
                    if($rol === 'admin'){
                        header("Location: admin.php"); // Admin va al panel de administración
                        exit;
                    } else {
                        header("Location: perfil.html"); // Usuario común va a su perfil
                        exit;
                    }
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "Usuario no encontrado.";
            }

            $stmt->close();

        } catch(Exception $e){
            $error = "Error interno: " . $e->getMessage();
        }

    } else {
        $error = "Completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    <?php if($error) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="post" action="">
        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Contraseña:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Ingresar</button>
    </form>
</body>
</html>
/* Prueba de Jona login con verificación de usuario y rol y redirección correcta */
