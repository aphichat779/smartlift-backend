<?php
// utils/TOTPHelper.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use PragmaRX\Google2FA\Google2FA;

class TOTPHelper
{
    /**
     * สร้าง secret key สำหรับ TOTP
     */
    public static function generateSecret()
    {
        $google2fa = new Google2FA();
        return $google2fa->generateSecretKey();
    }

    /**
     * สร้างลิงก์ QR Code สำหรับแสดงในหน้า Setup 2FA
     */
    public static function generateQRCodeURL($secret, $username, $issuer = TOTP_ISSUER)
    {
        // สร้าง otpauth URL ตามมาตรฐาน TOTP
        $otpauthUrl = "otpauth://totp/" . rawurlencode($issuer . ':' . $username)
            . "?secret=" . rawurlencode($secret)
            . "&issuer=" . rawurlencode($issuer)
            . "&algorithm=SHA1&digits=6&period=30";

        // ใช้ API ของ qrserver.com เพื่อแสดง QR Code
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauthUrl);
    }

    /**
     * ตรวจสอบรหัส OTP ที่ผู้ใช้กรอกว่า valid หรือไม่
     */
    public static function verifyCode($secret, $code, $window = 2)
    {
        $google2fa = new Google2FA();
        return $google2fa->verifyKey($secret, $code, $window);
    }
}
