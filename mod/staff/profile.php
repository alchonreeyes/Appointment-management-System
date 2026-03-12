<?php
// =======================================================
// UPDATED: staff Profile with Strong Password, Real-Time Validation, & Page Loader
// =======================================================
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php';

// I-load ang PHPMailer para sa OTP
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// =======================================================
// 1. SECURITY CHECK
// =======================================================
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

if (!$user_id || $user_role !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../../public/login.php');
    }
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // --- REAL-TIME CURRENT PASSWORD CHECKER ---
    if ($action === 'verifyCurrentPassword') {
        $input_pw = $_POST['current_password'] ?? '';
        if(empty($input_pw)) {
            echo json_encode(['success' => false]); 
            exit;
        }
        // FIXED: id -> staff_id
        $stmt = $conn->prepare("SELECT password FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $db_password = $stmt->get_result()->fetch_assoc()['password'];
        
        // CHECK 1: Try decrypt_data (Your system's standard)
        $decrypted_db_pw = decrypt_data($db_password);
        
        if ($decrypted_db_pw === $input_pw) {
            echo json_encode(['success' => true]);
        } 
        // CHECK 2: Fallback if it was accidentally saved as Bcrypt previously
        elseif (password_verify($input_pw, $db_password)) {
            echo json_encode(['success' => true]);
        } 
        else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    // --- REAL-TIME EMAIL AVAILABILITY CHECKER ---
    if ($action === 'checkEmail') {
        $check_email = trim($_POST['email'] ?? '');
        if (!filter_var($check_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid format']);
            exit;
        }

        $exists = false;
        // FIXED: id -> staff_id
        $checkStmt = $conn->prepare("SELECT staff_id, email FROM staff WHERE staff_id != ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        while ($row = $checkResult->fetch_assoc()) {
            if (decrypt_data($row['email']) === $check_email) {
                $exists = true;
                break;
            }
        }
        echo json_encode(['success' => true, 'exists' => $exists]);
        exit;
    }

    // --- A. SEND / RESEND OTP ACTION ---
    if ($action === 'sendOtp') {
        $new_email = trim($_POST['email'] ?? '');
        
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
            exit;
        }

        $domain = substr(strrchr($new_email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            echo json_encode(['success' => false, 'message' => 'Email domain cannot receive emails.']);
            exit;
        }

        // FIXED: id -> staff_id
        $checkStmt = $conn->prepare("SELECT staff_id, email FROM staff WHERE staff_id != ?");
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        while ($row = $checkResult->fetch_assoc()) {
            if (decrypt_data($row['email']) === $new_email) {
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another staff.']);
                exit;
            }
        }

        $otp = rand(100000, 999999);
        $_SESSION['email_change_otp'] = $otp;
        $_SESSION['email_change_email'] = $new_email;
        $_SESSION['email_change_expiry'] = time() + 300; 

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rogerjuancito0621@gmail.com'; 
            $mail->Password   = 'rhtstropgtnfgipb';          
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            
            $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Master Clinic Security');
            $mail->addAddress($new_email);
            $mail->isHTML(true);
            $mail->Subject = 'Email Change Verification Code';
            $mail->Body    = "
                <div style='font-family:sans-serif; padding:20px; background:#f4f4f4;'>
                    <div style='background:#fff; padding:20px; border-radius:8px; max-width:500px; margin:0 auto; border-top:5px solid #991010;'>
                        <h2 style='color:#991010;'>Verification Code</h2>
                        <p>You requested to change your staff email address. Please use the verification code below to complete the process:</p>
                        <h1 style='font-size:32px; letter-spacing:5px; color:#333; text-align:center; padding:10px; background:#f9f9f9; border-radius:5px;'>{$otp}</h1>
                        <p style='color:#666; font-size:12px;'>This code is valid for 5 minutes. If you did not request this change, please ignore this email.</p>
                    </div>
                </div>";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'Verification code sent to your new email.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP email.']);
        }
        exit;
    }

    // --- B. UPDATE PROFILE ACTION ---
    if ($action === 'updateProfile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        $current_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';
        $otp = trim($_POST['otp'] ?? '');

        if (!$name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Name and Email are required.']);
            exit;
        }

        try {
            // FIXED: id -> staff_id
            $stmt = $conn->prepare("SELECT email, password FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $currUser = $stmt->get_result()->fetch_assoc();
            
            $current_db_email = decrypt_data($currUser['email']);
            $current_db_hash = $currUser['password'];

            if ($email !== $current_db_email) {
                if (empty($otp)) {
                    echo json_encode(['success' => false, 'message' => 'REQUIRE_OTP']); 
                    exit;
                }
                
                if (!isset($_SESSION['email_change_otp']) || 
                    $otp != $_SESSION['email_change_otp'] || 
                    $email !== $_SESSION['email_change_email'] || 
                    time() > $_SESSION['email_change_expiry']) {
                    
                    echo json_encode(['success' => false, 'message' => 'Invalid or expired Verification Code.']);
                    exit;
                }
            }

            $finalPasswordToSave = $current_db_hash; 
            
            if (!empty($new_pw)) {
                if (empty($current_pw)) {
                    echo json_encode(['success' => false, 'message' => 'Current Password is required to set a new password.']);
                    exit;
                }
                if ($new_pw !== $confirm_pw) {
                    echo json_encode(['success' => false, 'message' => 'New Password and Confirm Password do not match.']);
                    exit;
                }
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pw)) {
                    echo json_encode(['success' => false, 'message' => 'Password does not meet the security requirements.']);
                    exit;
                }
                
                $isCurrentValid = false;
                if (decrypt_data($current_db_hash) === $current_pw) {
                    $isCurrentValid = true;
                } elseif (password_verify($current_pw, $current_db_hash)) {
                    $isCurrentValid = true; 
                }
                
                if (!$isCurrentValid) {
                    echo json_encode(['success' => false, 'message' => 'Incorrect Current Password.']);
                    exit;
                }
                
                $finalPasswordToSave = encrypt_data($new_pw); 
            }

            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            // FIXED: name -> full_name, id -> staff_id
            $updateStmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, password=? WHERE staff_id=?");
            $updateStmt->bind_param("sssi", $encryptedName, $encryptedEmail, $finalPasswordToSave, $user_id);
            
            if ($updateStmt->execute()) {
                $_SESSION['full_name'] = $name; 
                unset($_SESSION['email_change_otp']);
                unset($_SESSION['email_change_email']);
                unset($_SESSION['email_change_expiry']);
                
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        exit;
    }
}

// =======================================================
// 3. FETCH USER DATA & DECRYPT
// =======================================================
$user = null;
try {
    // FIXED: id -> staff_id, name -> full_name
    $stmt = $conn->prepare("SELECT staff_id, full_name, email, role FROM staff WHERE staff_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        session_destroy();
        header('Location: ../../public/login.php');
        exit;
    }

    $user['full_name'] = decrypt_data($user['full_name']);
    $user['email'] = decrypt_data($user['email']);

} catch (Exception $e) {
    die("Error loading profile data.");
}

$nameToUse = $user['full_name'] ?? 'staff';
$nameParts = explode(' ', trim($nameToUse));
if (count($nameParts) > 1) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $initials = strtoupper(substr($nameToUse, 0, 1));
    if (strlen($nameToUse) > 1) { 
        $initials .= strtoupper(substr($nameToUse, 1, 1)); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>staff Profile - Eye Master Clinic</title>
<style>
/* Core Styles & 100% Responsive Fixes */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; max-width: 100vw; overflow-x: hidden; padding-bottom: 40px; }

/* --- MISSING SPIN KEYFRAMES ADDED HERE --- */
@keyframes spin { 
    100% { transform: rotate(360deg); } 
}

/* --- PAGE LOADER CSS --- */
.page-loader { 
    position: fixed; inset: 0; background: #f8f9fa; z-index: 99999; 
    display: flex; flex-direction: column; align-items: center; justify-content: center; 
    transition: opacity 0.4s ease; 
}
.page-loader .spinner { 
    width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top: 4px solid #991010; 
    border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; 
}

/* Sidebar & Header */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between;}
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size:14px; }
nav a.active { background:#dc3545; color:#fff; }

/* Main Container */
.container { padding:30px 20px 40px 75px; max-width:1000px; margin:0 auto; }
.profile-card { background:#fff; border:1px solid #e6e9ee; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); animation: slideUp 0.4s ease; }

@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Profile Header */
.profile-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:35px 40px; display:flex; align-items:center; gap:25px; }
.profile-avatar { width:100px; height:100px; border-radius:50%; background:rgba(255,255,255,0.2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:36px; border:4px solid rgba(255,255,255,0.3); box-shadow: 0 5px 15px rgba(0,0,0,0.2); flex-shrink: 0; }
.profile-info { flex:1; }
.profile-name { font-size:28px; font-weight:800; color:#fff; margin-bottom:8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.badge { display:inline-block; padding:6px 14px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.staff-role { background:rgba(255,255,255,0.9); color:#991010; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.badge.staff-id { background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); margin-left: 8px; }

/* Profile Body */
.profile-body { padding:40px; }
.section-title { font-size:20px; font-weight:700; color:#2c3e50; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
.section-title:before { content:'🔒'; font-size:20px; }

.security-notice { background:#fff8e1; border-left:4px solid #ffc107; padding:15px; border-radius:4px; margin-bottom:30px; font-size:14px; color:#5d4037; display:flex; gap:10px; align-items:start; }
.security-notice strong { color:#e65100; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:24px; }
.form-group { display:flex; flex-direction:column; }
.form-group label { font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input { padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:15px; background:#fff; transition:all .2s; }
.form-group input:focus { outline:none; border-color:#991010; box-shadow:0 0 0 3px rgba(153,16,16,0.1); }
.form-group input:disabled { background:#f9fafb; color:#6b7f86; cursor:not-allowed; border-color: #e2e8f0; }

/* Validation Message */
.val-msg { font-size: 12px; margin-top: 5px; font-weight: 600; display: block; min-height: 15px;}

.password-wrapper { position:relative; }
.password-wrapper input { padding-right:45px; width: 100%; }
.password-wrapper button { position:absolute; right:0; top:0; bottom:0; width:40px; background:transparent; border:none; cursor:pointer; font-size:18px; color:#555; display: flex; align-items: center; justify-content: center; z-index: 10; opacity: 0.6; transition: opacity 0.2s; }
.password-wrapper button:hover { opacity: 1; }

.password-section-title { grid-column: 1 / -1; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-weight: 800; font-size: 16px; color: #1a202c; display: none; }

.form-actions { display:flex; gap:12px; justify-content:flex-end; padding-top:25px; border-top:1px solid #f1f5f9; }
.btn { padding:12px 24px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; display:flex; align-items:center; justify-content: center; gap:8px; }
.btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.btn-edit { background:#fff; color:#223; border: 1px solid #dde3ea; }
.btn-edit:hover { background: #f8f9fa; border-color: #cacedb; }
.btn-save { background:linear-gradient(135deg, #16a34a, #15803d); color:#fff; }
.btn-save:disabled { background: #94a3b8; cursor: not-allowed; transform: none; box-shadow: none;}
.btn-cancel { background:#fff; color:#4a5568; border: 2px solid #e2e8f0; }
.btn-logout { background:#fee2e2; color:#dc2626; margin-left: auto; }
.btn-logout:hover { background:#fecaca; }
.btn-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; }

/* Modals & Loaders */
.success-modal-overlay, .confirm-modal-overlay, #actionLoader, #otpModalOverlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.success-modal-overlay.show, .confirm-modal-overlay.show, #actionLoader.show, #otpModalOverlay.show { display: flex; animation: fadeIn 0.3s ease; }

.success-modal-card { background: #fff; padding: 25px 35px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 20px; max-width: 90%; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.success-icon-circle { width: 50px; height: 50px; background-color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.success-icon-circle svg { width: 28px; height: 28px; fill: none; stroke: #fff; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; animation: checkDraw 0.6s ease forwards; }
@keyframes checkDraw { to { stroke-dashoffset: 0; } }
.success-text { font-size: 16px; font-weight: 600; color: #333; }

/* Confirm & OTP Modal Styles */
.confirm-card { width: 440px; max-width: 96%; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { width: 56px; height: 56px; border-radius: 12px; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; flex-shrink: 0; }
.confirm-icon.danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.confirm-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.confirm-title { font-weight: 800; color: #1a202c; font-size: 20px; }
.confirm-msg { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 20px; text-align: center;}
.confirm-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 10px; border-top: 1px solid #e8ecf0; }

.otp-inputs { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; }
.otp-inputs input { width: 45px; height: 55px; font-size: 24px; font-weight: bold; text-align: center; border: 2px solid #cbd5e1; border-radius: 8px; }
.otp-inputs input:focus { border-color: #991010; outline: none; }

/* Resend Code Styles */
.resend-box { margin-top: 15px; font-size: 13px; color: #64748b;}
.resend-link { color: #1d4ed8; text-decoration: none; font-weight: 700; cursor: pointer; transition: color 0.2s; }
.resend-link:hover { color: #1e3a8a; text-decoration: underline; }
.resend-link.disabled { color: #94a3b8; cursor: not-allowed; text-decoration: none; }

/* Action Loader */
#actionLoader .loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
#actionLoader .loader-spinner { width: 32px; height: 32px; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; flex-shrink: 0; }

/* Toast for Errors */
.toast-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 9998; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
.toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 14px; font-weight: 600; min-width: 300px; max-width: 400px; text-align: left; animation: slideUp .3s ease; border-left: 5px solid #dc2626; }
.toast-icon { font-size: 18px; color: #dc2626; font-weight: 800; }

/* Mobile */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px;}
@media (max-width: 1000px) {
    .vertical-bar { display: none; }
    header { padding: 12px 20px; justify-content: space-between; }
    .container { padding: 20px; }
    #menu-toggle { display: block; }
    nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: 0.3s ease; backdrop-filter: blur(5px); }
    nav#main-nav.show { opacity: 1; visibility: visible; }
    nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); width: 100%; }
    .form-grid { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; text-align: center; padding: 30px 20px; }
    .form-actions { flex-wrap: wrap; }
    .btn { flex: 1; justify-content: center; }
    .btn-logout { margin-left: 0; order: 3; width: 100%; }
}
</style>
</head>
<body>

<div id="pageLoader" class="page-loader">
    <div class="spinner"></div>
    <div style="font-weight: 600; color: #4a5568;">Loading profile...</div>
</div>

<div id="actionLoader" aria-hidden="true">
    <div class="loader-card">
        <div class="loader-spinner"></div>
        <p id="actionLoaderText" style="font-weight: 600; color: #334155; font-size: 15px;">Processing...</p>
    </div>
</div>

<div id="successModal" class="success-modal-overlay">
    <div class="success-modal-card">
        <div class="success-icon-circle">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <div class="success-text" id="successMessageText">Profile updated!</div>
    </div>
</div>

<div id="confirmModal" class="confirm-modal-overlay" aria-hidden="true">
    <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
            <div class="confirm-icon danger" id="confirmIcon">🚪</div>
            <div class="confirm-title" id="confirmTitle">Confirm Logout</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">Are you sure you want to log out of your account?</div>
        <div class="confirm-actions">
            <button id="confirmCancel" class="btn btn-cancel">Cancel</button>
            <button id="confirmOk" class="btn btn-danger">Yes, Logout</button>
        </div>
    </div>
</div>

<div id="otpModalOverlay" class="confirm-modal-overlay" aria-hidden="true">
    <div class="confirm-card" role="dialog" aria-modal="true" style="width: 400px; text-align: center;">
        <div class="confirm-header" style="justify-content: center;">
            <div class="confirm-icon warning">✉️</div>
        </div>
        <div class="confirm-title" style="margin-bottom: 10px; text-align: center; width: 100%;">Verify New Email</div>
        <div class="confirm-msg">We sent a 6-digit code to your new email address. Enter it below to confirm the change.</div>
        
        <div class="otp-inputs" id="otpContainer">
            <input type="text" maxlength="1" oninput="moveToNext(this, 1)" id="otp1">
            <input type="text" maxlength="1" oninput="moveToNext(this, 2)" id="otp2">
            <input type="text" maxlength="1" oninput="moveToNext(this, 3)" id="otp3">
            <input type="text" maxlength="1" oninput="moveToNext(this, 4)" id="otp4">
            <input type="text" maxlength="1" oninput="moveToNext(this, 5)" id="otp5">
            <input type="text" maxlength="1" oninput="moveToNext(this, null)" id="otp6">
        </div>

        <button class="btn btn-save" style="width:100%; margin-bottom: 10px;" onclick="verifyOtpAndSave()">Verify & Save</button>
        <button class="btn btn-cancel" style="width:100%;" onclick="closeOtpModal()">Cancel</button>
        
        <div class="resend-box">
            Didn't receive the code? <br>
            <span id="resendBtn" class="resend-link" onclick="resendOtp()">Resend Code</span>
            <span id="timerText" style="display:none; color:#64748b;">in <b id="timerCount">60</b>s</span>
        </div>
        
    </div>
</div>

<header>
  <div class="logo-section">
    <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
  </div>
  <button id="menu-toggle">☰</button>
  <nav id="main-nav">
    <a href="staff_dashboard.php">🏠 Dashboard</a>
    <a href="appointment.php">📅 Appointments</a>
    <a href="patient_record.php">📘 Patient Record</a>
    <a href="product.php">💊 Product & Services</a>
    <a href="profile.php" class="active">🔍 Profile</a>
  </nav>
</header>

<div class="container">
  <div class="profile-card">
    <div class="profile-header">
      <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="profile-info">
        <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="profile-meta">
          <span class="badge staff-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'staff')) ?></span>
          <span class="badge staff-id">ID: <?= htmlspecialchars($user['staff_id']) ?></span>
        </div>
      </div>
    </div>

    <div class="profile-body">
      <div class="section-title">Account Information</div>

      <div class="security-notice">
        <span>🔒</span>
        <div>
            <strong>Security Notice:</strong><br>
            Changing your password requires your current password. Changing your email requires OTP verification sent to your new email.
        </div>
      </div>

      <form id="profileForm" onsubmit="return false;">
        <div class="form-grid">
          
          <div class="form-group">
            <label for="profileName">Full Name *</label>
            <input type="text" id="profileName" value="<?= htmlspecialchars($user['full_name']) ?>" disabled required>
            <span id="nameMsg" class="val-msg"></span>
          </div>
          
          <div class="form-group">
            <label for="profileEmail">Email Address *</label>
            <input type="email" id="profileEmail" value="<?= htmlspecialchars($user['email']) ?>" disabled required>
            <span id="emailMsg" class="val-msg"></span>
          </div>

          <div class="form-group" id="viewPasswordGroup" style="grid-column: 1 / -1;">
            <label>Password</label>
            <input type="password" value="********" disabled style="background:#f9fafb;">
          </div>

          <div class="password-section-title edit-pass-fields">Change Password (Leave blank to keep current)</div>

          <div class="form-group edit-pass-fields" style="display:none;">
            <label for="currentPassword">Current Password</label>
            <div class="password-wrapper">
              <input type="password" id="currentPassword" placeholder="Required if changing password">
              <button type="button" onclick="togglePasswordVis('currentPassword', this)">👁️</button>
            </div>
            <span id="currPwMsg" class="val-msg"></span>
          </div>

          <div class="form-group edit-pass-fields" style="display:none; grid-column: 1 / -1;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div class="form-group" style="margin-bottom: 0;">
                  <div class="password-wrapper">
                      <label for="newPassword">New Password</label>
                      <input type="password" id="newPassword" placeholder="Min. 8 chars, 1 Upper, 1 Num, 1 Spec Char">
                      <button type="button" onclick="togglePasswordVis('newPassword', this)" style="top:22px;">👁️</button>
                  </div>
                  <span id="newPwMsg" class="val-msg"></span>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                  <div class="password-wrapper">
                      <label for="confirmPassword">Confirm New Password</label>
                      <input type="password" id="confirmPassword" placeholder="Repeat new password">
                      <button type="button" onclick="togglePasswordVis('confirmPassword', this)" style="top:22px;">👁️</button>
                  </div>
                  <span id="confPwMsg" class="val-msg"></span>
                </div>
            </div>
          </div>

        </div>

        <div class="form-actions" id="viewActions">
          <button type="button" class="btn btn-logout" onclick="confirmLogout()">🚪 Logout</button>
          <button type="button" class="btn btn-edit" onclick="enableEdit()">✏️ Edit Details</button>
        </div>

        <div class="form-actions" id="editActions" style="display:none;">
          <button type="button" class="btn btn-cancel" onclick="cancelEdit()">Cancel</button>
          <button type="button" class="btn btn-save" onclick="initiateSave()" id="saveBtn" disabled>💾 Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// --- PAGE LOADER LOGIC ---
// Tinatanggal nito ang loading screen pagkatapos ng 1 segundo para iwas-freeze
setTimeout(function() {
    const pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        pageLoader.style.opacity = '0';
        setTimeout(() => {
            pageLoader.style.display = 'none';
        }, 400);
    }
}, 1000); 

let originalData = {
  name: <?= json_encode($user['full_name']) ?>,
  email: <?= json_encode($user['email']) ?>
};

// ==========================================
// FORM STATE & REAL-TIME VALIDATION LOGIC
// ==========================================
let isNameValid = true;
let isEmailValid = true;
let isCurrPwValid = false;
let isNewPwValid = false;
let isConfPwValid = false;

const nameInput = document.getElementById('profileName');
const emailInput = document.getElementById('profileEmail');
const currPwInput = document.getElementById('currentPassword');
const newPwInput = document.getElementById('newPassword');
const confPwInput = document.getElementById('confirmPassword');
const saveBtn = document.getElementById('saveBtn');

function validateForm() {
    let pwValid = true;
    
    // If user is trying to change password, all 3 fields must be valid
    if (currPwInput.value !== '' || newPwInput.value !== '' || confPwInput.value !== '') {
        pwValid = isCurrPwValid && isNewPwValid && isConfPwValid;
    }

    if (isNameValid && isEmailValid && pwValid) {
        saveBtn.disabled = false;
    } else {
        saveBtn.disabled = true;
    }
}

// 1. NAME VALIDATION
nameInput.addEventListener('input', function() {
    const val = this.value.trim();
    const msg = document.getElementById('nameMsg');
    
    if(val.length < 3) {
        msg.innerHTML = '<span style="color:#dc2626;">❌ Name must be at least 3 characters</span>';
        isNameValid = false;
    } else {
        msg.innerHTML = '<span style="color:#16a34a;">✅ Looks good</span>';
        isNameValid = true;
    }
    validateForm();
});

// 2. EMAIL VALIDATION (With AJAX Check)
let emailTimer;
emailInput.addEventListener('input', function() {
    clearTimeout(emailTimer);
    const val = this.value.trim();
    const msg = document.getElementById('emailMsg');
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if(!regex.test(val)) {
        msg.innerHTML = '<span style="color:#dc2626;">❌ Invalid email format</span>';
        isEmailValid = false;
        validateForm();
        return;
    }

    if (val === originalData.email) {
        msg.innerHTML = '<span style="color:#16a34a;">✅ Current email</span>';
        isEmailValid = true;
        validateForm();
        return;
    }

    msg.innerHTML = '<span style="color:#f59e0b;">Checking availability...</span>';
    isEmailValid = false; // Disable save while checking
    validateForm();

    emailTimer = setTimeout(() => {
        fetch('profile.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'checkEmail', email: val })
        }).then(r=>r.json()).then(res => {
            if(res.exists) {
                msg.innerHTML = '<span style="color:#dc2626;">❌ Email is already in use by another staff</span>';
                isEmailValid = false;
            } else {
                msg.innerHTML = '<span style="color:#16a34a;">✅ Email is available</span>';
                isEmailValid = true;
            }
            validateForm();
        }).catch(e => {
            msg.innerHTML = '<span style="color:#dc2626;">Error checking email</span>';
        });
    }, 600);
});

// 3. CURRENT PASSWORD VALIDATION (AJAX Check)
let pwTimer;
currPwInput.addEventListener('input', function() {
    clearTimeout(pwTimer);
    const val = this.value;
    const msg = document.getElementById('currPwMsg');
    
    if(!val) {
        msg.innerHTML = '';
        isCurrPwValid = false;
        validateForm();
        return;
    }
    
    msg.innerHTML = '<span style="color:#f59e0b;">Checking password...</span>';
    
    pwTimer = setTimeout(() => {
        fetch('profile.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'verifyCurrentPassword', current_password: val })
        }).then(r=>r.json()).then(res => {
            if(res.success) {
                msg.innerHTML = '<span style="color:#16a34a;">✅ Correct current password</span>';
                isCurrPwValid = true;
            } else {
                msg.innerHTML = '<span style="color:#dc2626;">❌ Incorrect current password</span>';
                isCurrPwValid = false;
            }
            validateForm();
        }).catch(e => {
            msg.innerHTML = '<span style="color:#dc2626;">Error checking password</span>';
        });
    }, 500);
});

// 4. NEW PASSWORD STRONG VALIDATION
newPwInput.addEventListener('input', function() {
    const val = this.value;
    const msg = document.getElementById('newPwMsg');
    
    if(!val) { 
        msg.innerHTML = ''; 
        isNewPwValid = false;
    } else {
        // Must contain at least 1 lowercase, 1 uppercase, 1 numeric, 1 special char, 8 chars minimum
        const strongRegex = new RegExp("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\\$%\\^&\\*\\_\\-])(?=.{8,})");
        
        if(!strongRegex.test(val)) {
            msg.innerHTML = '<span style="color:#dc2626;">❌ Weak password. Must have min 8 chars, 1 Uppercase, 1 Number, 1 Special Char</span>';
            isNewPwValid = false;
        } else {
            msg.innerHTML = '<span style="color:#16a34a;">✅ Strong password</span>';
            isNewPwValid = true;
        }
    }
    checkConfirmMatch();
    validateForm();
});

// 5. CONFIRM PASSWORD VALIDATION
confPwInput.addEventListener('input', checkConfirmMatch);

function checkConfirmMatch() {
    const new_val = newPwInput.value;
    const conf_val = confPwInput.value;
    const msg = document.getElementById('confPwMsg');
    
    if(!conf_val) { 
        msg.innerHTML = ''; 
        isConfPwValid = false;
    } else if(new_val === conf_val && isNewPwValid) {
        msg.innerHTML = '<span style="color:#16a34a;">✅ Passwords match</span>';
        isConfPwValid = true;
    } else {
        msg.innerHTML = '<span style="color:#dc2626;">❌ Passwords do not match</span>';
        isConfPwValid = false;
    }
    validateForm();
}

function clearValidations() {
    document.getElementById('nameMsg').innerHTML = '';
    document.getElementById('emailMsg').innerHTML = '';
    document.getElementById('currPwMsg').innerHTML = '';
    document.getElementById('newPwMsg').innerHTML = '';
    document.getElementById('confPwMsg').innerHTML = '';
    isNameValid = true;
    isEmailValid = true;
    isCurrPwValid = false;
    isNewPwValid = false;
    isConfPwValid = false;
    validateForm();
}
// ==========================================


function showActionLoader(message = 'Processing...') {
    const loaderText = document.getElementById('actionLoaderText');
    if (loaderText) loaderText.textContent = message;
    document.getElementById('actionLoader').classList.add('show');
}

function hideActionLoader() { document.getElementById('actionLoader').classList.remove('show'); }

function showSuccessModal(msg) {
    const modal = document.getElementById('successModal');
    const text = document.getElementById('successMessageText');
    if(modal && text) {
        text.textContent = msg;
        modal.classList.add('show');
        setTimeout(() => { modal.classList.remove('show'); }, 2000);
    }
}

function showToast(msg) {
    const overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    overlay.innerHTML = `<div class="toast"><span class="toast-icon">✕</span><div class="toast-message">${msg}</div></div>`;
    document.body.appendChild(overlay);
    setTimeout(() => {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }, 4000);
}

// Custom Confirm Logout
function showConfirm(message, okText = 'Confirm') {
    return new Promise(resolve => {
        const modal = document.getElementById('confirmModal');
        const msg = document.getElementById('confirmMsg');
        const btnOk = document.getElementById('confirmOk');
        const btnCancel = document.getElementById('confirmCancel');
        
        msg.innerHTML = message;
        btnOk.textContent = okText;
        modal.classList.add('show');
        
        let onOk, onCancel;
        function cleanUp(result) {
            modal.classList.remove('show');
            btnOk.removeEventListener('click', onOk);
            btnCancel.removeEventListener('click', onCancel);
            resolve(result);
        }
        onOk = () => cleanUp(true);
        onCancel = () => cleanUp(false);

        btnOk.addEventListener('click', onOk, { once: true });
        btnCancel.addEventListener('click', onCancel, { once: true });
    });
}

// Password Visiblity Toggles
function togglePasswordVis(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "🙈";
    } else {
        input.type = "password";
        btn.textContent = "👁️";
    }
}

function enableEdit() {
  nameInput.disabled = false;
  emailInput.disabled = false;
  
  // Show new password fields, hide dummy field
  document.getElementById('viewPasswordGroup').style.display = 'none';
  document.querySelectorAll('.edit-pass-fields').forEach(el => el.style.display = 'block');

  document.getElementById('viewActions').style.display = 'none';
  document.getElementById('editActions').style.display = 'flex';
  nameInput.focus();
  
  clearValidations();
}

function cancelEdit() {
  nameInput.value = originalData.name;
  emailInput.value = originalData.email;
  
  currPwInput.value = '';
  newPwInput.value = '';
  confPwInput.value = '';
  clearValidations();

  nameInput.disabled = true;
  emailInput.disabled = true;
  
  // Hide new password fields, show dummy field
  document.getElementById('viewPasswordGroup').style.display = 'block';
  document.querySelectorAll('.edit-pass-fields').forEach(el => el.style.display = 'none');

  document.getElementById('viewActions').style.display = 'flex';
  document.getElementById('editActions').style.display = 'none';
}

// ---------------------------------------------------------
// STEP 1: INITIAL SAVE CHECK (Checks if email changed)
// ---------------------------------------------------------
function initiateSave() {
    if(saveBtn.disabled) return; // double check

    const email = emailInput.value.trim();

    // IF EMAIL CHANGED -> Send OTP
    if (email !== originalData.email) {
        sendOtpRequest(email, true);
    } else {
        // IF EMAIL DID NOT CHANGE -> Update Directly
        submitFinalProfile('');
    }
}

// ---------------------------------------------------------
// STEP 2: OTP LOGIC & TIMER
// ---------------------------------------------------------
let resendInterval = null;

function sendOtpRequest(email, isInitial = false) {
    showActionLoader(isInitial ? 'Sending verification code...' : 'Resending code...');
    
    fetch('profile.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action: 'sendOtp', email: email })
    })
    .then(r => r.json())
    .then(res => {
        hideActionLoader();
        if (res.success) {
            document.getElementById('otpModalOverlay').classList.add('show');
            document.getElementById('otp1').focus();
            
            // Start the 60-second cooldown timer
            startResendTimer();
            
            if(!isInitial) {
                showToast("A new code has been sent to your email.");
            }
        } else {
            showToast(res.message);
        }
    }).catch(e => { hideActionLoader(); showToast('Network Error.'); });
}

function resendOtp() {
    // Check if button is disabled (timer active)
    const btn = document.getElementById('resendBtn');
    if (btn.classList.contains('disabled')) return;
    
    const email = emailInput.value.trim();
    
    // Clear previous inputs
    for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
    
    sendOtpRequest(email, false);
}

function startResendTimer() {
    const btn = document.getElementById('resendBtn');
    const timerText = document.getElementById('timerText');
    const count = document.getElementById('timerCount');
    let timeLeft = 60;

    // Disable link and show timer
    btn.classList.add('disabled');
    timerText.style.display = 'inline';
    count.innerText = timeLeft;
    
    // Clear existing timer if any
    if (resendInterval) clearInterval(resendInterval);
    
    resendInterval = setInterval(() => {
        timeLeft--;
        count.innerText = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(resendInterval);
            btn.classList.remove('disabled');
            timerText.style.display = 'none';
        }
    }, 1000);
}

function moveToNext(current, nextId) {
    if (current.value.length === 1 && nextId) {
        document.getElementById('otp' + nextId).focus();
    }
}

// Add backspace logic
document.getElementById('otpContainer').addEventListener('keydown', function(e) {
    if (e.key === 'Backspace' && e.target.value === '') {
        let prevId = parseInt(e.target.id.replace('otp', '')) - 1;
        if (prevId >= 1) {
            let prevInput = document.getElementById('otp' + prevId);
            prevInput.focus();
            prevInput.value = '';
        }
    }
});

function closeOtpModal() {
    document.getElementById('otpModalOverlay').classList.remove('show');
    // Clear inputs
    for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
}

function verifyOtpAndSave() {
    let otp = '';
    for(let i=1; i<=6; i++) otp += document.getElementById('otp'+i).value;
    
    if (otp.length !== 6) {
        showToast('Please enter the complete 6-digit code.'); return;
    }
    
    closeOtpModal();
    submitFinalProfile(otp);
}

// ---------------------------------------------------------
// STEP 3: FINAL DATABASE UPDATE
// ---------------------------------------------------------
function submitFinalProfile(otpCode) {
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const currentPass = currPwInput.value;
    const newPass = newPwInput.value;
    const confPass = confPwInput.value;

    showActionLoader('Verifying and saving profile...');

    const formData = new URLSearchParams({
        action: 'updateProfile',
        name: name, email: email,
        current_password: currentPass, new_password: newPass, confirm_password: confPass,
        otp: otpCode
    });

    fetch('profile.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(res => res.json())
    .then(payload => {
        hideActionLoader();
        if (payload.success) {
            showSuccessModal(payload.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(payload.message);
            if (payload.message.includes('Verification Code')) {
                // If OTP was wrong, re-open modal so they can try again
                document.getElementById('otpModalOverlay').classList.add('show');
                for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
                document.getElementById('otp1').focus();
            }
        }
    })
    .catch(err => {
        hideActionLoader();
        showToast('Network error. Please try again.');
    });
}

// ---------------------------------------------------------
// LOGOUT
// ---------------------------------------------------------
function confirmLogout() {
  showConfirm("Are you sure you want to log out of your staff account?", "Yes, Logout")
    .then(confirmed => {
      if (confirmed) {
        showActionLoader('Logging out...');
        fetch('profile.php', { 
          method: 'POST', 
          headers: {'Content-Type':'application/x-www-form-urlencoded'}, 
          body: new URLSearchParams({action: 'logout'}) 
        }).then(() => location.href = '../../public/login.php');
      }
    });
}

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.getElementById('main-nav');
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('show');
            if (mainNav.classList.contains('show')) { this.innerHTML = '✕'; } 
            else { this.innerHTML = '☰'; }
        });
    }
});
</script>
</body>
</html>