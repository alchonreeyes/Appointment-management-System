<?php
session_start();

// --- FIX: Initialize OTP attempts tracking (mirroring login.php) ---
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}
if (!isset($_SESSION['otp_last_attempt_time'])) {
    $_SESSION['otp_last_attempt_time'] = 0;
}
// --- End of FIX ---

// Load PHPMailer
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
include __DIR__ . '/database.php';

// --- FIX: Check if the user is temporarily locked out (mirroring login.php) ---
$is_locked_out = false;
$remaining_lockout = 0;
if ($_SESSION['otp_attempts'] >= 3) { 
    $lockout_time = 30; // 30 seconds lockout
    $remaining_lockout = $lockout_time - (time() - $_SESSION['otp_last_attempt_time']);
    
    if ($remaining_lockout > 0) {
        $is_locked_out = true;
    } else {
        // Reset attempts after lockout period
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_last_attempt_time'] = 0;
    }
}
// --- End of FIX ---


// Helper: send OTP via SMTP
function sendOTP($toEmail, $otp, $userRole) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // ==========================================================
        // !! IMPORTANT !!
        // ==========================================================
        $mail->Username   = 'rogerjuancito0621@gmail.com';     // ← Your full Gmail address
        $mail->Password   = 'r h t s t r o p g t n f g i p b';       // ← Your 16-character App Password
        
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Care Clinic');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your Password Reset Code - Eye Care Clinic';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #DC143C;'>Eye Care Clinic</h2>
            </div>
            <p style='font-size: 16px; line-height: 1.5;'>Hello,</p>
            <p style='font-size: 16px; line-height: 1.5;'>You've requested to reset your password for your Eye Care Clinic account.</p>
            <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0;'>
                <p style='font-size: 14px; margin: 0;'>Your password reset code is:</p>
                <h2 style='margin: 10px 0; color: #DC143C;'>$otp</h2>
                <p style='font-size: 14px; margin: 0;'>This code will expire in 2 minutes.</p>
            </div>
            <p style='font-size: 16px; line-height: 1.5;'>If you didn't request this password reset, please ignore this email.</p>
            <p style='font-size: 16px; line-height: 1.5;'>Thank you,<br>The Eye Care Clinic Team</p>
        </div>";
        $mail->AltBody = "Your password reset code for Eye Care Clinic: $otp\nIt expires in 2 minutes.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('OTP mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

// Control flow
$message = '';
$step = 'email';  // email → otp → reset → done
// --- FIX: Default message type to 'info' (or any neutral)
$message_type = 'info'; // 'error' or 'success'

// Handle email submission
// --- FIX: Added !$is_locked_out check ---
if (isset($_POST['submit_email']) && !$is_locked_out) {
    $email = trim($_POST['email']);
    
    $query = "(SELECT 
                id as original_id, 
                'admin' as original_table, 
                role 
              FROM admin WHERE email = ?)
             UNION
             (SELECT 
                staff_id as original_id, 
                'staff' as original_table, 
                role 
              FROM staff WHERE email = ?)";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        $otp = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_otp'] = $otp;
        
        // --- FIX: Use 2-min expiry and reset attempt counters ---
        $_SESSION['reset_otp_expires'] = time() + 120; // 2 minutes
        $_SESSION['otp_attempts'] = 0; // Reset attempt counter
        $_SESSION['otp_last_attempt_time'] = 0;  // Reset lockout time
        // --- End of FIX ---
        
        $_SESSION['reset_user_role'] = $user['role'];
        $_SESSION['reset_original_id'] = $user['original_id'];
        $_SESSION['reset_original_table'] = $user['original_table'];
        
        if (sendOTP($email, $otp, $user['role'])) {
            $step = 'otp';
            // --- FIX: Message changed for toast ---
            $message = "Success! OTP sent to " . htmlspecialchars($email) . ".";
            $message_type = 'success';
        } else {
            $message = "Error! Failed to send OTP. Please try again.";
            $message_type = 'error';
        }
    } else {
        // --- FIX: Increment attempts on "email not found" (mirroring login.php) ---
        $_SESSION['otp_attempts']++;
        $_SESSION['otp_last_attempt_time'] = time();
        // --- End of FIX ---
        $message = "Error! Email not found.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle Resend OTP Request
// --- FIX: Added !$is_locked_out check ---
elseif (isset($_POST['resend_otp']) && !$is_locked_out) {
    if (empty($_SESSION['reset_email'])) {
        $step = 'email';
        $message = "Error! Session expired. Please start over.";
        $message_type = 'error';
    } else {
        $email = $_SESSION['reset_email'];
        $userRole = $_SESSION['reset_user_role'];
        
        $otp = str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT);
        $_SESSION['reset_otp'] = $otp;

        // --- FIX: Reset expiry and attempt counters ---
        $_SESSION['reset_otp_expires'] = time() + 120; // 2 minutes
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_last_attempt_time'] = 0;
        // --- End of FIX ---

        if (sendOTP($email, $otp, $userRole)) {
            $step = 'otp';
            $message = "Success! A new OTP has been sent.";
            $message_type = 'success';
        } else {
            $step = 'otp'; 
            $message = "Error! Failed to resend OTP. Please try again.";
            $message_type = 'error';
        }
    }
}

