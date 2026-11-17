-- Seleccionar primero la base creada en el panel del hosting y luego importar.
-- MySQL 8+ o MariaDB 10.6+, InnoDB, utf8mb4. No incluye credenciales.
CREATE TABLE IF NOT EXISTS vd_users (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  auth_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS vd_clinic (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payload LONGTEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (JSON_VALID(payload))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO vd_clinic (id, revision, payload) VALUES
(1, 0, '{"patients":[],"appointments":[],"sessions":[],"treatments":[],"payments":[],"teeth":{}}');
CREATE TABLE IF NOT EXISTS vd_auth_limits (
  ip_hash CHAR(64) NOT NULL PRIMARY KEY,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_start BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Adjuntos fuera del documento clínico: no se descargan todas las imágenes al iniciar.
CREATE TABLE IF NOT EXISTS vd_attachments (
  id CHAR(64) NOT NULL PRIMARY KEY,
  mime VARCHAR(60) NOT NULL,
  content LONGBLOB NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Actualización aditiva. Seleccionar bmimport_vdnt en phpMyAdmin.
-- Sólo necesaria si el usuario de la aplicación no tiene permiso CREATE.
CREATE TABLE IF NOT EXISTS vd_remember_tokens (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id TINYINT UNSIGNED NOT NULL,
  auth_version INT UNSIGNED NOT NULL,
  expires_at BIGINT UNSIGNED NOT NULL,
  INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
