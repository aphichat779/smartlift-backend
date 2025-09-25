<?php
// utils/ValidationHelper.php
class ValidationHelper {
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Check if it's 10 digits (Thai phone number format)
        return preg_match('/^[0-9]{10}$/', $phone);
    }
    
    public static function validatePassword($password) {
        // At least 8 characters, contains uppercase, lowercase, number
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    public static function validateUsername($username) {
        // 3-20 characters, alphanumeric and underscore only
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
    }
    
    public static function validateName($name) {
        // 2-50 characters, letters and spaces only
        return preg_match('/^[a-zA-Zก-๙\s]{2,50}$/', $name);
    }
    
    public static function validateRequired($fields, $data) {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        return $errors;
    }
    
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateTOTPCode($code) {
        return preg_match('/^[0-9]{6}$/', $code);
    }
    
    public static function validateOTPCode($code) {
        return preg_match('/^[0-9]{6}$/', $code);
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function sendValidationError($errors) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
            'error_code' => 'VALIDATION_ERROR'
        ]);
        exit;
    }
}
?>

