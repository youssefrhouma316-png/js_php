-- ============================================
--  WorkPods - Database Schema
--  coworking_db
--  Entités : users, pods, reservations
-- ============================================

CREATE DATABASE IF NOT EXISTS `coworking_db`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `coworking_db`;

-- ── Table : users ───────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nom`        VARCHAR(80)  NOT NULL,
    `prenom`     VARCHAR(80)  NOT NULL,
    `email`      VARCHAR(180) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `telephone`  VARCHAR(20)  DEFAULT NULL,
    `role`       ENUM('user','admin') NOT NULL DEFAULT 'user',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin par défaut (password: Admin@123)
INSERT INTO `users` (`nom`,`prenom`,`email`,`password`,`role`) VALUES
('Admin','WorkPods','admin@workpods.test',
 '$2y$12$YDummyHashReplaceWithRealBcryptAdmin', 'admin');

-- ── Table : pods (Entité 1) ──────────────────
CREATE TABLE IF NOT EXISTS `pods` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nom`         VARCHAR(120) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `capacite`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `prix_heure`  DECIMAL(8,2) NOT NULL,
    `equipements` TEXT         DEFAULT NULL,   -- JSON string: ["Wi-Fi","Écran 4K"]
    `image`       VARCHAR(255) DEFAULT 'default-pod.jpg',
    `statut`      ENUM('disponible','maintenance','inactif') NOT NULL DEFAULT 'disponible',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données de démonstration
INSERT INTO `pods` (`nom`,`description`,`capacite`,`prix_heure`,`equipements`,`image`) VALUES
('Pod Solo Zen',   'Espace insonorisé idéal pour une concentration maximale.',  1, 8.00,  '["Wi-Fi Fibre","Climatisation","Bureau ergonomique","Prise 220V"]',        'pod1.jpg'),
('Pod Focus Pro',  'Équipé d\'un double écran et d\'une chaise gaming.',         1, 12.00, '["Wi-Fi Fibre","Double écran","Chaise gaming","Webcam HD","Lampe LED"]',    'pod2.jpg'),
('Pod Duo',        'Parfait pour les réunions à deux ou le pair-programming.',   2, 18.00, '["Wi-Fi Fibre","Écran partagé","Tableau blanc","2 chaises ergonomiques"]',  'pod3.jpg'),
('Pod Créatif',    'Inspirant et lumineux pour vos projets créatifs.',           1, 10.00, '["Wi-Fi Fibre","Tablette graphique","Imprimante","Lumière naturelle"]',     'pod4.jpg');

-- ── Table : reservations (Entité 2) ─────────
CREATE TABLE IF NOT EXISTS `reservations` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT UNSIGNED NOT NULL,
    `pod_id`       INT UNSIGNED NOT NULL,
    `date_resa`    DATE         NOT NULL,
    `heure_debut`  TIME         NOT NULL,
    `duree_heures` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `prix_total`   DECIMAL(8,2) NOT NULL,
    `statut`       ENUM('en_attente','confirmee','annulee') NOT NULL DEFAULT 'en_attente',
    `commentaire`  TEXT         DEFAULT NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_resa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_resa_pod`  FOREIGN KEY (`pod_id`)  REFERENCES `pods`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index pour accélérer les requêtes fréquentes
CREATE INDEX idx_resa_date   ON `reservations`(`date_resa`);
CREATE INDEX idx_resa_statut ON `reservations`(`statut`);
CREATE INDEX idx_resa_user   ON `reservations`(`user_id`);

-- ── Vue : statistiques mensuelles ───────────
CREATE OR REPLACE VIEW `v_stats_monthly` AS
    SELECT
        DATE_FORMAT(r.date_resa, '%Y-%m')   AS mois,
        COUNT(r.id)                          AS nb_reservations,
        SUM(r.prix_total)                    AS revenu_total,
        COUNT(DISTINCT r.user_id)            AS nb_clients
    FROM `reservations` r
    WHERE r.statut != 'annulee'
    GROUP BY DATE_FORMAT(r.date_resa, '%Y-%m')
    ORDER BY mois DESC;
