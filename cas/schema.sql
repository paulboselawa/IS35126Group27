-- CAS Learning Resources Portal — schema & seed data
-- Run AFTER database.sql (it relies on the `users` table).
USE `IS35126Group27`;

-- Allow teacher accounts (teachers manage CAS resources; admins can too)
ALTER TABLE `users`
  MODIFY `role` enum('admin','hr','applicant','teacher') DEFAULT 'applicant';

-- The 5 Key Learning Areas (names are editable by an admin in cas/klas.php)
CREATE TABLE IF NOT EXISTS `cas_klas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resources: syllabi, textbooks and student worksheets
CREATE TABLE IF NOT EXISTS `cas_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kla_id` int(11) NOT NULL,
  `type` enum('syllabus','textbook','worksheet') NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `stored_name` varchar(64) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `downloads` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kla_type` (`kla_id`, `type`),
  CONSTRAINT `cas_resources_kla_fk` FOREIGN KEY (`kla_id`) REFERENCES `cas_klas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cas_resources_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: default 5 KLAs (rename in the portal if your curriculum uses different ones)
INSERT IGNORE INTO `cas_klas` (`code`, `name`, `description`, `sort_order`) VALUES
('ENG', 'English / Language',       'Reading, writing, speaking and listening.',                1),
('MAT', 'Mathematics',              'Number, algebra, measurement, geometry and statistics.',   2),
('SCI', 'Science',                  'Living things, physical, chemical and earth sciences.',    3),
('SOS', 'Social Science',           'History, geography, civics and the community.',            4),
('PD',  'Personal Development',     'Health, physical education and wellbeing.',                5);

-- Seed: test teacher account (password "Password1!")
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`) VALUES
('teacher', 'teacher@cas.edu', '$2y$10$osNV/1ehdYn0yDufvZjAruCCMc7tCkE9aWORu0ydshxzbgX0FsIC2', 'teacher');
