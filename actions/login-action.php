<?php
// actions/login-action.php
session_start();
header('Content-Type: application/json');
include '../config/db.php';
require_once '../config/encryption_util.php'; 

$db = new Database();
$pdo = $db->getConnection();

// --- 1. COOLDOWN CONFIGURATION ---
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 30); // 30 seconds cooldown para hindi masyadong matagal sa testing

if (isset($_SESSION['login_cooldown_until'])) {
    $remaining = $_SESSION['login_cooldown_until'] - time();
    if ($remaining > 0) {
        echo json_encode(['success' => false, 'message' => "Too many attempts. Wait $remaining seconds."]);
        exit;
    } else {
        unset($_SESSION['login_cooldown_until']);
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $password_input = $_POST['password'] ?? '';

    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;

    // =========================================================
    // PRIORITY 1: CHECK ADMIN TABLE
    // =========================================================
    $stmtAdmin = $pdo->prepare("SELECT * FROM admin");
    $stmtAdmin->execute();
    $admins = $stmtAdmin->fetchAll(PDO::FETCH_ASSOC);

    foreach ($admins as $adminUser) {
        // I-decrypt ang email na nasa DB para ikumpara sa tinype ni user
        $decryptedEmail = decrypt_data($adminUser['email']);
        
        if ($decryptedEmail === $email_input) {
            // Match ang Email! Ngayon, i-verify ang Password (Bcrypt)
            if (password_verify($password_input, $adminUser['password'])) {
                
                // Success! Reset failure counters
                unset($_SESSION['login_attempts']);
                unset($_SESSION['login_cooldown_until']);

                $_SESSION['user_id'] = $adminUser['id'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['full_name'] = decrypt_data($adminUser['name']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Welcome, Admin!',
                    'redirect' => '../mod/admin/admin_dashboard.php'
                ]);
                exit;
            }
        }
    }

    // =========================================================
    // PRIORITY 2: CHECK STAFF TABLE
    // =========================================================
    $stmtStaff = $pdo->prepare("SELECT * FROM staff");
    $stmtStaff->execute();
    $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

    foreach ($staffList as $staffUser) {
        $decryptedStaffEmail = decrypt_data($staffUser['email']);
        
        if ($decryptedStaffEmail === $email_input) {
            // Match ang Email! Verify Password
            if (password_verify($password_input, $staffUser['password'])) {
                
                if ($staffUser['status'] !== 'Active') {
                    echo json_encode(['success' => false, 'message' => 'Account deactivated. Contact Admin.']);
                    exit;
                }

                unset($_SESSION['login_attempts']);
                unset($_SESSION['login_cooldown_until']);

                // Note: staff_id ang gamit sa staff table
                $_SESSION['user_id'] = $staffUser['staff_id'];
                $_SESSION['user_role'] = 'staff';
                $_SESSION['full_name'] = decrypt_data($staffUser['full_name']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Welcome, Staff!',
                    'redirect' => '../mod/staff/staff_dashboard.php'
                ]);
                exit;
            }
        }
    }

    // =========================================================
    // PRIORITY 3: CHECK CLIENTS (USERS TABLE) - Standard Plain Email
    // =========================================================
    $stmtClient = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmtClient->execute([$email_input]);
    $clientUser = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if ($clientUser && password_verify($password_input, $clientUser['password_hash'])) {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        $_SESSION['client_id'] = $clientUser['id'];
        $_SESSION['client_email'] = $clientUser['email'];
        $_SESSION['client_role'] = 'client';

        $redirect_url = $_SESSION['redirect_after_login'] ?? '../public/home.php';
        unset($_SESSION['redirect_after_login']);

        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
        exit;
    }

    // =========================================================
    // IF NO MATCH FOUND
    // =========================================================
    $_SESSION['login_attempts']++;
    $error_msg = "Invalid email or password.";

    if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
        $_SESSION['login_cooldown_until'] = time() + FIRST_COOLDOWN;
        $_SESSION['login_attempts'] = 0;
        $error_msg = "Too many failed attempts. Wait " . FIRST_COOLDOWN . "s.";
    }

    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}