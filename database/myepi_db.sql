-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 11 mai 2026 à 17:55
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `myepi_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

CREATE TABLE `absences` (
  `id` int(10) UNSIGNED NOT NULL,
  `etudiant_id` int(10) UNSIGNED NOT NULL,
  `matiere_id` int(10) UNSIGNED NOT NULL,
  `enseignant_id` int(10) UNSIGNED NOT NULL,
  `date_absence` date NOT NULL,
  `duree_heures` decimal(4,1) NOT NULL DEFAULT 1.5,
  `justifie` tinyint(1) NOT NULL DEFAULT 0,
  `observation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `absences`
--

INSERT INTO `absences` (`id`, `etudiant_id`, `matiere_id`, `enseignant_id`, `date_absence`, `duree_heures`, `justifie`, `observation`, `created_at`) VALUES
(1, 1, 4, 1, '2026-02-10', 1.5, 0, NULL, '2026-05-11 15:44:52'),
(2, 1, 5, 2, '2026-02-12', 3.0, 1, 'Certificat médical', '2026-05-11 15:44:52'),
(3, 1, 1, 1, '2026-01-20', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(4, 1, 9, 1, '2026-01-27', 1.5, 0, NULL, '2026-05-11 15:44:52'),
(5, 2, 9, 1, '2026-02-03', 1.5, 1, 'Transport', '2026-05-11 15:44:52'),
(6, 3, 3, 2, '2026-01-15', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(7, 3, 3, 2, '2026-01-22', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(8, 3, 3, 2, '2026-01-29', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(9, 3, 3, 2, '2026-02-05', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(10, 3, 3, 2, '2026-02-12', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(11, 3, 3, 2, '2026-02-19', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(12, 3, 3, 2, '2026-02-26', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(13, 3, 3, 2, '2026-03-05', 3.0, 0, NULL, '2026-05-11 15:44:52'),
(14, 3, 3, 2, '2026-03-12', 3.0, 0, NULL, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` enum('login','logout','create','edit','delete','view','publish','generate') NOT NULL,
  `module` varchar(60) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `module`, `details`, `ip_address`, `created_at`) VALUES
(1, 9, 'create', 'utilisateurs', 'Compte créé pour Sana MEJRI (GL 3A)', '192.168.1.10', '2026-05-11 15:44:52'),
(2, 7, 'edit', 'emploi_temps', 'Mise à jour emploi du temps TIC 2D', '192.168.1.22', '2026-05-11 15:44:52'),
(3, 9, 'publish', 'annonces', 'Annonce Clôture S1 diffusée à 1248 étudiants', '192.168.1.10', '2026-05-11 15:44:52'),
(4, 5, 'login', 'auth', 'Connexion réussie', '10.0.0.55', '2026-05-11 15:44:52'),
(5, 9, 'delete', 'utilisateurs', 'Compte supprimé — Étudiant inactif #00234', '192.168.1.10', '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `alertes_pedagogiques`
--

CREATE TABLE `alertes_pedagogiques` (
  `id` int(10) UNSIGNED NOT NULL,
  `type` enum('absence_prof','rattrapage','autre') NOT NULL DEFAULT 'autre',
  `message` text NOT NULL,
  `matiere_id` int(10) UNSIGNED DEFAULT NULL,
  `classe_id` int(10) UNSIGNED DEFAULT NULL,
  `date_seance` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `salle` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `alertes_pedagogiques`
--

INSERT INTO `alertes_pedagogiques` (`id`, `type`, `message`, `matiere_id`, `classe_id`, `date_seance`, `heure_debut`, `salle`, `actif`, `created_at`) VALUES
(1, 'absence_prof', 'Le cours de Réseaux de ce Jeudi est annulé. Pr. Ben Salah est absent.', NULL, 1, '2026-04-17', '08:30:00', 'DS.S201', 1, '2026-05-11 15:44:52'),
(2, 'rattrapage', 'Séance de rattrapage en Bases de données programmée ce Samedi de 09h00 à 12h00 en Salle 4.', 3, 1, '2026-04-19', '09:00:00', 'Salle 4', 1, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

CREATE TABLE `annonces` (
  `id` int(10) UNSIGNED NOT NULL,
  `titre` varchar(200) NOT NULL,
  `contenu` text NOT NULL,
  `type` enum('annonce','alerte','info','calendrier','examen') NOT NULL DEFAULT 'annonce',
  `destinataires` enum('tous','etudiants','enseignants','scolarite','admin') NOT NULL DEFAULT 'tous',
  `statut` enum('brouillon','publie','archive') NOT NULL DEFAULT 'brouillon',
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `date_publication` timestamp NULL DEFAULT NULL,
  `nb_lectures` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `annonces`
--

INSERT INTO `annonces` (`id`, `titre`, `contenu`, `type`, `destinataires`, `statut`, `auteur_id`, `date_publication`, `nb_lectures`, `created_at`) VALUES
(1, 'Calendrier des examens Semestre 1', 'Les examens du premier semestre se dérouleront du 15 au 28 Avril 2026. Consultez votre espace pour le détail des horaires.', 'examen', 'etudiants', 'publie', 7, '2026-05-11 15:44:52', 0, '2026-05-11 15:44:52'),
(2, 'Fermeture de la bibliothèque', 'La bibliothèque sera fermée ce vendredi pour inventaire annuel. Réouverture lundi matin.', 'info', 'tous', 'publie', 7, '2026-05-11 15:44:52', 0, '2026-05-11 15:44:52'),
(3, 'Réinscriptions S2 — Procédures', 'Les réinscriptions pour le semestre 2 sont ouvertes du 1er au 15 Mars. Pièces requises: CIN + reçu de paiement.', 'calendrier', 'etudiants', 'brouillon', 7, NULL, 0, '2026-05-11 15:44:52'),
(4, 'EPI IT Day — Inscriptions ouvertes', 'Rejoignez-nous pour la journée IT Day le 25 Avril. Workshops, conférences et networking. Inscription gratuite.', 'annonce', 'tous', 'publie', 9, '2026-05-11 15:44:52', 0, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(80) NOT NULL,
  `filiere_id` int(10) UNSIGNED NOT NULL,
  `annee` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom`, `filiere_id`, `annee`) VALUES
(1, 'TIC 2D.FR', 1, 2),
(2, 'TIC 3A', 1, 3),
(3, 'GL 3A', 2, 3),
(4, 'GL 3B', 2, 3),
(5, 'RT 2A', 3, 2);

-- --------------------------------------------------------

--
-- Structure de la table `cours_supports`
--

CREATE TABLE `cours_supports` (
  `id` int(10) UNSIGNED NOT NULL,
  `titre` varchar(200) NOT NULL,
  `matiere_id` int(10) UNSIGNED NOT NULL,
  `enseignant_id` int(10) UNSIGNED NOT NULL,
  `type_fichier` enum('PDF','PPT','TD','TP','Video','Autre') NOT NULL DEFAULT 'PDF',
  `fichier_url` varchar(400) NOT NULL,
  `taille_kb` int(10) UNSIGNED DEFAULT NULL,
  `date_upload` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cours_supports`
--

INSERT INTO `cours_supports` (`id`, `titre`, `matiere_id`, `enseignant_id`, `type_fichier`, `fichier_url`, `taille_kb`, `date_upload`) VALUES
(1, 'Chapitre 1 : Introduction à ReactJS', 1, 1, 'PDF', 'uploads/cours/web2-ch1.pdf', NULL, '2026-05-11 15:44:52'),
(2, 'Slides : Hooks en React', 1, 1, 'PPT', 'uploads/cours/web2-hooks.pptx', NULL, '2026-05-11 15:44:52'),
(3, 'TP 1 : Todolist Application', 1, 1, 'TD', 'uploads/cours/web2-tp1.pdf', NULL, '2026-05-11 15:44:52'),
(4, 'Chapitre 1 : Héritage & Polymorphisme', 2, 2, 'PDF', 'uploads/cours/poo-ch1.pdf', NULL, '2026-05-11 15:44:52'),
(5, 'TP 1 : Gestion Hôpital en Java', 2, 2, 'TP', 'uploads/cours/poo-tp1.pdf', NULL, '2026-05-11 15:44:52'),
(6, 'Cours Algorithmique Avancée - Complet', 3, 2, 'PDF', 'uploads/cours/algo-complet.pdf', NULL, '2026-05-11 15:44:52'),
(7, 'TD Graphes - Exercices corrigés', 4, 1, 'TD', 'uploads/cours/graphes-td.pdf', NULL, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `demandes_documents`
--

CREATE TABLE `demandes_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `etudiant_id` int(10) UNSIGNED NOT NULL,
  `type_document` enum('attestation_presence','releve_notes','attestation_scolarite','convention_stage','certificat_reussite') NOT NULL,
  `motif` varchar(200) NOT NULL,
  `statut` enum('en_attente','generee','refusee') NOT NULL DEFAULT 'en_attente',
  `fichier_url` varchar(400) DEFAULT NULL,
  `traitee_par` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `demandes_documents`
--

INSERT INTO `demandes_documents` (`id`, `etudiant_id`, `type_document`, `motif`, `statut`, `fichier_url`, `traitee_par`, `created_at`, `updated_at`) VALUES
(1, 1, 'attestation_presence', 'Stage en entreprise', 'en_attente', NULL, NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(2, 2, 'releve_notes', 'Candidature Master', 'generee', NULL, NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(3, 3, 'convention_stage', 'PFE entreprise XYZ', 'en_attente', NULL, NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `departements`
--

CREATE TABLE `departements` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `departements`
--

INSERT INTO `departements` (`id`, `nom`) VALUES
(1, 'Informatique & Technologies'),
(2, 'Mathématiques & Sciences'),
(3, 'Langues & Communication');

-- --------------------------------------------------------

--
-- Structure de la table `emploi_temps`
--

CREATE TABLE `emploi_temps` (
  `id` int(10) UNSIGNED NOT NULL,
  `classe_id` int(10) UNSIGNED NOT NULL,
  `matiere_id` int(10) UNSIGNED NOT NULL,
  `enseignant_id` int(10) UNSIGNED NOT NULL,
  `jour` enum('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `salle` varchar(50) NOT NULL,
  `type_seance` enum('Cours','TD','TP') NOT NULL DEFAULT 'Cours',
  `semestre` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `emploi_temps`
--

INSERT INTO `emploi_temps` (`id`, `classe_id`, `matiere_id`, `enseignant_id`, `jour`, `heure_debut`, `heure_fin`, `salle`, `type_seance`, `semestre`) VALUES
(1, 1, 2, 2, 'Mardi', '08:30:00', '10:00:00', 'BS.L301', 'Cours', 2),
(2, 1, 1, 1, 'Mardi', '10:15:00', '11:45:00', 'DS.LSS10', 'TP', 2),
(3, 1, 4, 1, 'Mercredi', '10:15:00', '11:45:00', 'DS.RDCSEM', 'TD', 2),
(4, 1, 8, 1, 'Mercredi', '12:00:00', '13:30:00', 'DS.S307', 'Cours', 2),
(5, 1, 6, 1, 'Lundi', '12:00:00', '13:30:00', 'DS.S402', 'Cours', 2),
(6, 1, 1, 1, 'Mardi', '13:45:00', '15:15:00', 'DS.LSS10', 'TP', 2),
(7, 1, 7, 1, 'Mercredi', '13:45:00', '15:15:00', 'BS.L202', 'TD', 2),
(8, 1, 9, 1, 'Jeudi', '13:45:00', '15:15:00', 'DS.S402', 'Cours', 2),
(9, 1, 5, 2, 'Vendredi', '08:30:00', '10:00:00', 'DS.S201', 'Cours', 2),
(10, 1, 2, 2, 'Samedi', '08:30:00', '10:00:00', 'BS.L301', 'TP', 2);

-- --------------------------------------------------------

--
-- Structure de la table `enseignants`
--

CREATE TABLE `enseignants` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `departement_id` int(10) UNSIGNED NOT NULL,
  `grade` varchar(80) DEFAULT 'Enseignant',
  `specialite` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `enseignants`
--

INSERT INTO `enseignants` (`id`, `utilisateur_id`, `departement_id`, `grade`, `specialite`) VALUES
(1, 5, 1, 'Enseignant Principal', 'Développement Web & POO Java'),
(2, 6, 1, 'Maître de Conférences', 'Programmation Orientée Objet');

-- --------------------------------------------------------

--
-- Structure de la table `etudiants`
--

CREATE TABLE `etudiants` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `classe_id` int(10) UNSIGNED NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `statut` enum('actif','suspendu','diplome','exclu') NOT NULL DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `etudiants`
--

INSERT INTO `etudiants` (`id`, `utilisateur_id`, `classe_id`, `date_naissance`, `adresse`, `telephone`, `statut`) VALUES
(1, 1, 1, '2002-05-14', NULL, '22345678', 'actif'),
(2, 2, 1, '2003-02-20', NULL, '55443322', 'actif'),
(3, 3, 3, '2001-11-08', NULL, '98765432', 'actif'),
(4, 4, 3, '2002-07-31', NULL, '12121212', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `filieres`
--

CREATE TABLE `filieres` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `filieres`
--

INSERT INTO `filieres` (`id`, `nom`, `code`) VALUES
(1, 'Technologies de l\'Information et de la Communication', 'TIC'),
(2, 'Génie Logiciel', 'GL'),
(3, 'Réseaux & Télécommunications', 'RT');

-- --------------------------------------------------------

--
-- Structure de la table `finances`
--

CREATE TABLE `finances` (
  `id` int(10) UNSIGNED NOT NULL,
  `etudiant_id` int(10) UNSIGNED NOT NULL,
  `tranche` tinyint(3) UNSIGNED NOT NULL,
  `montant` decimal(10,3) NOT NULL,
  `date_echeance` date NOT NULL,
  `date_paiement` date DEFAULT NULL,
  `statut` enum('en_attente','payee','en_retard','exonere') NOT NULL DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `finances`
--

INSERT INTO `finances` (`id`, `etudiant_id`, `tranche`, `montant`, `date_echeance`, `date_paiement`, `statut`) VALUES
(1, 1, 1, 1200.000, '2025-09-15', '2025-09-10', 'payee'),
(2, 1, 2, 1200.000, '2025-12-15', NULL, 'en_attente'),
(3, 1, 3, 1200.000, '2026-03-15', NULL, 'en_attente'),
(4, 2, 1, 1200.000, '2025-09-15', '2025-09-12', 'payee'),
(5, 2, 2, 1200.000, '2025-12-15', '2025-12-20', 'payee'),
(6, 2, 3, 1200.000, '2026-03-15', NULL, 'en_attente'),
(7, 3, 1, 1200.000, '2025-09-15', '2025-09-14', 'payee'),
(8, 3, 2, 1200.000, '2025-12-15', NULL, 'en_retard'),
(9, 3, 3, 1200.000, '2026-03-15', NULL, 'en_attente');

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

CREATE TABLE `matieres` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(150) NOT NULL,
  `code` varchar(20) NOT NULL,
  `filiere_id` int(10) UNSIGNED NOT NULL,
  `credits` tinyint(3) UNSIGNED DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `nom`, `code`, `filiere_id`, `credits`) VALUES
(1, 'Programmation Web 2', 'WEB2', 1, 4),
(2, 'Programmation Orientée Objet Java', 'POO', 1, 4),
(3, 'Algorithmique Avancée', 'ALGO', 1, 3),
(4, 'Graphes et Optimisation', 'GRAPH', 1, 3),
(5, 'Outils Mathématiques Ingénieur', 'MATH', 1, 3),
(6, 'Théorie des Langages', 'THL', 1, 3),
(7, 'Administration des Services', 'ADM', 1, 3),
(8, 'Modélisation Objet', 'UML', 2, 3),
(9, 'Projet d\'Intégration', 'PROJ', 1, 4),
(10, 'Électromagnétisme', 'EM', 3, 3),
(11, 'Anglais 4', 'ANG4', 1, 2),
(12, 'Français 4', 'FR4', 1, 2),
(13, 'Développement Web HTML/CSS/JS', 'WEB1', 1, 4);

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `expediteur_id` int(10) UNSIGNED NOT NULL,
  `destinataire_role` enum('etudiants','enseignants','scolarite','admin','tous') NOT NULL,
  `classe_id` int(10) UNSIGNED DEFAULT NULL,
  `objet` varchar(200) NOT NULL,
  `contenu` text NOT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_envoi` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `expediteur_id`, `destinataire_role`, `classe_id`, `objet`, `contenu`, `lu`, `date_envoi`) VALUES
(1, 5, 'etudiants', 1, 'Rattrapage séance Prog Web', 'Bonjour à tous, la séance annulée du 15/04 sera rattrapée le Samedi 20/04 à 09h00 en salle DS.LSS10.', 0, '2026-05-11 15:44:52'),
(2, 9, 'tous', NULL, 'Calendrier des examens publié', 'Le calendrier des examens du S2 est maintenant disponible sur le portail.', 0, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

CREATE TABLE `notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `etudiant_id` int(10) UNSIGNED NOT NULL,
  `matiere_id` int(10) UNSIGNED NOT NULL,
  `cc` decimal(5,2) DEFAULT NULL,
  `ds` decimal(5,2) DEFAULT NULL,
  `examen` decimal(5,2) DEFAULT NULL,
  `moyenne` decimal(5,2) GENERATED ALWAYS AS (case when `cc` is not null and `ds` is not null and `examen` is not null then round(`cc` * 0.20 + `ds` * 0.30 + `examen` * 0.50,2) else NULL end) STORED,
  `statut` enum('en_attente','validee','insuffisant','rattrapage') NOT NULL DEFAULT 'en_attente',
  `semestre` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `annee_univ` varchar(10) NOT NULL DEFAULT '2025/2026',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `etudiant_id`, `matiere_id`, `cc`, `ds`, `examen`, `statut`, `semestre`, `annee_univ`, `updated_at`) VALUES
(1, 1, 13, 18.00, 17.00, 19.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52'),
(2, 1, 3, 15.00, 14.50, 16.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52'),
(3, 1, 1, 14.00, 13.00, NULL, 'en_attente', 2, '2025/2026', '2026-05-11 15:44:52'),
(4, 2, 13, 16.00, 15.00, 17.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52'),
(5, 2, 3, 12.00, 11.00, 13.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52'),
(6, 3, 3, 8.00, 7.00, 9.00, 'insuffisant', 1, '2025/2026', '2026-05-11 15:44:52'),
(7, 3, 13, 10.00, 10.00, 11.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52'),
(8, 4, 13, 14.00, 13.00, 15.00, 'validee', 1, '2025/2026', '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `titre` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('alerte','info','annonce','urgence') NOT NULL DEFAULT 'info',
  `destinataires` enum('tous','etudiants','enseignants','scolarite') NOT NULL DEFAULT 'tous',
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `envoye_le` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `titre`, `message`, `type`, `destinataires`, `auteur_id`, `envoye_le`) VALUES
(1, 'Clôture des notes S1', 'Veuillez finaliser la saisie de toutes les notes avant le 20 Avril.', 'alerte', 'enseignants', 9, '2026-05-11 15:44:52'),
(2, 'Fermeture bibliothèque', 'Fermeture vendredi pour inventaire.', 'info', 'tous', 9, '2026-05-11 15:44:52'),
(3, 'EPI IT Day', 'Inscrivez-vous aux workshops de la journée IT Day.', 'annonce', 'etudiants', 9, '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `reclamations`
--

CREATE TABLE `reclamations` (
  `id` int(10) UNSIGNED NOT NULL,
  `auteur_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(80) NOT NULL,
  `details` text NOT NULL,
  `statut` enum('en_attente','en_cours','traitee','rejetee') NOT NULL DEFAULT 'en_attente',
  `reponse` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reclamations`
--

INSERT INTO `reclamations` (`id`, `auteur_id`, `type`, `details`, `statut`, `reponse`, `created_at`, `updated_at`) VALUES
(1, 1, 'Erreur sur une note', 'La note de mon TP Algorithmique est incorrecte. Le professeur m\'a confirmé 16/20 mais la base affiche 12.', 'traitee', 'La note a été corrigée après vérification auprès du professeur.', '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(2, 1, 'Infrastructure', 'Le climatiseur de la salle 5 est en panne depuis 2 semaines.', 'en_cours', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(3, 2, 'Problème emploi du temps', 'Conflit de créneaux le mardi entre Prog Web et POO.', 'en_attente', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52');

-- --------------------------------------------------------

--
-- Structure de la table `scolarite_personnel`
--

CREATE TABLE `scolarite_personnel` (
  `id` int(10) UNSIGNED NOT NULL,
  `utilisateur_id` int(10) UNSIGNED NOT NULL,
  `poste` varchar(100) DEFAULT 'Agent de scolarité'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `scolarite_personnel`
--

INSERT INTO `scolarite_personnel` (`id`, `utilisateur_id`, `poste`) VALUES
(1, 7, 'Responsable Scolarité'),
(2, 8, 'Agent Scolarité');

-- --------------------------------------------------------

--
-- Structure de la table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(10) UNSIGNED NOT NULL,
  `prenom` varchar(80) NOT NULL,
  `nom` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `cin_matricule` varchar(30) NOT NULL,
  `role` enum('etudiant','enseignant','scolarite','admin') NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `statut` enum('actif','suspendu','inactif') NOT NULL DEFAULT 'actif',
  `photo_url` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `prenom`, `nom`, `email`, `cin_matricule`, `role`, `mot_de_passe`, `statut`, `photo_url`, `created_at`, `updated_at`) VALUES
(1, 'Foulen', 'Ben Foulen', 'foulen@epi.ens.tn', '12345678', 'etudiant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(2, 'Sarah', 'Mansour', 'sarah@epi.ens.tn', '87654321', 'etudiant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(3, 'Ahmed', 'Trabelsi', 'ahmed@epi.ens.tn', '11223344', 'etudiant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(4, 'Sana', 'Mejri', 'sana@epi.ens.tn', '55667788', 'etudiant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(5, 'Yassine', 'MILED', 'y.miled@epi.ens.tn', 'ENS-9087', 'enseignant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(6, 'Amira', 'FOUGHALI', 'a.foughali@epi.ens.tn', 'ENS-0045', 'enseignant', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(7, 'Sara', 'KHELIL', 's.khelil@epi.ens.tn', 'SCO-0045', 'scolarite', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(8, 'Amani', 'HANI', 'amani.hani@epi.ens.tn', 'SCO-0046', 'scolarite', '$2y$12$8jxmUzblFSUwJAlMLrZEPONaFKDJWxWZfbhTj7LScGBHk0U7.DPJW', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:44:52'),
(9, 'EPI', 'Admin', 'admin@epi.tn', 'ADMIN-001', 'admin', '', 'actif', NULL, '2026-05-11 15:44:52', '2026-05-11 15:54:31');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `etudiant_id` (`etudiant_id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`);

--
-- Index pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `alertes_pedagogiques`
--
ALTER TABLE `alertes_pedagogiques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `classe_id` (`classe_id`);

--
-- Index pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auteur_id` (`auteur_id`);

--
-- Index pour la table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- Index pour la table `cours_supports`
--
ALTER TABLE `cours_supports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`);

--
-- Index pour la table `demandes_documents`
--
ALTER TABLE `demandes_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `etudiant_id` (`etudiant_id`),
  ADD KEY `traitee_par` (`traitee_par`);

--
-- Index pour la table `departements`
--
ALTER TABLE `departements`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `classe_id` (`classe_id`),
  ADD KEY `matiere_id` (`matiere_id`),
  ADD KEY `enseignant_id` (`enseignant_id`);

--
-- Index pour la table `enseignants`
--
ALTER TABLE `enseignants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `departement_id` (`departement_id`);

--
-- Index pour la table `etudiants`
--
ALTER TABLE `etudiants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `classe_id` (`classe_id`);

--
-- Index pour la table `filieres`
--
ALTER TABLE `filieres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `finances`
--
ALTER TABLE `finances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `etudiant_id` (`etudiant_id`);

--
-- Index pour la table `matieres`
--
ALTER TABLE `matieres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `filiere_id` (`filiere_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expediteur_id` (`expediteur_id`),
  ADD KEY `classe_id` (`classe_id`);

--
-- Index pour la table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_note` (`etudiant_id`,`matiere_id`,`semestre`,`annee_univ`),
  ADD KEY `matiere_id` (`matiere_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auteur_id` (`auteur_id`);

--
-- Index pour la table `reclamations`
--
ALTER TABLE `reclamations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auteur_id` (`auteur_id`);

--
-- Index pour la table `scolarite_personnel`
--
ALTER TABLE `scolarite_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cin_matricule` (`cin_matricule`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `alertes_pedagogiques`
--
ALTER TABLE `alertes_pedagogiques`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `annonces`
--
ALTER TABLE `annonces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `cours_supports`
--
ALTER TABLE `cours_supports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `demandes_documents`
--
ALTER TABLE `demandes_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `departements`
--
ALTER TABLE `departements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `enseignants`
--
ALTER TABLE `enseignants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `etudiants`
--
ALTER TABLE `etudiants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `filieres`
--
ALTER TABLE `filieres`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `finances`
--
ALTER TABLE `finances`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `matieres`
--
ALTER TABLE `matieres`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `reclamations`
--
ALTER TABLE `reclamations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `scolarite_personnel`
--
ALTER TABLE `scolarite_personnel`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `absences_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absences_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `alertes_pedagogiques`
--
ALTER TABLE `alertes_pedagogiques`
  ADD CONSTRAINT `alertes_pedagogiques_ibfk_1` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `alertes_pedagogiques_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `annonces`
--
ALTER TABLE `annonces`
  ADD CONSTRAINT `annonces_ibfk_1` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`);

--
-- Contraintes pour la table `cours_supports`
--
ALTER TABLE `cours_supports`
  ADD CONSTRAINT `cours_supports_ibfk_1` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cours_supports_ibfk_2` FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demandes_documents`
--
ALTER TABLE `demandes_documents`
  ADD CONSTRAINT `demandes_documents_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_documents_ibfk_2` FOREIGN KEY (`traitee_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `emploi_temps`
--
ALTER TABLE `emploi_temps`
  ADD CONSTRAINT `emploi_temps_ibfk_1` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `emploi_temps_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `emploi_temps_ibfk_3` FOREIGN KEY (`enseignant_id`) REFERENCES `enseignants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `enseignants`
--
ALTER TABLE `enseignants`
  ADD CONSTRAINT `enseignants_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enseignants_ibfk_2` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`);

--
-- Contraintes pour la table `etudiants`
--
ALTER TABLE `etudiants`
  ADD CONSTRAINT `etudiants_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `etudiants_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`);

--
-- Contraintes pour la table `finances`
--
ALTER TABLE `finances`
  ADD CONSTRAINT `finances_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiants` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `matieres`
--
ALTER TABLE `matieres`
  ADD CONSTRAINT `matieres_ibfk_1` FOREIGN KEY (`filiere_id`) REFERENCES `filieres` (`id`);

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`expediteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reclamations`
--
ALTER TABLE `reclamations`
  ADD CONSTRAINT `reclamations_ibfk_1` FOREIGN KEY (`auteur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `scolarite_personnel`
--
ALTER TABLE `scolarite_personnel`
  ADD CONSTRAINT `scolarite_personnel_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ------------------------------------------------------------
-- Ajout (migration 001) : table login_attempts
-- ------------------------------------------------------------
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
