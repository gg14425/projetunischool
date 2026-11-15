-- ============================================================
-- Migration 001 — Durcissement sécurité (My EPI App)
-- À exécuter UNE FOIS sur une base myepi_db déjà installée :
--     mysql -u root -p myepi_db < database/migrations/001_hardening.sql
-- (Déjà incluse dans myepi_db.sql pour les nouvelles installations.)
-- ============================================================

-- Limitation des tentatives de connexion (anti brute-force)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`          int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifiant` varchar(60)  NOT NULL,
  `ip_address`  varchar(45)  NOT NULL,
  `succes`      tinyint(1)   NOT NULL DEFAULT 0,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ident_ip` (`identifiant`,`ip_address`,`created_at`),
  KEY `idx_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
