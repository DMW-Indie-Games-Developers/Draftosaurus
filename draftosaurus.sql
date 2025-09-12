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


CREATE TABLE `partidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jugador1_id` varchar(50) DEFAULT NULL,
  `jugador2_id` varchar(50) DEFAULT NULL,
  `jugadorActivo` tinyint(1) DEFAULT NULL,
  `ronda` tinyint(1) DEFAULT 1,
  `turno` tinyint(1) DEFAULT 1,
  `mano1` json NOT NULL,          -- << sin DEFAULT
  `mano2` json NOT NULL,          -- << sin DEFAULT
  `jugadorQueTiroDado` tinyint(1) DEFAULT NULL,
  `restriccion` tinyint(1) DEFAULT NULL,
  `recintos` text,
  `ganador` tinyint DEFAULT NULL,
  `puntos_j1` int DEFAULT 0,
  `puntos_j2` int DEFAULT 0,
  `ultimo_jugador` tinyint DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado_partida` varchar(20) DEFAULT 'activa',
  `name_invitado` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `partidas_chk_1` CHECK (`jugadorActivo` in (1,2)),
  CONSTRAINT `partidas_chk_2` CHECK (`ronda` between 1 and 5),
  CONSTRAINT `partidas_chk_3` CHECK (`turno` between 1 and 3),
  CONSTRAINT `partidas_chk_4` CHECK (`restriccion` between 1 and 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


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

INSERT INTO contacto (nombre, email, asunto, mensaje) VALUES
('Juan Pérez','juan@example.com','Consulta sobre el juego','¿Cómo puedo unirme a una partida?'),
('Ana Gómez','ana@example.com','Error en la web','No puedo registrarme, me da un error.'),
('Carlos Ruiz','carlos@example.com','Sugerencia','Podrían agregar más tipos de dinosaurios.');