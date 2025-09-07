CREATE DATABASE IF NOT EXISTS draftosaurus
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE draftosaurus;

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
  estado     ENUM('activo','suspendido') DEFAULT 'activo',
  created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_rol CHECK (rol IN ('admin','usuario'))
) ENGINE = InnoDB;

-- Índices rápidos
CREATE INDEX idx_users_rol    ON users(rol);
CREATE INDEX idx_users_estado ON users(estado);

INSERT INTO users (username, email, password, rol, estado) 
VALUES 
('admin','admin@draftosaurus.com','$2y$12$7Yi9tBJek0gSZPqAIy7Xn.9gvbGcx0dVRZFETRFhADJVU31dt4.6q','admin',1), -- Contraseña admin
('test','test@draftosaurus.com','$2y$12$6GPrYxJ.VRfuGoZNvNke8eg9BkIebd.9k7msOjuQWDjjkikGNtr.y','usuario',1); -- contraseña test

CREATE TABLE partidas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  jugador1    VARCHAR(50) NOT NULL,
  jugador2    VARCHAR(50) DEFAULT NULL,
  estado      JSON        NOT NULL,  
  ganador     TINYINT     DEFAULT NULL, 
  puntos_j1   INT         DEFAULT 0,
  puntos_j2   INT         DEFAULT 0,
  fecha       TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_part_j1
    FOREIGN KEY (jugador1) REFERENCES users(username)
    ON DELETE CASCADE,
  CONSTRAINT fk_part_j2
    FOREIGN KEY (jugador2) REFERENCES users(username)
    ON DELETE SET NULL
) ENGINE = InnoDB;


CREATE TABLE jugadas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  id_partida  INT     NOT NULL,
  jugador     TINYINT NOT NULL,
  ronda       TINYINT NOT NULL,   
  turno       TINYINT NOT NULL,   
  recinto     VARCHAR(20),
  dino        VARCHAR(20),

  CONSTRAINT fk_jug_partida
    FOREIGN KEY (id_partida) REFERENCES partidas(id)
    ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE INDEX idx_partidas_jugador ON partidas(jugador1);
CREATE INDEX idx_partidas_jugador2 ON partidas(jugador2);
CREATE INDEX idx_jugadas_partida   ON jugadas(id_partida);

CREATE TABLE IF NOT EXISTS contacto (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  email       VARCHAR(255) NOT NULL,
  asunto      VARCHAR(255) NOT NULL,
  mensaje     TEXT NOT NULL,
  fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP
);