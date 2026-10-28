-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 06:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `factscanai`
--

-- --------------------------------------------------------

--
-- Table structure for table `analysis_feedback`
--

CREATE TABLE `analysis_feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `verification_id` int(11) NOT NULL,
  `feedback_type` enum('AGREE','DISAGREE','INACCURATE_SOURCE','OTHER') NOT NULL DEFAULT 'AGREE',
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analysis_feedback`
--

INSERT INTO `analysis_feedback` (`id`, `user_id`, `verification_id`, `feedback_type`, `comment`, `created_at`) VALUES
(1, 1, 1, 'AGREE', 'Accurate analysis, this hoax was debunked.', '2026-10-28 03:14:01');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_verifications`
--

CREATE TABLE `saved_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_verifications`
--

INSERT INTO `saved_verifications` (`id`, `user_id`, `verification_id`, `saved_at`) VALUES
(1, 1, 1, '2026-10-28 03:13:45');

-- --------------------------------------------------------

--
-- Table structure for table `trusted_apps`
--

CREATE TABLE `trusted_apps` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `trust_rating` decimal(3,2) DEFAULT 5.00,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trusted_apps`
--

INSERT INTO `trusted_apps` (`id`, `name`, `category`, `url`, `icon_url`, `description`, `trust_rating`, `is_active`) VALUES
(1, 'Snopes Fact Checker', 'Fact Checking', 'https://www.snopes.com', 'https://www.snopes.com/favicon.ico', 'The definitive internet reference source for urban legends, folklore, and debunking misinformation.', 4.90, 1),
(2, 'PolitiFact', 'Political & News', 'https://www.politifact.com', 'https://www.politifact.com/favicon.ico', 'Pulitzer Prize-winning fact-checking website rating the accuracy of public officials and news.', 4.85, 1),
(3, 'FactCheck.org', 'Public Policy', 'https://www.factcheck.org', 'https://www.factcheck.org/favicon.ico', 'A nonpartisan, nonprofit consumer advocate for voters monitoring factual accuracy in major media.', 4.92, 1),
(4, 'Reuters Fact Check', 'International News', 'https://www.reuters.com/fact-check', 'https://www.reuters.com/favicon.ico', 'Global fact-checking unit identifying and debunking claims shared across digital platforms.', 4.95, 1),
(5, 'Associated Press News Fact Check', 'Wire Agency', 'https://apnews.com/hub/ap-fact-check', 'https://apnews.com/favicon.ico', 'AP journalists across the globe verifying and providing context on trending news stories.', 4.94, 1),
(6, 'BBC Verify', 'Broadcasting', 'https://www.bbc.com/news/reality_check', 'https://www.bbc.com/favicon.ico', 'BBC investigative unit transparently assessing facts, video evidence, and digital media.', 4.88, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `password`, `created_at`) VALUES
(1, 'yuvadhan', 'yuva3@gmail.com', '$2y$10$oNU4rC4Yg668q8hgrfAap.172Js56Hnh096N/5skqyPKzg.cDLEOS', '2026-10-26 04:49:10'),
(4, 'yuvadhan', 'yuva3t@gmail.com', '$2y$10$5U9jGIxzexNXhzeUd1pFOOPiOytL9ANukNus/TYVz.yo6WrhjShTC', '2026-10-26 06:41:56'),
(5, 'ronaldo Updated', 'daksh12@gmail.com', '$2y$10$uhF/ZTb0k179gaqKyolxUOob7qZ.JhwO7d04.ZR4PPcwcO9AHMHVS', '2026-10-27 05:30:57'),
(6, 'Test Auditor', 'auditor_test@factscan.ai', '$2y$10$qhk2K/LFIGpdeqxkynWLteSnIeGlcB/PUuJA6C3uy4lD10/3bwZ8.', '2026-10-27 08:54:36'),
(9, 'Alex Morgan Updated', 'testuser_53219@factscan.ai', '$2y$10$czxv/RgJK3zv4KbLNjSZSOPfN90ScpPC9Vo6GgtAU1CmdZ3A5Kgh2', '2026-10-27 09:07:27'),
(14, 'Alex Morgan', 'user_16891@factscan.ai', '$2y$10$t43ilSJ8nYZ7WehJYVAdZeekfKpyqYFQIRI1w5SNst3GeZgALRdpe', '2026-10-28 03:41:49'),
(15, 'ronaldo', 'ronaldo@gmail.com', '$2y$10$QaceE13SxeNCJ0siuL/wjugJy0.PuTfxFQNHsvbM.W0V83BUGaaX6', '2026-10-28 03:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `language` varchar(20) DEFAULT 'English',
  `accent_color` varchar(20) DEFAULT 'blue',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`user_id`, `full_name`, `email`, `language`, `accent_color`, `updated_at`) VALUES
