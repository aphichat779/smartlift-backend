<?php
// utils/OTPHelper.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class OTPHelper {
    
    public static function generateOTP($length = OTP_LENGTH) {
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
    
    public static function sendEmailOTP($email, $otp, $purpose = 'verification') {
        $mail = new PHPMailer(true); 

        try {
            // ตั้งค่าเซิร์ฟเวอร์ SMTP
            $mail->isSMTP();                                       
            $mail->Host       = SMTP_HOST;                         
            $mail->SMTPAuth   = true;                              
            $mail->Username   = SMTP_USERNAME;                     
            $mail->Password   = SMTP_PASSWORD;                     
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    
            $mail->Port       = SMTP_PORT;                         

            $mail->setFrom(FROM_EMAIL, FROM_NAME); 
            $mail->addAddress($email);             

            $purposeText = [
                'verification' => 'verify your account',
                'login' => 'sign in to your account',
                'password_reset' => 'reset your password',
                'account_recovery' => 'recover your account'
            ];
            
            $purposeMessage = isset($purposeText[$purpose]) ? $purposeText[$purpose] : 'verify your account';
            $expirationMinutes = OTP_EXPIRATION_TIME / 60;

            // เนื้อหาอีเมล
            $mail->isHTML(true);                                  
            $mail->Subject = 'SmartLift verification code';       
            $mail->Body    = "
            <!DOCTYPE html>
            <html lang='th'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>SmartLift Verification</title>
                <style>
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        line-height: 1.6;
                        color: #202124;
                        margin: 0;
                        padding: 0;
                        background-color: #f8f9fa;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    }
                    .header {
                        background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
                        padding: 40px 30px;
                        text-align: center;
                    }
                    .logo {
                        color: #ffffff;
                        font-size: 28px;
                        font-weight: 500;
                        margin: 0;
                        letter-spacing: -0.5px;
                    }
                    .content {
                        padding: 40px 30px;
                    }
                    .greeting {
                        font-size: 18px;
                        font-weight: 400;
                        margin: 0 0 20px 0;
                        color: #202124;
                    }
                    .message {
                        font-size: 16px;
                        color: #5f6368;
                        margin: 0 0 30px 0;
                        line-height: 1.5;
                    }
                    .otp-container {
                        text-align: center;
                        margin: 40px 0;
                        padding: 30px;
                        background-color: #f8f9fa;
                        border-radius: 8px;
                        border: 1px solid #e8eaed;
                    }
                    .otp-label {
                        font-size: 14px;
                        color: #5f6368;
                        margin-bottom: 10px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        font-weight: 500;
                    }
                    .otp-code {
                        font-size: 32px;
                        font-weight: 600;
                        color: #1a73e8;
                        letter-spacing: 8px;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                        margin: 10px 0;
                    }
                    .expiry-info {
                        background-color: #fff3cd;
                        border: 1px solid #ffeaa7;
                        border-radius: 6px;
                        padding: 16px;
                        margin: 30px 0;
                    }
                    .expiry-info .icon {
                        display: inline-block;
                        margin-right: 8px;
                        color: #856404;
                    }
                    .expiry-text {
                        font-size: 14px;
                        color: #856404;
                        margin: 0;
                    }
                    .security-note {
                        background-color: #f1f3f4;
                        border-left: 4px solid #34a853;
                        padding: 20px;
                        margin: 30px 0;
                        border-radius: 0 6px 6px 0;
                    }
                    .security-note h3 {
                        margin: 0 0 10px 0;
                        font-size: 16px;
                        color: #202124;
                        font-weight: 500;
                    }
                    .security-note p {
                        margin: 0;
                        font-size: 14px;
                        color: #5f6368;
                        line-height: 1.4;
                    }
                    .footer {
                        background-color: #f8f9fa;
                        padding: 30px;
                        text-align: center;
                        border-top: 1px solid #e8eaed;
                    }
                    .footer-text {
                        font-size: 12px;
                        color: #9aa0a6;
                        margin: 0 0 10px 0;
                        line-height: 1.4;
                    }
                    .footer-brand {
                        font-size: 14px;
                        color: #5f6368;
                        font-weight: 500;
                        margin: 0;
                    }
                    @media (max-width: 600px) {
                        .container {
                            margin: 0;
                            border-radius: 0;
                        }
                        .content {
                            padding: 30px 20px;
                        }
                        .header {
                            padding: 30px 20px;
                        }
                        .otp-code {
                            font-size: 28px;
                            letter-spacing: 6px;
                        }
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 class='logo'>SmartLift</h1>
                    </div>
                    
                    <div class='content'>
                        <h2 class='greeting'>Hi there,</h2>
                        
                        <p class='message'>
                            Use the following verification code to {$purposeMessage}. This code is valid for a limited time only.
                        </p>
                        
                        <div class='otp-container'>
                            <div class='otp-label'>Verification Code</div>
                            <div class='otp-code'>{$otp}</div>
                        </div>
                        
                        <div class='expiry-info'>
                            <span class='icon'>⏰</span>
                            <p class='expiry-text'>
                                <strong>This code will expire in {$expirationMinutes} minutes.</strong>
                                Please use it as soon as possible.
                            </p>
                        </div>
                        
                        <div class='security-note'>
                            <h3>🔒 Security Notice</h3>
                            <p>
                                If you didn't request this verification code, please ignore this email. 
                                Never share this code with anyone. SmartLift will never ask for your verification code.
                            </p>
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p class='footer-text'>
                            This email was sent to {$email}. If you have any questions, 
                            please contact our support team.
                        </p>
                        <p class='footer-brand'>SmartLift System</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $mail->AltBody = "SmartLift Verification Code\n\n" .
                           "Hi there,\n\n" .
                           "Use the following verification code to {$purposeMessage}:\n\n" .
                           "Verification Code: {$otp}\n\n" .
                           "This code will expire in {$expirationMinutes} minutes.\n\n" .
                           "If you didn't request this verification code, please ignore this email.\n\n" .
                           "SmartLift System";
            
            $mail->send(); 
            error_log("Email sent successfully to {$email} using PHPMailer.");
            return true;
        } catch (Exception $e) {
            error_log("Failed to send email to {$email}. Mailer Error: {$mail->ErrorInfo}. Exception: {$e->getMessage()}");
            return false;
        }
    }
    
    public static function sendSMSOTP($phone, $otp, $purpose = 'verification') {
 
        $message = "SmartLift OTP for {$purpose}: {$otp}. Valid for " . (OTP_EXPIRATION_TIME / 60) . " minutes.";
        
        error_log("SMS to {$phone}: {$message}");
        
        return true; 
    }
    
    public static function verifyOTP($storedOTP, $inputOTP, $createdAt) {
        $expirationTime = strtotime($createdAt) + OTP_EXPIRATION_TIME;
        if (time() > $expirationTime) {
            return ['valid' => false, 'error' => 'expired'];
        }
        
        if (hash_equals($storedOTP, $inputOTP)) {
            return ['valid' => true];
        }
        
        return ['valid' => false, 'error' => 'invalid'];
    }
    
    public static function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5))); 
        }
        return $codes;
    }
}
?>