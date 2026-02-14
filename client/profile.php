<?php
session_start();

// --- 1. SESSION SEGMENTATION CHECK ---
if (!isset($_SESSION['client_id'])) {
    header("Location: ../public/login.php");
    exit();
}

// --- 2. DATABASE & UTILITIES SETUP ---
require '../config/db.php'; 
require_once '../config/encryption_util.php'; 

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['client_id']; 

$error_message = '';
$success_message = '';
$user = []; 

// =======================================================
// IMPROVED: RATE LIMITING SYSTEM (Prevents Spam Updates)
// =======================================================
function check_rate_limit($pdo, $user_id, $action_type = 'profile_update', $max_attempts = 3, $window_minutes = 60) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count, MAX(created_at) as last_attempt 
        FROM rate_limits 
        WHERE user_id = ? 
        AND action_type = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $stmt->execute([$user_id, $action_type, $window_minutes]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempt_count'] >= $max_attempts) {
        $last_attempt = strtotime($result['last_attempt']);
        $wait_time = $window_minutes - floor((time() - $last_attempt) / 60);
        return [
            'allowed' => false, 
            'wait_time' => max(1, $wait_time),
            'message' => "Too many updates. Please wait {$wait_time} minute(s) before trying again."
        ];
    }
    
    return ['allowed' => true];
}

