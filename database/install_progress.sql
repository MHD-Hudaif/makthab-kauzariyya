-- Database expansion for Progress Logs, Dynamic Course Targets, and Class Audits

-- 1. Alter courses table to specify milestone structures
ALTER TABLE `courses` 
ADD COLUMN `target_type` ENUM('lesson', 'juz') NOT NULL DEFAULT 'lesson',
ADD COLUMN `total_targets` INT NOT NULL DEFAULT 1;

-- Seed targets dynamically for existing courses
-- (TJ = Tajweed, NZ = Nazira, HZ = Hifz)
UPDATE `courses` SET `target_type` = 'lesson', `total_targets` = 28 WHERE `code` = 'TJ' OR `code` = 'SPECIAL';
UPDATE `courses` SET `target_type` = 'juz',    `total_targets` = 5  WHERE `code` = 'NZ' OR `code` = 'SHARIAH';
UPDATE `courses` SET `target_type` = 'juz',    `total_targets` = 30 WHERE `code` = 'HZ' OR `code` = 'HIFZ';

-- 2. Create progress_logs table
CREATE TABLE IF NOT EXISTS `progress_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    `logged_date` DATE NOT NULL,
    `is_present` TINYINT(1) DEFAULT 1,
    
    -- Tajweed Metrics
    `current_lesson` INT DEFAULT NULL, -- 1 to 28
    
    -- Nazira Metrics
    `current_juz` INT DEFAULT NULL,    -- 1 to 5
    `current_page` INT DEFAULT NULL,
    
    -- Hifz Metrics
    `sabak_lines` INT DEFAULT 0,       -- Lines memorized
    `sabqi_completed` TINYINT(1) DEFAULT 1, -- Did they recite last 10 pages? (0 or 1)
    `manzil_submitted` TINYINT(1) DEFAULT 1, -- Did they send 0.5 Juz voice note? (0 or 1)
    
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `one_log_per_day` (`student_id`, `logged_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create class_audits table
CREATE TABLE IF NOT EXISTS `class_audits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supervisor_id` INT UNSIGNED NOT NULL,
    `class_id` INT UNSIGNED NOT NULL,
    `audit_date` DATE NOT NULL,
    `camera_on` TINYINT(1) DEFAULT 1,
    `timekeeping_score` INT CHECK (`timekeeping_score` BETWEEN 1 AND 5),
    `motivation_score` INT CHECK (`motivation_score` BETWEEN 1 AND 5),
    `notes` TEXT,
    FOREIGN KEY (`supervisor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create leaves table
CREATE TABLE IF NOT EXISTS `leaves` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `leave_date` DATE NOT NULL,
    `reason` varchar(255) DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
