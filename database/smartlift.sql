-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 28, 2025 at 06:18 PM
-- Server version: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartlift`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_calls`
--

CREATE TABLE `app_calls` (
  `id` int(11) NOT NULL,
  `lift_id` int(11) NOT NULL,
  `floor_no` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT '1',
  `direction` char(1) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'U',
  `client_id` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `is_processed` enum('N','Y') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'N',
  `created_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup_codes`
--

CREATE TABLE `backup_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `backup_codes`
--

INSERT INTO `backup_codes` (`id`, `user_id`, `code`, `is_used`, `created_at`, `used_at`) VALUES
(1, 1, '4FC0BD2C30', 0, '2025-09-27 00:05:37', NULL),
(2, 1, '7296462124', 0, '2025-09-27 00:05:37', NULL),
(3, 1, 'FFC02C4A55', 0, '2025-09-27 00:05:37', NULL),
(4, 1, 'EF8F4F1B76', 0, '2025-09-27 00:05:37', NULL),
(5, 1, '842A2A02F1', 0, '2025-09-27 00:05:37', NULL),
(6, 1, '8209C08029', 0, '2025-09-27 00:05:37', NULL),
(7, 1, 'B911478F05', 0, '2025-09-27 00:05:37', NULL),
(8, 1, '4B6B3EF895', 0, '2025-09-27 00:05:37', NULL),
(9, 1, 'C468E448F6', 0, '2025-09-27 00:05:37', NULL),
(10, 1, 'CB8A27A8AF', 0, '2025-09-27 00:05:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `building_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `address` text COLLATE utf8_unicode_ci,
  `created_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `update_user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `org_id`, `building_name`, `description`, `address`, `created_user_id`, `created_at`, `update_user_id`, `updated_at`) VALUES
(1, 1, 'อาคาร 19', NULL, NULL, 1, '2023-10-07 15:16:25', 1, '2023-10-07 15:16:25'),
(2, 2, 'อาคาร 1 เทคนิคสกล', NULL, NULL, 1, '2023-10-07 15:21:12', 1, '2023-10-07 15:21:12'),
(3, 3, 'อาคารเย็นศิระ', NULL, NULL, 1, '2023-10-07 15:21:35', 1, '2023-10-07 15:21:35'),
(4, 1, 'อาคาร 1', NULL, NULL, 1, '2023-10-10 20:01:06', 1, '2023-10-10 20:01:06');

-- --------------------------------------------------------

--
-- Table structure for table `lifts`
--

