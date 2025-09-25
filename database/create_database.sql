-- Create database
CREATE DATABASE IF NOT EXISTS smartlift_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartlift_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    birthdate DATE NOT NULL,
    address TEXT NOT NULL,
    recovery_email VARCHAR(255),
    recovery_phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    ga_enabled BOOLEAN DEFAULT FALSE,
    ga_secret VARCHAR(32),
    failed_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- OTP tokens table
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    type ENUM('login', 'reset_2fa') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Backup codes table
CREATE TABLE IF NOT EXISTS backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recovery OTP table
CREATE TABLE IF NOT EXISTS recovery_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    method ENUM('email', 'sms') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Two-factor authentication reset log table
CREATE TABLE IF NOT EXISTS two_fa_reset_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT,
    reason TEXT NOT NULL,
    method ENUM('admin', 'otp') NOT NULL,
    old_secret VARCHAR(32),
    new_secret VARCHAR(32),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO users (
    username, 
    password, 
    first_name, 
    last_name, 
    email, 
    phone, 
    birthdate, 
    address, 
    role
) VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Admin',
    'User',
    'admin@smartlift.com',
    '0812345678',
    '1990-01-01',
    'SmartLift Headquarters',
    'admin'
);

-- Insert test user
INSERT INTO users (
    username, 
    password, 
    first_name, 
    last_name, 
    email, 
    phone, 
    birthdate, 
    address,
    recovery_email,
    recovery_phone
) VALUES (
    'testuser',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Test',
    'User',
    'test@example.com',
    '0987654321',
    '1995-05-15',
    '123 Test Street, Bangkok',
    'recovery@example.com',
    '0876543210'
);