// Handle OTP verification
// --- FIX: Added !$is_locked_out check ---
elseif (isset($_POST['verify_otp']) && !$is_locked_out) {
    $input = trim($_POST['otp'] ?? '');

    if (empty($_SESSION['reset_otp']) || time() > $_SESSION['reset_otp_expires']) {
        $message = "Error! OTP expired. Please request a new one.";
        $message_type = 'error';
        session_unset(); 
        $step = 'email';
    } 
    
    elseif ($input !== $_SESSION['reset_otp']) {
        
        // --- FIX: Updated attempt logic to trigger lockout immediately ---
        $_SESSION['otp_attempts']++;
        $_SESSION['otp_last_attempt_time'] = time();
        
        if ($_SESSION['otp_attempts'] >= 3) {
            // Ito 'yung 3rd attempt. Trigger na 'yung lockout AGAD.
            $message = "Error! Invalid OTP. Too many attempts. Please wait 30 seconds.";
            $is_locked_out = true;     // Manually set para makita ng JS
            $remaining_lockout = 30; // Manually set para makita ng JS
        } else {
            // Ito 'yung 1st or 2nd attempt.
            $remaining = 3 - $_SESSION['otp_attempts'];
            $message = "Error! Invalid OTP. $remaining attempts remaining.";
        }
        // --- End of FIX ---

        $message_type = 'error';
        $step = 'otp';
    } else {
        // Success!
        // --- FIX: Reset attempts on success (mirroring login.php) ---
        $_SESSION['otp_attempts'] = 0;
        $_SESSION['otp_last_attempt_time'] = 0;
        // --- End of FIX ---

        $step = 'reset';
        $message = "Success! OTP verified. Enter your new password.";
        $message_type = 'success';
    }
}

