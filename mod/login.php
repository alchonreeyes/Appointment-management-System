<?php
// Start session at the very beginning
session_start();
include 'database.php'; // <-- FIX 1: Corrected include path

// Initialize login attempts tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = 0;
}

// Check if the user is temporarily locked out
$is_locked_out = false;
$remaining_lockout = 0;
if ($_SESSION['login_attempts'] >= 5) { // Reduced for demonstration
    $lockout_time = 30; // 30 seconds lockout
    $remaining_lockout = $lockout_time - (time() - $_SESSION['last_attempt_time']);
    
    if ($remaining_lockout > 0) {
        $is_locked_out = true;
    } else {
        // Reset login attempts after lockout period
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt_time'] = 0;
    }
}

// Authentication result
$auth_status = 'idle';

// Check if the form is submitted
if (isset($_POST['login']) && !$is_locked_out) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // =================================================================
    // MODIFIED QUERY: Added the 'status' column
    // Admins get a placeholder 'Active' status.
    // =================================================================
    $query = "(SELECT 
                id as original_id, 
                name as full_name, 
                email, 
                password, 
                role,
                'Active' as status  -- <-- ADDED: Placeholder for admin
            FROM admin WHERE email = ?)
            UNION
            (SELECT 
                staff_id as original_id, 
                full_name, 
                email, 
                password, 
                role,
                status              -- <-- ADDED: Actual status from staff
            FROM staff WHERE email = ?)";
            
    $stmt = $conn->prepare($query);
    
    // =================================================================
    // MODIFIED BINDING: Bind the email parameter twice (for both queries)
    // =================================================================
    $stmt->bind_param("ss", $email, $email);
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Direct password comparison (NO HASHING) - PLEASE USE PROPER HASHING IN PRODUCTION
        if ($password == $user['password']) {

            // =================================================================
            // <-- START: NEW CHECK for 'Inactive' Staff
            // =================================================================
            if ($user['role'] === 'staff' && $user['status'] === 'Inactive') {
                
                // Set a specific auth status for the JavaScript to catch
                $auth_status = 'inactive_error'; 
                
                // Note: We don't increment login attempts because the password was correct.
                // This is an administrative block, not a security failure.
            
            } else {
                // <-- END: NEW CHECK
                
                // User is 'admin' OR 'staff' with 'Active' status - Proceed with login
            
                // Reset login attempts on successful login
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                
                session_regenerate_id(true);
                
                // Set common session variables
                $_SESSION['user_id'] = $user['original_id']; // Use the original ID from the source table
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Set authentication status
                $auth_status = 'success';
                
                // Set role-specific session variables for backward compatibility
                switch ($user['role']) {
                    case 'admin':
                        $_SESSION['admin_logged_in'] = true;
                        $redirect = "admin/admin_dashboard.php";
                        break;
                    case 'staff':
                        $_SESSION['staff_logged_in'] = true;
                        $_SESSION['role'] = 'staff';
                        $redirect = "staff/staff_dashboard.php";
                        break;
                    default:
                        $auth_status = 'error';
                        break;
                }
                
                if (isset($redirect)) {
                    // Introduce a delay before redirecting
                    sleep(3);  // Delay for 1 second
                    header("Location: $redirect");
                    exit();
                }
            } // <-- ADDED: Closing 'else' for the inactive check
        } else {
            // Increment login attempts (WRONG PASSWORD)
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $auth_status = 'error';
        }
    } else {
        // Increment login attempts (USER NOT FOUND)
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $auth_status = 'error';
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eye Care Clinic System</title>
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
    
    /* === BACKGROUND IMAGE === */
    background-image: url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3wzNzEyMywxMjA3fDB8MXxzZWFyY2h8MTB8fG1lZGljYWwlMjBiYWNrZ3JvdW5kfGVufDB8fHx8MTcyMDY1NDU3NXww&ixlib=rb-4.0.3&q=80&w=1080');
    background-size: cover;
    background-position: center center;
    background-attachment: fixed;
    /* === END BACKGROUND IMAGE === */

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
    
    /* === BACKGROUND OVERLAY === */
    background-color: rgba(255, 255, 255, 0.5); 
    /* === END OVERLAY === */
    
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

.login-container {
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

.login-card {
    background: var(--white);
    border-radius: 24px;
    box-shadow: var(--shadow-heavy);
    overflow: hidden;
    border: 1px solid var(--border-color);
}

/* Header Section with Eye Icon */
.login-header {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    padding: 40px 30px 30px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.login-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    border-radius: 50%;
}

.eye-icon-container {
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

.eye-icon-container i {
    font-size: 36px;
    color: var(--white);
}

.login-header h1 {
    color: var(--white);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.login-header p {
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

/* Form Section */
.login-form {
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

/* Checkbox */
.terms-checkbox {
    display: flex;
    align-items: flex-start;
    margin: 20px 0 24px;
    gap: 12px;
}

.checkbox-input {
    width: 20px;
    height: 20px;
    accent-color: var(--primary-red);
    cursor: pointer;
    margin-top: 2px;
}

.checkbox-label {
    font-size: 14px;
    color: var(--text-light);
    line-height: 1.5;
}

.checkbox-label a {
    color: var(--primary-red);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s ease;
}

.checkbox-label a:hover {
    color: var(--dark-red);
    text-decoration: underline;
}

/* Button */
.btn-login {
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
    margin-top: 24px; /* Added margin to replace checkbox gap */
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(220, 20, 60, 0.3);
}

.btn-login:active {
    transform: translateY(0);
}

.btn-login:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Footer Links */
.login-footer {
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

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--white);
    margin: 5% auto;
    padding: 0;
    width: 90%;
    max-width: 600px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-height: 85vh;
    overflow: hidden;
    animation: slideDown 0.4s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
    padding: 30px;
    position: relative;
}

.modal-header h2 {
    color: var(--white);
    font-size: 24px;
    margin: 0;
    padding-right: 40px;
}

.close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: var(--white);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body {
    padding: 30px;
    overflow-y: auto;
    max-height: calc(85vh - 140px);
}

.modal-body ul {
    list-style: none;
    padding: 0;
}

.modal-body li {
    padding: 15px 0;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-dark);
    line-height: 1.6;
}

.modal-body li:last-child {
    border-bottom: none;
}

.modal-body strong {
    color: var(--primary-red);
    font-weight: 600;
}

/* Overlays */
.auth-overlay,
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

.auth-box,
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

.auth-icon {
    font-size: 80px;
    margin-bottom: 20px;
}

.auth-success .auth-icon {
    color: var(--success-color);
}

.auth-error .auth-icon {
    color: var(--error-color);
}

.auth-message {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-dark);
}

.lockout-timer {
    font-size: 64px;
    color: var(--error-color);
    font-weight: 700;
    margin: 20px 0;
}

/* Loading Overlay */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(220, 20, 60, 0.95);
    z-index: 4000;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid var(--white);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text {
    color: var(--white);
    margin-top: 20px;
    font-size: 16px;
    font-weight: 600;
}

/* Input Disabled State */
.input-disabled {
    pointer-events: none;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 480px) {
    .login-form {
        padding: 25px 25px 35px;
    }
    
    .login-header {
        padding: 30px 20px 25px;
    }
    
    .login-header h1 {
        font-size: 24px;
    }
    
    .eye-icon-container {
        width: 70px;
        height: 70px;
    }
    
    .eye-icon-container i {
        font-size: 32px;
    }
    
    .modal-body {
        padding: 20px;
    }
}
    </style>
</head>
<body>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Authenticating...</div>
    </div>

    <div id="authOverlay" class="auth-overlay">
        <div class="auth-box">
            <div class="auth-icon">
                <i id="authIcon" class="fas"></i>
            </div>
            <div class="auth-message" id="authMessage">Authentication Status</div>
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

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="eye-icon-container">
                    <i class="fas fa-eye"></i>
                </div>
                <h1>Eye Care Login</h1>
                <p>Secure Access Portal</p>
            </div>

            <div class="logo-section">
                <img src="photo/LOGO.jpg" alt="Eye Clinic Logo">
            </div>

            <form class="login-form" method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="emailInput">Email Address</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-envelope"></i>
                        <input type="email" class="form-input" id="emailInput" name="email" placeholder="your.email@clinic.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="passwordInput">Password</label>
                    <div class="input-wrapper">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" class="form-input" id="passwordInput" name="password" placeholder="Enter your password" required>
                        <span class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye-slash" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginButton" name="login">
                    Sign In
                </button>
            </form>

            <div class="login-footer">
                <a href="forgot_password.php" class="footer-link">
                    <i class="fas fa-key"></i>
                    Forgot Password?
                </a>
            </div>
        </div>
    </div>

    <script>
        // Configuration (replace with PHP values)
        const authStatus = <?php echo json_encode($auth_status); ?>;
        const isLockedOut = <?php echo $is_locked_out ? 'true' : 'false'; ?>;
        const remainingLockout = <?php echo $remaining_lockout; ?>;
        // REMOVED: const isFirstVisit = false;

        // DOM Elements
        const loginForm = document.getElementById('loginForm');
        const emailInput = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const passwordToggle = document.getElementById('passwordToggle');
        const toggleIcon = document.getElementById('toggleIcon');
        const loginButton = document.getElementById('loginButton');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const authOverlay = document.getElementById('authOverlay');
        const authIcon = document.getElementById('authIcon');
        const authMessage = document.getElementById('authMessage');
        const lockoutOverlay = document.getElementById('lockoutOverlay');
        const lockoutTimer = document.getElementById('lockoutTimer');
        // REMOVED: const termsModal = document.getElementById('termsModal');
        // REMOVED: const openModalBtn = document.getElementById('openModal');
        // REMOVED: const closeModalBtn = document.getElementById('closeModal');

        // Password Toggle
        passwordToggle.addEventListener('click', function() {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });

        // REMOVED: Terms Modal Functions (openTermsModal, closeTermsModal, event listeners)

        // Lockout Mechanism
        function initializeLockout() {
            if (isLockedOut) {
                emailInput.disabled = true;
                passwordInput.disabled = true;
                loginButton.disabled = true;
                document.querySelector('.login-form').classList.add('input-disabled');
                lockoutOverlay.style.display = 'flex';

                let timeLeft = remainingLockout;
                lockoutTimer.textContent = timeLeft; // Set initial time
                
                const lockoutInterval = setInterval(() => {
                    timeLeft--;
                    lockoutTimer.textContent = timeLeft;

                    if (timeLeft <= 0) {
                        clearInterval(lockoutInterval);
                        // Reload the page to reset the state from PHP
                        window.location.reload(); 
                    }
                }, 1000);
            }
        }

        // Auth Status Display
        function showAuthStatus() {
            if (authStatus === 'success') {
                authOverlay.classList.add('auth-success');
                authIcon.classList.add('fa-check-circle');
                authMessage.textContent = 'Login Successful!';
                authOverlay.style.display = 'flex';
                // No timeout, user will be redirected by PHP
            } else if (authStatus === 'error') {
                authOverlay.classList.add('auth-error');
                authIcon.classList.add('fa-times-circle');
                authMessage.textContent = 'Login Failed';
                authOverlay.style.display = 'flex';
                setTimeout(() => {
                    authOverlay.style.display = 'none';
                }, 2000);
            
            // =================================================================
            // <-- START: NEW JAVASCRIPT BLOCK for 'Inactive' Staff
            // =================================================================
            } else if (authStatus === 'inactive_error') {
                authOverlay.classList.add('auth-error'); // Use the red icon style
                authIcon.classList.add('fa-times-circle'); // Use the same error icon
                authMessage.textContent = 'YOUR ACCOUNT IS INACTIVE, CONTACT ADMIN TO ACTIVE YOUR ACCOUNT.';
                authOverlay.style.display = 'flex';
                // Keep the message on screen longer for them to read
                setTimeout(() => {
                    authOverlay.style.display = 'none';
                }, 4000); // 4 seconds
            }
            // =================================================================
            // <-- END: NEW JAVASCRIPT BLOCK
            // =================================================================
        }

        // Form Submission
        loginForm.addEventListener('submit', function(e) {
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }

            if (passwordInput.value.length === 0) {
                 e.preventDefault();
                 alert('Password cannot be empty.');
                 return;
            }

            // If validation passes, show loading overlay
            // The 3-second sleep in PHP will handle the redirect delay
            loadingOverlay.style.display = 'flex';
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeLockout();
            showAuthStatus();

            // REMOVED: if (isFirstVisit) { ... }
        });

        // Prevent back button
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>