(1, 'yuvadhan', 'yuva3@gmail.com', 'English', 'blue', '2026-10-27 08:35:24'),
(4, 'yuvadhan', 'yuva3t@gmail.com', 'English', 'blue', '2026-10-27 08:35:24'),
(5, 'ronaldo Updated', 'daksh12@gmail.com', 'English', 'blue', '2026-10-28 03:47:17'),
(6, 'Test Auditor', 'auditor_test@factscan.ai', 'English', 'purple', '2026-10-27 08:54:36'),
(9, 'Alex Morgan Updated', 'testuser_53219@factscan.ai', 'Spanish', 'emerald', '2026-10-27 09:13:09'),
(14, 'Alex Morgan', 'user_16891@factscan.ai', 'English', 'blue', '2026-10-28 03:41:49'),
(15, 'ronaldo', 'ronaldo@gmail.com', 'English', 'blue', '2026-10-28 03:43:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `strict_verification` tinyint(1) DEFAULT 1,
  `auto_save_history` tinyint(1) DEFAULT 1,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `custom_gemini_api_key` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `strict_verification`, `auto_save_history`, `notifications_enabled`, `custom_gemini_api_key`) VALUES
(1, 1, 1, 1, NULL),
(4, 1, 1, 1, NULL),
(5, 1, 1, 1, NULL),
(6, 1, 1, 1, NULL),
(9, 1, 0, 1, NULL),
(14, 1, 1, 1, NULL),
(15, 1, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_history`
--

CREATE TABLE `verification_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `input_type` enum('URL','TEXT_CLAIM','DOCUMENT_UPLOAD') NOT NULL DEFAULT 'TEXT_CLAIM',
  `claim_input` text NOT NULL,
  `headline` varchar(255) NOT NULL DEFAULT '',
  `publisher` varchar(100) DEFAULT 'Web Analysis',
  `is_real` tinyint(1) NOT NULL DEFAULT 1,
  `confidence` int(11) NOT NULL DEFAULT 90,
  `summary` text NOT NULL,
  `warning_signals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`warning_signals`)),
  `verified_sources` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`verified_sources`)),
  `raw_gemini_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_history`
--

INSERT INTO `verification_history` (`id`, `user_id`, `input_type`, `claim_input`, `headline`, `publisher`, `is_real`, `confidence`, `summary`, `warning_signals`, `verified_sources`, `raw_gemini_response`, `created_at`) VALUES
(1, 1, 'TEXT_CLAIM', 'NASA discovers dark eclipse covering earth for 3 days', 'NASA discovers dark eclipse covering earth for 3 days', 'Submitted News Claim', 0, 91, 'The claim contains unverified assertions without corroborating announcements from official institutions or credible registries.', '[\"No official press release or government advisory found matching this claim\",\"Sensational urgency-inducing language without traceable evidence\",\"Unsubstantiated viral message circulating across unofficial channels\"]', '[{\"title\":\"International Fact-Checking Network (IFCN)\",\"url\":\"https:\\/\\/www.poynter.org\\/ifcn\\/\",\"description\":\"Alert advisory: viral claim flagged for misleading or unsubstantiated content\"}]', NULL, '2026-10-28 03:13:25'),
(2, 5, 'TEXT_CLAIM', 'NASA confirms asteroid collision predicted for next month causing 3 days of complete darkness', 'NASA confirms asteroid collision predicted for next month causing 3 days...', 'Submitted News Claim', 0, 91, 'The claim contains unverified assertions without corroborating announcements from official institutions or credible registries.', '[\"No official press release or government advisory found matching this claim\",\"Sensational urgency-inducing language without traceable evidence\",\"Unsubstantiated viral message circulating across unofficial channels\"]', '[{\"title\":\"International Fact-Checking Network (IFCN)\",\"url\":\"https:\\/\\/www.poynter.org\\/ifcn\\/\",\"description\":\"Alert advisory: viral claim flagged for misleading or unsubstantiated content\"}]', NULL, '2026-10-28 03:48:20'),
(3, 5, 'TEXT_CLAIM', 'ronaldo good', 'ronaldo good', 'Submitted News Claim', 1, 94, 'The key statements align with verified press releases, primary documentation, and reputable digital records.', '[\"Identifiable source and publication context identified\",\"Findings independently corroborated by reputable research institutions\",\"Scope and metrics match official published records\"]', '[{\"title\":\"Reuters Fact Check Registry\",\"url\":\"https:\\/\\/www.reuters.com\\/fact-check\",\"description\":\"Independent global news monitoring and primary source verification\"},{\"title\":\"Associated Press News Investigations\",\"url\":\"https:\\/\\/apnews.com\\/hub\\/ap-fact-check\",\"description\":\"Verified news wire archival records and correspondent reports\"}]', NULL, '2026-10-28 03:48:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analysis_feedback`
--
ALTER TABLE `analysis_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_user` (`user_id`),
  ADD KEY `fk_feedback_verification` (`verification_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `saved_verifications`
--
ALTER TABLE `saved_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_verification` (`user_id`,`verification_id`),
  ADD KEY `fk_saved_verification` (`verification_id`);

--
-- Indexes for table `trusted_apps`
--
ALTER TABLE `trusted_apps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `verification_history`
--
ALTER TABLE `verification_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analysis_feedback`
--
ALTER TABLE `analysis_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_verifications`
--
ALTER TABLE `saved_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `trusted_apps`
--
ALTER TABLE `trusted_apps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `verification_history`
--
ALTER TABLE `verification_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analysis_feedback`
--
ALTER TABLE `analysis_feedback`
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_verification` FOREIGN KEY (`verification_id`) REFERENCES `verification_history` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_verifications`
--
ALTER TABLE `saved_verifications`
  ADD CONSTRAINT `fk_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_saved_verification` FOREIGN KEY (`verification_id`) REFERENCES `verification_history` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
