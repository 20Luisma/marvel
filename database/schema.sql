-- Clean Marvel Album - MySQL/MariaDB schema
-- Ejecutar este archivo para provisionar la base de datos utilizada en hosting.

CREATE TABLE IF NOT EXISTS `albums` (
  `album_id` CHAR(36) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `cover_image` VARCHAR(512) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`album_id`),
  KEY `idx_albums_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `heroes` (
  `hero_id` CHAR(36) NOT NULL,
  `album_id` CHAR(36) NOT NULL,
  `nombre` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `contenido` MEDIUMTEXT NOT NULL,
  `imagen` VARCHAR(1024) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`hero_id`),
  UNIQUE KEY `uniq_hero_slug_per_album` (`album_id`, `slug`),
  KEY `idx_heroes_album` (`album_id`),
  KEY `idx_heroes_slug` (`slug`),
  CONSTRAINT `fk_heroes_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`album_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(50) NOT NULL,
  `context_id` VARCHAR(120) NULL,
  `action` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_scope_context` (`scope`, `context_id`),
  KEY `idx_activity_occurred` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
