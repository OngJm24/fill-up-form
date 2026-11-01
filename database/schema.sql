-- Full schema for a fresh install:  mysql -uroot < database/schema.sql
CREATE DATABASE IF NOT EXISTS `simple-forms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `simple-forms`;

CREATE TABLE IF NOT EXISTS `submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `birthday` DATE NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `sim_card` VARCHAR(50) NOT NULL,
    `fav_food` VARCHAR(100),
    `fav_drink` VARCHAR(100),
    `hobby` VARCHAR(100),
    `fav_color` VARCHAR(50),
    `fun_question` TEXT,
    `fav_animal` VARCHAR(100),
    `weird_food` VARCHAR(100),
    `biggest_dream` TEXT,
    `role_model` VARCHAR(100),
    `future_self` VARCHAR(255),
    `world_change` TEXT,
    `selfie` VARCHAR(255),
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_time` (`email`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mood_photos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT UNSIGNED NOT NULL,
    `mood` VARCHAR(20) NOT NULL,
    `photo_path` VARCHAR(255) NOT NULL,  -- file name inside storage/uploads
    `reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limiting for logins and submissions. Stores an HMAC of the IP, never the raw IP.
CREATE TABLE IF NOT EXISTS `throttle` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(20) NOT NULL,
    `client_key` CHAR(64) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_lookup` (`action`, `client_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
