-- FactScan AI - Knowledge Base & Reasoning Trail Database Schema
-- Provides indexed storage for verified facts, sovereign registries, and explanation chains

CREATE TABLE IF NOT EXISTS `factscan_knowledge_facts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `entity_name` VARCHAR(120) NOT NULL,
    `domain` VARCHAR(80) NOT NULL DEFAULT 'Geopolitics',
    `claim_pattern` VARCHAR(255) NOT NULL,
    `is_factual` TINYINT(1) NOT NULL DEFAULT 1,
    `official_title` VARCHAR(255) NOT NULL,
    `capital_or_context` VARCHAR(150) DEFAULT NULL,
    `subdivisions` VARCHAR(255) DEFAULT NULL,
    `primary_source` VARCHAR(255) NOT NULL,
    `debunk_rationale` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_entity` (`entity_name`),
    INDEX `idx_domain` (`domain`),
    INDEX `idx_factual` (`is_factual`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `factscan_reasoning_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `input_hash` CHAR(64) NOT NULL,
    `input_text` TEXT NOT NULL,
    `verdict` ENUM('REAL', 'FAKE', 'MISLEADING') NOT NULL DEFAULT 'REAL',
    `confidence` INT NOT NULL DEFAULT 90,
    `why_this_result` TEXT NOT NULL,
    `verdict_justification` TEXT NOT NULL,
    `reasoning_steps` JSON DEFAULT NULL,
    `citations` JSON DEFAULT NULL,
    `engine` VARCHAR(80) NOT NULL DEFAULT 'reasoning_engine',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hash` (`input_hash`),
    INDEX `idx_verdict` (`verdict`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed core geopolitical facts
INSERT INTO `factscan_knowledge_facts` (`entity_name`, `domain`, `claim_pattern`, `is_factual`, `official_title`, `capital_or_context`, `subdivisions`, `primary_source`, `debunk_rationale`) VALUES
('India', 'Geopolitics', 'India is a sovereign country', 1, 'Republic of India', 'New Delhi', '28 states and 8 union territories', 'United Nations Member Directory & Constitution Article 1', 'India is an independent sovereign democratic republic and UN member state.'),
('India', 'Geopolitics', 'India is a state', 0, 'Republic of India', 'New Delhi', '28 states and 8 union territories', 'United Nations Member Directory & Constitution Article 1', 'India is a sovereign country comprised of states, not itself a state of another nation.'),
('United States', 'Geopolitics', 'United States is a sovereign country', 1, 'United States of America', 'Washington, D.C.', '50 states and federal district', 'United Nations Member Directory', 'The US is an independent sovereign federal republic.'),
('California', 'Geopolitics', 'California is a state of United States', 1, 'State of California', 'Sacramento', '58 counties', 'US Constitution', 'California is a constituent state of the United States of America.'),
('California', 'Geopolitics', 'California is an independent sovereign country', 0, 'State of California', 'Sacramento', '58 counties', 'US Constitution & UN Registry', 'California is a US state without independent sovereign UN representation.'),
('France', 'Geopolitics', 'France is a sovereign country', 1, 'French Republic', 'Paris', '18 regions', 'UN Security Council & French Constitution', 'France is an independent sovereign state in Europe.'),
('Japan', 'Geopolitics', 'Japan is a sovereign country', 1, 'Japan', 'Tokyo', '47 prefectures', 'UN Member Directory', 'Japan is an independent sovereign nation in East Asia.'),
('United Kingdom', 'Geopolitics', 'United Kingdom is a sovereign country', 1, 'United Kingdom of Great Britain and Northern Ireland', 'London', 'England, Scotland, Wales, Northern Ireland', 'UN Member Directory', 'The UK is an independent sovereign state.');
