-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Mar 20, 2025 at 06:21 AM
-- Server version: 5.7.39
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `medical_appointments`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `status`, `notes`, `created_at`) VALUES
(2, 1, 1, '2025-03-16', '10:30:00', 'cancelled', 'Routine check-up', '2025-03-16 02:14:16'),
(3, 3, 1, '2025-03-26', '08:00:00', 'completed', 'I wanna make sure it works', '2025-03-16 02:08:45'),
(4, 2, 1, '2025-03-20', '12:00:00', 'scheduled', 'ahahsahdd', '2025-03-15 07:15:47'),
(5, 2, 1, '2025-03-15', '08:00:00', 'cancelled', 'pewee', '2025-03-15 07:16:38'),
(6, 1, 1, '2025-03-17', '09:00:00', 'scheduled', 'General check-up', '2025-03-16 06:45:47'),
(7, 2, 1, '2025-03-18', '11:00:00', 'scheduled', 'Follow-up visit', '2025-03-16 06:45:47'),
(8, 3, 1, '2025-03-19', '14:30:00', 'completed', 'Post-surgery review', '2025-03-16 06:45:47'),
(9, 1, 1, '2025-03-20', '10:15:00', 'scheduled', 'Blood test review', '2025-03-16 06:45:47'),
(10, 2, 1, '2025-03-21', '16:00:00', 'cancelled', 'Patient unavailable', '2025-03-16 06:45:47'),
(11, 3, 1, '2025-03-22', '12:45:00', 'scheduled', 'Routine screening', '2025-03-16 06:45:47'),
(12, 1, 1, '2025-03-23', '08:30:00', 'completed', 'Annual physical', '2025-03-16 06:45:47'),
(13, 2, 1, '2025-03-24', '13:15:00', 'scheduled', 'X-ray results discussion', '2025-03-16 06:45:47'),
(14, 3, 1, '2025-03-25', '15:45:00', 'cancelled', 'Rescheduled by patient', '2025-03-16 06:45:47'),
(15, 1, 1, '2025-03-26', '09:30:00', 'scheduled', 'Consultation', '2025-03-16 06:45:47'),
(16, 2, 11, '2025-03-24', '18:00:00', 'completed', 'test', '2025-03-16 22:27:58');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialty` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `first_name`, `last_name`, `email`, `phone`, `specialty`, `password_hash`, `created_at`) VALUES
(1, 'John', 'Doe', 'johndoe@example.com', '1234567890', 'Cardiologist', 'John', '2025-03-11 07:01:47'),
(2, 'Emily', 'Clark', 'eclark@example.com', '1112223333', 'Neurologist', 'password1', '2025-03-16 06:46:03'),
(3, 'Michael', 'Brown', 'mbrown@example.com', '2223334444', 'Pediatrician', 'password2', '2025-03-16 06:46:03'),
(4, 'Sarah', 'Lee', 'slee@example.com', '3334445555', 'Dermatologist', 'password3', '2025-03-16 06:46:03'),
(5, 'Robert', 'Taylor', 'rtaylor@example.com', '4445556666', 'Orthopedic Surgeon', 'password4', '2025-03-16 06:46:03'),
(6, 'Jessica', 'Miller', 'jmiller@example.com', '5556667777', 'Endocrinologist', 'password5', '2025-03-16 06:46:03'),
(7, 'David', 'Wilson', 'dwilson@example.com', '6667778888', 'Urologist', 'password6', '2025-03-16 06:46:03'),
(8, 'Laura', 'Martinez', 'lmartinez@example.com', '7778889999', 'Gynecologist', 'password7', '2025-03-16 06:46:03'),
(9, 'Daniel', 'Anderson', 'danderson@example.com', '8889990000', 'Oncologist', 'password8', '2025-03-16 06:46:03'),
(10, 'Sophia', 'Thomas', 'sthomas@example.com', '9990001111', 'Psychiatrist', 'password9', '2025-03-16 06:46:03'),
(11, 'James', 'Garcia', 'jgarcia@example.com', '0001112222', 'Pulmonologist', 'password10', '2025-03-16 06:46:03');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `content`, `is_read`, `created_at`) VALUES
(1, 1, 'Your appointment is scheduled for 2025-03-17 at 09:00 AM.', 1, '2025-03-16 06:46:23'),
(2, 2, 'Your appointment is scheduled for 2025-03-18 at 11:00 AM.', 1, '2025-03-16 06:46:23'),
(3, 3, 'Your appointment is completed on 2025-03-19.', 1, '2025-03-16 06:46:23'),
(4, 1, 'Reminder: Blood test review appointment on 2025-03-20.', 0, '2025-03-16 06:46:23'),
(5, 2, 'Appointment cancelled: 2025-03-21 at 16:00 PM.', 1, '2025-03-16 06:46:23'),
(6, 3, 'Your next check-up is scheduled for 2025-03-22.', 0, '2025-03-16 06:46:23'),
(7, 1, 'Annual physical completed on 2025-03-23.', 1, '2025-03-16 06:46:23'),
(8, 2, 'Reminder: X-ray discussion on 2025-03-24.', 1, '2025-03-16 06:46:23'),
(9, 3, 'Appointment rescheduled: 2025-03-25.', 1, '2025-03-16 06:46:23'),
(10, 1, 'Upcoming consultation on 2025-03-26.', 0, '2025-03-16 06:46:23');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `role` varchar(20) DEFAULT 'patient',
  `date_of_birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `email`, `phone`, `password_hash`, `created_at`, `role`, `date_of_birth`) VALUES
