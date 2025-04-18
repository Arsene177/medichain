-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : dim. 30 mars 2025 à 15:05
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `medichain`
--

-- --------------------------------------------------------

--
-- Structure de la table `access_control`
--

DROP TABLE IF EXISTS `access_control`;
CREATE TABLE IF NOT EXISTS `access_control` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `status` enum('allowed','suspended') DEFAULT 'allowed',
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_access` (`patient_id`,`doctor_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `access_control`
--

INSERT INTO `access_control` (`id`, `patient_id`, `doctor_id`, `status`, `reason`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'suspended', '', '2025-03-25 01:51:48', '2025-03-25 08:49:12');

-- --------------------------------------------------------

--
-- Structure de la table `access_logs`
--

DROP TABLE IF EXISTS `access_logs`;
CREATE TABLE IF NOT EXISTS `access_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `record_id` int NOT NULL,
  `accessed_by` int NOT NULL,
  `access_type` enum('view','edit') NOT NULL,
  `access_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_id` (`record_id`),
  KEY `accessed_by` (`accessed_by`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `access_logs`
--

INSERT INTO `access_logs` (`id`, `record_id`, `accessed_by`, `access_type`, `access_time`, `ip_address`) VALUES
(1, 1, 2, 'view', '2025-03-23 14:08:14', '192.168.1.1'),
(2, 2, 3, 'view', '2025-03-23 14:08:14', '192.168.1.2'),
(3, 1, 2, 'view', '2025-03-25 01:45:14', '::1'),
(4, 3, 2, 'edit', '2025-03-25 01:45:41', NULL),
(5, 3, 2, 'view', '2025-03-25 01:45:43', '::1'),
(6, 3, 3, 'view', '2025-03-25 11:41:45', '::1');

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(1, 1, 1, '2024-03-25', '09:00:00', 'scheduled', 'Regular checkup', '2025-03-23 14:08:14'),
(2, 1, 1, '2024-03-26', '14:30:00', 'scheduled', 'Follow-up visit', '2025-03-23 14:08:14'),
(3, 2, 2, '2024-03-27', '10:00:00', 'scheduled', 'Initial consultation', '2025-03-23 14:08:14'),
(4, 1, 2, '2025-03-27', '13:30:00', 'scheduled', '', '2025-03-24 12:44:46');

-- --------------------------------------------------------

--
-- Structure de la table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `hospital` varchar(100) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('M','F','Other') NOT NULL,
  `emergency_contact` varchar(20) NOT NULL,
  `id_type` varchar(50) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `years_of_experience` int NOT NULL,
  `qualification` varchar(100) NOT NULL,
  `certification` varchar(100) NOT NULL,
  `consultation_fee` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `address` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `full_name`, `email`, `phone_number`, `specialization`, `hospital`, `license_number`, `date_of_birth`, `gender`, `emergency_contact`, `id_type`, `id_number`, `years_of_experience`, `qualification`, `certification`, `consultation_fee`, `status`, `rejection_reason`, `created_at`, `address`) VALUES
(1, 8, 'Djonkoun Arsene', 'carimecarime@gmail.com', '68789', 'GENCO', 'GENHOS', 'IUGBK897', '2025-03-18', 'M', 'KJN', 'National ID', '3442', 65, 'PRO', 'NURS', 7500.00, 'rejected', 'no sufficient documents', '2025-03-30 11:58:53', 0),
(2, 9, 'boss boss', 'arsenedjonkoun0@gmail.com', '679908', 'doctor', 'gen', 'oihç_87', '2025-03-19', 'M', 'ytjhg', 'National ID', '68908Y', 5, 'hello', 'doctor', 6.00, 'rejected', 'no doctor', '2025-03-30 12:13:37', 0);

-- --------------------------------------------------------

--
-- Structure de la table `email_settings`
--

DROP TABLE IF EXISTS `email_settings`;
CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int NOT NULL DEFAULT '587',
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `smtp_encryption` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `email_settings`
--

INSERT INTO `email_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `created_at`, `updated_at`) VALUES
(1, 'smtp.gmail.com', 587, '', '', 'tls', '2025-03-25 20:55:35', '2025-03-25 20:55:35');

-- --------------------------------------------------------

--
-- Structure de la table `medical_conditions`
--

DROP TABLE IF EXISTS `medical_conditions`;
CREATE TABLE IF NOT EXISTS `medical_conditions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text,
  `is_contagious` tinyint(1) DEFAULT '0',
  `incubation_period` varchar(100) DEFAULT NULL,
  `symptoms` text,
  `treatment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `medical_conditions`
--

