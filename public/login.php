<?php 
session_start();
// Check if in cooldown
$in_cooldown = false;
$cooldown_remaining = 0;

// <<< FIX: Check for the SEGMENTED key 'client_id' >>>
if (isset($_SESSION['client_id'])) {
    // If client is logged in, redirect them away from the login page
    header("Location: home.php"); 
    exit(); 
}

if (isset($_SESSION['login_cooldown_until'])) {
    $cooldown_remaining = $_SESSION['login_cooldown_until'] - time();
    if ($cooldown_remaining > 0) {
        $in_cooldown = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/login.css" />
    <title>Client Login</title>
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="login-container">
        <div class="login-image">
            <h2>Welcome Back!</h2>
            <p>Log in to access your eye care services and manage your appointments with ease.</p>
        </div> 
        <!-- we dont need login-image.  -->
        
        <div class="login-form">
            <div class="form-header">
                <h1>Client Login</h1>
                <p>Please enter your credentials</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert <?= $in_cooldown ? 'alert-cooldown' : 'alert-error' ?>">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php if ($in_cooldown): ?>
                        <div class="cooldown-timer" id="cooldownTimer">⏱️ <?= $cooldown_remaining ?>s</div>
                    <?php endif; ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            
            <form action="../actions/login-action.php" method="POST" id="loginForm">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required <?= $in_cooldown ? 'disabled' : '' ?>>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required <?= $in_cooldown ? 'disabled' : '' ?>>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?= $in_cooldown ? 'disabled' : '' ?>>
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="login-button" id="loginBtn" <?= $in_cooldown ? 'disabled' : '' ?>>
                    <?= $in_cooldown ? 'Please Wait...' : 'Log In' ?>
                </button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($in_cooldown): ?>
    <script>
        let remaining = <?= $cooldown_remaining ?>;
        const timerEl = document.getElementById('cooldownTimer');
        const loginBtn = document.getElementById('loginBtn');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        
        const countdown = setInterval(() => {
            remaining--;
            
            if (remaining <= 0) {
                clearInterval(countdown);
                // Enable form
                loginBtn.disabled = false;
                loginBtn.textContent = 'Log In';
                emailInput.disabled = false;
                passwordInput.disabled = false;
                
                // Reload page to clear cooldown
                window.location.reload();
            } else {
                timerEl.textContent = `⏱️ ${remaining}s`;
            }
        }, 1000);
    </script>
    <?php endif; ?>

    <?php include '../includes/footer.php' ?>
    <div id="successPopup" class="success-overlay">
    <div class="success-card">
        <div class="checkmark-circle">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        <h2>Login Successful!</h2>
        <p>Redirecting to dashboard...</p>
    </div>
</div>


<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Stop normal submission

    const formData = new FormData(this);
    formData.append('login', 'true'); // Add the submit trigger

    const btn = document.getElementById('loginBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Verifying...';

    fetch('../actions/login-action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show Success Popup
            const popup = document.getElementById('successPopup');
            popup.classList.add('active');
            
            // Wait 1.5 seconds then redirect
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1500);
        } else {
            // Reload page to show PHP error (handled by session)
            window.location.reload(); 
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.location.reload(); // Fallback
    });
});
</script>
</body>
</html>