(1, 'Alice', 'Smith', 'alice@example.com', '9876543210', '$2y$10$ZJF2JIEz9gYo5nP9ISBopuyBZdN9c9.qEh4E5Ix.x8OF1HHBimjvK', '2025-03-11 07:02:37', 'patient', NULL),
(2, 'zouhair', 'youssef', 'zouhair@gmail.com', '0688000980', 'zouhair', '2025-03-12 00:27:37', 'patient', NULL),
(3, 'salma', 'salma', 'salma@gmail.com', '0688000980', 'Salma_2003', '2025-03-12 00:42:12', 'patient', NULL),
(4, 'Liam', 'Johnson', 'ljohnson@example.com', '1112223333', 'pass123', '2025-03-16 06:46:36', 'patient', NULL),
(5, 'Olivia', 'Williams', 'owilliams@example.com', '2223334444', 'pass234', '2025-03-16 06:46:36', 'patient', NULL),
(6, 'Noah', 'Brown', 'nbrown@example.com', '3334445555', 'pass345', '2025-03-16 06:46:36', 'patient', NULL),
(7, 'Emma', 'Davis', 'edavis@example.com', '4445556666', 'pass456', '2025-03-16 06:46:36', 'patient', NULL),
(8, 'William', 'Martinez', 'wmartinez@example.com', '5556667777', 'pass567', '2025-03-16 06:46:36', 'patient', NULL),
(9, 'Ava', 'Garcia', 'agarcia@example.com', '6667778888', 'pass678', '2025-03-16 06:46:36', 'patient', NULL),
(10, 'James', 'Rodriguez', 'jrodriguez@example.com', '7778889999', 'pass789', '2025-03-16 06:46:36', 'patient', NULL),
(11, 'Sophia', 'Lopez', 'slopez@example.com', '8889990000', 'pass890', '2025-03-16 06:46:36', 'patient', NULL),
(12, 'Benjamin', 'Hernandez', 'bhernandez@example.com', '9990001111', 'pass901', '2025-03-16 06:46:36', 'patient', NULL),
(13, 'Isabella', 'Gonzalez', 'igonzalez@example.com', '0001112222', 'pass012', '2025-03-16 06:46:36', 'patient', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `medication` text,
  `dosage` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `prescriptions`
--

INSERT INTO `prescriptions` (`id`, `doctor_id`, `patient_id`, `medication`, `dosage`, `created_at`) VALUES
(1, 1, 1, 'Amoxicillin', '500mg, twice a day', '2025-03-16 06:46:46'),
(2, 2, 2, 'Metformin', '850mg, once a day', '2025-03-16 06:46:46'),
(3, 3, 3, 'Lisinopril', '10mg, once a day', '2025-03-16 06:46:46'),
(4, 4, 1, 'Ibuprofen', '400mg, three times a day', '2025-03-16 06:46:46'),
(5, 5, 2, 'Paracetamol', '500mg, as needed', '2025-03-16 06:46:46'),
(6, 6, 3, 'Omeprazole', '20mg, before breakfast', '2025-03-16 06:46:46'),
(7, 7, 1, 'Aspirin', '81mg, once a day', '2025-03-16 06:46:46'),
(8, 8, 2, 'Atorvastatin', '40mg, once a day', '2025-03-16 06:46:46'),
(9, 9, 3, 'Prednisone', '5mg, tapering dose', '2025-03-16 06:46:46'),
(10, 10, 1, 'Ciprofloxacin', '500mg, twice a day for 7 days', '2025-03-16 06:46:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_appointments_date` (`appointment_date`),
  ADD KEY `idx_appointments_doctor` (`doctor_id`),
  ADD KEY `idx_appointments_patient` (`patient_id`),
  ADD KEY `idx_appointment_dates` (`appointment_date`),
  ADD KEY `idx_doctor_appointments` (`doctor_id`),
  ADD KEY `idx_patient_appointments` (`patient_id`),
  ADD KEY `idx_appointment_status` (`status`),
  ADD KEY `idx_appointment_doctor` (`doctor_id`),
  ADD KEY `idx_appointment_patient` (`patient_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
