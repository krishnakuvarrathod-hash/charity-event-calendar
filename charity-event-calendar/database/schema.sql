-- ========================================================
-- Charity Event Calendar - Database Schema
-- Database Name: charity_calendar
-- Compatible with MySQL 8.0+ and Amazon RDS MySQL
-- ========================================================

CREATE DATABASE IF NOT EXISTS charity_calendar 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE charity_calendar;

-- --------------------------------------------------------
-- 1. Table: users
-- Stores visitors, organizers, and administrators
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('visitor', 'organizer', 'admin') NOT NULL DEFAULT 'visitor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Table: charities
-- Stores charity profiles linked to organizer user accounts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS charities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    charity_name VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(50) NULL,
    website VARCHAR(255) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_charities_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_charities_user (user_id),
    INDEX idx_charities_city (city),
    INDEX idx_charities_name (charity_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table: venues
-- Physical or online event locations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(200) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_venues_city (city),
    INDEX idx_venues_name (venue_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table: events
-- Charity events with approval status and capacity
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    charity_id INT NOT NULL,
    venue_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    registration_deadline DATETIME NOT NULL,
    capacity INT NOT NULL DEFAULT 50,
    image_url VARCHAR(500) NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_charity FOREIGN KEY (charity_id) 
        REFERENCES charities(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_venue FOREIGN KEY (venue_id) 
        REFERENCES venues(id) ON DELETE RESTRICT,
    INDEX idx_events_status (status),
    INDEX idx_events_start (start_datetime),
    INDEX idx_events_category (category),
    INDEX idx_events_charity (charity_id),
    INDEX idx_events_venue (venue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table: registrations
-- User registrations for events with duplicate prevention
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
    CONSTRAINT unique_user_event UNIQUE (event_id, user_id),
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id) 
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reg_event (event_id),
    INDEX idx_reg_user (user_id),
    INDEX idx_reg_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table: login_attempts (for rate limiting / brute-force protection)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(191) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_ip_time (ip_address, attempt_time),
    INDEX idx_attempts_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
