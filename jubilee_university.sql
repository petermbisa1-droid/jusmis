-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 13, 2025 at 01:03 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `jubilee_university`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_name` (`year_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approved_grades`
--

DROP TABLE IF EXISTS `approved_grades`;
CREATE TABLE IF NOT EXISTS `approved_grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade_id` int NOT NULL,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `class_id` int NOT NULL,
  `grading_period_id` int NOT NULL,
  `cw1` int DEFAULT NULL,
  `cw2` int DEFAULT NULL,
  `mid` int DEFAULT NULL,
  `exam` int DEFAULT NULL,
  `final_score` int DEFAULT NULL,
  `grade_letter` varchar(2) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resolution_status` enum('none','supplementary','deferred','query_resolved') NOT NULL DEFAULT 'none',
  `resolved_score` decimal(5,2) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `grade_id` (`grade_id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `session_id` (`session_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `class_id` (`class_id`),
  KEY `approved_by` (`approved_by`),
  KEY `approved_grades_ibfk_grading_calendar` (`grading_period_id`)
) ENGINE=InnoDB AUTO_INCREMENT=248 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `file_path` varchar(500) NOT NULL,
  `deadline` datetime NOT NULL,
  `uploaded_by` int DEFAULT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `session_id` (`session_id`),
  KEY `academic_year_id` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--

DROP TABLE IF EXISTS `assignment_submissions`;
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `assignment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `feedback` text,
  PRIMARY KEY (`id`),
  KEY `assignment_id` (`assignment_id`),
  KEY `student_id` (`student_id`),
  KEY `session_id` (`session_id`),
  KEY `academic_year_id` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `registration_course_id` int NOT NULL,
  `attendance_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('present','absent') DEFAULT 'absent',
  `recorded_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `registration_course_id` (`registration_course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `registration_course_id`, `attendance_date`, `start_time`, `end_time`, `status`, `recorded_by`, `created_at`) VALUES
(1, 36, '2025-08-04', '08:00:00', '09:00:00', 'present', NULL, '2025-08-04 09:20:38'),
(2, 36, '2025-08-04', '08:00:00', '09:00:00', 'present', NULL, '2025-08-04 09:25:38');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