function log_rate_limit($pdo, $user_id, $action_type = 'profile_update') {
    $stmt = $pdo->prepare("INSERT INTO rate_limits (user_id, action_type, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action_type]);
}

// ========================================================
// AGE CHANGE VALIDATION & COOLDOWN SYSTEM
// ========================================================
class AgeChangeValidator {
    private $pdo;
    private $user_id;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    // Check if user can change their birth date/age
    public function canChangeAge($new_birth_date, $current_birth_date = null) {
        $result = [
            'allowed' => true,
            'message' => '',
            'cooldown_remaining' => 0
        ];
        
        // Check recent changes (last 30 days)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as change_count, 
                   MAX(created_at) as last_change,
                   TIMESTAMPDIFF(DAY, MAX(created_at), NOW()) as days_since_last_change
            FROM age_change_history 
            WHERE user_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$this->user_id]);
        $history = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Rule 1: Maximum 2 age changes per 30 days
        if ($history['change_count'] >= 2) {
            $result['allowed'] = false;
            $result['message'] = "You've reached the maximum limit of 2 age changes per 30 days.";
            $result['cooldown_remaining'] = 30 - $history['days_since_last_change'];
            return $result;
        }
        
        // Rule 2: 14-day cooldown after last change
        if ($history['last_change'] && $history['days_since_last_change'] < 14) {
            $cooldown_left = 14 - $history['days_since_last_change'];
            $result['allowed'] = false;
            $result['message'] = "Age changes can only be made every 14 days. Please wait {$cooldown_left} more day(s).";
            $result['cooldown_remaining'] = $cooldown_left;
            return $result;
        }
        
        // Rule 3: Prevent drastic age changes (more than 5 years at once)
        if ($current_birth_date) {
            $old_date = new DateTime($current_birth_date);
            $new_date = new DateTime($new_birth_date);
            
            $old_age = (new DateTime())->diff($old_date)->y;
            $new_age = (new DateTime())->diff($new_date)->y;
            $age_difference = abs($old_age - $new_age);
            
            if ($age_difference > 5) {
                $result['allowed'] = false;
                $result['message'] = "Age changes are limited to 5 years at a time. Requested change: {$age_difference} years.";
                return $result;
            }
            
            // Rule 4: Prevent age bouncing (frequent small changes)
            $stmt = $this->pdo->prepare("
                SELECT new_age, previous_age, 
                       ABS(new_age - previous_age) as change_amount
                FROM age_change_history 
                WHERE user_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 60 DAY)
                ORDER BY created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$this->user_id]);
            $recent_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($recent_changes) >= 2) {
                $total_change = 0;
                $direction_changes = 0;
                $last_change = null;
                
                foreach ($recent_changes as $change) {
                    if ($last_change !== null) {
                        // Check if direction changed (up then down)
                        $current_dir = ($change['new_age'] > $change['previous_age']) ? 'up' : 'down';
                        $last_dir = ($last_change['new_age'] > $last_change['previous_age']) ? 'up' : 'down';
                        
                        if ($current_dir !== $last_dir) {
                            $direction_changes++;
                        }
                    }
                    $last_change = $change;
                }
                
                if ($direction_changes >= 2) {
                    $result['allowed'] = false;
                    $result['message'] = "Too many age adjustments detected. Please contact support if this is an error.";
                    return $result;
                }
            }
        }
        
        return $result;
    }
    
    // Log age change for history tracking
    public function logAgeChange($previous_birth_date, $new_birth_date, $change_reason = 'user_update') {
        $previous_age = $previous_birth_date ? (new DateTime())->diff(new DateTime($previous_birth_date))->y : null;
        $new_age = (new DateTime())->diff(new DateTime($new_birth_date))->y;
        
        $stmt = $this->pdo->prepare("
            INSERT INTO age_change_history 
            (user_id, previous_age, new_age, previous_birth_date, new_birth_date, change_reason, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->user_id,
            $previous_age,
            $new_age,
            $previous_birth_date,
            $new_birth_date,
            $change_reason,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    // Get age change history for user
    public function getChangeHistory($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM age_change_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$this->user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
// =======================================================
// IMPROVED: CENTRALIZED VALIDATION RULES (Easy to Scale!)
// =======================================================
class ValidationRules {
    private static $rules = [
        'full_name' => [
            'required' => true,
            'min_length' => 3,
            'max_length' => 100,
            'pattern' => '/^[a-zA-Z\s\.\-\']+$/',
            'pattern_message' => 'Full name can only contain letters, spaces, dots, hyphens and apostrophes',
            'sanitize' => true
        ],
        'email' => [
            'required' => true,
            'type' => 'email',
            'readonly' => true
        ],
        'phone_number' => [
            'required' => true,
            'pattern' => '/^09\d{9}$/',
            'pattern_message' => 'Phone number must be 11 digits starting with 09 (e.g., 09123456789)',
            'sanitize' => 'phone'
        ],
        'age' => [
            'required' => true,
            'type' => 'integer',
            'min' => 16,        // Changed from 1 to 16
            'max' => 90,        // Changed from 120 to 90
            'message' => 'Age must be between 16 and 90 years old',
            'auto_calculated' => true
        ],
        'gender' => [
            'required' => true,
            'readonly' => true
        ],
        'occupation' => [
            'required' => true,
            'min_length' => 2,
            'max_length' => 100,
            'pattern' => '/^[a-zA-Z0-9\s\.\-\/]+$/',
            'pattern_message' => 'Occupation can only contain letters, numbers, spaces, dots, hyphens and slashes',
            'sanitize' => true
        ],
        'address' => [
            'required' => false,
            'min_length' => 5,
            'max_length' => 255,
            'sanitize' => true
        ],
        'birth_date' => [
            'required' => true,
            'type' => 'date',
            'max_date' => 'today',
            'min_age' => 16,      // NEW: Minimum age requirement
            'max_age' => 90,      // NEW: Maximum age requirement
            'message' => 'You must be between 16 and 90 years old to use this service'
        ],
        'suffix' => [
            'required' => false,
            'max_length' => 10,
            'allowed_values' => ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', ''],
            'message' => 'Invalid suffix value'
        ]
    ];
    
    public static function validate($field_name, $value) {
        if (!isset(self::$rules[$field_name])) {
            return ['valid' => true, 'value' => $value];
        }
        
        $rules = self::$rules[$field_name];
        $original_value = $value;
        
        // Sanitize first if needed
        if (isset($rules['sanitize'])) {
            $value = self::sanitize($value, $rules['sanitize']);
        }
        
        // Check if readonly (shouldn't be changed)
        if (isset($rules['readonly']) && $rules['readonly']) {
            return ['valid' => true, 'value' => $value, 'readonly' => true];
        }
        
        // Required check
        if (isset($rules['required']) && $rules['required']) {
            if (empty($value) && $value !== '0') {
                return [
                    'valid' => false, 
                    'message' => ucfirst(str_replace('_', ' ', $field_name)) . ' is required',
                    'value' => $value
                ];
            }
        }
        
        // If empty and not required, skip other validations
        if (empty($value) && $value !== '0') {
            return ['valid' => true, 'value' => $value];
        }
        
        // Type-specific validations
        if (isset($rules['type'])) {
            switch ($rules['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return ['valid' => false, 'message' => 'Invalid email format', 'value' => $value];
                    }
                    break;
                    
                case 'integer':
                    if (!is_numeric($value) || intval($value) != $value) {
                        return ['valid' => false, 'message' => 'Must be a valid number', 'value' => $value];
                    }
                    $value = intval($value);
                    
                    // Check age-specific rules
                    if ($field_name === 'age') {
                        if (isset($rules['min']) && $value < $rules['min']) {
                            return ['valid' => false, 'message' => $rules['message'] ?? "Minimum age is {$rules['min']}", 'value' => $value];
                        }
                        if (isset($rules['max']) && $value > $rules['max']) {
                            return ['valid' => false, 'message' => $rules['message'] ?? "Maximum age is {$rules['max']}", 'value' => $value];
                        }
                    }
                    break;
                    
                case 'date':
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        return ['valid' => false, 'message' => 'Invalid date format', 'value' => $value];
                    }
                    
                    // Check if date is in the future
                    if (isset($rules['max_date']) && $rules['max_date'] === 'today') {
                        if ($date > new DateTime()) {
                            return ['valid' => false, 'message' => $rules['message'] ?? 'Date cannot be in the future', 'value' => $value];
                        }
                    }
                    
                    // NEW: Check age requirements for birth date
                    if (isset($rules['min_age']) || isset($rules['max_age'])) {
                        $today = new DateTime();
                        $calculated_age = $today->diff($date)->y;
                        
                        if (isset($rules['min_age']) && $calculated_age < $rules['min_age']) {
                            return ['valid' => false, 'message' => "You must be at least {$rules['min_age']} years old", 'value' => $value];
                        }
                        
                        if (isset($rules['max_age']) && $calculated_age > $rules['max_age']) {
                            return ['valid' => false, 'message' => "Maximum age allowed is {$rules['max_age']} years", 'value' => $value];
                        }
                    }
                    break;
            }
        }
        
        // Min/Max validations for non-age fields
        if (isset($rules['min']) && is_numeric($value) && $field_name !== 'age' && $value < $rules['min']) {
            return ['valid' => false, 'message' => $rules['message'] ?? "Minimum value is {$rules['min']}", 'value' => $value];
        }
        
        if (isset($rules['max']) && is_numeric($value) && $field_name !== 'age' && $value > $rules['max']) {
            return ['valid' => false, 'message' => $rules['message'] ?? "Maximum value is {$rules['max']}", 'value' => $value];
        }
        
        // Length validations
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            return ['valid' => false, 'message' => "Minimum length is {$rules['min_length']} characters", 'value' => $value];
        }
        
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return ['valid' => false, 'message' => "Maximum length is {$rules['max_length']} characters", 'value' => $value];
        }
        
        // Pattern validation
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            return ['valid' => false, 'message' => $rules['pattern_message'] ?? 'Invalid format', 'value' => $value];
        }
        
        // Allowed values check
        if (isset($rules['allowed_values']) && !in_array($value, $rules['allowed_values'])) {
            return ['valid' => false, 'message' => $rules['message'] ?? 'Invalid value', 'value' => $value];
        }
        
        return ['valid' => true, 'value' => $value];
    }
    
    // ... rest of the class remains the same ...

    
    private static function sanitize($value, $type) {
        if ($type === 'phone') {
            return preg_replace('/\s+/', '', $value); // Remove all spaces
        }
        
        if ($type === true) {
            return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }
        
        return trim($value);
    }
    
    public static function getAllRules() {
        return self::$rules;
    }
}

