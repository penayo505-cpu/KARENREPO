-- Actualización aditiva. Seleccionar bmimport_vdnt en phpMyAdmin.
-- Sólo necesaria si el usuario de la aplicación no tiene permiso CREATE.
CREATE TABLE IF NOT EXISTS vd_remember_tokens (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id TINYINT UNSIGNED NOT NULL,
  auth_version INT UNSIGNED NOT NULL,
  expires_at BIGINT UNSIGNED NOT NULL,
  INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
