<?php
session_start();

// Load PHPMailer
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
include __DIR__ . '/database.php';

// Helper: send OTP via SMTP
function sendOTP($toEmail, $otp, $userRole) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // ==========================================================
        // !! IMPORTANT !!
        // Put your Gmail address and your 16-character App Password here
        // ==========================================================
        $mail->Username   = 'rogerjuancito0621@gmail.com';     // ← Your full Gmail address
        $mail->Password   = 'r h t s t r o p g t n f g i p b';      // ← Your 16-character App Password
        
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // --- FIX 1: Updated Email Branding ---
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
                <p style='font-size: 14px; margin: 0;'>This code will expire in 5 minutes.</p>
            </div>
            <p style='font-size: 16px; line-height: 1.5;'>If you didn't request this password reset, please ignore this email.</p>
            <p style='font-size: 16px; line-height: 1.5;'>Thank you,<br>The Eye Care Clinic Team</p>
        </div>";
        $mail->AltBody = "Your password reset code for Eye Care Clinic: $otp\nIt expires in 5 minutes.";
        // --- End of FIX 1 ---

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
$message_type = 'error'; // 'error' or 'success'

// Handle email submission
if (isset($_POST['submit_email'])) {
    $email = trim($_POST['email']);
    
    // FIX 2: Use UNION query to find user in admin or staff table (matches login.php)
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
        $_SESSION['reset_otp_expires'] = time() + 300;
        $_SESSION['reset_user_role'] = $user['role'];
        $_SESSION['reset_original_id'] = $user['original_id'];
        $_SESSION['reset_original_table'] = $user['original_table'];
        
        if (sendOTP($email, $otp, $user['role'])) {
            $step = 'otp';
            $message = "<strong>Success!</strong> OTP sent to <strong>$email</strong>.";
            $message_type = 'success';
        } else {
            $message = "<strong>Error!</strong> Failed to send OTP. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = "<strong>Error!</strong> Email not found.";
        $message_type = 'error';
    }
    $stmt->close();
}

// Handle OTP verification
elseif (isset($_POST['verify_otp'])) {
    $input = trim($_POST['otp'] ?? '');
    if (empty($_SESSION['reset_otp']) || time() > $_SESSION['reset_otp_expires']) {
        $message = "<strong>Error!</strong> OTP expired. Please start again.";
        $message_type = 'error';
        session_unset();
        $step = 'email';
    } elseif ($input !== $_SESSION['reset_otp']) {
        $message = "<strong>Error!</strong> Invalid OTP. Try again.";
        $message_type = 'error';
        $step = 'otp';
    } else {
        $step = 'reset';
        $message = "<strong>Success!</strong> OTP verified. Enter your new password.";
        $message_type = 'success';
    }
}

// Handle password reset
elseif (isset($_POST['reset_password'])) {
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($pass) < 4) { // Simple validation
        $message = "<strong>Error!</strong> Password must be at least 4 characters long.";
        $message_type = 'error';
        $step = 'reset';
    } elseif ($pass !== $confirm) {
        $message = "<strong>Error!</strong> Passwords do not match.";
        $message_type = 'error';
        $step = 'reset';
    } else {
        $email = $_SESSION['reset_email'];
        $originalId = $_SESSION['reset_original_id'];
        $originalTable = $_SESSION['reset_original_table'];
        
        $stmt_original = null;
        
        // FIX 3: Update the correct table (admin or staff) using the correct ID column
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
                
                $message = "Password updated successfully."; // This message won't be seen, as we go to 'done'
                session_unset();
                $step = 'done';
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<strong>Error!</strong> Failed to update password: " . $e->getMessage();
                $message_type = 'error';
                $step = 'reset';
            }
        } else {
            $message = "<strong>Error!</strong> No matching user table found to update.";
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
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

/* Animated Background Pattern */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 30%, rgba(220, 20, 60, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(220, 20, 60, 0.03) 0%, transparent 50%);
    z-index: 0;
    pointer-events: none;
}

/* Medical Icons Background */
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
    animation: slideUp 0.6s ease-out;
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

/* Header Section with Icon */
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

/* Logo Section */
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

/* Progress Steps */
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

/* Form Section */
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
    box-sizing: border-box; /* Added */
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

/* OTP Input Special Styling */
.otp-input {
    text-align: center;
    letter-spacing: 8px;
    font-size: 24px;
    font-weight: 700;
    padding-left: 16px !important; /* Override icon padding */
    padding-right: 16px !important;
}

/* Message Box */
.message-box {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    line-height: 1.5;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-box i {
    font-size: 20px;
    flex-shrink: 0; /* Prevent icon from shrinking */
}

.message-box.success {
    background: #d1fae5;
    border: 2px solid var(--success-color);
    color: #065f46;
}

.message-box.success i {
    color: var(--success-color);
}

.message-box.error {
    background: #fee2e2;
    border: 2px solid var(--error-color);
    color: #991b1b;
}

.message-box.error i {
    color: var(--error-color);
}

/* Button */
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

/* Success Section */
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

/* Footer Links */
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

/* Help Text */
.help-text {
    text-align: center;
    color: var(--text-light);
    font-size: 13px;
    margin-top: 16px;
    line-height: 1.5;
}

/* Responsive */
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
    <div class="container">
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

            <?php if (!empty($message)): ?>
            <div style="padding: 0 40px;">
                <div class="message-box <?php echo $message_type; ?>">
                    <i class="fas <?php echo ($message_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <div>
                        <?php echo $message; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>


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
                        <input type="text" class="form-input otp-input" id="otpInput" name="otp" placeholder="000000" maxlength="6" required>
                    </div>
                    <div class="help-text">
                        We've sent a 6-digit code to your email. Code expires in 5 minutes.
                    </div>
                </div>

                <button type="submit" class="btn-submit" name="verify_otp">
                    Verify Code
                </button>
            </form>
            
            <?php elseif($step === 'reset'): ?>
            <form class="forgot-form" method="POST">
                <div class="form-group">
                    <label class="form-label" for="passwordInput">New Password</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" class="form-input" id="passwordInput" name="password" placeholder="Enter new password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPasswordInput">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" class="form-input" id="confirmPasswordInput" name="confirm_password" placeholder="Confirm new password" required>
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
        // All step handling is now done by PHP on page load.
        // No extra JavaScript is needed for step logic.
    </script>
</body>
</html>