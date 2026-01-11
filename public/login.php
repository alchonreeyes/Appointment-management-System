<?php 
session_start();

// ✅ CAPTURE REDIRECT URL FROM QUERY PARAMETER
$redirect_after_login = $_GET['redirect'] ?? '../public/home.php';
$_SESSION['redirect_after_login'] = $redirect_after_login;

// 1. CHECK SESSION (Kung naka-login na, bawal na dito)
if (isset($_SESSION['client_id'])) {
    header("Location: home.php"); 
    exit(); 
}
// Kung Admin/Staff naman ang naka-login
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin') header("Location: ../mod/admin/admin_dashboard.php");
    else header("Location: ../mod/staff/staff_dashboard.php");
    exit();
}

// 2. CHECK COOLDOWN
$in_cooldown = false;
$cooldown_remaining = 0;
if (isset($_SESSION['login_cooldown_until'])) {
    $cooldown_remaining = $_SESSION['login_cooldown_until'] - time();
    if ($cooldown_remaining > 0) {
        $in_cooldown = true;
    } else {
        unset($_SESSION['login_cooldown_until']); // Reset kung tapos na
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Eye Care System</title>
    
    <link rel="stylesheet" href="../assets/login.css" />

    <style>
        /* =========================================
           ADDED: SUCCESS MODAL STYLES (Overlay)
           ========================================= */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); /* Dimmed Background */
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .success-overlay.active {
            display: flex;
            opacity: 1;
        }

        .success-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transform: scale(0.8);
            transition: transform 0.3s ease;
            width: 300px;
        }

        .success-overlay.active .success-card {
            transform: scale(1);
        }

        .success-card h2 { color: #22c55e; margin: 15px 0 5px; font-size: 20px; }
        .success-card p { color: #666; font-size: 14px; margin-bottom: 0; }

        /* Error Alert Styling */
        .alert-box {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #f87171;
            display: none; /* Hidden by default */
        }
        
        /* Cooldown Styling */
        .alert-cooldown {
            background-color: #fff7ed;
            color: #9a3412;
            border-color: #fdba74;
            display: block;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="login-container">
        <div class="login-form" style="margin: 0 auto; max-width: 450px;">
            <div class="form-header">
                <h1>Welcome Back</h1>
                <p>Please enter your credentials to login.</p>
            </div>

            <div id="errorAlert" class="alert-box"></div>

            <?php if ($in_cooldown): ?>
                <div class="alert-box alert-cooldown" style="display:block;">
                    Too many attempts. Please wait.
                    <div id="cooldownTimer" style="font-weight:bold; margin-top:5px;">⏱️ <?= $cooldown_remaining ?>s</div>
                </div>
            <?php endif; ?>

            <form id="loginForm">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required <?= $in_cooldown ? 'disabled' : '' ?>>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" required <?= $in_cooldown ? 'disabled' : '' ?> style="width:100%">
                    </div>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?= $in_cooldown ? 'disabled' : '' ?>>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" class="login-button" id="loginBtn" <?= $in_cooldown ? 'disabled' : '' ?>>
                    <?= $in_cooldown ? 'Please Wait...' : 'Log In' ?>
                </button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>

    <div id="successPopup" class="success-overlay">
        <div class="success-card">
            <div class="checkmark-circle">
                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                    <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
            <h2>Login Successful!</h2>
            <p>Redirecting you now...</p>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // 1. HANDLE COOLDOWN TIMER (Kung meron)
        <?php if ($in_cooldown): ?>
            let remaining = <?= $cooldown_remaining ?>;
            const timerEl = document.getElementById('cooldownTimer');
            
            const countdown = setInterval(() => {
                remaining--;
                if (timerEl) timerEl.textContent = `⏱️ ${remaining}s`;
                
                if (remaining <= 0) {
                    clearInterval(countdown);
                    window.location.reload(); // Reload para ma-enable ulit ang form
                }
            }, 1000);
        <?php endif; ?>

        // 2. HANDLE FORM SUBMISSION via AJAX
        const loginForm = document.getElementById('loginForm');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Pigilan ang normal submit

                const btn = document.getElementById('loginBtn');
                const originalText = btn.innerHTML;
                const errorAlert = document.getElementById('errorAlert');
                
                // Reset UI
                btn.disabled = true;
                btn.innerHTML = 'Verifying...';
                errorAlert.style.display = 'none';

                const formData = new FormData(this);

                fetch('../actions/login-action.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
    if (data.success) {
        // SUCCESS: Show Popup & Redirect
        const popup = document.getElementById('successPopup');
        popup.classList.add('active');
        
        setTimeout(() => {
            window.location.href = data.redirect;
        }, 1500);

    } else {
        // ERROR: Show Message
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        errorAlert.style.display = 'block';
        errorAlert.innerText = "❌ " + data.message;
        
        // ✅ NEW: Redirect to verify page if unverified
        if (data.redirect) {
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 2000);
        }
        
        // Cooldown check
        if (data.message.includes("attempts")) {
            setTimeout(() => window.location.reload(), 2000);
        }
    }
})
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    errorAlert.style.display = 'block';
                    errorAlert.innerText = "⚠️ Network error. Please try again.";
                });
            });
        }
    });
    </script>
</body>
</html>