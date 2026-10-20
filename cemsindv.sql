-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 14, 2026 at 10:58 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cemsindv`
--

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `organizer` varchar(255) NOT NULL,
  `organizer_email` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `mode` enum('physical','online','hybrid') DEFAULT 'physical',
  `reg_close_date` date DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `fee` enum('free','paid') DEFAULT 'free',
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `description`, `organizer`, `organizer_email`, `event_type`, `venue`, `event_date`, `event_time`, `mode`, `reg_close_date`, `max_participants`, `fee`, `fee_amount`, `contact_person`, `contact_no`, `remarks`, `status`, `created_by`, `created_at`, `last_updated`) VALUES
(1, 'Web Development Workshop', 'Learn HTML, CSS, and JavaScript basics', 'IT Department', 'staff@example.com', 'workshop', 'Computer Lab 1', '2026-01-09', '14:00:00', 'physical', '2026-01-07', 30, 'free', 0.00, 'Dr. Smith', '012-3456789', NULL, 'active', 2, '2026-01-02 07:15:30', '2026-01-02 07:15:30'),
(2, 'Sports Carnival 2025', 'Annual university sports competition', 'Sports Club', 'student@example.com', 'sport', 'University Stadium', '2026-01-16', '09:00:00', 'physical', '2026-01-12', 100, 'free', 0.00, 'Mr. Johnson', '012-9876543', NULL, 'active', 1, '2026-01-02 07:15:30', '2026-01-02 07:29:49'),
(3, 'Hackathon 2026', 'To enhanced student\'s skill in code, brainstorm and pitch', '', '', '', 'FKI', '2026-04-23', '08:30:00', 'physical', NULL, 45, 'free', 0.00, NULL, NULL, NULL, 'active', 5, '2026-01-02 07:19:33', '2026-01-02 08:57:20'),
(4, 'Chess Competition', 'The top 3 winner will be representing UMS to national level', '', '', '', 'DKP Baru', '2026-04-24', '10:00:00', 'physical', NULL, 20, 'free', 0.00, NULL, NULL, NULL, 'active', 4, '2026-01-02 11:52:23', '2026-01-02 11:52:23');

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','cancelled','pending_payment') DEFAULT 'registered',
  `payment_method` varchar(50) DEFAULT NULL,
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registrations`
--

INSERT INTO `event_registrations` (`id`, `event_id`, `user_id`, `user_email`, `user_name`, `registration_date`, `status`, `payment_method`, `comments`) VALUES
(1, 3, 5, 'staff@campusbloom.edu', 'Demo Staff', '2026-01-02 07:20:26', 'registered', NULL, ''),
(2, 2, 6, 'public@campusbloom.edu', 'Demo Public', '2026-01-02 07:29:49', 'registered', NULL, ''),
(3, 3, 4, 'student@campusbloom.edu', 'Demo Student', '2026-01-02 08:57:20', 'registered', NULL, ''),
(4, 1, 5, 'staff@campusbloom.edu', 'Demo Staff', '2026-01-02 11:34:05', 'cancelled', NULL, ''),
(5, 2, 5, 'staff@campusbloom.edu', 'Demo Staff', '2026-01-04 17:58:34', 'registered', NULL, ''),
(6, 2, 4, 'student@campusbloom.edu', 'Demo Student', '2026-01-06 08:08:56', 'registered', NULL, ''),
(7, 4, 4, 'student@campusbloom.edu', 'Demo Student', '2026-01-14 09:36:26', 'registered', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_type` varchar(50) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT 'pending',
  `amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('public','student','staff') DEFAULT 'public',
  `organization` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `organization`, `phone`, `created_at`) VALUES
(1, 'Student User', 'student@example.com', '$2y$10$0xrkURvfBWyWD8Bc8eSD5uraQKLCi8jW/idA857EJxL5eyQz2HP0i', 'student', 'University Malaysia Sabah', '012-3456789', '2026-01-02 07:15:30'),
(2, 'Staff User', 'staff@example.com', '$2y$10$0xrkURvfBWyWD8Bc8eSD5uraQKLCi8jW/idA857EJxL5eyQz2HP0i', 'staff', 'University Malaysia Sabah', '012-9876543', '2026-01-02 07:15:30'),
(3, 'Public User', 'public@example.com', '$2y$10$0xrkURvfBWyWD8Bc8eSD5uraQKLCi8jW/idA857EJxL5eyQz2HP0i', 'public', 'Public Organization', '012-1112233', '2026-01-02 07:15:30'),
(4, 'Demo Student', 'student@campusbloom.edu', '$2y$10$QgQdVROcOLZ270uBxGqtY.IiD8XFmGHpAknCorA47Ew3klxzXcsQ2', 'student', 'University Malaysia Sabah', '+6012-3456789', '2026-01-02 07:17:42'),
(5, 'Demo Staff', 'staff@campusbloom.edu', '$2y$10$aZyHPPBwOZ.CXIhQwkyI6O8nULU5aKSlOqv9esVFC1IS6.2rktQyu', 'staff', 'University Malaysia Sabah', '+6012-3456790', '2026-01-02 07:17:43'),
(6, 'Demo Public', 'public@campusbloom.edu', '$2y$10$Xb86G/p9MMC8t1IUWO78QuM2gUjLiU/LsQ9wxlMJhMngdbrmhuQyO', 'public', 'General Public', '+6012-3456791', '2026-01-02 07:17:43'),
(7, 'abigail sadom', 'student7@example.com', '$2y$10$sbzEtcQLiIe4Yf.M.M4/YOnkaiaaPGmfpT/ZRSW/vrfyjs4cyFP1.', 'student', 'FKI', '012-0000000', '2026-01-02 11:55:34'),
(8, 'abigail sadom', 'ab@cems.edu', '$2y$10$b1TyhHirtVZbDOEuxoiy2.UGweAQCFksZ16BQCRlRO7aLVmBKn61O', 'student', 'FKI', '012-0000000', '2026-01-02 11:56:39'),
(9, 'Abigail', 'abigail@cems.edu', '$2y$10$fZXMR502x1dac6UweFzkV.qCfwKKhmgdDcF0Af/hTZvX4JhMDtUn2', 'student', 'FKI', '012-0000000', '2026-01-02 12:00:00'),
(10, 'abigail', 'abigailal@cems.edu', '$2y$10$6PYXg1vthQRkGHIZm3A4XOVMu/dxEPSmL/b6y2KqJqdczglVoq67q', 'student', 'FKI', '012-0000000', '2026-01-02 12:02:17'),
(11, 'abigail sadom', 'abgails@cems.edu', '$2y$10$SkK9Vz7kdyzUPvG9pTlOyORVuaNTtJeD8tQzrM6PE.CryJN.MIG2q', 'student', 'FKI', '012-0000000', '2026-01-02 05:27:59'),
(12, 'abigail sadom', 'abgailsadd@cems.edu', '$2y$10$pD1f9Ln7LffzEm/zEmSZZepcbYCj2k7SKSgf/LUK2Zy4aJydXORmG', 'student', 'FKI', '012-0000000', '2026-01-02 05:28:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_registration` (`event_id`,`user_email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