// Handle password reset
elseif (isset($_POST['reset_password'])) {
    // This step is only reachable after success, so no lockout check needed
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($pass) < 4) { 
        $message = "Error! Password must be at least 4 characters long.";
        $message_type = 'error';
        $step = 'reset';
    } elseif ($pass !== $confirm) {
        $message = "Error! Passwords do not match.";
        $message_type = 'error';
        $step = 'reset';
    } else {
        $email = $_SESSION['reset_email'];
        $originalId = $_SESSION['reset_original_id'];
        $originalTable = $_SESSION['reset_original_table'];
        
        $stmt_original = null;
        
        switch ($originalTable) {
            case 'admin':
                $stmt_original = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt_original->bind_param("si", $pass, $originalId);
                break;
            case 'staff':
                $stmt_original = $conn->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
                $stmt_original->bind_param("si", $pass, $originalId);
                break;
        }
        
        if ($stmt_original) {
            $conn->begin_transaction();
            try {
                $stmt_original->execute();
                $conn->commit();
                
                $message = "Password updated successfully."; // This won't be seen by toast, but it's ok
                session_unset();
                $step = 'done';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error! Failed to update password: " . $e->getMessage();
                $message_type = 'error';
                $step = 'reset';
            }
        } else {
            $message = "Error! No matching user table found to update.";
            $message_type = 'error';
            $step = 'reset';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Eye Care Clinic System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
/* Root Variables for Eye Clinic Theme */
:root {
    --primary-red: #DC143C;
    --dark-red: #B31A1A;
    --light-red: #FF6B6B;
    --accent-red: #E63946;
    --medical-blue: #2C5F8D;
    --text-dark: #1a1a1a;
    --text-light: #666;
    --white: #ffffff;
    --light-bg: #f8f9fa;
    --border-color: #e0e0e0;
    --success-color: #10b981;
    --error-color: #ef4444;
    --shadow-light: 0 2px 8px rgba(220, 20, 60, 0.08);
    --shadow-medium: 0 4px 20px rgba(220, 20, 60, 0.12);
    --shadow-heavy: 0 8px 32px rgba(220, 20, 60, 0.15);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    
    /* background-image: url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wzNzEyMywxMjA3fDB8MXxzZWFyY2h8MTB8fG1lZGljYWwlMjBiYWNrZ3JvdW5kfGVufDB8fHx8MTcyMDY1NDU3NXww&ixlib=rb-4.0.3&q=80&w=1080');
    background-size: cover; */
    background-position: center center;
    background-attachment: fixed;
    
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    
    background-color: rgba(255, 255, 255, 0.5); /* White overlay */
    
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(220, 20, 60, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(220, 20, 60, 0.03) 0%, transparent 50%);
    z-index: 0;
    pointer-events: none;
}

body::after {
    content: '';
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 800px;
    height: 800px;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="30" fill="none" stroke="%23DC143C" stroke-width="0.5" opacity="0.03"/><circle cx="50" cy="50" r="20" fill="none" stroke="%23DC143C" stroke-width="0.5" opacity="0.03"/></svg>');
    background-size: 200px 200px;
    opacity: 0.3;
    z-index: 0;
    pointer-events: none;
}

.container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 480px;
    /* animation: slideUp 0.6s ease-out; */ /* Removed to let JS control it */
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.forgot-card {
    background: var(--white);
    border-radius: 24px;
    box-shadow: var(--shadow-heavy);
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.forgot-header {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    padding: 40px 30px 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.forgot-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.icon-container {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    position: relative;
    z-index: 1;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
    }
}

.icon-container i {
    font-size: 36px;
    color: var(--white);
}

.forgot-header h1 {
    color: var(--white);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.forgot-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    font-weight: 400;
}

.logo-section {
    padding: 30px 30px 20px;
    text-align: center;
    background: var(--white);
}

.logo-section img {
    max-height: 70px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    padding: 30px 40px 20px;
    position: relative;
}

.progress-steps::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 40px;
    right: 40px;
    height: 2px;
    background: var(--border-color);
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--white);
    border: 2px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    color: var(--text-light);
    transition: all 0.3s ease;
}

.step.active .step-circle {
    background: var(--success-color);
    border-color: var(--success-color);
    color: var(--white);
}

.step.completed .step-circle {
    background: var(--success-color);
    border-color: var(--success-color);
    color: var(--white);
}

.step-label {
    font-size: 12px;
    color: var(--text-light);
    font-weight: 500;
}

.step.active .step-label {
    color: var(--success-color);
    font-weight: 600;
}

.step.completed .step-label {
    color: var(--success-color);
    font-weight: 600;
}

.forgot-form {
    padding: 30px 40px 40px;
}

.form-group {
    margin-bottom: 24px;
    position: relative;
}

.form-label {
    display: block;
    color: var(--text-dark);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 0.3px;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 16px;
    color: var(--text-light);
    font-size: 16px;
    transition: color 0.3s ease;
    z-index: 1;
}

.form-input {
    width: 100%;
    padding: 14px 16px 14px 48px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    font-size: 15px;
    color: var(--text-dark);
    background: var(--light-bg);
    transition: all 0.3s ease;
    box-sizing: border-box; 
}

/* --- NEW: Adjust padding for password fields --- */
.input-wrapper input[type="password"] {
    padding-right: 48px;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-red);
    background: var(--white);
    box-shadow: 0 0 0 4px rgba(220, 20, 60, 0.08);
}

