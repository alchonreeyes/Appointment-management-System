<?php
// actions/login-action.php
session_start();
header('Content-Type: application/json');

// 1. DATABASE CONNECTION
require_once '../config/db.php';
require_once '../config/encryption_util.php'; 

$db = new Database();
$pdo = $db->getConnection();

// --- COOLDOWN CONFIGURATION ---
define('MAX_ATTEMPTS', 3);
define('FIRST_COOLDOWN', 30); 

// Check Cooldown
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
        // Decrypt email to compare
        $decryptedEmail = decrypt_data($adminUser['email']);
        
        if ($decryptedEmail === $email_input) {
            // ✅ FIX: Decrypt the stored password first
            $db_pass = $adminUser['password'];
            $decrypted_pass = decrypt_data($db_pass);

            // ✅ FIX: Compare Plain Input vs (Decrypted DB OR Plain DB)
            // This handles both Encrypted passwords AND old Plain Text passwords
            if ($password_input === $decrypted_pass || $password_input === $db_pass) {
                
                // Login Success
                loginSuccess($adminUser, 'admin', $adminUser['name']);
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
        // Decrypt email to compare
        $decryptedStaffEmail = decrypt_data($staffUser['email']);
        
        if ($decryptedStaffEmail === $email_input) {
            
            // Check Status
            if ($staffUser['status'] !== 'Active') {
                echo json_encode(['success' => false, 'message' => 'Account deactivated. Contact Admin.']);
                exit;
            }

            // ✅ FIX: Decrypt the stored password first
            $db_pass = $staffUser['password'];
            $decrypted_pass = decrypt_data($db_pass);

            // ✅ FIX: Compare Plain Input vs (Decrypted DB OR Plain DB)
            // Note: We REMOVED password_verify() because you are using encryption, not hashing
            if ($password_input === $decrypted_pass || $password_input === $db_pass) {
                
                // Login Success
                loginSuccess($staffUser, 'staff', $staffUser['full_name']);
            }
        }
    }

    // =========================================================
    // PRIORITY 3: CHECK CLIENTS (USERS TABLE) - Standard Hashing
    // =========================================================
    // Clients usually use standard password_hash/verify, so we keep this distinct
    $stmtClient = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmtClient->execute([$email_input]);
    $clientUser = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if ($clientUser && password_verify($password_input, $clientUser['password_hash'])) {
        
        // Check verification
        if ($clientUser['is_verified'] == 0) {
            $_SESSION['email'] = $clientUser['email']; 
            echo json_encode([
                'success' => false, 
                'message' => 'Please verify your email first.',
                'redirect' => '../public/verify_email.php'
            ]);
            exit;
        }
        
        // Login Success Client
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_cooldown_until']);

        $_SESSION['client_id'] = $clientUser['id'];
        $_SESSION['client_email'] = $clientUser['email'];
        $_SESSION['client_role'] = 'client'; // Fixed role key

        $redirect_url = $_SESSION['redirect_after_login'] ?? '../public/home.php';
        unset($_SESSION['redirect_after_login']);

        echo json_encode(['success' => true, 'redirect' => $redirect_url]);
        exit;
    }

    // =========================================================
    // IF NO MATCH FOUND (FAILURE)
    // =========================================================
    $_SESSION['login_attempts']++;
    $remaining_attempts = MAX_ATTEMPTS - $_SESSION['login_attempts'];
    $error_msg = "Invalid email or password. ($remaining_attempts attempts left)";

    if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
        $_SESSION['login_cooldown_until'] = time() + FIRST_COOLDOWN;
        $_SESSION['login_attempts'] = 0;
        $error_msg = "Too many failed attempts. Wait " . FIRST_COOLDOWN . "s.";
    }

    echo json_encode(['success' => false, 'message' => $error_msg]);
    exit;
}

// --- HELPER FUNCTION TO REDUCE REPETITION ---
function loginSuccess($user, $role, $encryptedName) {
    unset($_SESSION['login_attempts']);
    unset($_SESSION['login_cooldown_until']);

    $_SESSION['user_role'] = $role;
    $_SESSION['full_name'] = decrypt_data($encryptedName); // Decrypt name for session

    if ($role === 'admin') {
        $_SESSION['user_id'] = $user['id'];
        $redirect = '../mod/admin/admin_dashboard.php';
        $msg = 'Welcome, Admin!';
    } else {
        $_SESSION['user_id'] = $user['staff_id'];
        $redirect = '../mod/staff/staff_dashboard.php';
        $msg = 'Welcome, Staff!';
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'redirect' => $redirect
    ]);
    exit;
}
?>