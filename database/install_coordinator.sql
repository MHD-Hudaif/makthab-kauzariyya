-- Update Database Schema for Coordinator, Courses, and Classes

CREATE TABLE IF NOT EXISTS `courses` (
  `id` int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL UNIQUE,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `classes` (
  `id` int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` int UNSIGNED NOT NULL,
  `supervisor_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('regular', 'individual') DEFAULT 'regular',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_classes_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_classes_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraint to students table (class_id is already defined in install_users.sql)
ALTER TABLE `students` ADD CONSTRAINT `fk_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `class_teachers` (
  `class_id` int UNSIGNED NOT NULL,
  `teacher_id` int UNSIGNED NOT NULL,
  PRIMARY KEY (`class_id`, `teacher_id`),
  CONSTRAINT `fk_ct_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ct_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Sample Courses
INSERT IGNORE INTO `courses` (`id`, `name`, `code`, `description`) VALUES
(1, 'Hifzul Quran', 'HIFZ', 'Comprehensive Quran memorization program with tajweed standards.'),
(2, 'Shariah Course', 'SHARIAH', 'A comprehensive Islamic jurisprudence and theology curriculum.'),
(3, 'Specialisations', 'SPECIAL', 'Post-graduate programs specializing in Fiqh (Islamic Law) and Qirath.');

-- Seed Sample Classes
INSERT IGNORE INTO `classes` (`id`, `course_id`, `supervisor_id`, `name`, `type`) VALUES
(1, 1, 2, 'Hifz Class A', 'regular'),
(2, 2, 2, 'Shariah Class 1', 'regular'),
(3, 1, 2, 'Individual Hifz', 'individual'),
(4, 3, 2, 'Fiqh Specialisation', 'regular');

-- Seed Sample Class Teacher assignments (Teacher with id=4 teaches in Hifz Class A, Individual Hifz, Fiqh Specialisation)
INSERT IGNORE INTO `class_teachers` (`class_id`, `teacher_id`) VALUES
(1, 4),
(3, 4),
(4, 4);

-- Update Sample Student (id=5) to belong to Hifz Class A (class_id=1)
UPDATE `students` SET `class_id` = 1 WHERE `user_id` = 5;