.form-input:focus + .input-icon {
    color: var(--primary-red);
}

.otp-input {
    text-align: center;
    letter-spacing: 8px;
    font-size: 24px;
    font-weight: 700;
    padding-left: 16px !important; 
    padding-right: 16px !important;
}

.btn-submit {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: var(--shadow-medium);
    position: relative;
    overflow: hidden;
}

.btn-submit::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-submit:hover::before {
    left: 100%;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.3);
}

.btn-submit:active {
    transform: translateY(0);
}

.btn-resend {
    background: none;
    border: none;
    color: var(--primary-red);
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: color 0.3s ease;
    padding: 10px;
    text-align: center;
    display: inline-block; 
}

.btn-resend:hover {
    color: var(--dark-red);
    text-decoration: underline;
}

.btn-submit:disabled {
    background: #ccc;
    cursor: not-allowed;
    box-shadow: none;
    transform: none;
}
.btn-submit:disabled::before {
    display: none; 
}

.btn-resend:disabled {
    background: none;
    color: #999;
    text-decoration: none;
    cursor: not-allowed;
}

.success-section {
    padding: 40px;
    text-align: center;
}

.success-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
        opacity: 0;
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.success-icon i {
    font-size: 48px;
    color: var(--white);
}

.success-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 12px;
}

.success-message {
    font-size: 15px;
    color: var(--text-light);
    margin-bottom: 32px;
    line-height: 1.6;
}

.btn-login {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    color: var(--white);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: var(--shadow-medium);
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.3);
}

.forgot-footer {
    text-align: center;
    padding: 20px 40px 30px;
    border-top: 1px solid var(--border-color);
}

.footer-link {
    color: var(--primary-red);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: color 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.footer-link:hover {
    color: var(--dark-red);
}

.help-text {
    text-align: center;
    color: var(--text-light);
    font-size: 13px;
    margin-top: 16px;
    line-height: 1.5;
}

/* --- START: Copied from login.php for Lockout --- */
.lockout-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
    z-index: 3000;
    justify-content: center;
    align-items: center;
}
.lockout-overlay.show { /* --- NEW: Added .show class --- */
    display: flex;
    animation: fadeIn .2s ease;
}

.lockout-box {
    background: var(--white);
    padding: 50px;
    border-radius: 24px;
    text-align: center;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    animation: popIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

@keyframes popIn {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } } /* --- NEW: Added fadeIn --- */


.auth-icon { /* Re-used by lockout-box from login.php */
    font-size: 80px;
    margin-bottom: 20px;
}

.lockout-timer {
    font-size: 64px;
    color: var(--error-color);
    font-weight: 700;
    margin: 20px 0;
}

.input-disabled {
    pointer-events: none;
    opacity: 0.5;
}
/* --- END: Copied from login.php for Lockout --- */


/* --- START: NEW CSS from appointment.php --- */
#loader-overlay {
    position: fixed; inset: 0; background: #ffffff; z-index: 99999;
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; transition: opacity 0.5s ease;
}
.loader-spinner {
    width: 50px; height: 50px; border-radius: 50%;
    border: 5px solid #f3f3f3; border-top: 5px solid var(--primary-red);
    animation: spin 1s linear infinite;
}
.loader-text {
    margin-top: 15px; font-size: 16px;
    font-weight: 600; color: var(--text-light);
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#actionLoader {
    display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); 
    z-index: 9990; align-items: center; justify-content: center; padding: 20px; 
    backdrop-filter: blur(4px);
}
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
#actionLoader .loader-card {
    background: #fff; border-radius: 12px; padding: 24px; 
    display: flex; align-items: center; gap: 16px; 
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
#actionLoader .loader-spinner {
    border-top-color: var(--primary-red); width: 32px; height: 32px; 
    border-width: 4px; flex-shrink: 0;
}
#actionLoaderText {
    font-weight: 600; color: #334155; font-size: 15px;
}

