<?php
// config/config.php

// JWT Configuration
define('JWT_SECRET_KEY', 'smartlift_secret_key_2025_very_secure');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION_TIME', 86400 * 365); // จำกัดเวลาโทเค็น

// 2FA Configuration
define('TOTP_ISSUER', 'SmartLift');
define('TOTP_DIGITS', 6);
define('TOTP_PERIOD', 30);

// OTP Configuration
define('OTP_EXPIRATION_TIME', 300); // 5 minutes
define('OTP_LENGTH', 6);

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// CORS Configuration
define('ALLOWED_ORIGINS', ['http://localhost:3000', 'http://localhost:5173', 'http://localhost:5174', 'http://172.20.10.4:5173', 'http://172.20.10.4']);

// Email Configuration (for OTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'aphichat.se2003@gmail.com');
define('SMTP_PASSWORD', 'tjlx jxbt jjzg tcqw');
define('FROM_EMAIL', 'noreply@smartlift.com');
define('FROM_NAME', 'SmartLift System');

// SMS Configuration (for OTP)
define('SMS_API_KEY', '');
define('SMS_API_URL', '');

// Error Messages
define('ERROR_MESSAGES', [
    'INVALID_CREDENTIALS' => 'Invalid username or password',
    'ACCOUNT_LOCKED' => 'Account is locked due to too many failed attempts',
    'INVALID_2FA_CODE' => 'Invalid 2FA code',
    'EXPIRED_OTP' => 'OTP has expired',
    'INVALID_OTP' => 'Invalid OTP code',
    'USER_NOT_FOUND' => 'User not found',
    'UNAUTHORIZED' => 'Unauthorized access',
    'FORBIDDEN' => 'Access forbidden',
    'VALIDATION_ERROR' => 'Validation error',
    'SERVER_ERROR' => 'Internal server error'
]);
?>

