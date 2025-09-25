-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 21, 2025 at 04:47 PM
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
-- Database: `elevator_system`
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

-- --------------------------------------------------------

--
-- Table structure for table `building`
--

CREATE TABLE `building` (
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
  `tk_status` enum('1','2','3','4','5') NOT NULL,
  `tk_data` varchar(255) NOT NULL,
  `task_start_date` datetime DEFAULT NULL,
  `rp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user` varchar(255) NOT NULL,
  `mainten_id` int(11) NOT NULL,
  `org_name` varchar(255) NOT NULL,
  `building_name` varchar(255) NOT NULL,
  `lift_id` varchar(255) NOT NULL,
  `tools` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `task_status`
--

CREATE TABLE `task_status` (
  `tk_status_id` int(11) NOT NULL,
  `tk_id` int(11) NOT NULL,
  `status` enum('preparing','working','finish','prepared','assign') NOT NULL,
  `time` datetime NOT NULL,
  `detail` varchar(255) NOT NULL,
  `tk_status_tool` longtext,
  `tk_img` longblob,
  `section` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `org_id` int(11) NOT NULL,
  `user_img` varchar(255) DEFAULT NULL COMMENT 'URL of the user profile image',
  `recovery_email` varchar(255) DEFAULT NULL COMMENT 'Email สำหรับกู้คืน (อาจต่างจาก email หลัก)',
  `recovery_phone` varchar(20) DEFAULT NULL COMMENT 'Phone สำหรับกู้คืน',
  `last_2fa_reset` datetime DEFAULT NULL COMMENT 'วันที่ reset 2FA ครั้งล่าสุด',
  `failed_2fa_attempts` int(11) NOT NULL DEFAULT '0' COMMENT 'จำนวนครั้งที่ล้มเหลวในการใช้ 2FA',
  `locked_until` datetime DEFAULT NULL COMMENT 'ล็อคบัญชีจนถึงเวลาที่กำหนด',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=Inactive, 1=Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Indexes for table `building`
--
ALTER TABLE `building`
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
  ADD PRIMARY KEY (`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `building`
--
ALTER TABLE `building`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lifts`
--
ALTER TABLE `lifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `rp_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `status_logs`
--
ALTER TABLE `status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task`
--
ALTER TABLE `task`
  MODIFY `tk_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `task_status`
--
ALTER TABLE `task_status`
  MODIFY `tk_status_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
