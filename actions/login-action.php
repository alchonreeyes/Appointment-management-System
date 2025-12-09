<?php
// login-action.php
session_start();
header('Content-Type: application/json'); // Tell browser this is JSON data
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

// ========================================
// COOLDOWN CONFIGURATION
// ========================================
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 10);  // 10 seconds
define('SECOND_COOLDOWN', 20); // 20 seconds

// 1. CHECK COOLDOWN
if (isset($_SESSION['login_cooldown_until'])) {
    $remaining = $_SESSION['login_cooldown_until'] - time();
    if ($remaining > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Too many attempts. Wait $remaining seconds."
        ]);
        exit;
    } else {
        unset($_SESSION['login_cooldown_until']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Init attempts
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['total_failed_attempts'] = 0;
    }

    // Fetch User
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. SUCCESSFUL LOGIN
    if ($user && password_verify($password, $user['password_hash'])) {
        // Clear fails
        unset($_SESSION['login_attempts']);
        unset($_SESSION['total_failed_attempts']);
        unset($_SESSION['login_cooldown_until']);
        
        // Set Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Check Verification
        if ($user['is_verified'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Please verify your email first.']);
            exit;
        }

        // Decide Redirect
        $redirectUrl = ($user['role'] === 'admin' || $user['role'] === 'staff') 
            ? '../mod/staff/staff_dashboard.php' // Updated path based on your screenshots
            : '../public/home.php';

        echo json_encode([
            'success' => true,
            'redirect' => $redirectUrl
        ]);
        exit;
    } 
    // 3. FAILED LOGIN
    else {
        $_SESSION['login_attempts']++;
        $_SESSION['total_failed_attempts']++;
        
        $attempts_left = MAX_ATTEMPTS - $_SESSION['login_attempts'];
        $error_msg = "Invalid email or password.";

        // Apply Cooldown if max reached
        if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
            $duration = ($_SESSION['total_failed_attempts'] <= MAX_ATTEMPTS) ? FIRST_COOLDOWN : SECOND_COOLDOWN;
            $_SESSION['login_cooldown_until'] = time() + $duration;
            $_SESSION['login_attempts'] = 0; // Reset counter for next cycle
            
            $error_msg = "Too many failed attempts. Please wait $duration seconds.";
        } else {
            $error_msg .= " You have $attempts_left attempt(s) remaining.";
        }
        
        // Store in session for PHP fallback, but mostly for JSON response
        $_SESSION['error'] = $error_msg;

        echo json_encode([
            'success' => false,
            'message' => $error_msg
        ]);
        exit;
    }
}
?>