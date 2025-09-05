-- Propósito:
--  - Definir el esquema mínimo de la base de datos para la API de login.
--
-- Notas:
--  - Ajusta el nombre de usuario/contraseña del MySQL en app/config/Database.php según tu entorno.
--  - Ejecuta este script en tu servidor MySQL para crear la base y la tabla necesaria.

CREATE DATABASE IF NOT EXISTS login_api;

USE login_api;

-- Tabla principal de usuarios
CREATE TABLE users (
   id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,         -- Identificador único autoincremental
   username VARCHAR(50) NOT NULL UNIQUE,               -- Nombre de usuario, único
   email VARCHAR(100) NOT NULL UNIQUE,                 -- Correo electrónico, único
   password VARCHAR(255) NOT NULL,                     -- Hash de la contraseña (no se guarda en texto plano)
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,     -- Fecha/hora de creación (automática)
   updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- Última actualización
);

-- Índices adicionales para acelerar búsquedas por username y por email
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_email ON users(email);