.toast-overlay {
    position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6);
    z-index: 9998; display: flex; align-items: center; justify-content: center;
    opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px);
}
.toast {
    background: #fff; color: #1a202c; padding: 24px; border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999;
    display: flex; align-items: center; gap: 16px;
    font-weight: 600; min-width: 300px; max-width: 450px;
    text-align: left; animation: slideUp .3s ease;
}
.toast-icon {
    font-size: 24px; font-weight: 800; width: 44px; height: 44px;
    border-radius: 50%; display: flex; align-items: center;
    justify-content: center; flex-shrink: 0; color: #fff;
}
.toast-message { font-size: 15px; line-height: 1.5; }
.toast.success { border-top: 4px solid var(--success-color); }
.toast.success .toast-icon { background: var(--success-color); }
.toast.error { border-top: 4px solid var(--error-color); }
.toast.error .toast-icon { background: var(--error-color); }
/* --- END: NEW CSS from appointment.php --- */

/* --- START: NEW CSS for Password Toggle --- */
.password-toggle {
    position: absolute;
    right: 16px;
    color: var(--text-light);
    cursor: pointer;
    font-size: 16px;
    transition: color 0.3s ease;
    z-index: 2;
    padding: 8px;
}
.password-toggle:hover {
    color: var(--primary-red);
}
/* --- END: NEW CSS for Password Toggle --- */


@media (max-width: 480px) {
    .forgot-form {
        padding: 25px 25px 35px;
    }
    
    .forgot-header {
        padding: 30px 20px 25px;
    }
    
    .forgot-header h1 {
        font-size: 24px;
    }
    
    .icon-container {
        width: 70px;
        height: 70px;
    }
    
    .icon-container i {
        font-size: 32px;
    }

    .progress-steps {
        padding: 20px 25px 15px;
    }

    .step-circle {
        width: 36px;
        height: 36px;
        font-size: 12px;
    }

    .step-label {
        font-size: 10px;
    }
}
    </style>