// =======================================================
// 3. HANDLE PROFILE UPDATE (With Validation & Rate Limiting)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    // STEP 1: Check rate limit
    $rate_check = check_rate_limit($pdo, $user_id, 'profile_update', 3, 60); // 3 updates per hour
    if (!$rate_check['allowed']) {
        $_SESSION['error_message'] = $rate_check['message'];
        header("Location: profile.php");
        exit();
    }
    
    // STEP 2: Collect and validate all fields
    $validated_data = [];
    $validation_errors = [];
    
    $fields_to_validate = ['full_name', 'email', 'phone_number', 'age', 'gender', 'occupation', 'address', 'birth_date', 'suffix'];
    
    foreach ($fields_to_validate as $field) {
        $value = trim($_POST[$field] ?? '');
        $validation_result = ValidationRules::validate($field, $value);
        
        if (!$validation_result['valid']) {
            $validation_errors[] = $validation_result['message'];
        } else {
            $validated_data[$field] = $validation_result['value'];
        }
    }
    
    // STEP 3: If validation errors, stop and show them
    if (!empty($validation_errors)) {
        $_SESSION['error_message'] = "Validation failed: " . implode("; ", $validation_errors);
        header("Location: profile.php");
        exit();
    }
    // NEW: AGE CHANGE VALIDATION
