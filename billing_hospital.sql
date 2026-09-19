-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 16, 2026 at 09:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `billing_hospital`
--

-- --------------------------------------------------------

--
-- Table structure for table `admission`
--

CREATE TABLE `admission` (
  `admission_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `admission_datetime` datetime NOT NULL,
  `discharge_datetime` datetime DEFAULT NULL,
  `chief_complaint` varchar(255) DEFAULT NULL,
  `admission_type` varchar(50) DEFAULT NULL,
  `total_room_transfers` int(11) NOT NULL DEFAULT 0,
  `admitted_by_user_id` int(11) DEFAULT NULL,
  `discharged_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admission_diagnosis`
--

CREATE TABLE `admission_diagnosis` (
  `admission_diagnosis_id` int(11) NOT NULL,
  `admission_id` int(11) NOT NULL,
  `diagnosis_id` int(11) NOT NULL,
  `diagnosis_type` varchar(50) DEFAULT NULL,
  `diagnosed_datetime` datetime NOT NULL,
  `diagnosed_by_doctor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admission_doctor`
--

CREATE TABLE `admission_doctor` (
  `admission_doctor_id` int(11) NOT NULL,
  `admission_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `doctor_role` varchar(100) DEFAULT NULL,
  `assigned_datetime` datetime NOT NULL,
  `ended_datetime` datetime DEFAULT NULL,
  `consultation_fee_charged` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admission_status`
--

CREATE TABLE `admission_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `color_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_status`
--

INSERT INTO `admission_status` (`status_id`, `status_name`, `color_code`) VALUES
(1, 'Admitted', '#2a64c0'),
(2, 'Discharged', '#5cbe27'),
(3, 'Transferred', '#f73b3b'),
(4, 'Ready for Discharge', '#10b981');

-- --------------------------------------------------------

--
-- Table structure for table `billing_statement`
--

CREATE TABLE `billing_statement` (
  `statement_id` int(11) NOT NULL,
  `admission_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `statement_date` datetime NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `insurance_coverage_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `government_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_status`
--

CREATE TABLE `billing_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `color_code` varchar(20) DEFAULT NULL,
  `is_paid_status` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_status`
--

INSERT INTO `billing_status` (`status_id`, `status_name`, `color_code`, `is_paid_status`) VALUES
(1, 'Pending', '#d3a51d', 0),
(2, 'Partially Paid', '#17a2b8', 0),
(3, 'Paid', '#28a745', 1),
(4, 'Overdue', '#dc3545', 0);

-- --------------------------------------------------------

--
-- Table structure for table `charge`
--

CREATE TABLE `charge` (
  `charge_id` int(11) NOT NULL,
  `statement_id` int(11) NOT NULL,
  `charge_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `actual_price` decimal(10,2) NOT NULL,
  `charge_datetime` datetime NOT NULL,
  `processed_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `service_start_date` date DEFAULT NULL,
  `service_end_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `charge_category`
--

CREATE TABLE `charge_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `calculation_type` varchar(50) DEFAULT NULL,
  `is_recurring` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `charge_category`
--

INSERT INTO `charge_category` (`category_id`, `category_name`, `description`, `calculation_type`, `is_recurring`) VALUES
(1, 'Room Charges', 'Daily room accommodation fees', 'Per Item', 1),
(2, 'Laboratory', 'Diagnostic and lab test fees', 'Per Item', 0),
(3, 'Medication', 'Pharmacy and medicine dispensing', 'Per Item', 0),
(4, 'Professional Fee', 'Doctor consultation / service fees', 'Flat', 0);

-- --------------------------------------------------------

--
-- Table structure for table `charge_item`
--

CREATE TABLE `charge_item` (
  `charge_item_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `default_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_taxable` tinyint(1) NOT NULL DEFAULT 0,
  `requires_doctor_order` tinyint(1) NOT NULL DEFAULT 0,
  `unit_of_measure` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `charge_item`
--

INSERT INTO `charge_item` (`charge_item_id`, `category_id`, `item_code`, `item_name`, `default_price`, `is_active`, `is_taxable`, `requires_doctor_order`, `unit_of_measure`) VALUES
(1, 1, 'ROOM-PVT', 'Private Room per Day', 1500.00, 1, 0, 0, 'day'),
(2, 1, 'ROOM-ICU', 'ICU Room per Day', 3000.00, 1, 0, 0, 'day'),
(3, 2, 'LAB-CBC', 'Complete Blood Count', 350.00, 1, 1, 1, 'test'),
(4, 2, 'LAB-XRAY', 'X-Ray, Chest', 800.00, 1, 1, 1, 'test'),
(5, 3, 'MED-PARA500', 'Paracetamol 500mg (tablet)', 5.00, 1, 1, 0, 'tablet'),
(6, 4, 'PROF-CONSULT', 'Doctor Consultation Fee', 500.00, 1, 0, 0, 'visit');

-- --------------------------------------------------------

--
-- Table structure for table `consultation`
--

CREATE TABLE `consultation` (
  `consultation_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `consultation_datetime` datetime NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diagnosis`
--

CREATE TABLE `diagnosis` (
  `diagnosis_id` int(11) NOT NULL,
  `icd_code` varchar(50) DEFAULT NULL,
  `diagnosis_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diagnosis`
--

INSERT INTO `diagnosis` (`diagnosis_id`, `icd_code`, `diagnosis_name`, `description`, `is_active`) VALUES
(1, 'I10', 'Essential Hypertension', 'High blood pressure with no identifiable cause.', 0),
(2, 'J45', 'Asthma', 'Chronic inflammatory airway disease.', 1),
(3, 'S72.0', 'Fracture of Femur', 'Closed fracture of the neck of femur.', 1),
(4, 'E11', 'Type 2 Diabetes Mellitus', 'Non-insulin-dependent diabetes.', 1),
(5, 'J18', 'Pneumonia, unspecified', 'Inflammation of the lung tissue.', 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `doctor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(100) NOT NULL,
  `consultation_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`doctor_id`, `user_id`, `license_number`, `consultation_fee`, `is_active`) VALUES
(1, 2, 'PRC-100234', 500.00, 1),
(2, 9, '1231', 700.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_specialization`
--

CREATE TABLE `doctor_specialization` (
  `doctor_specialization_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `specialization_id` int(11) NOT NULL,
  `is_primary` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_specialization`
--

INSERT INTO `doctor_specialization` (`doctor_specialization_id`, `doctor_id`, `specialization_id`, `is_primary`) VALUES
(3, 1, 1, 'Yes'),
(4, 1, 4, 'No');

-- --------------------------------------------------------

--
-- Table structure for table `gender`
--

CREATE TABLE `gender` (
  `gender_id` int(11) NOT NULL,
  `gender_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gender`
--

INSERT INTO `gender` (`gender_id`, `gender_name`) VALUES
(1, 'Male'),
(2, 'Female'),
(3, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `patient_id` int(11) NOT NULL,
  `gender_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `emergency_contact` varchar(150) DEFAULT NULL,
  `emergency_contact_number` varchar(30) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `payment_id` int(11) NOT NULL,
  `statement_id` int(11) NOT NULL,
  `payment_type_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_datetime` datetime NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `received_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_type`
--

CREATE TABLE `payment_type` (
  `payment_type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_type`
--

INSERT INTO `payment_type` (`payment_type_id`, `type_name`, `description`) VALUES
(1, 'Cash', 'Paid in physical currency'),
(2, 'Credit Card', 'Paid via credit/debit card terminal'),
(3, 'Insurance', 'Covered by an insurance provider'),
(4, 'Bank Transfer', 'Paid via direct bank transfer');

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'Full system access and configuration'),
(2, 'doctor', 'Attending / consulting physician'),
(3, 'nurse', 'Ward and bedside care staff'),
(4, 'cashier', 'Handles invoicing and payments');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `room_id` int(11) NOT NULL,
  `room_type_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `floor_level` int(11) DEFAULT NULL,
  `building` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`room_id`, `room_type_id`, `status_id`, `room_number`, `floor_level`, `building`, `is_active`) VALUES
(1, 5, 1, '101', 0, 'Main Building', 1),
(2, 2, 1, '201', 2, 'Main Building', 1),
(3, 3, 1, 'ICU-1', 3, 'Main Building', 1),
(4, 1, 1, '102', 1, 'Main Building', 1),
(5, 2, 1, '202', 2, 'Main Building', 1),
(6, 3, 1, 'asd', 0, 'ambo', 1);

-- --------------------------------------------------------

--
-- Table structure for table `room_assignment`
--

CREATE TABLE `room_assignment` (
  `room_assignment_id` int(11) NOT NULL,
  `admission_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `daily_rate_at_assignment` decimal(10,2) NOT NULL,
  `transfer_reason` varchar(255) DEFAULT NULL,
  `transferred_by_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_status`
--

CREATE TABLE `room_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `color_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_status`
--

INSERT INTO `room_status` (`status_id`, `status_name`, `color_code`) VALUES
(1, 'Available', '#36bc34'),
(2, 'Occupied', '#dc3545'),
(3, 'Maintenance', '#ffc107'),
(4, 'Reserved', '#17a2b8');

-- --------------------------------------------------------

--
-- Table structure for table `room_type`
--

CREATE TABLE `room_type` (
  `room_type_id` int(11) NOT NULL,
  `room_type_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `rate_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `capacity` int(11) NOT NULL,
  `includes_meals` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_type`
--

INSERT INTO `room_type` (`room_type_id`, `room_type_name`, `description`, `rate_per_day`, `capacity`, `includes_meals`, `created_at`, `is_active`) VALUES
(1, 'Ward', 'Shared ward accommodation', 500.00, 4, 1, '2026-09-14 10:02:54', 1),
(2, 'Private', 'Single-occupancy private room', 1500.00, 1, 1, '2026-09-14 10:02:54', 1),
(3, 'ICU', 'Intensive care unit', 3000.00, 1, 0, '2026-09-14 10:02:54', 1),
(4, 'ambot', 'ambot', 123.00, 1, 0, '2026-09-15 08:45:24', 1),
(5, 'ambotnimo', 'nimo', 1500.00, 1, 1, '2026-09-15 09:06:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `service_request`
--

CREATE TABLE `service_request` (
  `request_id` int(11) NOT NULL,
  `admission_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `charge_item_id` int(11) NOT NULL,
  `request_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `specialization`
--

CREATE TABLE `specialization` (
  `specialization_id` int(11) NOT NULL,
  `specialization_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `specialization`
--

INSERT INTO `specialization` (`specialization_id`, `specialization_name`) VALUES
(1, 'Cardiology'),
(2, 'Pediatrics'),
(3, 'Orthopedics'),
(4, 'General Medicine'),
(5, 'Pulmonology');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `role_id`, `username`, `password_hash`, `first_name`, `last_name`, `email`, `contact_number`, `created_at`, `is_active`) VALUES
(1, 1, 'admin.garcia', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Marissa', 'Garcia', 'admin@gmail.com', '0917-000-0001', '2026-09-14 10:02:54', 1),
(2, 2, 'dr.santos', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Rafaels', 'Santos', 'doctor@gmail.com', '0917-000-0002', '2026-09-14 10:02:54', 1),
(3, 3, 'nurse.aquino', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Amy', 'Aquino', 'nurse@gmail.com', '0917-000-0005', '2026-09-14 10:02:54', 1),
(4, 4, 'billing.torres', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Bianca', 'Torres', 'cashier@gmail.com', '0917-000-0006', '2026-09-14 10:02:54', 1),
(5, 3, 'nurse.reyes', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Corazon', 'Reyes', 'nurse2@gmail.com', '0917-000-0007', '2026-09-14 10:02:54', 1),
(6, 4, 'cashier.lim', '$2y$10$qe0m16woi2CfoDCDsisJneXBPUHFfNi4TRTDn.vNH40B2rFf7JqIe', 'Dennis', 'Lim', 'cashier2@gmail.com', '0917-000-0008', '2026-09-14 10:02:54', 1),
(7, 1, 'akshe', '$2y$10$C1F7VK9VqOveozLWttU5DuWjaMq37jWGJSg2W.G.trk2Vb6uW7MKu', 'vladimer', 'tuyor', 'powerless177@gmail.com', '09295413954', '2026-09-14 18:46:03', 0),
(8, 2, 'asda', '$2y$10$mMZDmPJ9y3pO4VHDrYO2ue74OVdypanMZetrqzWMEw9INm9owNk3i', 'vladimer', 'tuyor', 'powerless17327@gmail.com', '09295413954', '2026-09-14 21:08:55', 1),
(9, 2, 'dmsada', '$2y$10$3He2C.fBjmDkCw77l.OGpes9C2Gv/NwA2t..xl9d9ieR2aTd7O0WO', 'try', 'ambot', 'powerles32s177@gmail.com', '09295413954', '2026-09-14 21:13:47', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admission`
--
ALTER TABLE `admission`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `fk_admission_patient` (`patient_id`),
  ADD KEY `fk_admission_status` (`status_id`),
  ADD KEY `fk_admission_admitted_by` (`admitted_by_user_id`),
  ADD KEY `fk_admission_discharged_by` (`discharged_by_user_id`);

--
-- Indexes for table `admission_diagnosis`
--
ALTER TABLE `admission_diagnosis`
  ADD PRIMARY KEY (`admission_diagnosis_id`),
  ADD KEY `fk_admission_diagnosis_admission` (`admission_id`),
  ADD KEY `fk_admission_diagnosis_diagnosis` (`diagnosis_id`),
  ADD KEY `fk_admission_diagnosis_doctor` (`diagnosed_by_doctor_id`);

--
-- Indexes for table `admission_doctor`
--
ALTER TABLE `admission_doctor`
  ADD PRIMARY KEY (`admission_doctor_id`),
  ADD KEY `fk_admission_doctor_admission` (`admission_id`),
  ADD KEY `fk_admission_doctor_doctor` (`doctor_id`);

--
-- Indexes for table `admission_status`
--
ALTER TABLE `admission_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `billing_statement`
--
ALTER TABLE `billing_statement`
  ADD PRIMARY KEY (`statement_id`),
  ADD KEY `fk_billing_statement_admission` (`admission_id`),
  ADD KEY `fk_billing_statement_status` (`status_id`),
  ADD KEY `fk_billing_statement_user` (`created_by_user_id`);

--
-- Indexes for table `billing_status`
--
ALTER TABLE `billing_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `charge`
--
ALTER TABLE `charge`
  ADD PRIMARY KEY (`charge_id`),
  ADD KEY `fk_charge_statement` (`statement_id`),
  ADD KEY `fk_charge_item` (`charge_item_id`),
  ADD KEY `fk_charge_user` (`processed_by_user_id`);

--
-- Indexes for table `charge_category`
--
ALTER TABLE `charge_category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `charge_item`
--
ALTER TABLE `charge_item`
  ADD PRIMARY KEY (`charge_item_id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `fk_charge_item_category` (`category_id`);

--
-- Indexes for table `consultation`
--
ALTER TABLE `consultation`
  ADD PRIMARY KEY (`consultation_id`),
  ADD KEY `fk_consultation_patient` (`patient_id`),
  ADD KEY `fk_consultation_doctor` (`doctor_id`);

--
-- Indexes for table `diagnosis`
--
ALTER TABLE `diagnosis`
  ADD PRIMARY KEY (`diagnosis_id`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`doctor_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `license_number` (`license_number`);

--
-- Indexes for table `doctor_specialization`
--
ALTER TABLE `doctor_specialization`
  ADD PRIMARY KEY (`doctor_specialization_id`),
  ADD UNIQUE KEY `uq_doctor_specialization` (`doctor_id`,`specialization_id`),
  ADD KEY `fk_doctor_specialization_specialization` (`specialization_id`);

--
-- Indexes for table `gender`
--
ALTER TABLE `gender`
  ADD PRIMARY KEY (`gender_id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `fk_patient_gender` (`gender_id`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_statement` (`statement_id`),
  ADD KEY `fk_payment_type` (`payment_type_id`),
  ADD KEY `fk_payment_user` (`received_by_user_id`);

--
-- Indexes for table `payment_type`
--
ALTER TABLE `payment_type`
  ADD PRIMARY KEY (`payment_type_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `fk_room_type` (`room_type_id`),
  ADD KEY `fk_room_status` (`status_id`);

--
-- Indexes for table `room_assignment`
--
ALTER TABLE `room_assignment`
  ADD PRIMARY KEY (`room_assignment_id`),
  ADD KEY `fk_room_assignment_admission` (`admission_id`),
  ADD KEY `fk_room_assignment_room` (`room_id`),
  ADD KEY `fk_room_assignment_user` (`transferred_by_user_id`);

--
-- Indexes for table `room_status`
--
ALTER TABLE `room_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `room_type`
--
ALTER TABLE `room_type`
  ADD PRIMARY KEY (`room_type_id`);

--
-- Indexes for table `service_request`
--
ALTER TABLE `service_request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_service_request_admission` (`admission_id`),
  ADD KEY `fk_service_request_doctor` (`doctor_id`),
  ADD KEY `fk_service_request_charge_item` (`charge_item_id`);

--
-- Indexes for table `specialization`
--
ALTER TABLE `specialization`
  ADD PRIMARY KEY (`specialization_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admission`
--
ALTER TABLE `admission`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `admission_diagnosis`
--
ALTER TABLE `admission_diagnosis`
  MODIFY `admission_diagnosis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `admission_doctor`
--
ALTER TABLE `admission_doctor`
  MODIFY `admission_doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `admission_status`
--
ALTER TABLE `admission_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `billing_statement`
--
ALTER TABLE `billing_statement`
  MODIFY `statement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `billing_status`
--
ALTER TABLE `billing_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `charge`
--
ALTER TABLE `charge`
  MODIFY `charge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `charge_category`
--
ALTER TABLE `charge_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `charge_item`
--
ALTER TABLE `charge_item`
  MODIFY `charge_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `consultation`
--
ALTER TABLE `consultation`
  MODIFY `consultation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `diagnosis`
--
ALTER TABLE `diagnosis`
  MODIFY `diagnosis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `doctor_specialization`
--
ALTER TABLE `doctor_specialization`
  MODIFY `doctor_specialization_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `gender`
--
ALTER TABLE `gender`
  MODIFY `gender_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `payment_type`
--
ALTER TABLE `payment_type`
  MODIFY `payment_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `room_assignment`
--
ALTER TABLE `room_assignment`
  MODIFY `room_assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `room_status`
--
ALTER TABLE `room_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_type`
--
ALTER TABLE `room_type`
  MODIFY `room_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `service_request`
--
ALTER TABLE `service_request`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `specialization`
--
ALTER TABLE `specialization`
  MODIFY `specialization_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission`
--
ALTER TABLE `admission`
  ADD CONSTRAINT `fk_admission_admitted_by` FOREIGN KEY (`admitted_by_user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_admission_discharged_by` FOREIGN KEY (`discharged_by_user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_admission_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`),
  ADD CONSTRAINT `fk_admission_status` FOREIGN KEY (`status_id`) REFERENCES `admission_status` (`status_id`);

--
-- Constraints for table `admission_diagnosis`
--
ALTER TABLE `admission_diagnosis`
  ADD CONSTRAINT `fk_admission_diagnosis_admission` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`),
  ADD CONSTRAINT `fk_admission_diagnosis_diagnosis` FOREIGN KEY (`diagnosis_id`) REFERENCES `diagnosis` (`diagnosis_id`),
  ADD CONSTRAINT `fk_admission_diagnosis_doctor` FOREIGN KEY (`diagnosed_by_doctor_id`) REFERENCES `doctor` (`doctor_id`);

--
-- Constraints for table `admission_doctor`
--
ALTER TABLE `admission_doctor`
  ADD CONSTRAINT `fk_admission_doctor_admission` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`),
  ADD CONSTRAINT `fk_admission_doctor_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`);

--
-- Constraints for table `billing_statement`
--
ALTER TABLE `billing_statement`
  ADD CONSTRAINT `fk_billing_statement_admission` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`),
  ADD CONSTRAINT `fk_billing_statement_status` FOREIGN KEY (`status_id`) REFERENCES `billing_status` (`status_id`),
  ADD CONSTRAINT `fk_billing_statement_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `charge`
--
ALTER TABLE `charge`
  ADD CONSTRAINT `fk_charge_item` FOREIGN KEY (`charge_item_id`) REFERENCES `charge_item` (`charge_item_id`),
  ADD CONSTRAINT `fk_charge_statement` FOREIGN KEY (`statement_id`) REFERENCES `billing_statement` (`statement_id`),
  ADD CONSTRAINT `fk_charge_user` FOREIGN KEY (`processed_by_user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `charge_item`
--
ALTER TABLE `charge_item`
  ADD CONSTRAINT `fk_charge_item_category` FOREIGN KEY (`category_id`) REFERENCES `charge_category` (`category_id`);

--
-- Constraints for table `consultation`
--
ALTER TABLE `consultation`
  ADD CONSTRAINT `fk_consultation_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`),
  ADD CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`patient_id`);

--
-- Constraints for table `doctor`
--
ALTER TABLE `doctor`
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `doctor_specialization`
--
ALTER TABLE `doctor_specialization`
  ADD CONSTRAINT `fk_doctor_specialization_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`),
  ADD CONSTRAINT `fk_doctor_specialization_specialization` FOREIGN KEY (`specialization_id`) REFERENCES `specialization` (`specialization_id`);

--
-- Constraints for table `patient`
--
ALTER TABLE `patient`
  ADD CONSTRAINT `fk_patient_gender` FOREIGN KEY (`gender_id`) REFERENCES `gender` (`gender_id`);

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `fk_payment_statement` FOREIGN KEY (`statement_id`) REFERENCES `billing_statement` (`statement_id`),
  ADD CONSTRAINT `fk_payment_type` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_type` (`payment_type_id`),
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`received_by_user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `room`
--
ALTER TABLE `room`
  ADD CONSTRAINT `fk_room_status` FOREIGN KEY (`status_id`) REFERENCES `room_status` (`status_id`),
  ADD CONSTRAINT `fk_room_type` FOREIGN KEY (`room_type_id`) REFERENCES `room_type` (`room_type_id`);

--
-- Constraints for table `room_assignment`
--
ALTER TABLE `room_assignment`
  ADD CONSTRAINT `fk_room_assignment_admission` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`),
  ADD CONSTRAINT `fk_room_assignment_room` FOREIGN KEY (`room_id`) REFERENCES `room` (`room_id`),
  ADD CONSTRAINT `fk_room_assignment_user` FOREIGN KEY (`transferred_by_user_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `service_request`
--
ALTER TABLE `service_request`
  ADD CONSTRAINT `fk_service_request_admission` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`),
  ADD CONSTRAINT `fk_service_request_charge_item` FOREIGN KEY (`charge_item_id`) REFERENCES `charge_item` (`charge_item_id`),
  ADD CONSTRAINT `fk_service_request_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`doctor_id`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;