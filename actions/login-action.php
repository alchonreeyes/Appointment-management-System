<?php
session_start();
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

// ========================================
// COOLDOWN CONFIGURATION
// ========================================
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 10);  // 10 seconds
define('SECOND_COOLDOWN', 20); // 20 seconds (maximum)

// ========================================
// CHECK IF USER IS IN COOLDOWN
// ========================================
if (isset($_SESSION['login_cooldown_until'])) {
    $remaining = $_SESSION['login_cooldown_until'] - time();
    
    if ($remaining > 0) {
        $_SESSION['error'] = "Too many failed attempts. Please wait $remaining seconds before trying again.";
        header('Location: ../public/login.php');
        exit;
    } else {
        // Cooldown expired, reset
        unset($_SESSION['login_cooldown_until']);
    }
}

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Initialize attempt tracking
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['total_failed_attempts'] = 0;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ========================================
    // SUCCESSFUL LOGIN
    // ========================================
    if ($user && password_verify($password, $user['password_hash'])) {
        // Reset attempts on successful login
        unset($_SESSION['login_attempts']);
        unset($_SESSION['total_failed_attempts']);
        unset($_SESSION['login_cooldown_until']);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if($user['is_verified'] == 0){
            $_SESSION['error'] = 'Please verify your email before logging in.';
            header('Location: ../public/login.php');
            exit;
        }

        if ($user['role'] === 'admin' || $user['role'] === 'staff') {
            header('Location: ../admin/dashboard.php');
            exit;
        } else {
            header("Location: ../public/home.php");
            exit;
        }
    } 
    // ========================================
    // FAILED LOGIN
    // ========================================
    else {
        $_SESSION['login_attempts']++;
        $_SESSION['total_failed_attempts']++;
        
        $attempts_left = MAX_ATTEMPTS - $_SESSION['login_attempts'];
        
        // ========================================
        // REACHED MAX ATTEMPTS - APPLY COOLDOWN
        // ========================================
        if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
            // Determine cooldown duration
            if ($_SESSION['total_failed_attempts'] <= MAX_ATTEMPTS) {
                // First cooldown: 10 seconds
                $cooldown_duration = FIRST_COOLDOWN;
            } else {
                // Second+ cooldown: 20 seconds (maximum)
                $cooldown_duration = SECOND_COOLDOWN;
            }
            
            $_SESSION['login_cooldown_until'] = time() + $cooldown_duration;
            $_SESSION['login_attempts'] = 0; // Reset attempts for next round
            
            $_SESSION['error'] = "Too many failed login attempts. Please wait $cooldown_duration seconds before trying again.";
        } else {
            $_SESSION['error'] = "Invalid email or password. You have $attempts_left attempt(s) remaining.";
        }
        
        header("Location: ../public/login.php");
        exit;
    }
}
?>