CREATE DATABASE IF NOT EXISTS draftosaurus
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE draftosaurus;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  username          VARCHAR(50)  NOT NULL UNIQUE,
  email             VARCHAR(100) NOT NULL UNIQUE,
  password          VARCHAR(255) NOT NULL,
  avatar            VARCHAR(255) DEFAULT 'img/isotipoOficial.png',
  puntuacion_total  INT          DEFAULT 0,
  partidas_jugadas  INT          DEFAULT 0,
  partidas_ganadas  INT          DEFAULT 0,
  rol               VARCHAR(20)  NOT NULL DEFAULT 'usuario',
  estado            ENUM('activo','suspendido') DEFAULT 'activo',
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- Añadir constraint CHECK después de crear la tabla
ALTER TABLE users ADD CONSTRAINT chk_rol CHECK (rol IN ('admin','usuario'));

-- Índices rápidos
CREATE INDEX idx_users_rol    ON users(rol);
CREATE INDEX idx_users_estado ON users(estado);

-- Insertar usuarios de prueba
INSERT INTO users (username, email, password, rol, estado) 
VALUES 
('admin','admin@draftosaurus.com','$2y$12$7Yi9tBJek0gSZPqAIy7Xn.9gvbGcx0dVRZFETRFhADJVU31dt4.6q','admin','activo'),
('test','test@draftosaurus.com','$2y$12$6GPrYxJ.VRfuGoZNvNke8eg9BkIebd.9k7msOjuQWDjjkikGNtr.y','usuario','activo');

-- Tabla de partidas
CREATE TABLE IF NOT EXISTS partidas (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  jugador1_id       INT DEFAULT NULL,
  jugador2_id       INT DEFAULT NULL,
  jugadorActivo     TINYINT(1) DEFAULT NULL,
  ronda             TINYINT(1) DEFAULT 1,
  turno             TINYINT(1) DEFAULT 1,
  mano1             JSON NOT NULL,
  mano2             JSON NOT NULL,
  jugadorQueTiroDado TINYINT(1) DEFAULT NULL,
  restriccion       TINYINT(1) DEFAULT NULL,
  recintos          TEXT,
  ganador           TINYINT DEFAULT NULL,
  puntos_j1         INT DEFAULT 0,
  puntos_j2         INT DEFAULT 0,
  ultimo_jugador    TINYINT DEFAULT 1,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  estado_partida    VARCHAR(20) DEFAULT 'activa',
  name_invitado     VARCHAR(100) DEFAULT NULL,
  
  CONSTRAINT fk_partidas_jugador1 FOREIGN KEY (jugador1_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_partidas_jugador2 FOREIGN KEY (jugador2_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir constraints CHECK después de crear la tabla
ALTER TABLE partidas 
  ADD CONSTRAINT partidas_chk_1 CHECK (jugadorActivo IN (1,2)),
  ADD CONSTRAINT partidas_chk_2 CHECK (ronda BETWEEN 1 AND 5),
  ADD CONSTRAINT partidas_chk_3 CHECK (turno BETWEEN 1 AND 3),
  ADD CONSTRAINT partidas_chk_4 CHECK (restriccion BETWEEN 1 AND 6);

-- Tabla de jugadas
CREATE TABLE IF NOT EXISTS jugadas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  id_partida  INT     NOT NULL,
  jugador     TINYINT NOT NULL,
  ronda       TINYINT NOT NULL,   
  turno       TINYINT NOT NULL,   
  recinto     VARCHAR(20),
  dino        VARCHAR(20),
  
  CONSTRAINT fk_jugadas_partida FOREIGN KEY (id_partida) REFERENCES partidas(id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Tabla de contacto
CREATE TABLE IF NOT EXISTS contacto (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  email       VARCHAR(255) NOT NULL,
  asunto      VARCHAR(255) NOT NULL,
  mensaje     TEXT NOT NULL,
  fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- Insertar datos de contacto de ejemplo
INSERT INTO contacto (nombre, email, asunto, mensaje) VALUES
('Juan Pérez','juan@example.com','Consulta sobre el juego','¿Cómo puedo unirme a una partida?'),
('Ana Gómez','ana@example.com','Error en la web','No puedo registrarme, me da un error.'),
('Carlos Ruiz','carlos@example.com','Sugerencia','Podrían agregar más tipos de dinosaurios.');

-- Crear índices
CREATE INDEX idx_partidas_jugador1 ON partidas(jugador1_id);
CREATE INDEX idx_partidas_jugador2 ON partidas(jugador2_id);
CREATE INDEX idx_jugadas_partida ON jugadas(id_partida);
CREATE INDEX idx_contacto_email ON contacto(email);