-- Verification Roster Table Schema & Initial Seed
CREATE TABLE IF NOT EXISTS `verification_roster` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('teacher', 'student') NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `assigned_teacher_name` VARCHAR(200) DEFAULT NULL,
  `is_claimed` TINYINT(1) DEFAULT 0,
  `claimed_user_id` INT UNSIGNED DEFAULT NULL,
  `claimed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`type`),
  INDEX (`name`),
  INDEX (`is_claimed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure helper columns exist on users and students table
-- (Users: roster_id)
-- (Students: parent_phone)
