-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 30, 2025 at 06:40 PM
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
(11, 1, '72242B95FE', 0, '2025-09-29 04:09:55', NULL),
(12, 1, '8DAA5CE46C', 0, '2025-09-29 04:09:55', NULL),
(13, 1, '6C655F424F', 0, '2025-09-29 04:09:55', NULL),
(14, 1, 'E2B7D4188D', 0, '2025-09-29 04:09:55', NULL),
(15, 1, 'AA57D4ACF9', 0, '2025-09-29 04:09:55', NULL),
(16, 1, 'A64D2F9C9B', 0, '2025-09-29 04:09:55', NULL),
(17, 1, '856033D0C2', 0, '2025-09-29 04:09:55', NULL),
(18, 1, 'E36B12C98C', 0, '2025-09-29 04:09:55', NULL),
(19, 1, 'DB43E85815', 0, '2025-09-29 04:09:55', NULL),
(20, 1, '9D7EBFD328', 0, '2025-09-29 04:09:55', NULL);

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
(1, '2025-09-29', 1, 1, 4, 8, '- ปุ่มกดภายในไม่ทำงาน [URG:MEDIUM] [STA:OPEN]'),
(2, '2025-09-29', 1, 3, 3, 5, '- มีเสียงดังผิดปกติระหว่างวิ่ง [URG:MEDIUM] [STA:OPEN]');

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
(1, '', NULL, 2, 3, 'Technician123', 3, 'PSU', 'อาคารเย็นศิระ', '5', '[]', 'preparing');

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
(1, 1, 'assign', '2025-09-30 10:29:47', 'Assigned by admin', NULL, NULL, 'assignment'),
(2, 1, 'preparing', '2025-09-30 10:34:48', 'Technician accepted → preparing.', NULL, NULL, 'progress');

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
  `role` enum('admin','org-admin','technician','user') NOT NULL DEFAULT 'user',
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
(1, 'Admin12345', '$2y$10$yIxtYY1YTrh9E9t0cOKJ/OXU2uzoZ0lCxxtiGyXHmgFbo/c9/wJ2K', 'Admin', 'APHICHAT', 'aphichat.se@ku.th', '0840780999', '2025-09-29', 'ABC1234770', 'admin', 'C6JJ6OA5OUBFWE5G', 1, NULL, NULL, '', '', NULL, 0, NULL, 1),
(2, 'OrgAdmin123', '$2y$10$Q9vwj4uKNpVABuDND8fN7u3FdW20TXoq9ELm9xi6MHwme3q4HWqwK', 'OrgAdmin', 'APHICHAT', 'aphichat.se@ku.th', '0840780999', '2025-09-29', 'ABC1234770', 'user', NULL, 0, 1, NULL, '', '', NULL, 0, NULL, 1),
(3, 'Technician123', '$2y$10$.I0LkdkulLlzjF50pip5OOtFBukPoSus7Ysdk/uspmoOI1gPbKziK', 'Technician', 'APHICHAT', 'aphichat.se@ku.th', '0840780999', '2025-09-29', 'ABC1234770', 'technician', NULL, 0, NULL, NULL, '', '', NULL, 0, NULL, 1),
(4, 'User1234', '$2y$10$5LvuPWZfjDS5S7f77os7Vuo7PXiCohI4isJX5UIK9mj0l7vGKPhs.', 'User', 'APHICHAT', 'aphichat.se@ku.th', '0840780999', '2025-09-29', 'ABC1234770', 'user', NULL, 0, 1, NULL, '', '', NULL, 0, NULL, 1),
(5, 'User5678', '$2y$10$4MxuwIOhgud.BMerbqtefOW0GgIhgYl83CMeTMmXbjMI3efjXc9bu', 'User', 'name', 'aphichat.se@ku.th', '0840780999', '2025-09-29', 'ABC1234770', 'user', NULL, 0, NULL, NULL, '', '', NULL, 0, NULL, 0);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `backup_codes`
--
ALTER TABLE `backup_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
  MODIFY `rp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task`
--
ALTER TABLE `task`
  MODIFY `tk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `task_status`
--
ALTER TABLE `task_status`
  MODIFY `tk_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD CONSTRAINT `otp_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