</head>
<body>

    <div id="loader-overlay">
        <div class="loader-spinner"></div>
        <p class="loader-text">Loading...</p>
    </div>

    <div id="actionLoader" class="detail-overlay" style="z-index: 9990;" aria-hidden="true">
        <div class="loader-card">
            <div class="loader-spinner"></div>
            <p id="actionLoaderText">Processing...</p>
        </div>
    </div>
    <div id="lockoutOverlay" class="lockout-overlay">
        <div class="lockout-box">
            <div class="auth-icon">
                <i class="fas fa-lock" style="color: var(--error-color);"></i>
            </div>
            <h2 style="color: var(--text-dark); margin-bottom: 10px;">Too Many Attempts</h2>
            <div class="lockout-timer" id="lockoutTimer">30</div>
            <p style="color: var(--text-light);">Please wait before trying again</p>
        </div>
    </div>
    <div class="container" id="main-content" style="display: none;">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="icon-container">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Reset Password</h1>
                <p>Secure Password Recovery</p>
            </div>

            <div class="logo-section">
                <img src="asset/logo.jpg" alt="Eye Clinic Logo">
            </div>

            <div class="progress-steps">
                <div class="step <?php if($step === 'email') echo 'active'; elseif($step !== 'email') echo 'completed'; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Email</div>
                </div>
                <div class="step <?php if($step === 'otp') echo 'active'; elseif($step === 'reset' || $step === 'done') echo 'completed'; ?>">
                    <div class="step-circle"><?php echo ($step === 'reset' || $step === 'done') ? '<i class="fas fa-check"></i>' : '2'; ?></div>
                    <div class="step-label">Verify</div>
                </div>
                <div class="step <?php if($step === 'reset') echo 'active'; elseif($step === 'done') echo 'completed'; ?>">
                    <div class="step-circle"><?php echo ($step === 'done') ? '<i class="fas fa-check"></i>' : '3'; ?></div>
                    <div class="step-label">Reset</div>
                </div>
            </div>

            <?php if($step === 'email'): ?>
            <form class="forgot-form" method="POST">
                <div class="form-group">
                    <label class="form-label" for="emailInput">Email Address</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-envelope"></i>
                        <input type="email" class="form-input" id="emailInput" name="email" placeholder="your.email@clinic.com" required>
                    </div>
                    <div class="help-text">
                        Enter the email address associated with your account
                    </div>
                </div>

                <button type="submit" class="btn-submit" name="submit_email">
                    Continue
                </button>
            </form>
            
            <?php elseif($step === 'otp'): ?>
            <form class="forgot-form" method="POST">
                <div class="form-group">
                    <label class="form-label" for="otpInput">Enter Verification Code</label>
                    <div class="input-wrapper">
   <input type="text" inputmode="numeric" pattern="[0-9]*" oninput="this.value = this.value.replace(/[^0-9]/g, '');" class="form-input otp-input" id="otpInput" name="otp" placeholder="000000" maxlength="6" required>
                    </div>
                    <div class="help-text">
                        We've sent a 6-digit code to your email. Code expires in 2 minutes.
                    </div>
                </div>

                <button type="submit" class="btn-submit" name="verify_otp">
                    Verify Code
                </button>
            </form>
            
            <form class="forgot-form" method="POST" style="padding-top: 0; padding-bottom: 20px; text-align: center; margin-top: -30px;">
                <button type="submit" name="resend_otp" class="btn-resend">
                    Didn't receive the code? Resend
                </button>
            </form>
            
            <?php elseif($step === 'reset'): ?>
            <form class="forgot-form" method="POST">
                <div class="form-group">
                    <label class="form-label" for="passwordInput">New Password</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" class="form-input" id="passwordInput" name="password" placeholder="Enter new password" required>
                        <span class="password-toggle" id="passwordToggle1">
                            <i class="fas fa-eye-slash" id="toggleIcon1"></i>
                        </span>
                        </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPasswordInput">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" class="form-input" id="confirmPasswordInput" name="confirm_password" placeholder="Confirm new password" required>
                        <span class="password-toggle" id="passwordToggle2">
                            <i class="fas fa-eye-slash" id="toggleIcon2"></i>
                        </span>
                        </div>
                    <div class="help-text">
                        Password must be at least 4 characters long
                    </div>
                </div>

                <button type="submit" class="btn-submit" name="reset_password">
                    Reset Password
                </button>
            </form>
            <?php else: // ($step === 'done') ?>
            <div class="success-section">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h2 class="success-title">Password Reset Complete!</h2>
                <p class="success-message">
                    Your password has been successfully updated. You can now log in with your new password.
                </p>
                <a href="login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Back to Login
                </a>
            </div>
            <?php endif; ?>
            <?php if($step !== 'done'): // Hide footer on success page ?>
            <div class="forgot-footer">
                <a href="login.php" class="footer-link">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Configuration (from PHP)
        const isLockedOut = <?php echo $is_locked_out ? 'true' : 'false'; ?>;
        const remainingLockout = <?php echo $remaining_lockout; ?>;
        const phpMessage = <?php echo json_encode($message); ?>;
        const phpMessageType = <?php echo json_encode($message_type); ?>;

        // DOM Elements
        const lockoutOverlay = document.getElementById('lockoutOverlay');
        const lockoutTimer = document.getElementById('lockoutTimer');
        const actionLoader = document.getElementById('actionLoader');
        const actionLoaderText = document.getElementById('actionLoaderText');
        
        // --- START: NEW Password Toggle Logic ---
        const passwordInput = document.getElementById('passwordInput');
        const passwordToggle1 = document.getElementById('passwordToggle1');
        const toggleIcon1 = document.getElementById('toggleIcon1');

        const confirmInput = document.getElementById('confirmPasswordInput');
        const passwordToggle2 = document.getElementById('passwordToggle2');
        const toggleIcon2 = document.getElementById('toggleIcon2');

        if (passwordToggle1) {
            passwordToggle1.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleIcon1.classList.toggle('fa-eye');
                toggleIcon1.classList.toggle('fa-eye-slash');
            });
        }
        
        if (passwordToggle2) {
            passwordToggle2.addEventListener('click', function() {
                const isPassword = confirmInput.type === 'password';
                confirmInput.type = isPassword ? 'text' : 'password';
                toggleIcon2.classList.toggle('fa-eye');
                toggleIcon2.classList.toggle('fa-eye-slash');
            });
        }
        // --- END: NEW Password Toggle Logic ---

        // --- Action Loader Functions (from appointment.php) ---
        function showActionLoader(message = 'Processing...') {
            if (actionLoaderText) actionLoaderText.textContent = message;
            if (actionLoader) {
                actionLoader.classList.add('show');
                actionLoader.setAttribute('aria-hidden', 'false');
            }
        }

        function hideActionLoader() {
            if (actionLoader) {
                actionLoader.classList.remove('show');
                actionLoader.setAttribute('aria-hidden', 'true');
            }
        }

        // --- Toast Notification Function (from appointment.php) ---
        function showToast(msg, type = 'success') {
            const toastType = (type === 'info' || type === 'success') ? 'success' : 'error';
            const icon = toastType === 'success' ? '✓' : '✕';

            const overlay = document.createElement('div');
            overlay.className = 'toast-overlay';
            const toast = document.createElement('div');
            toast.className = `toast ${toastType}`;
            toast.innerHTML = `
                <div class="toast-icon">${icon}</div>
                <div class="toast-message">${msg}</div>
            `;
            overlay.appendChild(toast);
            document.body.appendChild(overlay);
            const timer = setTimeout(() => {
                overlay.style.opacity = '0';
                overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
            }, 2500); 
            overlay.addEventListener('click', () => {
                clearTimeout(timer);
                overlay.style.opacity = '0';
                overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
            }, { once: true });
        }
        
        // --- Lockout Function (from login.php) ---
        function initializeLockout() {
            if (isLockedOut) {
                document.querySelectorAll('.form-input').forEach(el => el.disabled = true);
                document.querySelectorAll('.btn-submit, .btn-resend').forEach(el => el.disabled = true);
                document.querySelectorAll('.forgot-form').forEach(el => el.classList.add('input-disabled'));
                
                lockoutOverlay.classList.add('show'); // Use .show to trigger animation

                let timeLeft = remainingLockout;
                lockoutTimer.textContent = timeLeft; 
                
                const lockoutInterval = setInterval(() => {
                    timeLeft--;
                    lockoutTimer.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(lockoutInterval);
                        window.location.reload(); 
                    }
                }, 1000);
            }
        }

        // --- Initialize secondary tasks (lockout & toast) ---
        document.addEventListener('DOMContentLoaded', () => {
            // This block ONLY handles non-critical tasks
            try {
                // 1. Check for lockout
                initializeLockout();

                // 2. Check for PHP messages and show toast
                if (phpMessage && phpMessageType !== 'info') { 
                    const cleanMessage = phpMessage.replace(/<[^>]+>/g, '');
                    showToast(cleanMessage, phpMessageType);
                }
            } catch (e) {
                console.error("Error during page initialization (lockout/toast):", e);
                // Log the error but don't stop the page
            }
        });

        // --- Action Loader Triggers ---
        document.querySelector('button[name="submit_email"]')?.form.addEventListener('submit', () => {
            if (!isLockedOut) showActionLoader('Checking email...');
        });
        document.querySelector('button[name="verify_otp"]')?.form.addEventListener('submit', () => {
            if (!isLockedOut) showActionLoader('Verifying code...');
        });
         document.querySelector('button[name="resend_otp"]')?.form.addEventListener('submit', () => {
            if (!isLockedOut) showActionLoader('Resending new code...');
        });
        document.querySelector('button[name="reset_password"]')?.form.addEventListener('submit', () => {
            if (!isLockedOut) showActionLoader('Updating password...');
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Use a short timeout like in appointment.php to ensure all assets are settling
        setTimeout(function() { 
            const loader = document.getElementById('loader-overlay');
            const content = document.getElementById('main-content');
            
            if (loader) {
                loader.style.opacity = '0';
                loader.addEventListener('transitionend', () => {
                    loader.style.display = 'none';
                }, { once: true });
            }
            
            if (content) {
                content.style.display = 'block';
                // Re-trigger the fade-in animation (slideUp) that's already in your CSS
                content.style.animation = 'slideUp 0.6s ease-out';
            }
        }, 500); // 500ms delay
    });
    </script>
    </body>
</html>