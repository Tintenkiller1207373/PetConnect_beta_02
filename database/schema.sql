-- ============================================================================
-- PetConnect - Datenbank-Schema
-- Zur Wiederfindung vermisster Haustiere mit PyTorch-Bildanalyse
-- ============================================================================

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS petconnect;
USE petconnect;

-- ============================================================================
-- 1. Users Tabelle (Besitzer)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    location VARCHAR(255),
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Pets Tabelle (Registrierte Haustiere)
-- ============================================================================
CREATE TABLE IF NOT EXISTS pets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    species ENUM('dog', 'cat') NOT NULL,
    breed VARCHAR(100),
    color VARCHAR(100),
    age INT,
    weight DECIMAL(5,2),
    distinguishing_features TEXT,
    profile_image VARCHAR(255) NOT NULL,
    microchip_id VARCHAR(50),
    is_missing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_missing (is_missing),
    INDEX idx_species (species)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. Pet Images Tabelle (Alle Bilder für KI-Verarbeitung)
-- ============================================================================
CREATE TABLE IF NOT EXISTS pet_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    embedding LONGBLOB,
    embedding_model VARCHAR(50) DEFAULT 'resnet50',
    is_primary BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    INDEX idx_pet_id (pet_id),
    INDEX idx_is_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. Missing Reports Tabelle (Vermisstenberichte)
-- ============================================================================
CREATE TABLE IF NOT EXISTS missing_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pet_id INT NOT NULL,
    user_id INT NOT NULL,
    location_last_seen VARCHAR(255) NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    date_missing DATE NOT NULL,
    description TEXT,
    reward_amount DECIMAL(10, 2),
    status ENUM('active', 'resolved', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pet_id (pet_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. Finder Reports Tabelle (Fundfunde durch Finder)
-- ============================================================================
CREATE TABLE IF NOT EXISTS finder_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    image_path VARCHAR(255) NOT NULL,
    embedding LONGBLOB,
    location_found VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    finder_contact VARCHAR(255),
    finder_phone VARCHAR(20),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. Match Results Tabelle (KI-Abgleich Ergebnisse)
-- ============================================================================
CREATE TABLE IF NOT EXISTS match_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    finder_report_id INT NOT NULL,
    pet_id INT NOT NULL,
    similarity_score DECIMAL(5, 4) NOT NULL,
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_notified BOOLEAN DEFAULT FALSE,
    notification_sent_at TIMESTAMP NULL,
    FOREIGN KEY (finder_report_id) REFERENCES finder_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (pet_id) REFERENCES pets(id) ON DELETE CASCADE,
    INDEX idx_finder_pet (finder_report_id, pet_id),
    INDEX idx_similarity_score (similarity_score),
    UNIQUE KEY unique_match (finder_report_id, pet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. Sessions Tabelle (Session Management)
-- ============================================================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_token (session_token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. Activity Logs Tabelle (Audit Trail)
-- ============================================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Indexes für Performance-Optimierung
-- ============================================================================
CREATE INDEX idx_pets_missing_species ON pets(is_missing, species);
CREATE INDEX idx_missing_reports_pet_status ON missing_reports(pet_id, status);
CREATE INDEX idx_match_results_similarity ON match_results(finder_report_id, similarity_score DESC);
