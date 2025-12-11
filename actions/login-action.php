<?php
// actions/login-action.php
session_start();
header('Content-Type: application/json');
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

// 1. COOLDOWN LOGIC
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 10);
define('SECOND_COOLDOWN', 20);

if (isset($_SESSION['login_cooldown_until'])) {
    $remaining = $_SESSION['login_cooldown_until'] - time();
    if ($remaining > 0) {
        echo json_encode(['success' => false, 'message' => "Too many attempts. Wait $remaining seconds."]);
        exit;
    } else {
        unset($_SESSION['login_cooldown_until']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;

    // 2. FETCH USER (Looking for CLIENTS in users table)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 3. VERIFY PASSWORD
    if ($user && password_verify($password, $user['password_hash'])) {
        // Clear failure counters
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);
        
        // --- THE CRITICAL FIX ---
        // We set 'client_id' instead of 'user_id'
        // This ensures the client session is INDEPENDENT and matches your pages
        $_SESSION['client_id'] = $user['id']; 
        $_SESSION['client_email'] = $user['email'];
        $_SESSION['client_role'] = 'client'; 

        // Check Verification
        if ($user['is_verified'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Please verify your email first.']);
            exit;
        }

        // Always redirect to Client Home
        echo json_encode([
            'success' => true,
            'redirect' => '../public/home.php'
        ]);
        exit;
    } 
    // 4. FAILED LOGIN
    else {
        $_SESSION['login_attempts']++;
        $error_msg = "Invalid email or password.";
        
        if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
            $duration = FIRST_COOLDOWN; 
            $_SESSION['login_cooldown_until'] = time() + $duration;
            $_SESSION['login_attempts'] = 0;
            $error_msg = "Too many failed attempts. Please wait $duration seconds.";
        }
        
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit;
    }
}
?>