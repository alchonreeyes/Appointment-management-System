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
    // PRIORITY 1: CHECK ADMIN TABLE
    // =========================================================
    $stmtAdmin = $pdo->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
    $stmtAdmin->execute([$email]);
    $adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    // NOTE: Plain Text Password for Admin
    if ($adminUser && $adminUser['password'] === $password) {
        
        // RESET FAILURES
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        // SET SESSION FOR ADMIN
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['user_role'] = 'admin'; // Always 'admin' from this table
        $_SESSION['full_name'] = $adminUser['name'];

        echo json_encode([
            'success' => true,
            'message' => 'Welcome, Admin!',
            'redirect' => '../mod/admin/admin_dashboard.php'
        ]);
        exit;
    }

    // =========================================================
    // PRIORITY 2: CHECK STAFF TABLE
    // =========================================================
    $stmtStaff = $pdo->prepare("SELECT * FROM staff WHERE email = ? LIMIT 1");
    $stmtStaff->execute([$email]);
    $staffUser = $stmtStaff->fetch(PDO::FETCH_ASSOC);

    // NOTE: Based on your database, staff uses plain text password too
    // If you want hashed passwords for staff, use password_verify() instead
    if ($staffUser && $staffUser['password'] === $password) {
        
        // Check if staff is active
        if ($staffUser['status'] !== 'Active') {
            echo json_encode(['success' => false, 'message' => 'Your account has been deactivated. Please contact admin.']);
            exit;
        }
        
        // RESET FAILURES
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        // SET SESSION FOR STAFF
        $_SESSION['user_id'] = $staffUser['staff_id'];
        $_SESSION['user_role'] = 'staff';
        $_SESSION['full_name'] = $staffUser['full_name'];

        echo json_encode([
            'success' => true,
            'message' => 'Welcome, Staff!',
            'redirect' => '../mod/staff/staff_dashboard.php'
        ]);
        exit;
    }

    // =========================================================
    // PRIORITY 3: CHECK CLIENTS TABLE (USERS)
    // =========================================================
    $stmtClient = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmtClient->execute([$email]);
    $clientUser = $stmtClient->fetch(PDO::FETCH_ASSOC);

    // NOTE: Clients use hashed passwords (secure)
    if ($clientUser && password_verify($password, $clientUser['password_hash'])) {
        
        // RESET FAILURES
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        // SET SESSION FOR CLIENT
        $_SESSION['client_id'] = $clientUser['id'];
        $_SESSION['client_email'] = $clientUser['email'];
        $_SESSION['client_role'] = 'client';

        // Check Verification
        if ($clientUser['is_verified'] == 0) {
            echo json_encode(['success' => false, 'message' => 'Please verify your email first.']);
            exit;
        }

        // ✅ USE SAVED REDIRECT OR DEFAULT TO HOME
        $redirect_url = $_SESSION['redirect_after_login'] ?? '../public/home.php';
        unset($_SESSION['redirect_after_login']); // Clean up session

        echo json_encode([
            'success' => true,
            'redirect' => $redirect_url
        ]);
        exit;
    }

    // =========================================================
    // FAILED LOGIN (No match found)
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