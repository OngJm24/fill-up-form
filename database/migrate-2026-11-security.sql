-- Upgrade an existing install:  mysql -uroot simple-forms < database/migrate-2026-11-security.sql
CREATE TABLE IF NOT EXISTS `throttle` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(20) NOT NULL,
    `client_key` CHAR(64) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_lookup` (`action`, `client_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos now live in storage/uploads and are referenced by file name only.
UPDATE `submissions` SET `selfie` = SUBSTRING_INDEX(`selfie`, '/', -1) WHERE `selfie` LIKE 'uploads/%';
UPDATE `mood_photos` SET `photo_path` = SUBSTRING_INDEX(`photo_path`, '/', -1) WHERE `photo_path` LIKE 'uploads/%';

ALTER TABLE `submissions` ADD INDEX IF NOT EXISTS `idx_email_time` (`email`, `submitted_at`);