$age_validator = new AgeChangeValidator($pdo, $user_id);
$current_birth_date = decrypt_data($current_data['birth_date']);
// Check if birth date is actually changing
if ($validated_data['birth_date'] != $current_birth_date) {
    $age_check = $age_validator->canChangeAge($validated_data['birth_date'], $current_birth_date);
    
    if (!$age_check['allowed']) {
        $_SESSION['error_message'] = "Age change rejected: " . $age_check['message'];
        if ($age_check['cooldown_remaining'] > 0) {
            $_SESSION['error_message'] .= " Cooldown: " . $age_check['cooldown_remaining'] . " day(s) remaining.";
        }
        header("Location: profile.php");
        exit();
    }
}
    
    // STEP 4: Check if data actually changed (prevent unnecessary updates)
    try {
        $check_stmt = $pdo->prepare("
            SELECT u.full_name, u.email, u.phone_number, u.address, 
                   c.age, c.gender, c.occupation, c.birth_date, c.suffix
            FROM users u
            LEFT JOIN clients c ON u.id = c.user_id
            WHERE u.id = ?
        ");
        $check_stmt->execute([$user_id]);
        $current_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decrypt current data
        $current_data['full_name'] = decrypt_data($current_data['full_name']);
        $current_data['phone_number'] = decrypt_data($current_data['phone_number']);
        $current_data['address'] = decrypt_data($current_data['address']);
        $current_data['occupation'] = decrypt_data($current_data['occupation']);
        $current_data['birth_date'] = decrypt_data($current_data['birth_date']);
        
        // Compare values
        $has_changes = false;
        foreach (['full_name', 'phone_number', 'address', 'occupation', 'age', 'birth_date', 'suffix'] as $field) {
            if ($current_data[$field] != $validated_data[$field]) {
                $has_changes = true;
                break;
            }
        }
        
        if (!$has_changes) {
            $_SESSION['error_message'] = "No changes detected. Update cancelled.";
            header("Location: profile.php");
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error checking data: " . $e->getMessage();
        header("Location: profile.php");
        exit();
    }
    
    // STEP 5: Encrypt sensitive data
    $encrypted_full_name = encrypt_data($validated_data['full_name']);
    $encrypted_phone_number = encrypt_data($validated_data['phone_number']);
    $encrypted_address = encrypt_data($validated_data['address']);
    $encrypted_occupation = encrypt_data($validated_data['occupation']);

    // STEP 6: Update database with transaction
    // STEP 6: Update database with transaction
try {
    $pdo->beginTransaction();

    $update_user_stmt = $pdo->prepare("
        UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE id = ?
    ");
    $update_user_stmt->execute([
        $encrypted_full_name,
        $validated_data['email'], 
        $encrypted_phone_number,
        $encrypted_address,
        $user_id
    ]);

    $update_client_stmt = $pdo->prepare("
        UPDATE clients 
        SET birth_date = ?, gender = ?, age = ?, suffix = ?, occupation = ? 
        WHERE user_id = ?
    ");
    $update_client_stmt->execute([
        $validated_data['birth_date'], 
        $validated_data['gender'], 
        $validated_data['age'], 
        $validated_data['suffix'], 
        $encrypted_occupation, 
        $user_id
    ]);
    
    // NEW: Log age change if birth date was updated
    if ($validated_data['birth_date'] != $current_birth_date) {
        $age_validator->logAgeChange($current_birth_date, $validated_data['birth_date'], 'profile_update');
        
        // Also update session with new age for immediate feedback
        $_SESSION['user_age'] = $validated_data['age'];
    }
    
    // STEP 7: Log this update for rate limiting
    log_rate_limit($pdo, $user_id, 'profile_update');
    
    $pdo->commit();
    $_SESSION['success_message'] = "Profile updated successfully!";
    
    // Add note about age change if applicable
    if ($validated_data['birth_date'] != $current_birth_date) {
        $age_difference = abs((new DateTime())->diff(new DateTime($validated_data['birth_date']))->y - 
                              (new DateTime())->diff(new DateTime($current_birth_date))->y);
        $_SESSION['success_message'] .= " Age updated by {$age_difference} year(s).";
    }
    
    header("Location: profile.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error_message'] = "Update failed: " . $e->getMessage();
    header("Location: profile.php");
    exit();
}
}

// =======================================================
// 4. HANDLE PASSWORD CHANGE (With Rate Limiting)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    
    // Check rate limit for password changes (stricter: 2 attempts per hour)
    $rate_check = check_rate_limit($pdo, $user_id, 'password_change', 2, 60);
    if (!$rate_check['allowed']) {
        $_SESSION['error_message'] = $rate_check['message'];
        header("Location: profile.php");
        exit();
    }
    
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Enhanced password validation
    if (strlen($new_password) < 8) {
        $_SESSION['error_message'] = "New password must be at least 8 characters.";
        header("Location: profile.php");
        exit();
    }
    
    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
        $_SESSION['error_message'] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        header("Location: profile.php");
        exit();
    }
    
    $fetch_hash_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $fetch_hash_stmt->execute([$user_id]);
    $user_pass_data = $fetch_hash_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_pass_data && password_verify($current_password, $user_pass_data['password_hash'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT); 
            $update_password = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt_pass = $pdo->prepare($update_password);
            
            if ($stmt_pass->execute([$hashed_password, $user_id])) {
                log_rate_limit($pdo, $user_id, 'password_change');
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "New passwords do not match.";
            header("Location: profile.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Current password is incorrect.";
        header("Location: profile.php");
        exit();
    }
}

// =======================================================
// 5. FETCH AND DECRYPT USER DATA
// =======================================================
try {
    $query = "SELECT u.*, c.birth_date, c.gender, c.age, c.suffix, c.occupation 
              FROM users u 
              LEFT JOIN clients c ON u.id = c.user_id 
              WHERE u.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user_encrypted = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_encrypted) {
        session_destroy();
        header("Location: ../public/login.php");
        exit();
    }
    
    $user = $user_encrypted;
    $user['full_name']    = decrypt_data($user_encrypted['full_name'] ?? '');
    $user['phone_number'] = decrypt_data($user_encrypted['phone_number'] ?? '');
    $user['address']      = decrypt_data($user_encrypted['address'] ?? ''); 
    $user['occupation']   = decrypt_data($user_encrypted['occupation'] ?? '');  
    $user['birth_date']   = decrypt_data($user_encrypted['birth_date'] ?? '');  
 
} catch (Exception $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
}

// --- 6. HANDLE MESSAGES ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$name_parts = explode(' ', $user['full_name'] ?? '');
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user['full_name'] ?? 'U', 0, 2));
}

