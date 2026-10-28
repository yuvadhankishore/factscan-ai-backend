-- FactScan AI Complete Database Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.4+ (XAMPP)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `factscanai` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `factscanai`;

-- --------------------------------------------------------
-- 1. Table structure for table `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 2. Table structure for table `user_profiles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `language` varchar(20) DEFAULT 'English',
  `accent_color` varchar(20) DEFAULT 'blue',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `user_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` int(11) NOT NULL,
  `strict_verification` tinyint(1) DEFAULT 1,
  `auto_save_history` tinyint(1) DEFAULT 1,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `custom_gemini_api_key` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `verification_history`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `verification_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `input_type` enum('URL','TEXT_CLAIM','DOCUMENT_UPLOAD') NOT NULL DEFAULT 'TEXT_CLAIM',
  `claim_input` text NOT NULL,
  `headline` varchar(255) NOT NULL,
  `publisher` varchar(100) DEFAULT 'Web Analysis',
  `is_real` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 for True/Real, 0 for Fake/Misleading',
  `confidence` int(11) NOT NULL DEFAULT 90 COMMENT 'Percentage 0-100',
  `summary` text NOT NULL,
  `warning_signals` json DEFAULT NULL COMMENT 'Array of detected warning flags',
  `verified_sources` json DEFAULT NULL COMMENT 'Array of source objects [{title, url, description}]',
  `raw_gemini_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_history_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_verification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 5. Table structure for table `saved_verifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `verification_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_verification` (`user_id`, `verification_id`),
  KEY `fk_saved_verification` (`verification_id`),
  CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_verification` FOREIGN KEY (`verification_id`) REFERENCES `verification_history` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 6. Table structure for table `trusted_apps`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `trusted_apps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `trust_rating` decimal(3,2) DEFAULT 5.00,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 7. Table structure for table `analysis_feedback`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `analysis_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `verification_id` int(11) NOT NULL,
  `feedback_type` enum('AGREE','DISAGREE','INACCURATE_SOURCE','OTHER') NOT NULL DEFAULT 'AGREE',
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_feedback_user` (`user_id`),
  KEY `fk_feedback_verification` (`verification_id`),
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_feedback_verification` FOREIGN KEY (`verification_id`) REFERENCES `verification_history` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- 8. Table structure for table `password_resets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Seed Data for `trusted_apps` (Genuine Sources Directory)
-- --------------------------------------------------------
INSERT INTO `trusted_apps` (`id`, `name`, `category`, `url`, `icon_url`, `description`, `trust_rating`, `is_active`) VALUES
(1, 'Snopes Fact Checker', 'Fact Checking', 'https://www.snopes.com', 'https://www.snopes.com/favicon.ico', 'The definitive internet reference source for urban legends, folklore, and debunking misinformation.', 4.90, 1),
(2, 'PolitiFact', 'Political & News', 'https://www.politifact.com', 'https://www.politifact.com/favicon.ico', 'Pulitzer Prize-winning fact-checking website rating the accuracy of public officials and news.', 4.85, 1),
(3, 'FactCheck.org', 'Public Policy', 'https://www.factcheck.org', 'https://www.factcheck.org/favicon.ico', 'A nonpartisan, nonprofit consumer advocate for voters monitoring factual accuracy in major media.', 4.92, 1),
(4, 'Reuters Fact Check', 'International News', 'https://www.reuters.com/fact-check', 'https://www.reuters.com/favicon.ico', 'Global fact-checking unit identifying and debunking claims shared across digital platforms.', 4.95, 1),
(5, 'Associated Press News Fact Check', 'Wire Agency', 'https://apnews.com/hub/ap-fact-check', 'https://apnews.com/favicon.ico', 'AP journalists across the globe verifying and providing context on trending news stories.', 4.94, 1),
(6, 'BBC Verify', 'Broadcasting', 'https://www.bbc.com/news/reality_check', 'https://www.bbc.com/favicon.ico', 'BBC investigative unit transparently assessing facts, video evidence, and digital media.', 4.88, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), description = VALUES(description);

COMMIT;