DROP TABLE IF EXISTS `audit_trail`;
CREATE TABLE IF NOT EXISTS `audit_trail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `activity` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_name`, `role`, `activity`, `created_at`, `ip_address`, `user_agent`) VALUES
(130, 'Unknown', 'superuser', 'Created class: BBA -Year 1 Semester 2', '2025-08-06 13:17:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(131, 'Unknown User', 'superuser', 'Created class: BBA -Year 1 Semester 2, Year: 1, Semester: Semester 2', '2025-08-06 13:17:05', NULL, NULL),
(132, 'Unknown', 'superuser', 'Assigned courses to class ID 17', '2025-08-06 13:17:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(133, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/01', '2025-08-06 13:21:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(134, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/02', '2025-08-06 13:22:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(135, 'Unknown', 'superuser', 'Updated course PHI-115', '2025-08-06 14:09:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(136, 'Unknown', 'superuser', 'Updated course PHI-116', '2025-08-06 14:09:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(137, 'Unknown', 'superuser', 'Updated course ECN-112', '2025-08-06 14:09:40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(138, 'Unknown', 'superuser', 'Updated course ACC-111', '2025-08-06 14:09:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(139, 'Unknown', 'superuser', 'Created session \'Session 2\' for academic year ID 3', '2025-08-06 23:33:08', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(140, 'Unknown', 'superuser', 'Activated session ID 5 and opened Semester 1 & 2', '2025-08-06 23:33:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(141, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 2 (ID: 17)', '2025-08-06 23:33:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(142, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-06 23:35:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(143, 'Unknown', 'superuser', 'Activated session ID 4 and opened Semester 1 & 2', '2025-08-06 23:44:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(144, 'Unknown', 'superuser', 'Activated session ID 5 and opened Semester 1 & 2', '2025-08-06 23:44:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(145, 'Unknown', 'superuser', 'Activated session ID 4 and opened Semester 1 & 2', '2025-08-06 23:46:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(146, 'Unknown', 'superuser', 'Activated session ID 5 and opened Semester 1 & 2', '2025-08-06 23:48:28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(147, 'Unknown', 'superuser', 'Activated session ID 4 and opened Semester 1 & 2', '2025-08-07 00:04:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(148, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 2 (ID: 17)', '2025-08-07 00:04:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(149, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-07 00:04:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(150, 'Unknown', 'superuser', 'Activated session ID 5 and opened Semester 1 & 2', '2025-08-07 00:09:41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(151, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 2 (ID: 17)', '2025-08-07 00:09:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(152, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-07 00:10:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(153, 'Unknown', 'superuser', 'Activated session ID 4 and opened Semester 1 & 2', '2025-08-07 00:23:26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(154, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-07 00:23:36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(155, 'Unknown', 'superuser', 'Activated session ID 5 and opened Semester 1 & 2', '2025-08-07 00:24:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(156, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-07 00:24:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(157, 'Unknown', 'superuser', 'Activated session ID 4 and opened Semester 1 & 2', '2025-08-07 00:35:29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(158, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 16)', '2025-08-07 00:35:48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(159, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 2 (ID: 17)', '2025-08-07 00:35:54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(160, 'Unknown', 'superuser', 'Created academic year: 2025-2026', '2025-08-07 07:31:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(161, 'Unknown', 'superuser', 'Created session \'Session 1\' for academic year ID 4', '2025-08-07 07:31:58', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(162, 'Unknown', 'superuser', 'Activated session ID 6 and opened Semester 1 & 2', '2025-08-07 07:32:02', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(163, 'Unknown', 'superuser', 'Created class: BBA -Year 1 Semester 1', '2025-08-07 07:32:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(164, 'Unknown User', 'superuser', 'Created class: BBA -Year 1 Semester 1, Year: 1, Semester: Semester 1', '2025-08-07 07:32:22', NULL, NULL),
(165, 'Unknown', 'superuser', 'Created class: BBA -Year 1 Semester 2', '2025-08-07 07:32:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(166, 'Unknown User', 'superuser', 'Created class: BBA -Year 1 Semester 2, Year: 1, Semester: Semester 2', '2025-08-07 07:32:33', NULL, NULL),
(167, 'Unknown', 'superuser', 'Assigned courses to class ID 19', '2025-08-07 07:33:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(168, 'Unknown', 'superuser', 'Assigned courses to class ID 18', '2025-08-07 07:33:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(169, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/03', '2025-08-07 07:38:14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(170, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/04', '2025-08-07 07:39:11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(171, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/05', '2025-08-07 07:50:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(172, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/06', '2025-08-07 07:51:38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(173, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/07', '2025-08-07 08:14:32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(174, 'Unknown', 'superuser', 'Enrolled new student BBA/10/01/01/08', '2025-08-07 08:25:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(175, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=3, session_id=6, class_id=18. Success=1, Failed=3', '2025-08-07 09:59:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(176, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=3, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:00:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(177, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=3, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:01:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(178, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=7, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:01:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(179, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=7, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:02:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(180, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=5, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:02:18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(181, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=5, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:02:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(182, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=6, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:02:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(183, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=6, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:03:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(184, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=4, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:03:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(185, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=4, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:03:34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(186, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=8, session_id=6, class_id=18. Success: 1, Failed: 3', '2025-08-07 10:04:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(187, 'Peter Paul Mbisa', 'lecturer', 'Uploaded CA grades for course_id=8, session_id=6, class_id=19. Success: 3, Failed: 1', '2025-08-07 10:04:27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(188, 'Unknown', 'superuser', 'Created class: BBA -Year 2 Semester 1', '2025-08-07 10:53:43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(189, 'Unknown User', 'superuser', 'Created class: BBA -Year 2 Semester 1, Year: 2, Semester: Semester 1', '2025-08-07 10:53:43', NULL, NULL),
(190, 'Unknown', 'superuser', 'Assigned courses to class ID 20', '2025-08-07 10:54:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(191, 'Unknown', 'superuser', 'Created session \'Session 2\' for academic year ID 4', '2025-08-07 12:41:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(192, 'Unknown', 'superuser', 'Activated session ID 7 and opened Semester 1 & 2', '2025-08-07 12:41:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(193, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 18)', '2025-08-07 12:42:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(194, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 2 (ID: 19)', '2025-08-07 12:42:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(195, 'Unknown', 'superuser', 'Updated class: BBA -Year 2 Semester 1 (ID: 20)', '2025-08-07 12:42:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(196, 'Unknown', 'superuser', 'Activated session ID 6 and opened Semester 1 & 2', '2025-08-07 15:02:33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(197, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 18)', '2025-08-07 15:02:46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(198, 'Unknown', 'superuser', 'Activated session ID 7 and opened Semester 1 & 2', '2025-08-07 15:03:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(199, 'Unknown', 'superuser', 'Closed semester ID 11', '2025-08-07 15:03:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(200, 'Unknown', 'superuser', 'Closed semester ID 12', '2025-08-07 15:03:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(201, 'Unknown', 'superuser', 'Updated class: BBA -Year 2 Semester 1 (ID: 20)', '2025-08-07 15:03:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'),
(202, 'Unknown', 'superuser', 'Updated class: BBA -Year 1 Semester 1 (ID: 18)', '2025-08-07 15:03:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `year` int NOT NULL,
  `semester_number` int DEFAULT NULL,
  `programme_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `session_id` int NOT NULL,
  `semester` enum('Semester 1','Semester 2') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `promotion_class_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `programme_id` (`programme_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `session_id` (`session_id`),
  KEY `fk_promotion_class` (`promotion_class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `class_name`, `year`, `semester_number`, `programme_id`, `academic_year_id`, `session_id`, `semester`, `is_active`, `created_by`, `created_at`, `updated_by`, `updated_at`, `promotion_class_id`) VALUES
(18, 'BBA -Year 1 Semester 1', 1, NULL, 1, 4, 7, 'Semester 1', 1, 'Unknown User', '2025-08-07 07:32:22', '0', '2025-08-07 15:03:42', NULL),
(19, 'BBA -Year 1 Semester 2', 1, NULL, 1, 4, 7, 'Semester 2', 1, 'Unknown User', '2025-08-07 07:32:33', '0', '2025-08-07 12:42:15', NULL),
(20, 'BBA -Year 2 Semester 1', 2, NULL, 1, 4, 7, 'Semester 1', 1, 'Unknown User', '2025-08-07 10:53:43', '0', '2025-08-07 15:03:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_courses`
--

DROP TABLE IF EXISTS `class_courses`;
CREATE TABLE IF NOT EXISTS `class_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `course_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `class_courses`
--

INSERT INTO `class_courses` (`id`, `class_id`, `course_id`, `created_at`, `created_by`, `updated_by`) VALUES
(31, 16, 3, '2025-08-04 21:35:46', NULL, NULL),
(32, 16, 7, '2025-08-04 21:35:46', NULL, NULL),
(33, 16, 5, '2025-08-04 21:35:46', NULL, NULL),
(34, 16, 6, '2025-08-04 21:35:46', NULL, NULL),
(35, 16, 4, '2025-08-04 21:35:46', NULL, NULL),
(36, 16, 8, '2025-08-04 21:35:46', NULL, NULL),
(37, 17, 3, '2025-08-06 13:17:31', NULL, NULL),
(38, 17, 7, '2025-08-06 13:17:31', NULL, NULL),
(39, 17, 5, '2025-08-06 13:17:31', NULL, NULL),
(40, 17, 6, '2025-08-06 13:17:31', NULL, NULL),
(41, 17, 4, '2025-08-06 13:17:31', NULL, NULL),
(42, 17, 8, '2025-08-06 13:17:31', NULL, NULL),
(43, 19, 3, '2025-08-07 07:33:03', NULL, NULL),
(44, 19, 7, '2025-08-07 07:33:03', NULL, NULL),
(45, 19, 5, '2025-08-07 07:33:03', NULL, NULL),
(46, 19, 6, '2025-08-07 07:33:03', NULL, NULL),
(47, 19, 4, '2025-08-07 07:33:04', NULL, NULL),
(48, 19, 8, '2025-08-07 07:33:04', NULL, NULL),
(49, 18, 3, '2025-08-07 07:33:38', NULL, NULL),
(50, 18, 7, '2025-08-07 07:33:38', NULL, NULL),
(51, 18, 5, '2025-08-07 07:33:38', NULL, NULL),
(52, 18, 6, '2025-08-07 07:33:38', NULL, NULL),
(53, 18, 4, '2025-08-07 07:33:38', NULL, NULL),
(54, 18, 8, '2025-08-07 07:33:38', NULL, NULL),
(55, 20, 12, '2025-08-07 10:54:05', NULL, NULL),
(56, 20, 13, '2025-08-07 10:54:05', NULL, NULL),
(57, 20, 11, '2025-08-07 10:54:05', NULL, NULL),
(58, 20, 9, '2025-08-07 10:54:05', NULL, NULL),
(59, 20, 10, '2025-08-07 10:54:05', NULL, NULL),
(60, 20, 14, '2025-08-07 10:54:05', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_streams`
--

DROP TABLE IF EXISTS `class_streams`;
CREATE TABLE IF NOT EXISTS `class_streams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `base_class_id` int NOT NULL,
  `eligible_class_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `base_class_id` (`base_class_id`),
  KEY `eligible_class_id` (`eligible_class_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `class_streams`
--

INSERT INTO `class_streams` (`id`, `base_class_id`, `eligible_class_id`) VALUES
(1, 18, 19),
(2, 19, 20);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit_hours` int NOT NULL,
  `type` enum('core','elective') NOT NULL,
  `lecturer_id` int DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `lecturer_id` (`lecturer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_code`, `course_name`, `credit_hours`, `type`, `lecturer_id`, `created_by`, `created_at`, `updated_by`, `updated_at`, `is_active`) VALUES
(3, 'ACC-111', 'Introduction to Accounting I', 10, 'core', 8, 'Super User', '2025-07-30 17:58:09', NULL, '2025-08-06 12:09:47', 1),
(4, 'ECN-112', 'Micro Economics', 10, 'core', 8, 'Super User', '2025-07-30 18:16:13', NULL, '2025-08-06 12:09:40', 1),
(5, 'BEH-113', 'Introduction To Human Behavior', 10, 'core', 8, 'Super User', '2025-07-30 18:16:45', NULL, '2025-08-04 19:36:42', 1),
(6, 'LAN-114', 'Language and Communication', 10, 'core', 8, 'Super User', '2025-07-30 18:17:09', NULL, '2025-08-04 19:36:37', 1),
(7, 'PHI-115', 'Introduction to Critical Thinking', 10, 'core', 8, 'Super User', '2025-07-30 18:17:31', NULL, '2025-08-06 12:09:26', 1),
(8, 'PHI-116', 'Principles and Practice of Umunthu', 10, 'core', 8, 'Super User', '2025-07-30 18:17:53', NULL, '2025-08-06 12:09:32', 1),
(9, 'ACC-121', 'Introduction to Accounting II', 10, 'core', 6, 'Super User', '2025-07-30 18:18:34', NULL, '2025-07-30 18:18:34', 1),
(10, 'ECN-122', 'Macro Economics', 10, 'core', 5, 'Super User', '2025-07-30 18:19:07', NULL, '2025-07-30 18:19:07', 1),
(11, 'ICT-123', 'Computer Applications', 10, 'core', 6, 'Super User', '2025-07-30 18:19:32', NULL, '2025-07-30 18:19:32', 1),
(12, 'LAN-124', 'Business Communication', 10, 'core', 5, 'Super User', '2025-07-30 18:19:54', NULL, '2025-07-30 18:19:54', 1),
(13, 'MAT-125', 'Business Mathematics', 10, 'core', NULL, 'Super User', '2025-07-30 18:20:30', NULL, '2025-08-04 19:36:22', 1),
(14, 'MGT-126', 'Organizational Behavior', 10, 'core', NULL, 'Super User', '2025-07-30 18:20:52', NULL, '2025-08-04 19:36:16', 1);

-- --------------------------------------------------------

--
-- Table structure for table `fee_settings`
--

DROP TABLE IF EXISTS `fee_settings`;
CREATE TABLE IF NOT EXISTS `fee_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `class_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class_id` (`class_id`,`academic_year_id`),
  KEY `academic_year_id` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
CREATE TABLE IF NOT EXISTS `grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `class_id` int NOT NULL,
  `grading_period_id` int NOT NULL,
  `cw1` int DEFAULT '0',
  `cw2` int DEFAULT '0',
  `mid` int DEFAULT '0',
  `exam` int DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `final_score` int GENERATED ALWAYS AS (round((((((`cw1` + `cw2`) + `mid`) / 3) * 0.4) + (`exam` * 0.6)),0)) STORED,
  `grade_letter` varchar(2) GENERATED ALWAYS AS ((case when (`final_score` >= 75) then _utf8mb4'A' when (`final_score` >= 65) then _utf8mb4'B' when (`final_score` >= 45) then _utf8mb4'C' else _utf8mb4'F' end)) STORED,
  `remarks` varchar(20) GENERATED ALWAYS AS ((case when (`final_score` >= 75) then _utf8mb4'Distinction' when (`final_score` >= 65) then _utf8mb4'Credit' when (`final_score` >= 45) then _utf8mb4'Pass' else _utf8mb4'Fail' end)) STORED,
  `status` enum('pass','fail','graduate') NOT NULL DEFAULT 'fail',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_grade` (`student_id`,`course_id`,`session_id`,`grading_period_id`),
  KEY `course_id` (`course_id`),
  KEY `session_id` (`session_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `class_id` (`class_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `fk_grading_period_id` (`grading_period_id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `grades`
--
DROP TRIGGER IF EXISTS `trg_grades_before_insert`;
DELIMITER $$
CREATE TRIGGER `trg_grades_before_insert` BEFORE INSERT ON `grades` FOR EACH ROW BEGIN
  DECLARE coursework_avg DECIMAL(5,2);
  SET coursework_avg = (NEW.cw1 + NEW.cw2 + NEW.mid) / 3;
  SET NEW.final_score = (coursework_avg * 0.4) + (NEW.exam * 0.6);

  IF NEW.final_score < 45 THEN
    SET NEW.remarks = 'Fail';
    SET NEW.status = 'Fail';
  ELSEIF NEW.final_score < 65 THEN
    SET NEW.remarks = 'Pass';
    SET NEW.status = 'Pass';
  ELSEIF NEW.final_score < 75 THEN
    SET NEW.remarks = 'Credit';
    SET NEW.status = 'Pass';
  ELSE
    SET NEW.remarks = 'Distinction';
    SET NEW.status = 'Pass';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_grades_before_update`;
DELIMITER $$
CREATE TRIGGER `trg_grades_before_update` BEFORE UPDATE ON `grades` FOR EACH ROW BEGIN
  DECLARE coursework_avg DECIMAL(5,2);
  SET coursework_avg = (NEW.cw1 + NEW.cw2 + NEW.mid) / 3;
  SET NEW.final_score = (coursework_avg * 0.4) + (NEW.exam * 0.6);

  IF NEW.final_score < 45 THEN
    SET NEW.remarks = 'Fail';
    SET NEW.status = 'Fail';
  ELSEIF NEW.final_score < 65 THEN
    SET NEW.remarks = 'Pass';
    SET NEW.status = 'Pass';
  ELSEIF NEW.final_score < 75 THEN
    SET NEW.remarks = 'Credit';
    SET NEW.status = 'Pass';
  ELSE
    SET NEW.remarks = 'Distinction';
    SET NEW.status = 'Pass';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `grades_new`
--

DROP TABLE IF EXISTS `grades_new`;
CREATE TABLE IF NOT EXISTS `grades_new` (
  `id` int NOT NULL DEFAULT '0',
  `student_id` int NOT NULL,
  `course_id` int NOT NULL,
  `session_id` int NOT NULL,
  `class_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `grading_period_id` int NOT NULL,
  `cw1` int DEFAULT '0',
  `cw2` int DEFAULT '0',
  `mid` int DEFAULT '0',
  `exam` int DEFAULT '0',
  `final_score` varbinary(0) DEFAULT NULL,
  `grade_letter` varchar(2) DEFAULT NULL,
  `remarks` varchar(20) DEFAULT NULL,
  `status` enum('pass','fail','graduate') NOT NULL DEFAULT 'fail',
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_legend`
--

DROP TABLE IF EXISTS `grade_legend`;
CREATE TABLE IF NOT EXISTS `grade_legend` (
  `id` int NOT NULL AUTO_INCREMENT,
  `min_score` int NOT NULL,
  `max_score` int NOT NULL,
  `grade_letter` varchar(2) NOT NULL,
  `remarks` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `range_unique` (`min_score`,`max_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grading_calendar`
--

DROP TABLE IF EXISTS `grading_calendar`;
CREATE TABLE IF NOT EXISTS `grading_calendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grading_period_name` varchar(100) NOT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `academic_year_id` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `student_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `session_id` int NOT NULL,
  `class_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `student_id` (`student_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `session_id` (`session_id`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `student_id`, `academic_year_id`, `session_id`, `class_id`, `total_amount`, `created_at`) VALUES
(2, 'INV-JU-001', 17, 3, 4, 16, 500000.00, '2025-08-06 19:48:59'),
(3, 'INV-JU-003', 18, 3, 4, 17, 500000.00, '2025-08-06 19:49:20'),
(4, 'INV-JU-004', 24, 4, 6, 19, 500000.00, '2025-08-07 08:28:10'),
(5, 'INV-JU-005', 21, 4, 6, 18, 500000.00, '2025-08-07 08:28:25'),
(6, 'INV-JU-006', 22, 4, 6, 19, 500000.00, '2025-08-07 08:28:40'),
(7, 'INV-JU-007', 23, 4, 6, 19, 500000.00, '2025-08-07 08:29:16');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `invoice_id` int NOT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `receipt_number` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `fk_invoice` (`invoice_id`),
  KEY `fk_payments_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `student_id`, `invoice_id`, `session_id`, `academic_year_id`, `amount_paid`, `payment_date`, `receipt_number`, `created_at`) VALUES
(5, NULL, 3, 0, 0, 500000.00, '2025-08-06', 'JU501', '2025-08-06 19:50:01'),
(7, NULL, 2, 0, 0, 500000.00, '2025-08-06', 'JU502', '2025-08-06 21:41:36'),
(8, NULL, 7, 0, 0, 500000.00, '2025-08-07', 'JU510', '2025-08-07 08:29:39'),
(9, NULL, 6, 0, 0, 500000.00, '2025-08-07', 'JU511', '2025-08-07 08:29:59'),
(10, NULL, 5, 0, 0, 500000.00, '2025-08-07', '512', '2025-08-07 08:30:10'),
(11, NULL, 4, 0, 0, 500000.00, '2025-08-07', '513', '2025-08-07 08:30:20');

-- --------------------------------------------------------

--
-- Table structure for table `programmes`
--

DROP TABLE IF EXISTS `programmes`;
CREATE TABLE IF NOT EXISTS `programmes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `programme_name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `programme_name` (`programme_name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `programmes`
--

INSERT INTO `programmes` (`id`, `programme_name`, `code`, `is_active`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'Bachelor of Commerce in Business Administration', 'BBA', 1, 'superuser', 'superuser', '2025-07-30 15:15:16', '2025-07-30 15:19:30'),
(2, 'Bachelor of Commerce in Human Resources Management', 'BHRM', 1, 'superuser', NULL, '2025-07-30 15:20:45', '2025-07-30 15:20:45'),
(3, 'Bachelor of Science in Public Sector Management', 'PSM', 1, 'superuser', 'superuser', '2025-07-30 15:24:59', '2025-07-30 15:26:19'),
(4, 'Bachelor of Commerce (Accounting and Finance)', 'BAF', 1, 'superuser', NULL, '2025-07-30 15:26:37', '2025-07-30 15:26:37'),
(6, 'Bachelor of Science in Public Sector Accounting & Finance', 'PAF', 1, 'superuser', NULL, '2025-08-04 21:35:13', '2025-08-04 21:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

DROP TABLE IF EXISTS `registrations`;
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `session_id` int NOT NULL,
  `class_id` int NOT NULL,
  `year_of_study` int NOT NULL,
  `semester` varchar(10) NOT NULL,
  `exam_number` varchar(20) DEFAULT NULL,
  `registration_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `registered_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `session_id` (`session_id`),
  KEY `class_id` (`class_id`),
  KEY `registered_by` (`registered_by`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `registrations`
--
DROP TRIGGER IF EXISTS `trg_update_session_id`;
DELIMITER $$
CREATE TRIGGER `trg_update_session_id` AFTER UPDATE ON `registrations` FOR EACH ROW BEGIN
    IF NEW.session_id <> OLD.session_id THEN
        UPDATE registration_courses
        SET session_id = NEW.session_id
        WHERE registration_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `registration_courses`
--

DROP TABLE IF EXISTS `registration_courses`;
CREATE TABLE IF NOT EXISTS `registration_courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `registration_id` int NOT NULL,
  `class_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `course_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `session_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_id` (`registration_id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE IF NOT EXISTS `semesters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `semester_name` varchar(100) NOT NULL,
  `is_open` tinyint(1) DEFAULT '0',
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`id`, `session_id`, `semester_name`, `is_open`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(11, 6, 'Semester 1', 0, NULL, NULL, '2025-08-07 07:32:02', '2025-08-07 15:03:15'),
(12, 6, 'Semester 2', 0, NULL, NULL, '2025-08-07 07:32:02', '2025-08-07 15:03:16'),
(13, 7, 'Semester 1', 1, NULL, NULL, '2025-08-07 12:41:57', '2025-08-07 12:41:57'),
(14, 7, 'Semester 2', 1, NULL, NULL, '2025-08-07 12:41:57', '2025-08-07 12:41:57');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `academic_year_id` int NOT NULL,
  `session_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT '0',
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_year_id` (`academic_year_id`,`session_name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` enum('superuser','Lecturer','Dean','Student','Finance','Admission','librarian','Quality','Human Resource','Registrar','Vicechancellor','Systems Administrator') NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `full_name`, `email`, `phone`, `position`, `department`, `role`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(8, 'Peter Paul Mbisa', 'petermbisa1@gmail.com', '0985845712', 'Lecturer', 'Academic', 'Lecturer', 'superuser', NULL, '2025-08-04 18:28:01', '2025-08-04 20:28:01');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(25) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `sex` enum('Male','Female') NOT NULL,
  `date_of_birth` date NOT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `postal_address` text,
  `physical_address` text,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mode_of_study` enum('Normal','Evening','Weekend','Blended') NOT NULL,
  `next_of_kin_name` varchar(100) DEFAULT NULL,
  `next_of_kin_address` text,
  `next_of_kin_phone` varchar(20) DEFAULT NULL,
  `next_of_kin_email` varchar(100) DEFAULT NULL,
  `programme_id` int NOT NULL,
  `class_id` int NOT NULL,
  `year` int NOT NULL,
  `semester` enum('1','2') DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(255) NOT NULL,
  `updated_by` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `role` varchar(50) NOT NULL DEFAULT 'student',
  `user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registration_number` (`registration_number`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `programme_id` (`programme_id`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_academic_records`
--

DROP TABLE IF EXISTS `student_academic_records`;
CREATE TABLE IF NOT EXISTS `student_academic_records` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `qualification` varchar(50) DEFAULT NULL,
  `center_number` varchar(20) DEFAULT NULL,
  `exam_number` varchar(20) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `year` year DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_other_qualifications`
--

DROP TABLE IF EXISTS `student_other_qualifications`;
CREATE TABLE IF NOT EXISTS `student_other_qualifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `qualification_type` varchar(50) DEFAULT NULL,
  `institution` varchar(100) DEFAULT NULL,
  `year_of_award` year DEFAULT NULL,
  `work_experience_years` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_promotions`
--

DROP TABLE IF EXISTS `student_promotions`;
CREATE TABLE IF NOT EXISTS `student_promotions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `session_id` int NOT NULL,
  `academic_year_id` int NOT NULL,
  `old_class_id` int NOT NULL,
  `new_class_id` int NOT NULL,
  `promotion_status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`,`session_id`,`academic_year_id`)
) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

DROP TABLE IF EXISTS `timetable`;
CREATE TABLE IF NOT EXISTS `timetable` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `class_id` int NOT NULL,
  `mode` varchar(20) NOT NULL,
  `day` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `course_id` int NOT NULL,
  `classroom` varchar(50) DEFAULT NULL,
  `lecturer_initials` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `academic_year_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `session_id`, `class_id`, `mode`, `day`, `start_time`, `end_time`, `course_id`, `classroom`, `lecturer_initials`, `created_at`, `academic_year_id`) VALUES
(71, 1, 0, 'normal', 'Monday', '08:00:00', '09:00:00', 3, 'Room 1', 'AK', '2025-08-02 15:21:08', 2),
(72, 1, 0, 'normal', 'Monday', '09:00:00', '10:00:00', 9, 'Room 2', 'PM', '2025-08-02 15:21:08', 2),
(73, 1, 0, 'normal', 'Tuesday', '10:00:00', '12:00:00', 12, 'Room 5', 'JB', '2025-08-02 15:21:08', 2),
(74, 1, 0, 'normal', 'Friday', '15:00:00', '17:00:00', 13, 'Room 1', 'AK', '2025-08-02 15:21:08', 2),
(77, 4, 0, 'normal', 'Monday', '08:00:00', '09:00:00', 5, 'Room 1', 'PM', '2025-08-06 14:08:08', 3),
(78, 4, 0, 'weekend', 'Saturday', '08:00:00', '09:00:00', 3, 'Room 2', 'PM', '2025-08-06 14:08:27', 3);

-- --------------------------------------------------------

--
-- Table structure for table `university_profile`
--

DROP TABLE IF EXISTS `university_profile`;
CREATE TABLE IF NOT EXISTS `university_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `phone` varchar(50) DEFAULT NULL,
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `university_profile`
--

INSERT INTO `university_profile` (`id`, `name`, `address`, `phone`, `updated_by`, `updated_at`) VALUES
(1, 'Jubilee University', 'P.O. Box 645, Lilongwe', '+265 992 470 433', 'Super User', '2025-08-04 10:22:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `role` enum('superuser','admin','staff','registrar','vicechancellor','dean','lecturer','admissions','quality_assurance','human_resource','finance','librarian','student') NOT NULL DEFAULT 'staff',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `updated_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `force_password_change` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `role`, `created_by`, `created_at`, `is_active`, `updated_by`, `updated_at`, `force_password_change`) VALUES
(26, 'Peter Paul Mbisa', 'petermbisa1@gmail.com', '$2y$10$2mrS2a4Fm5r2Yldm.s3LH.xRbaC1vzzpAPBek89Y.lYd51OoU9saq', 'lecturer', 'superuser', '2025-08-04 18:28:01', 1, NULL, '2025-08-04 20:28:48', 0),
(32, 'Super User', 'superuser', '$2y$10$K6nAZRo9U9EVTZ2SLlthN.GjIyRSUlbLdWT377U4kH5lotInuCBwC', 'superuser', 'system', '2025-08-06 11:22:50', 1, NULL, '2025-08-06 13:23:40', 0),
(35, 'Kasha Kukada', 'BBA/10/01/01/05', '$2y$10$Ig3dOv2RgoaZMQF6Wewxy.dORHack9Uc56bAeBmpvzsaBcoxFrFdO', 'student', 'superuser', '2025-08-07 07:50:21', 1, NULL, '2025-08-07 07:52:05', 0),
(36, 'Harry Mande', 'BBA/10/01/01/06', '$2y$10$8P1NwBZ8pJ/245N2M6KV7.e4N.s8QLS2Oocuz12Wq5HFo56eWTcoe', 'student', 'superuser', '2025-08-07 07:51:38', 1, NULL, '2025-08-07 08:02:29', 0),
(37, 'Kelvin Kalua', 'BBA/10/01/01/07', '$2y$10$Vac0yxcXGo9RaolUQ7ZOfuVbmORUC23VjW8MnikWmbZOHGhpBDgZW', 'student', 'superuser', '2025-08-07 08:14:32', 1, NULL, '2025-08-07 08:15:58', 0),
(38, 'Robert Banya', 'BBA/10/01/01/08', '$2y$10$3QOvxzakYqgeGdFaQ4YOkO8nQ.FhX0JD5RE1QOSWhBS9of0UunOaa', 'student', 'superuser', '2025-08-07 08:25:12', 1, NULL, '2025-08-07 08:25:52', 0);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approved_grades`
--
ALTER TABLE `approved_grades`
  ADD CONSTRAINT `approved_grades_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_4` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_5` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_6` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approved_grades_ibfk_8` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `approved_grades_ibfk_grading_calendar` FOREIGN KEY (`grading_period_id`) REFERENCES `grading_calendar` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `fk_promotion_class` FOREIGN KEY (`promotion_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `fk_grading_period_id` FOREIGN KEY (`grading_period_id`) REFERENCES `grading_calendar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_4` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_5` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_7` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grading_calendar`
--
ALTER TABLE `grading_calendar`
  ADD CONSTRAINT `grading_calendar_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grading_calendar_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_4` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_5` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`),
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `students_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_academic_records`
--
ALTER TABLE `student_academic_records`
  ADD CONSTRAINT `student_academic_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_other_qualifications`
--
ALTER TABLE `student_other_qualifications`
  ADD CONSTRAINT `student_other_qualifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