// Get validation rules for frontend
$validation_rules = ValidationRules::getAllRules();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Success/Error Modal Styles */
        .notification-modal {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
        }

        .notification-modal.show {
            display: block;
        }

        .notification-content {
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 500px;
        }

        .notification-content.success {
            border-left: 4px solid #10b981;
        }

        .notification-content.error {
            border-left: 4px solid #ef4444;
        }

        .notification-icon {
            font-size: 24px;
        }

        .notification-content.success .notification-icon {
            color: #10b981;
        }

        .notification-content.error .notification-icon {
            color: #ef4444;
        }

        .notification-text {
            flex: 1;
            color: #1f2937;
            font-size: 14px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .notification-modal.hiding {
            animation: slideOutRight 0.3s ease-out;
        }

        /* Input Error Styling */
        .input-error {
            border: 2px solid #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .error-text {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .error-text.show {
            display: block;
        }
        
        /* Character counter */
        .char-counter {
            font-size: 11px;
            color: #6b7280;
            text-align: right;
            margin-top: 2px;
        }
        
        .char-counter.warning {
            color: #f59e0b;
        }
        
        .char-counter.error {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>

    <!-- Notification Modal -->
    <div id="notificationModal" class="notification-modal">
        <div class="notification-content" id="notificationContent">
            <div class="notification-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="notification-text" id="notificationText"></div>
        </div>
    </div>

    <div class="ojo-container">
        
        <div class="account-header">
            <h1>MY ACCOUNT</h1>
        </div>

        <div class="account-grid">
            <nav class="account-menu">
                <ul>
                    <li><a href="profile.php" class="active">Account Details</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="settings.php">Settings</a></li>
                    <li><a href="../actions/logout.php" style="color: #e74c3c;">Log out</a></li>
                </ul>
            </nav>

            <main class="account-content">
                
                <h3>Personal Information</h3>

                <form method="POST" action="" id="profileForm">
                    <div class="ojo-form-grid">
                        <div class="ojo-group">
                            <label>Full Name *</label>
                            <input type="text" 
                                   name="full_name" 
                                   id="full_name" 
                                   value="<?= htmlspecialchars($user['full_name']) ?>" 
                                   maxlength="<?= $validation_rules['full_name']['max_length'] ?>"
                                   required>
                            <span class="error-text" id="full_name_error"></span>
                            <div class="char-counter" id="full_name_counter"></div>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Email Address *</label>
                            <input type="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" 
                                   readonly 
                                   style="color:#999;"
                                   title="Email cannot be changed">
                        </div>
                        
                        <div class="ojo-group">
                            <label>Phone Number *</label>
                            <input type="text" 
                                   name="phone_number" 
                                   id="phone_number" 
                                   value="<?= htmlspecialchars($user['phone_number']) ?>" 
                                   maxlength="11" 
                                   required>
                            <span class="error-text" id="phone_number_error"></span>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Occupation *</label>
                            <input type="text" 
                                   name="occupation" 
                                   id="occupation"
                                   value="<?= htmlspecialchars($user['occupation']) ?>" 
                                   maxlength="<?= $validation_rules['occupation']['max_length'] ?>"
                                   required>
                            <span class="error-text" id="occupation_error"></span>
                            <div class="char-counter" id="occupation_counter"></div>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Age *</label>
                            <input type="number" 
       name="age" 
       id="age" 
       value="<?= htmlspecialchars($user['age']) ?>" 
       min="<?= $validation_rules['age']['min'] ?>" 
       max="<?= $validation_rules['age']['max'] ?>" 
       readonly
       style="background-color: #f3f4f6; color: #6b7280; cursor: not-allowed;"
       title="Age is automatically calculated from birth date"
       required>
                            <span class="error-text" id="age_error"></span>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Gender *</label>
                            <input type="text" 
                                   name="gender" 
                                   value="<?= htmlspecialchars($user['gender'] ?? '') ?>" 
                                   readonly
                                   style="color:#999;"
                                   title="Gender cannot be changed">
                        </div>
                        
                        <div class="ojo-group">
                            <label>Address</label>
                            <input type="text" 
                                   name="address" 
                                   id="address"
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>" 
                                   maxlength="<?= $validation_rules['address']['max_length'] ?>">
                            <span class="error-text" id="address_error"></span>
                            <div class="char-counter" id="address_counter"></div>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Birth Date *</label>
                            <input type="date" 
                                   name="birth_date" 
                                   id="birth_date"
                                   value="<?= htmlspecialchars($user['birth_date'] ?? '') ?>"
                                   max="<?= date('Y-m-d') ?>">
                            <span class="error-text" id="birth_date_error"></span>
                        </div>
                        
                        <div class="ojo-group">
                            <label>Suffix</label>
                            <select name="suffix" id="suffix">
                                <option value="">None</option>
                                <?php 
                                $suffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
                                foreach ($suffixes as $suf) {
                                    $selected = ($user['suffix'] == $suf) ? 'selected' : '';
                                    echo "<option value='$suf' $selected>$suf</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-ojo" id="submitBtn">SAVE CHANGES</button>
                </form>

            </main>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        // Pass PHP validation rules to JavaScript
        const validationRules = <?= json_encode($validation_rules) ?>;
        
        // Show notification if message exists
        <?php if ($success_message): ?>
            // Auto-calculate age on page load if birth date exists
document.addEventListener('DOMContentLoaded', function() {
    const birthDateInput = document.getElementById('birth_date');
    if (birthDateInput && birthDateInput.value) {
        // Trigger change event to calculate age
        birthDateInput.dispatchEvent(new Event('change'));
    }
});
            showNotification('<?= addslashes($success_message) ?>', 'success');
        <?php endif; ?>

        <?php if ($error_message): ?>
            showNotification('<?= addslashes($error_message) ?>', 'error');
        <?php endif; ?>

        function showNotification(message, type) {
            const modal = document.getElementById('notificationModal');
            const content = document.getElementById('notificationContent');
            const text = document.getElementById('notificationText');
            const icon = content.querySelector('.notification-icon i');

            text.textContent = message;
            content.className = 'notification-content ' + type;
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
            } else {
                icon.className = 'fas fa-exclamation-circle';
            }

            modal.classList.add('show');

            setTimeout(() => {
                modal.classList.add('hiding');
                setTimeout(() => {
                    modal.classList.remove('show', 'hiding');
                }, 300);
            }, 5000);
        }
        // Enhanced birth date validation with age restrictions
function validateBirthDate(value) {
    if (!value) return { valid: false, message: 'Birth date is required' };
    
    const birthDate = new Date(value);
    const today = new Date();
    
    if (birthDate > today) {
        return { valid: false, message: 'Birth date cannot be in the future' };
    }
    
    // Calculate age
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    // Check age restrictions
    if (age < 16) {
        return { valid: false, message: 'You must be at least 16 years old' };
    }
    
    if (age > 90) {
        return { valid: false, message: 'Maximum age allowed is 90 years' };
    }
    
    return { valid: true, age: age };
}

// Update the form validation to include birth date
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let isValid = true;
    const fieldsToValidate = ['full_name', 'phone_number', 'age', 'occupation', 'address', 'birth_date'];
    
    fieldsToValidate.forEach(fieldName => {
        const input = document.getElementById(fieldName);
        if (!input) return;
        
        let result;
        
        if (fieldName === 'birth_date') {
            result = validateBirthDate(input.value);
            if (!result.valid) {
                e.preventDefault();
                showFieldError(fieldName, result.message);
                isValid = false;
            } else {
                // Update age field with calculated age
                const ageInput = document.getElementById('age');
                ageInput.value = result.age;
                clearFieldError(fieldName);
            }
        } else {
            result = validateField(fieldName, input.value);
            if (!result.valid) {
                e.preventDefault();
                showFieldError(fieldName, result.message);
                isValid = false;
            } else {
                clearFieldError(fieldName);
            }
        }
    });

    if (!isValid) {
        showNotification('Please correct the errors before submitting.', 'error');
    } else {
        // Additional validation: Show warning for age changes
        const birthDateInput = document.getElementById('birth_date');
        const originalBirthDate = "<?= htmlspecialchars($user['birth_date'] ?? '') ?>";
        
        if (birthDateInput.value && birthDateInput.value !== originalBirthDate) {
            const oldDate = new Date(originalBirthDate);
            const newDate = new Date(birthDateInput.value);
            
            const oldAge = calculateAge(oldDate);
            const newAge = calculateAge(newDate);
            const ageDifference = Math.abs(newAge - oldAge);
            
            if (ageDifference > 5) {
                if (!confirm(`You're changing your age by ${ageDifference} years. Age changes are limited to 5 years at a time and can only be done every 14 days. Continue?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    }
});

// Helper function to calculate age
function calculateAge(birthDate) {
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

// Add real-time age validation to birth date field
document.getElementById('birth_date').addEventListener('change', function() {
    const result = validateBirthDate(this.value);
    if (!result.valid) {
        showFieldError('birth_date', result.message);
    } else {
        clearFieldError('birth_date');
        // Update age field
        document.getElementById('age').value = result.age;
        
        // Show age change warning if applicable
        const originalBirthDate = "<?= htmlspecialchars($user['birth_date'] ?? '') ?>";
        if (originalBirthDate && this.value !== originalBirthDate) {
            const oldDate = new Date(originalBirthDate);
            const newDate = new Date(this.value);
            const oldAge = calculateAge(oldDate);
            const newAge = calculateAge(newDate);
            const ageDifference = Math.abs(newAge - oldAge);
            
            if (ageDifference > 0) {
                const warningDiv = document.createElement('div');
                warningDiv.className = 'warning-notice';
                warningDiv.style.cssText = `
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 4px;
                    padding: 8px;
                    margin-top: 8px;
                    font-size: 12px;
                    color: #856404;
                `;
                warningDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    Age will change by ${ageDifference} year(s). 
                    ${ageDifference > 5 ? '<strong>Note: Changes over 5 years require approval.</strong>' : ''}
                `;
                
                // Remove existing warning
                const existingWarning = this.parentNode.querySelector('.warning-notice');
                if (existingWarning) existingWarning.remove();
                
                this.parentNode.appendChild(warningDiv);
            }
        }
    }
});
        // Validation helper functions
        function validateField(fieldName, value) {
            const rules = validationRules[fieldName];
            if (!rules) return { valid: true };
            
            // Required check
            if (rules.required && (!value || value.trim() === '')) {
                return { valid: false, message: fieldName.replace('_', ' ').toUpperCase() + ' is required' };
            }
            
            // Skip other validations if empty and not required
            if (!value && !rules.required) {
                return { valid: true };
            }
            
            // Min/Max for numbers
            if (rules.type === 'integer') {
                const num = parseInt(value);
                if (isNaN(num)) {
                    return { valid: false, message: 'Must be a valid number' };
                }
                if (rules.min && num < rules.min) {
                    return { valid: false, message: rules.message || `Minimum value is ${rules.min}` };
                }
                if (rules.max && num > rules.max) {
                    return { valid: false, message: rules.message || `Maximum value is ${rules.max}` };
                }
            }
            
            // Length checks
            if (rules.min_length && value.length < rules.min_length) {
                return { valid: false, message: `Minimum length is ${rules.min_length} characters` };
            }
            if (rules.max_length && value.length > rules.max_length) {
                return { valid: false, message: `Maximum length is ${rules.max_length} characters` };
            }
            
            // Pattern matching
            if (rules.pattern) {
                const pattern = new RegExp(rules.pattern.replace(/^\/|\/$/g, ''));
                if (!pattern.test(value)) {
                    return { valid: false, message: rules.pattern_message || 'Invalid format' };
                }
            }
            
            return { valid: true };
        }

        function showFieldError(fieldName, message) {
            const input = document.getElementById(fieldName);
            const error = document.getElementById(fieldName + '_error');
            
            if (input && error) {
                input.classList.add('input-error');
                error.textContent = message;
                error.classList.add('show');
            }
        }

        function clearFieldError(fieldName) {
            const input = document.getElementById(fieldName);
            const error = document.getElementById(fieldName + '_error');
            
            if (input && error) {
                input.classList.remove('input-error');
                error.classList.remove('show');
            }
        }

        // Character counters
        function updateCharCounter(fieldName) {
            const input = document.getElementById(fieldName);
            const counter = document.getElementById(fieldName + '_counter');
            const rules = validationRules[fieldName];
            
            if (!input || !counter || !rules || !rules.max_length) return;
            
            const current = input.value.length;
            const max = rules.max_length;
            const remaining = max - current;
            
            counter.textContent = `${current}/${max} characters`;
            
            counter.classList.remove('warning', 'error');
            if (remaining < 20) {
                counter.classList.add('warning');
            }
            if (remaining < 0) {
                counter.classList.add('error');
            }
        }

        // Form validation on submit
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            let isValid = true;
            const fieldsToValidate = ['full_name', 'phone_number', 'age', 'occupation', 'address', 'birth_date'];
            
            fieldsToValidate.forEach(fieldName => {
                const input = document.getElementById(fieldName);
                if (!input) return;
                
                const result = validateField(fieldName, input.value);
                if (!result.valid) {
                    e.preventDefault();
                    showFieldError(fieldName, result.message);
                    isValid = false;
                } else {
                    clearFieldError(fieldName);
                }
            });

            if (!isValid) {
                showNotification('Please correct the errors before submitting.', 'error');
            }
        });

        // Auto-calculate age from birth date
        document.getElementById('birth_date').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            
            if (this.value && birthDate <= today) {
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                // Adjust if birthday hasn't occurred this year
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                // Update age field
                const ageInput = document.getElementById('age');
                ageInput.value = age;
                
                // Clear any age errors since it's auto-calculated
                clearFieldError('age');
            }
        });

        // Real-time validation for all fields
        ['full_name', 'phone_number', 'age', 'occupation', 'address', 'birth_date'].forEach(fieldName => {
            const input = document.getElementById(fieldName);
            if (!input) return;
            
            input.addEventListener('input', function() {
                const result = validateField(fieldName, this.value);
                if (this.value && !result.valid) {
                    showFieldError(fieldName, result.message);
                    
                } else {
                    clearFieldError(fieldName);
                }
                
                // Update character counter if applicable
                updateCharCounter(fieldName);
            });
            
            // Initialize character counter
            updateCharCounter(fieldName);
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>