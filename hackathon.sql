-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 14, 2025 at 04:13 PM
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
-- Database: `hackathon`
--

-- --------------------------------------------------------

--
-- Table structure for table `accomplishment`
--

CREATE TABLE `accomplishment` (
  `accomplishment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kra_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `month` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `reviewer_id` int(11) DEFAULT NULL,
  `review_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(10) DEFAULT NULL,
  `impact_score` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `accomplishment`
--

INSERT INTO `accomplishment` (`accomplishment_id`, `user_id`, `kra_id`, `description`, `month`, `status`, `reviewer_id`, `review_comments`, `created_at`, `file_path`, `file_type`, `impact_score`, `updated_at`) VALUES
(20, 1, 4, 'test', '0000-00-00', 'Approved', 2, NULL, '2025-04-14 12:02:53', NULL, NULL, NULL, '2025-04-14 12:03:05'),
(21, 1, 2, 'test', '0000-00-00', 'Approved', 2, NULL, '2025-04-14 12:05:03', 'uploads/67fcf9ef5d8b3_Screenshot 2025-04-13 230330.png', NULL, NULL, '2025-04-14 12:05:22'),
(22, 1, 4, 'test', '2025-04-01', 'Approved', 2, NULL, '2025-04-14 12:12:41', NULL, NULL, NULL, '2025-04-14 12:12:52'),
(23, 1, 4, 'test3', '2025-04-01', 'Pending', 2, NULL, '2025-04-14 12:29:19', NULL, NULL, NULL, '2025-04-14 12:29:19');

--
-- Triggers `accomplishment`
--
DELIMITER $$
CREATE TRIGGER `assign_reviewer` BEFORE INSERT ON `accomplishment` FOR EACH ROW BEGIN
    DECLARE dean_id INT;
    SELECT user_id INTO dean_id
    FROM users
    WHERE role = 'Dean' AND department_id = (
        SELECT department_id FROM users WHERE user_id = NEW.user_id
    )
    LIMIT 1;
    SET NEW.reviewer_id = dean_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_accomplishment_update` AFTER UPDATE ON `accomplishment` FOR EACH ROW BEGIN
    INSERT INTO accomplishment_log (
        accomplishment_id, user_id, action, old_status, new_status, comments
    )
    VALUES (
        OLD.accomplishment_id, OLD.reviewer_id, 'Status Update',
        OLD.status, NEW.status, NEW.review_comments
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accomplishment_log`
--

CREATE TABLE `accomplishment_log` (
  `log_id` int(11) NOT NULL,
  `accomplishment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` enum('Pending','Approved','Rejected') DEFAULT NULL,
  `new_status` enum('Pending','Approved','Rejected') DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accomplishment_log`
--

INSERT INTO `accomplishment_log` (`log_id`, `accomplishment_id`, `user_id`, `action`, `old_status`, `new_status`, `comments`, `created_at`) VALUES
(1, 20, 2, 'Status Update', 'Pending', 'Approved', NULL, '2025-04-14 12:03:05'),
(2, 21, 2, 'Status Update', 'Pending', 'Approved', NULL, '2025-04-14 12:05:22'),
(3, 22, 2, 'Status Update', 'Pending', 'Approved', NULL, '2025-04-14 12:12:52');

-- --------------------------------------------------------

--
-- Stand-in structure for view `dean_performance_summary`
-- (See below for the actual view)
--
CREATE TABLE `dean_performance_summary` (
`department_name` varchar(100)
,`kra_name` varchar(50)
,`total_accomplishments` bigint(21)
,`approved` decimal(22,0)
,`pending` decimal(22,0)
,`rejected` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`department_id`, `department_name`) VALUES
(1, 'BTLE'),
(2, 'BSE'),
(3, 'BEED'),
(4, 'BECED'),
(5, 'LS');

-- --------------------------------------------------------

--
-- Table structure for table `kra`
--

CREATE TABLE `kra` (
  `kra_id` int(11) NOT NULL,
  `kra_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kra`
--

INSERT INTO `kra` (`kra_id`, `kra_name`, `description`, `weight`) VALUES
(1, 'Instruction', 'Teaching-related activities', 0.40),
(2, 'Research', 'Publications, grants, etc.', 0.30),
(3, 'Extension', 'Community outreach', 0.15),
(4, 'Designation', 'Administrative roles', 0.10),
(5, 'Support', 'Support activities', 0.05),
(6, 'Strategic Initiatives', 'Institutional projects', 0.10);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `employee_number` varchar(20) NOT NULL,
  `role` enum('Faculty','Dean') NOT NULL,
  `department_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `employee_number`, `role`, `department_id`, `password_hash`) VALUES
(1, 'Daxton', 'Tabuan', '2022100179', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(2, 'Jamo', 'Omaj', '2022100027', 'Dean', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(3, 'Liam', 'Carter', '2022100200', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(4, 'Emma', 'Sullivan', '2022100201', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(5, 'Noah', 'Bennett', '2022100202', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(6, 'Olivia', 'Foster', '2022100203', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(7, 'Ava', 'Reynolds', '2022100204', 'Faculty', 1, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(8, 'Elijah', 'Morgan', '2022100205', 'Faculty', 2, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(9, 'Sophia', 'Hayes', '2022100206', 'Faculty', 2, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(10, 'James', 'Parker', '2022100207', 'Faculty', 2, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(11, 'Isabella', 'Coleman', '2022100208', 'Faculty', 2, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(12, 'Lucas', 'Simmons', '2022100209', 'Faculty', 2, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(13, 'Mia', 'Davidson', '2022100210', 'Faculty', 3, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(14, 'Henry', 'Wallace', '2022100211', 'Faculty', 3, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(15, 'Amelia', 'Porter', '2022100212', 'Faculty', 3, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(16, 'Alexander', 'Fisher', '2022100213', 'Faculty', 3, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(17, 'Harper', 'Gardner', '2022100214', 'Faculty', 3, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(18, 'Daniel', 'Hanson', '2022100215', 'Faculty', 4, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(19, 'Evelyn', 'Crawford', '2022100216', 'Faculty', 4, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(20, 'Michael', 'Barrett', '2022100217', 'Faculty', 4, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(21, 'Charlotte', 'Morrison', '2022100218', 'Faculty', 4, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(22, 'William', 'Fleming', '2022100219', 'Faculty', 4, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(23, 'Aria', 'Stevens', '2022100220', 'Faculty', 5, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(24, 'Benjamin', 'Lawson', '2022100221', 'Faculty', 5, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(25, 'Luna', 'Ramirez', '2022100222', 'Faculty', 5, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(26, 'Jackson', 'Wheeler', '2022100223', 'Faculty', 5, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S'),
(27, 'Mila', 'Hudson', '2022100224', 'Faculty', 5, '$2y$10$RHko17TEpSLB.Y6kszF4VOpyFPB/8M.3aN8emCPoNRaLij9ekvw5S');

-- --------------------------------------------------------

--
-- Structure for view `dean_performance_summary`
--
DROP TABLE IF EXISTS `dean_performance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `dean_performance_summary`  AS SELECT `d`.`department_name` AS `department_name`, `k`.`kra_name` AS `kra_name`, count(`a`.`accomplishment_id`) AS `total_accomplishments`, sum(case when `a`.`status` = 'Approved' then 1 else 0 end) AS `approved`, sum(case when `a`.`status` = 'Pending' then 1 else 0 end) AS `pending`, sum(case when `a`.`status` = 'Rejected' then 1 else 0 end) AS `rejected` FROM (((`accomplishment` `a` join `users` `u` on(`a`.`user_id` = `u`.`user_id`)) join `department` `d` on(`u`.`department_id` = `d`.`department_id`)) join `kra` `k` on(`a`.`kra_id` = `k`.`kra_id`)) GROUP BY `d`.`department_id`, `k`.`kra_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accomplishment`
--
ALTER TABLE `accomplishment`
  ADD PRIMARY KEY (`accomplishment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `kra_id` (`kra_id`),
  ADD KEY `reviewer_id` (`reviewer_id`);

--
-- Indexes for table `accomplishment_log`
--
ALTER TABLE `accomplishment_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `accomplishment_id` (`accomplishment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `kra`
--
ALTER TABLE `kra`
  ADD PRIMARY KEY (`kra_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `employee_number` (`employee_number`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accomplishment`
--
ALTER TABLE `accomplishment`
  MODIFY `accomplishment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accomplishment_log`
--
ALTER TABLE `accomplishment_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `kra`
--
ALTER TABLE `kra`
  MODIFY `kra_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accomplishment`
--
ALTER TABLE `accomplishment`
  ADD CONSTRAINT `accomplishment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `accomplishment_ibfk_2` FOREIGN KEY (`kra_id`) REFERENCES `kra` (`kra_id`),
  ADD CONSTRAINT `accomplishment_ibfk_3` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `accomplishment_log`
--
ALTER TABLE `accomplishment_log`
  ADD CONSTRAINT `accomplishment_log_ibfk_1` FOREIGN KEY (`accomplishment_id`) REFERENCES `accomplishment` (`accomplishment_id`),
  ADD CONSTRAINT `accomplishment_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
