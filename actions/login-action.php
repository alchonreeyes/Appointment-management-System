<?php
// actions/login-action.php
session_start();
header('Content-Type: application/json');
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

// --- 1. COOLDOWN CONFIGURATION ---
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 10);

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

    // =========================================================
    // PRIORITY 1: CHECK ADMIN / STAFF TABLE
    // (Assuming table name is 'admin' and has a 'role' column)
    // =========================================================
    $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
    $stmtAdmin->execute([$email]);
    $adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    // NOTE: Plain Text Password Check muna para sa Admin/Staff (gaya ng request mo)
    if ($adminUser && $adminUser['password'] === $password) {
        
        // RESET FAILURES
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        // SET SESSION FOR ADMIN/STAFF
        // Gumamit tayo ng ibang session key para hindi maghalo sa client
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['user_role'] = $adminUser['role']; // 'admin' or 'staff'
        $_SESSION['full_name'] = $adminUser['name'];

        // DETERMINE REDIRECT PATH
        $redirect = '';
        if ($adminUser['role'] === 'admin') {
            $redirect = '../mod/admin/admin_dashboard.php';
        } else {
            // Assuming staff dashboard path
            $redirect = '../mod/staff/staff_dashboard.php'; 
        }

        echo json_encode([
            'success' => true,
            'message' => 'Welcome, ' . $adminUser['role'] . '!',
            'redirect' => $redirect
        ]);
        exit;
    }

    // =========================================================
    // PRIORITY 2: CHECK CLIENTS TABLE (USERS)
    // =========================================================
    $stmtClient = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmtClient->execute([$email]);
    $clientUser = $stmtClient->fetch(PDO::FETCH_ASSOC);

    // Client uses Hashed Password (standard security)
    if ($clientUser && password_verify($password, $clientUser['password_hash'])) {
        
        // RESET FAILURES
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        // SET SESSION FOR CLIENT
        $_SESSION['client_id'] = $clientUser['id'];
        $_SESSION['client_email'] = $clientUser['email'];
        $_SESSION['client_role'] = 'client';

        // Check Verification (Optional)
        if ($clientUser['is_verified'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Please verify your email first.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'redirect' => '../public/home.php'
        ]);
        exit;
    }

    // =========================================================
    // FAILED LOGIN (Neither Admin nor Client)
    // =========================================================
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
?>