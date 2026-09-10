-- Create the database
CREATE DATABASE IF NOT EXISTS `simple-forms`;
USE `simple-forms`;

-- Create the submissions table
CREATE TABLE IF NOT EXISTS `submissions` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `mood_photos` (
    `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT(11) UNSIGNED NOT NULL,
    `mood` VARCHAR(20) NOT NULL,
    `photo_path` VARCHAR(255) NOT NULL,
    `reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE
);