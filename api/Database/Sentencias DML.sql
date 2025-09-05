-- Insertar datos iniciales de ejemplo
INSERT INTO usuarios (username, email, password, rol) VALUES
('admin', 'admin@draftosaurus.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('jugador1', 'jugador1@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario'),
('jugador2', 'jugador2@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'usuario');


-- ==========================================
-- ÍNDICES PARA CONSULTAS FRECUENTES
-- ==========================================

-- 1. Partidas que están activas y necesitan turno
-- Consulta: SELECT * FROM partidas WHERE estado = 'activa' AND jugador_actual_id = ?;
CREATE INDEX idx_partidas_activas_turno ON partidas(estado, jugador_actual_id);

-- 2. Saber si un usuario ya está en una partida (evitar duplicados)
-- Consulta: SELECT 1 FROM partida_usuarios WHERE id_partida = ? AND id_usuario = ?;
CREATE INDEX idx_partida_usuario_unico ON partida_usuarios(id_partida, id_usuario);

-- 3. Obtener todos los jugadores de una partida (para mostrar puntuaciones)
-- Consulta: SELECT u.username, pu.puntuacion_final
--           FROM partida_usuarios pu
--           JOIN usuarios u ON u.id = pu.id_usuario
--           WHERE pu.id_partida = ?;
CREATE INDEX idx_partida_usuarios_listado ON partida_usuarios(id_partida, id_usuario, puntuacion_final);

-- 4. Colocaciones por partida + ronda (para reconstruir el tablero)
-- Consulta: SELECT * FROM colocaciones
--           WHERE id_partida_usuario IN (SELECT id_partida_usuario
--                                         FROM partida_usuarios
--                                         WHERE id_partida = ?)
--             AND ronda = ?;
CREATE INDEX idx_colocaciones_partida_ronda ON colocaciones(id_partida_usuario, ronda);

-- 5. Ranking global (consulta muy frecuente)
-- SELECT username, puntuacion_total, partidas_ganadas
-- FROM usuarios
-- ORDER BY puntuacion_total DESC
-- LIMIT 50;
CREATE INDEX idx_ranking_global ON usuarios(puntuacion_total DESC, partidas_ganadas DESC, username);

-- 6. Búsqueda de usuario por username o email (login / registro)
CREATE INDEX idx_usuario_login ON usuarios(username, email);

-- 7. Partidas finalizadas de un usuario (historial)
-- SELECT p.id_partida, p.fecha_creacion, p.id_ganador
-- FROM partidas p
-- JOIN partida_usuarios pu ON pu.id_partida = p.id_partida
-- WHERE pu.id_usuario = ? AND p.estado = 'finalizada';
CREATE INDEX idx_partidas_finalizadas_usuario ON partidas(estado, id_ganador, fecha_creacion);

-- 8. Velocidad al actualizar puntuación final al terminar partida
-- UPDATE partida_usuarios SET puntuacion_final = ? WHERE id_partida = ? AND id_usuario = ?;
CREATE INDEX idx_update_puntuacion ON partida_usuarios(id_partida, id_usuario, puntuacion_final);