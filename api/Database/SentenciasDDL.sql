-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS draftosaurus;
USE draftosaurus;

-- Tabla de usuarios con los atributos solicitados
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'img/isotipoOfficial.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    rol ENUM('usuario', 'admin') DEFAULT 'usuario',
    
    -- Atributos adicionales para el juego
    partidas_jugadas INT DEFAULT 0,
    partidas_ganadas INT DEFAULT 0,
    puntuacion_total INT DEFAULT 0
);

-- Tabla de partidas
CREATE TABLE partidas (
    id_partida INT AUTO_INCREMENT PRIMARY KEY,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('activa', 'finalizada') DEFAULT 'activa',
    ronda_actual INT DEFAULT 1,
    jugador_actual_id INT,
    id_ganador INT NULL,
    FOREIGN KEY (jugador_actual_id) REFERENCES usuarios(id),
    FOREIGN KEY (id_ganador) REFERENCES usuarios(id)
);

-- Tabla de relación entre usuarios y partidas
CREATE TABLE partida_usuarios (
    id_partida_usuario INT AUTO_INCREMENT PRIMARY KEY,
    id_partida INT,
    id_usuario INT,
    puntuacion_final INT DEFAULT 0,
    FOREIGN KEY (id_partida) REFERENCES partidas(id_partida) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla para guardar las colocaciones de dinosaurios
CREATE TABLE colocaciones (
    id_colocacion INT AUTO_INCREMENT PRIMARY KEY,
    id_partida_usuario INT,
    recinto VARCHAR(50) NOT NULL,
    dinosaurio VARCHAR(20) NOT NULL,
    ronda INT NOT NULL,
    fecha_colocacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_partida_usuario) REFERENCES partida_usuarios(id_partida_usuario) ON DELETE CASCADE
);



-- Crear índices para mejorar el rendimiento
CREATE INDEX idx_partida_usuarios ON partida_usuarios(id_partida, id_usuario);
CREATE INDEX idx_colocaciones ON colocaciones(id_partida_usuario, recinto, ronda);
CREATE INDEX idx_usuarios_puntuacion ON usuarios(puntuacion_total DESC);
CREATE INDEX idx_usuarios_username ON usuarios(username);
CREATE INDEX idx_usuarios_email ON usuarios(email);