CREATE TABLE `lifts` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `lift_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `max_level` int(11) NOT NULL,
  `mac_address` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `floor_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `lift_state` char(12) COLLATE utf8_unicode_ci NOT NULL DEFAULT '000000000000',
  `up_status` char(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT '00000000',
  `down_status` char(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT '00000000',
  `car_status` char(8) COLLATE utf8_unicode_ci NOT NULL DEFAULT '00000000',
  `created_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `lifts`
--

INSERT INTO `lifts` (`id`, `org_id`, `building_id`, `lift_name`, `max_level`, `mac_address`, `floor_name`, `description`, `lift_state`, `up_status`, `down_status`, `car_status`, `created_user_id`, `created_at`, `updated_user_id`, `updated_at`) VALUES
(1, 1, 1, 'KUSE', 15, 'DEADBEEF01ED', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15', 'KUSE', '030900000000', '00000000', '00000000', '00000000', 1, '2022-10-31 11:41:50', 2, '2023-06-01 13:46:19'),
(2, 2, 2, 'SNKTC', 4, 'DEADBEEF02ED', '1,2,3,4', 'SNKTC', '018C00000000', '00000000', '00000000', '00000000', 1, '2022-12-06 03:08:32', 2, '2023-06-01 08:46:25'),
(3, 3, 3, 'FL1', 14, 'DEADBEEF03ED', '1,1A,2,2A,3,4,5,6,7,8,9,10,11,12', 'Fire Lift 1', '000000000000', '00000000', '00000000', '00000000', 1, '2023-06-01 14:10:50', 1, '2023-06-01 14:10:50'),
(4, 3, 3, 'PL1', 14, 'DEADBEEF04ED', '1,1A,2,2A,3,4,5,6,7,8,9,10,11,12', 'Patient 1', '000000000000', '00000000', '00000000', '00000000', 1, '2023-06-01 14:11:43', 1, '2023-06-01 14:11:43'),
(5, 3, 3, 'BL1', 14, 'DEADBEEF05ED', '1,1A,2,2A,3,4,5,6,7,8,9,10,11,12', 'Bed 1', '000000000000', '00000000', '00000000', '00000000', 1, '2023-06-01 14:12:17', 1, '2023-06-01 14:12:17'),
(6, 3, 3, 'PL2', 14, 'DEADBEEF06ED', '1,1A,2,2A,3,4,5,6,7,8,9,10,11,12', 'Patient 2', '000000000000', '00000000', '00000000', '00000000', 1, '2023-06-01 14:12:43', 1, '2023-06-01 14:12:43'),
(7, 3, 3, 'PL3', 14, 'DEADBEEF07ED', '1,1A,2,2A,3,4,5,6,7,8,9,10,11,12', 'Patient 3', '000000000000', '00000000', '00000000', '00000000', 1, '2023-06-01 14:13:10', 1, '2023-06-01 14:13:10'),
(8, 1, 4, 'CSC01', 4, 'DEADBEEF08ED', '1,2,3,4', 'Building 1', '000000000000', '00000000', '00000000', '00000000', 1, '2023-09-24 17:34:19', 1, '2023-09-24 17:34:19'),
(22, 18, NULL, 'TEST', 5, 'DEADBEEF09ED', '1,2,3,4,5', '', '000000000000', '00000000', '00000000', '00000000', 1, '2024-05-27 12:35:25', 1, '2024-05-27 12:35:25');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `org_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `created_user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `org_name`, `description`, `created_user_id`, `created_at`, `updated_user_id`, `updated_at`) VALUES
(1, 'KU CSC', 'มหาวิทยาลัยเกษตรศาสตร์\r\nวิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร\r\n59 หมู่ 1 ถ.วปรอ 366 ต.เชียงเครือ อ.เมือง จ.สกลนคร 47000 โทรศัพท์ 061-0287788', 1, '2022-10-31 11:40:46', 1, '2022-10-31 11:40:46'),
(2, 'SNKTC', '\r\nวิทยาลัยเทคนิคสกลนคร', 1, '2023-02-13 12:30:16', 1, '2023-02-13 12:30:16'),
(3, 'PSU', 'มหาวิทยาลัยสงขลานครินทร์', 1, '2023-06-01 14:10:06', 1, '2023-06-01 14:10:06'),
(18, 'TEST', '', 1, '2024-02-18 15:55:15', 1, '2024-02-18 15:55:15');

-- --------------------------------------------------------

--
-- Table structure for table `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method` enum('email','sms') NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_otps`
--

CREATE TABLE `recovery_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `otp_type` enum('email','sms') NOT NULL,
  `contact_info` varchar(255) NOT NULL COMMENT 'email address or phone number',
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `report`
--

CREATE TABLE `report` (
  `rp_id` int(11) NOT NULL,
  `date_rp` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `lift_id` int(11) NOT NULL,
  `detail` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `report`
--

INSERT INTO `report` (`rp_id`, `date_rp`, `user_id`, `org_id`, `building_id`, `lift_id`, `detail`) VALUES
(7, '2025-09-28', 1, 2, 2, 2, '- ไฟชั้นแสดงผลผิดพลาด [URG:MEDIUM] [STA:OPEN]'),
(8, '2025-09-28', 6, 1, 4, 8, '- ระบบ Overload แจ้งเตือนผิดปกติ [URG:MEDIUM] [STA:OPEN]'),
(9, '2025-09-28', 1, 3, 3, 3, '- ปุ่มกดภายในไม่ทำงาน [URG:MEDIUM] [STA:OPEN]'),
(10, '2025-09-28', 1, 3, 3, 7, '- ปุ่มกดภายในไม่ทำงาน [URG:MEDIUM] [STA:OPEN]');

-- --------------------------------------------------------

--
-- Table structure for table `status_logs`
--

CREATE TABLE `status_logs` (
  `id` int(11) NOT NULL,
  `lift_id` int(11) NOT NULL,
  `lift_state` varchar(20) DEFAULT NULL,
  `up_status` varchar(20) DEFAULT NULL,
  `down_status` varchar(20) DEFAULT NULL,
  `car_status` varchar(20) DEFAULT NULL,
  `created_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_user_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `task`
--

CREATE TABLE `task` (
  `tk_id` int(11) NOT NULL,
  `tk_data` varchar(255) NOT NULL,
  `task_start_date` datetime DEFAULT NULL,
  `rp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user` varchar(255) NOT NULL,
  `mainten_id` int(11) NOT NULL,
  `org_name` varchar(255) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `lift_id` varchar(255) NOT NULL,
  `tools` longtext NOT NULL,
  `tk_status` enum('assign','preparing','progress','test','complete') NOT NULL DEFAULT 'assign'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `task`
--

INSERT INTO `task` (`tk_id`, `tk_data`, `task_start_date`, `rp_id`, `user_id`, `user`, `mainten_id`, `org_name`, `building_name`, `lift_id`, `tools`, `tk_status`) VALUES
(5, '', NULL, 7, 6, 'Tech1234', 6, 'SNKTC', 'อาคาร 1 เทคนิคสกล', '2', '[]', 'complete'),
(6, '', NULL, 8, 6, 'Tech1234', 6, 'KU CSC', 'อาคาร 1', '8', '[]', 'progress'),
(7, '', NULL, 10, 6, 'Tech1234', 6, 'PSU', 'อาคารเย็นศิระ', '7', '[]', 'assign'),
(8, '', NULL, 9, 6, 'Tech1234', 6, 'PSU', 'อาคารเย็นศิระ', '3', '[]', 'progress');

-- --------------------------------------------------------

--
-- Table structure for table `task_status`
--

CREATE TABLE `task_status` (
  `tk_status_id` int(11) NOT NULL,
  `tk_id` int(11) NOT NULL,
  `status` enum('assign','preparing','progress','test','complete') NOT NULL,
  `time` datetime NOT NULL,
  `detail` varchar(255) NOT NULL,
  `tk_status_tool` longtext,
  `tk_img` longblob,
  `section` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `task_status`
--

INSERT INTO `task_status` (`tk_status_id`, `tk_id`, `status`, `time`, `detail`, `tk_status_tool`, `tk_img`, `section`) VALUES
(12, 5, 'assign', '2025-09-28 21:05:17', 'Assigned by admin', NULL, NULL, 'assignment'),
(13, 5, 'progress', '2025-09-28 21:05:29', 'Technician accepted the task.', NULL, NULL, 'progress'),
(14, 5, 'progress', '2025-09-28 21:06:08', 'qwqefqad', '/uploads/task_status/20250928140608_86475819_7.png', NULL, 'progress'),
(15, 5, 'progress', '2025-09-28 21:24:59', 'ๅ/-ๅ-ๅ-ๅ-ๅ', NULL, NULL, 'progress'),
(16, 5, 'progress', '2025-09-28 21:25:30', 'ๅ/-ภหดแฟกหเอดฟำเฟำ', '/uploads/task_status/20250928142530_d5aa2734_Screenshot 2025-08-01 233535.png', NULL, 'progress'),
(17, 5, 'test', '2025-09-28 21:33:26', '', NULL, NULL, 'progress'),
(18, 5, 'complete', '2025-09-28 21:36:22', '', NULL, NULL, 'progress'),
(19, 6, 'assign', '2025-09-28 21:39:14', 'Assigned by admin', NULL, NULL, 'assignment'),
(20, 6, 'progress', '2025-09-28 21:39:19', 'Technician accepted the task.', NULL, NULL, 'progress'),
(21, 7, 'assign', '2025-09-28 21:40:19', 'Assigned by admin', NULL, NULL, 'assignment'),
(22, 8, 'assign', '2025-09-28 21:40:23', 'Assigned by admin', NULL, NULL, 'assignment'),
(23, 7, 'assign', '2025-09-28 21:52:07', 'Technician accepted (confirm assign).', NULL, NULL, 'progress'),
(24, 7, 'assign', '2025-09-28 21:53:21', 'Technician accepted (confirm assign).', NULL, NULL, 'progress'),
(25, 7, 'assign', '2025-09-28 21:53:38', 'Technician accepted (confirm assign).', NULL, NULL, 'progress'),
(26, 7, 'assign', '2025-09-28 22:05:41', 'Technician accepted (confirm assign).', NULL, NULL, 'progress'),
(27, 8, 'preparing', '2025-09-28 22:12:55', 'Technician accepted → preparing.', NULL, NULL, 'progress'),
(28, 8, 'preparing', '2025-09-28 22:14:11', 'เตรียมเครื่องมือ', '/uploads/task_status/20250928151411_026e6766_Screenshot 2025-08-28 172059.png', NULL, 'progress'),
(29, 8, 'progress', '2025-09-28 22:43:39', '', '/uploads/task_status/20250928154339_9fca7770_Screenshot 2025-08-03 234051.png', NULL, 'progress'),
(30, 8, 'progress', '2025-09-28 22:55:35', '', '/uploads/task_status/20250928155535_3ebbafe8_Screenshot 2025-08-01 224847.png', NULL, 'progress');

-- --------------------------------------------------------

--
-- Table structure for table `tools`
--

CREATE TABLE `tools` (
  `tool_id` int(11) NOT NULL,
  `tool_name` varchar(255) NOT NULL,
  `cost` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `twofa_reset_logs`
--

CREATE TABLE `twofa_reset_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_method` enum('email','sms','backup_code') NOT NULL,
  `old_secret_key` varchar(255) DEFAULT NULL,
  `new_secret_key` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `reset_reason` varchar(255) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL COMMENT 'หากมี admin ช่วยในการ reset',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `twofa_settings`
--

CREATE TABLE `twofa_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text,
  `updated_user_id` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `address` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `ga_secret_key` varchar(255) DEFAULT NULL COMMENT 'Authenticator secret key',
  `ga_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=Disabled, 1=Enabled for Authenticator',
  `org_id` int(11) DEFAULT NULL,
  `user_img` varchar(255) DEFAULT NULL COMMENT 'URL of the user profile image',
  `recovery_email` varchar(255) DEFAULT NULL COMMENT 'Email สำหรับกู้คืน (อาจต่างจาก email หลัก)',
  `recovery_phone` varchar(20) DEFAULT NULL COMMENT 'Phone สำหรับกู้คืน',
  `last_2fa_reset` datetime DEFAULT NULL COMMENT 'วันที่ reset 2FA ครั้งล่าสุด',
  `failed_2fa_attempts` int(11) NOT NULL DEFAULT '0' COMMENT 'จำนวนครั้งที่ล้มเหลวในการใช้ 2FA',
  `locked_until` datetime DEFAULT NULL COMMENT 'ล็อคบัญชีจนถึงเวลาที่กำหนด',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=Inactive, 1=Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `phone`, `birthdate`, `address`, `role`, `ga_secret_key`, `ga_enabled`, `org_id`, `user_img`, `recovery_email`, `recovery_phone`, `last_2fa_reset`, `failed_2fa_attempts`, `locked_until`, `is_active`) VALUES
(1, 'Admin12345', '$2y$10$0UGatAbMAO.c0Wb2yF1iJu6LBWRYJaOJxUt/60CZHM0fv.Yqv0xgq', 'Aphichat', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-22', '123 asdfgrdcf', 'admin', '6EPSJCDRKPYRBWWE', 1, 1, '/uploads/profile_images/68d580b39c9b06.83776048.jpg', '', '', NULL, 0, NULL, 1),
(2, 'Aphichat123', '$2y$10$1l2iqxYhaZ2SI5QYmxtgyOWA01dOwWTzmcWtijksyfQnD464wbdRW', 'Aphichat', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-27', '123 asdfgrdcf', 'technician', NULL, 0, 3, NULL, '', '', NULL, 0, NULL, 1),
(3, 'User1234', '$2y$10$wm3TjKw9sT8NAtJqhNKUUO4ux20rjzlCx4n/MpFZU8UFCKEgrj7ii', 'Aphichat', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-27', 'Asdr77980', 'user', NULL, 0, 1, NULL, '', '', NULL, 0, NULL, 1),
(4, 'User5678', '$2y$10$RnNH/3aeh7keCDxChfQFN..PgFZ.gbflowj79Y7LIym0BsHFjw1Oq', 'Aphichat', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-27', '1234abcde', 'user', NULL, 0, 1, NULL, '', '', NULL, 0, NULL, 1),
(5, 'User8910', '$2y$10$/MIiMsOA9SI//IMHSR7bWugnKnnehUuOj31GF.qDwJrO8nP18DmX.', 'Aphichat', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-27', 'Asdr77980', 'user', NULL, 0, NULL, NULL, '', '', NULL, 0, NULL, 1),
(6, 'Tech1234', '$2y$10$peB89GBOnWhOgTu4Qb03mOj3b6tbgYefj/68aLj9ZbcLPrpRONMqy', 'Tech', 'Seesaard', 'aphichat.se@ku.th', '0840780999', '2025-09-27', 'Asdr77980', 'technician', NULL, 0, NULL, '/uploads/profile_images/68d91ec9775db3.18845944.jpeg', '', '', NULL, 0, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `work`
--

CREATE TABLE `work` (
  `wk_id` int(11) NOT NULL,
  `wk_status` enum('1','2','3','4') NOT NULL,
  `tk_id` int(11) NOT NULL,
  `wk_detail` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `backup_codes`
--
ALTER TABLE `backup_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lifts`
--
ALTER TABLE `lifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_organizations_org_name` (`org_name`);

--
-- Indexes for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recovery_otps`
--
ALTER TABLE `recovery_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `otp_code` (`otp_code`);

--
-- Indexes for table `report`
--
ALTER TABLE `report`
  ADD PRIMARY KEY (`rp_id`);

--
-- Indexes for table `status_logs`
--
ALTER TABLE `status_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `task`
--
ALTER TABLE `task`
  ADD PRIMARY KEY (`tk_id`);

--
-- Indexes for table `task_status`
--
ALTER TABLE `task_status`
  ADD PRIMARY KEY (`tk_status_id`);

--
-- Indexes for table `tools`
--
ALTER TABLE `tools`
  ADD PRIMARY KEY (`tool_id`);

--
-- Indexes for table `twofa_reset_logs`
--
ALTER TABLE `twofa_reset_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Indexes for table `twofa_settings`
--
ALTER TABLE `twofa_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`),
  ADD KEY `updated_user_id` (`updated_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work`
--
ALTER TABLE `work`
  ADD PRIMARY KEY (`wk_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `backup_codes`
--
ALTER TABLE `backup_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lifts`
--
ALTER TABLE `lifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_otps`
--
ALTER TABLE `recovery_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report`
--
ALTER TABLE `report`
  MODIFY `rp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task`
--
ALTER TABLE `task`
  MODIFY `tk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `task_status`
--
ALTER TABLE `task_status`
  MODIFY `tk_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `tools`
--
ALTER TABLE `tools`
  MODIFY `tool_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `twofa_reset_logs`
--
ALTER TABLE `twofa_reset_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `twofa_settings`
--
ALTER TABLE `twofa_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `work`
--
ALTER TABLE `work`
  MODIFY `wk_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backup_codes`
--
ALTER TABLE `backup_codes`
  ADD CONSTRAINT `backup_codes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD CONSTRAINT `otp_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recovery_otps`
--
ALTER TABLE `recovery_otps`
  ADD CONSTRAINT `recovery_otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `twofa_reset_logs`
--
ALTER TABLE `twofa_reset_logs`
  ADD CONSTRAINT `twofa_reset_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `twofa_reset_logs_ibfk_2` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `twofa_settings`
--
ALTER TABLE `twofa_settings`
  ADD CONSTRAINT `twofa_settings_ibfk_1` FOREIGN KEY (`updated_user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
