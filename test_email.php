<?php
require_once 'config/config.php';
require_once 'utils/OTPHelper.php';

// ทดสอบส่งอีเมล OTP
$testEmail = 'aphichat.se@ku.th';
$testOTP = OTPHelper::generateOTP();

echo "Sending OTP: $testOTP to $testEmail\n";

$result = OTPHelper::sendEmailOTP($testEmail, $testOTP, '2FA Reset');

if ($result) {
    echo "Email sent successfully!\n";
} else {
    echo "Failed to send email. Check error logs.\n";
}
?>