INSERT INTO `medical_conditions` (`id`, `name`, `category`, `description`, `is_contagious`, `incubation_period`, `symptoms`, `treatment`, `created_at`, `updated_at`) VALUES
(1, 'Hypertension', 'Cardiovascular', 'High blood pressure condition', 0, 'N/A', 'Headaches, shortness of breath, nosebleeds', 'Lifestyle changes, medication', '2025-03-30 15:05:27', '2025-03-30 15:05:27'),
(2, 'Diabetes Type 2', 'Endocrine', 'Chronic condition affecting blood sugar regulation', 0, 'N/A', 'Increased thirst, frequent urination, hunger, fatigue', 'Diet control, medication, exercise', '2025-03-30 15:05:27', '2025-03-30 15:05:27'),
(3, 'COVID-19', 'Infectious Disease', 'Coronavirus disease 2019', 1, '2-14 days', 'Fever, cough, shortness of breath, loss of taste/smell', 'Rest, isolation, medical treatment', '2025-03-30 15:05:27', '2025-03-30 15:05:27'),
(4, 'Asthma', 'Respiratory', 'Chronic respiratory condition', 0, 'N/A', 'Wheezing, shortness of breath, chest tightness', 'Inhalers, medication, avoiding triggers', '2025-03-30 15:05:27', '2025-03-30 15:05:27'),
(5, 'Malaria', 'Infectious Disease', 'Mosquito-borne disease', 1, '7-30 days', 'Fever, chills, headache, muscle pain', 'Antimalarial medication, rest, fluids', '2025-03-30 15:05:27', '2025-03-30 15:05:27');

-- --------------------------------------------------------

--
-- Structure de la table `medical_records`
--

DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE IF NOT EXISTS `medical_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `entry_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `entry_type` enum('regular','emergency','prescription') NOT NULL,
  `content` text NOT NULL,
  `doctor_signature` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `medical_records`
--

INSERT INTO `medical_records` (`id`, `patient_id`, `doctor_id`, `entry_date`, `entry_type`, `content`, `doctor_signature`, `created_at`) VALUES
(1, 1, 1, '2025-03-23 14:08:14', 'regular', 'Initial checkup completed. Patient in good health.', 'Dr. John Doe, Cardiologist', '2025-03-23 14:08:14'),
(2, 2, 2, '2025-03-23 14:08:14', 'prescription', 'Prescribed antibiotics for infection.', 'Dr. Jane Smith, Pediatrician', '2025-03-23 14:08:14'),
(3, 1, 2, '2025-03-25 01:45:41', 'emergency', 'Arsene ze boy', 'Dr. John Doe | Central Hospital | +237 123456789', '2025-03-25 01:45:41');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('M','F','Other') NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `medical_record_id` varchar(20) NOT NULL,
  `registered_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_record_id` (`medical_record_id`),
  KEY `user_id` (`user_id`),
  KEY `registered_by` (`registered_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `full_name`, `date_of_birth`, `gender`, `phone_number`, `email`, `address`, `medical_record_id`, `registered_by`, `created_at`) VALUES
(1, 4, 'Alice Johnson', '1990-05-15', 'F', '+237 111222333', 'alice@example.com', '123 Main St, Douala', 'MR001', 1, '2025-03-23 14:08:14'),
(2, 5, 'Bob Wilson', '1985-08-20', 'M', '+237 777888999', 'bob@example.com', '456 Park Ave, Yaounde', 'MR002', 2, '2025-03-23 14:08:14');

-- --------------------------------------------------------

--
-- Structure de la table `record_access`
--

DROP TABLE IF EXISTS `record_access`;
CREATE TABLE IF NOT EXISTS `record_access` (
  `id` int NOT NULL AUTO_INCREMENT,
  `record_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `access_level` enum('read','write') NOT NULL,
  `granted_by` int NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `record_id` (`record_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `granted_by` (`granted_by`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `record_access`
--

INSERT INTO `record_access` (`id`, `record_id`, `doctor_id`, `access_level`, `granted_by`, `granted_at`, `expires_at`) VALUES
(1, 1, 2, 'read', 1, '2025-03-23 14:08:14', NULL),
(2, 2, 1, 'read', 1, '2025-03-23 14:08:14', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_duration` int NOT NULL DEFAULT '30',
  `max_appointments_per_day` int NOT NULL DEFAULT '20',
  `enable_waiting_list` tinyint(1) NOT NULL DEFAULT '1',
  `enable_email_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `system_settings`
--

INSERT INTO `system_settings` (`id`, `appointment_duration`, `max_appointments_per_day`, `enable_waiting_list`, `enable_email_notifications`, `created_at`, `updated_at`) VALUES
(1, 30, 20, 1, 1, '2025-03-25 20:55:35', '2025-03-25 20:55:35');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','patient') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `email` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `email`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-03-30 05:20:25', 0),
(8, 'Brayan1717', '$2y$10$9C32amGv0AMMozakF.97EuCsspsqnIHoEdwQLN4/qX0bhuwlADVgG', 'doctor', '2025-03-30 11:58:53', 0),
(9, 'Arsene', '$2y$10$WZB.boVWoWKjh0MvMFLAl.K4pHJeTKpFasjjiNgWFFReh.mMvPSmi', 'doctor', '2025-03-30 12:13:37', 0);

-- --------------------------------------------------------

--
-- Structure de la table `waiting_list`
--

DROP TABLE IF EXISTS `waiting_list`;
CREATE TABLE IF NOT EXISTS `waiting_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `reason` text NOT NULL,
  `status` enum('waiting','notified','scheduled','expired') DEFAULT 'waiting',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `waiting_list`
--

INSERT INTO `waiting_list` (`id`, `patient_id`, `doctor_id`, `preferred_date`, `preferred_time`, `reason`, `status`, `created_at`) VALUES
(1, 1, 2, '2024-03-25', '09:00:00', 'Consultation for new symptoms', 'waiting', '2025-03-23 14:08:14'),
(2, 2, 1, '2024-03-26', '11:00:00', 'Follow-up checkup', 'waiting', '2025-03-23 14:08:14');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
