-- phpMyAdmin SQL Dump
-- version 5.2.0
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2026 at 05:40 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library_db`
--
CREATE DATABASE IF NOT EXISTS `library_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `library_db`;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','staff','member') NOT NULL DEFAULT 'member',
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Passwords: superadmin/admin => 'admin123' | staff => 'staff123' | member1 => 'member123'
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `full_name`, `phone`, `address`, `status`, `created_at`) VALUES
(1, 'superadmin', 'super@library.local', '$2y$10$ItgU0edbh821DpzTJusf5.4sSt1FIyoFtmKOxyzoPjLU3FYbo/6nm', 'superadmin', 'Super Administrator', '1234567890', 'Main Library HQ', 'active', current_timestamp()),
(2, 'admin', 'admin@library.local', '$2y$10$339m61Ouo5OBulKSjpNhMekxVgYLDRaN.X3TogysvncwxH3uot/8a', 'admin', 'Library Admin', '1234567891', 'Library Admin Office', 'active', current_timestamp()),
(3, 'staff', 'staff@library.local', '$2y$10$84yB/pfrB/kNeHIRGyIXIeTd1kmSKMclwV.H9etgudu8TGSRtsYCC', 'staff', 'Library Staff', '1234567892', 'Library Front Desk', 'active', current_timestamp()),
(4, 'member1', 'member1@example.com', '$2y$10$qID37AnGNxuaFGWBALBZ0uanFot8Bm5atVkJpdM3BDRzsmmmvIw56', 'member', 'John Doe', '1234567893', '123 Fiction Lane, Booktown', 'active', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Fiction', 'Fictional literature and novels.', current_timestamp()),
(2, 'Science', 'Scientific books covering physics, biology, etc.', current_timestamp()),
(3, 'History', 'Historical accounts and non-fiction.', current_timestamp()),
(4, 'Technology', 'Computing, engineering, and tech resources.', current_timestamp()),
(5, 'Children', 'Books tailored for younger audiences.', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `books`
--
CREATE TABLE `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `total_copies` int(11) NOT NULL DEFAULT 1,
  `available_copies` int(11) NOT NULL DEFAULT 1,
  `shelf_location` varchar(50) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `isbn` (`isbn`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `books_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category_id`, `publisher`, `year`, `total_copies`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES
(1, '1984', 'George Orwell', '9780451524935', 1, 'Signet Classic', 1949, 10, 10, 'A1-S1', 'Dystopian social science fiction novel and cautionary tale.', current_timestamp()),
(2, 'A Brief History of Time', 'Stephen Hawking', '9780553380163', 2, 'Bantam', 1988, 5, 5, 'B2-S1', 'Popular-science book on cosmology.', current_timestamp()),
(3, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', '9780062316097', 3, 'Harper', 2011, 8, 8, 'C1-S3', 'Explores the history of the human species.', current_timestamp()),
(4, 'Clean Code', 'Robert C. Martin', '9780132350884', 4, 'Prentice Hall', 2008, 12, 12, 'T1-S2', 'A Handbook of Agile Software Craftsmanship.', current_timestamp()),
(5, 'The Very Hungry Caterpillar', 'Eric Carle', '9780241003008', 5, 'World Publishing Company', 1969, 15, 15, 'CH-S1', 'Children''s picture book.', current_timestamp()),
(6, 'To Kill a Mockingbird', 'Harper Lee', '9780060935467', 1, 'J. B. Lippincott & Co.', 1960, 20, 20, 'A2-S1', 'Southern Gothic novel.', current_timestamp()),
(7, 'Cosmos', 'Carl Sagan', '9780345331359', 2, 'Random House', 1980, 6, 6, 'B1-S1', 'Exploration of the universe.', current_timestamp()),
(8, 'Guns, Germs, and Steel', 'Jared Diamond', '9780393317558', 3, 'W. W. Norton & Company', 1997, 7, 7, 'C2-S2', 'The Fates of Human Societies.', current_timestamp()),
(9, 'The Pragmatic Programmer', 'Andrew Hunt, David Thomas', '9780201616224', 4, 'Addison-Wesley', 1999, 10, 10, 'T2-S1', 'From Journeyman to Master.', current_timestamp()),
(10, 'Where the Wild Things Are', 'Maurice Sendak', '9780060254926', 5, 'Harper & Row', 1963, 12, 12, 'CH-S2', 'Children''s picture book.', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `members`
--
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `member_id` varchar(20) NOT NULL,
  `membership_type` varchar(50) DEFAULT 'Standard',
  `join_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','expired','suspended') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `member_id` (`member_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `members` (`id`, `user_id`, `member_id`, `membership_type`, `join_date`, `expiry_date`, `status`) VALUES
(1, 4, 'LIB001', 'Standard', '2023-01-01', '2026-12-31', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `book_issues`
--
CREATE TABLE `book_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('issued','returned','overdue') NOT NULL DEFAULT 'issued',
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `member_id` (`member_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `book_issues_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`),
  CONSTRAINT `book_issues_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `book_issues_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--
CREATE TABLE `returns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `return_date` date NOT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `fine_paid` enum('yes','no') DEFAULT 'no',
  `returned_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `issue_id` (`issue_id`),
  KEY `returned_by` (`returned_by`),
  CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `book_issues` (`id`),
  CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--
CREATE TABLE `fines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `issue_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','paid','waived') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `issue_id` (`issue_id`),
  CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  CONSTRAINT `fines_ibfk_2` FOREIGN KEY (`issue_id`) REFERENCES `book_issues` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fine_settings`
--
CREATE TABLE `fine_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fine_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_fine` decimal(10,2) NOT NULL DEFAULT 0.00,
  `grace_period_days` int(11) NOT NULL DEFAULT 0,
  `calc_method` varchar(50) NOT NULL DEFAULT 'flat',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `fine_settings` (`id`, `fine_per_day`, `max_fine`, `grace_period_days`, `calc_method`, `updated_at`) VALUES
(1, 5.00, 500.00, 1, 'flat', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `status` enum('pending','approved','cancelled','collected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`),
  CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('library_name', 'City Public Library', current_timestamp()),
('library_address', '123 Main Street', current_timestamp()),
('library_phone', '+1-555-0100', current_timestamp()),
('library_email', 'info@citylibrary.com', current_timestamp()),
('library_hours', 'Mon-Fri: 8AM-8PM, Sat-Sun: 10AM-6PM', current_timestamp()),
('max_borrow_days', '14', current_timestamp()),
('max_books_per_member', '5', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `library_info`
--
CREATE TABLE `library_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `info_key` varchar(100) NOT NULL,
  `info_value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `info_key` (`info_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `library_info` (`info_key`, `info_value`) VALUES
('about', 'Welcome to City Public Library, a place for discovering the universe through literature and research.'),
('rules', '1. Maintain silence. 2. No food or drinks. 3. Return books on time.'),
('timings', 'Mon-Fri: 8AM-8PM, Sat-Sun: 10AM-6PM');

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
