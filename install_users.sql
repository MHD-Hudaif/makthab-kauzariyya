-- Install Users Schema for Makthab Kauzariyya

CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(80) NOT NULL UNIQUE,
  `email` varchar(150) DEFAULT NULL UNIQUE,
  `phone` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `role` enum('admin', 'supervisor', 'coordinator', 'teacher', 'student') NOT NULL,
  `status` enum('active', 'inactive', 'suspended') DEFAULT 'active',
  `profile_photo` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL UNIQUE,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `id` int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` int UNSIGNED NOT NULL,
  `admission_no` varchar(50) DEFAULT NULL UNIQUE,
  `parent_name` varchar(200) DEFAULT NULL,
  `class_id` int UNSIGNED DEFAULT NULL,
  `dob` date DEFAULT NULL,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` int UNSIGNED NOT NULL,
  `specialisation` varchar(150) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
