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
            SUCCESS MODAL STYLES (Overlay)
            ========================================= */
            .success-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: none;
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
                display: none;
            }
            
            /* Cooldown Styling */
            .alert-cooldown {
                background-color: #fff7ed;
                color: #9a3412;
                border-color: #fdba74;
                display: block;
            }

            /* =========================================
            RESEND VERIFICATION MODAL
            ========================================= */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .modal-overlay.active {
                display: flex;
                opacity: 1;
            }

            .modal-content {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                width: 90%;
                max-width: 400px;
                transform: scale(0.9);
                transition: transform 0.3s ease;
            }

            .modal-overlay.active .modal-content {
                transform: scale(1);
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .modal-header h3 {
                margin: 0;
                color: #004aad;
                font-size: 20px;
            }

            .close-modal {
                background: none;
                border: none;
                font-size: 24px;
                color: #666;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .close-modal:hover {
                color: #004aad;
            }

            .modal-body p {
                color: #666;
                margin-bottom: 20px;
                font-size: 14px;
            }

            .modal-input-group {
                margin-bottom: 20px;
            }

            .modal-input-group label {
                display: block;
                margin-bottom: 8px;
                color: #333;
                font-weight: 500;
                font-size: 14px;
            }

            .modal-input-group input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 14px;
                box-sizing: border-box;
            }

            .modal-input-group input:focus {
                outline: none;
                border-color: #004aad;
            }

            .modal-buttons {
                display: flex;
                gap: 10px;
            }

            .modal-btn {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s;
            }

            .modal-btn-primary {
                background: #004aad;
                color: white;
            }

            .modal-btn-primary:hover:not(:disabled) {
                background: #003580;
            }

            .modal-btn-primary:disabled {
                background: #ccc;
                cursor: not-allowed;
            }

            .modal-btn-secondary {
                background: #f3f4f6;
                color: #666;
            }

            .modal-btn-secondary:hover {
                background: #e5e7eb;
            }

            .resend-link {
                display: inline-block;
                color: #004aad;
                text-decoration: none;
                font-size: 13px;
                margin-top: 10px;
                cursor: pointer;
            }

            .resend-link:hover {
                text-decoration: underline;
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

                <!-- Registration Success Alert -->
                <?php if (isset($_SESSION['registration_success'])): ?>
                    <div class="alert-box" style="background-color: #d1fae5; color: #065f46; border-color: #6ee7b7; display: block; margin-bottom: 15px;">
                        ✅ <?= htmlspecialchars($_SESSION['registration_success']); unset($_SESSION['registration_success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Verification Success Alert -->
                <?php if (isset($_SESSION['verification_success'])): ?>
                    <div class="alert-box" style="background-color: #d1fae5; color: #065f46; border-color: #6ee7b7; display: block; margin-bottom: 15px;">
                        ✅ <?= htmlspecialchars($_SESSION['verification_success']); unset($_SESSION['verification_success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Alert (for login failures) -->
                <div id="errorAlert" class="alert-box"></div>
                <!-- Security Warning (if blocked) -->
    <div id="securityWarning" class="alert-box" style="background-color: #fee2e2; color: #991b1b; border-color: #f87171; display: none;">
        <strong>⚠️ Access Restricted</strong><br>
        Your activity has been flagged. Please contact support if you believe this is an error.
    </div>
                <!-- Cooldown Alert -->
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
        <div style="position:relative; display: flex; align-items: center;">
            <input type="password" id="password" name="password" required <?= $in_cooldown ? 'disabled' : '' ?> style="width:100%; padding-right: 40px;">
            
            <span id="togglePassword" style="position: absolute; right: 10px; cursor: pointer; color: #999;">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </span>
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

                    <!-- ✅ NEW: Resend Verification Link -->
                    <div style="text-align: center; margin-top: 10px;">
                        <a href="#" class="resend-link" id="openResendModal">
                            📧 Didn't receive verification email?
                        </a>
                    </div>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Popup -->
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

        <!-- ✅ NEW: Resend Verification Modal -->
        <div id="resendModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Resend Verification Email</h3>
                    <button type="button" class="close-modal" id="closeModal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Enter your email address and we'll send you a new verification link.</p>
                    
                    <div id="resendAlert" class="alert-box" style="margin-bottom: 15px;"></div>
                    
                    <form id="resendForm">
                        <div class="modal-input-group">
                            <label for="resendEmail">Email Address</label>
                            <input type="email" id="resendEmail" name="email" required placeholder="your@email.com">
                        </div>
                        <div class="modal-buttons">
                            <button type="button" class="modal-btn modal-btn-secondary" id="cancelResend">Cancel</button>
                            <button type="submit" class="modal-btn modal-btn-primary" id="resendBtn">Send Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php include '../includes/footer.php' ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');

    togglePassword.addEventListener('click', function (e) {
        // Toggle the type attribute
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // Optional: Change color to indicate active state
        this.style.color = type === 'text' ? '#004aad' : '#999';
    });
            // 1. HANDLE COOLDOWN TIMER
            <?php if ($in_cooldown): ?>
                let remaining = <?= $cooldown_remaining ?>;
                const timerEl = document.getElementById('cooldownTimer');
                
                const countdown = setInterval(() => {
                    remaining--;
                    if (timerEl) timerEl.textContent = `⏱️ ${remaining}s`;
                    
                    if (remaining <= 0) {
                        clearInterval(countdown);
                        window.location.reload();
                    }
                }, 1000);
            <?php endif; ?>

            // 2. HANDLE LOGIN FORM SUBMISSION
            const loginForm = document.getElementById('loginForm');
            
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const btn = document.getElementById('loginBtn');
                    const originalText = btn.innerHTML;
                    const errorAlert = document.getElementById('errorAlert');
                    
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
                const popup = document.getElementById('successPopup');
                popup.classList.add('active');
                
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                btn.disabled = false;
                btn.innerHTML = originalText;
                
                errorAlert.style.display = 'block';
                errorAlert.innerText = "❌ " + data.message;
                
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                }
                
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

            // ✅ 3. HANDLE RESEND VERIFICATION MODAL
            const resendModal = document.getElementById('resendModal');
            const openModalBtn = document.getElementById('openResendModal');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelResend');
            const resendForm = document.getElementById('resendForm');
            const resendAlert = document.getElementById('resendAlert');

            // Open modal
            openModalBtn.addEventListener('click', function(e) {
                e.preventDefault();
                resendModal.classList.add('active');
                resendAlert.style.display = 'none';
                document.getElementById('resendEmail').value = '';
            });

            // Close modal
            function closeModal() {
                resendModal.classList.remove('active');
            }

            closeModalBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            // Close on outside click
            resendModal.addEventListener('click', function(e) {
                if (e.target === resendModal) {
                    closeModal();
                }
            });

            // Handle resend form submission
            resendForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const resendBtn = document.getElementById('resendBtn');
                const originalText = resendBtn.innerHTML;
                
                resendBtn.disabled = true;
                resendBtn.innerHTML = 'Sending...';
                resendAlert.style.display = 'none';

                const formData = new FormData(this);

                fetch('../actions/resend_verification.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = originalText;
                    
                    resendAlert.style.display = 'block';
                    
                    if (data.success) {
                        resendAlert.style.backgroundColor = '#d1fae5';
                        resendAlert.style.color = '#065f46';
                        resendAlert.style.borderColor = '#6ee7b7';
                        resendAlert.innerText = "✅ " + data.message;
                        
                        setTimeout(() => {
                            closeModal();
                        }, 2000);
                    } else {
                        resendAlert.style.backgroundColor = '#fee2e2';
                        resendAlert.style.color = '#991b1b';
                        resendAlert.style.borderColor = '#f87171';
                        resendAlert.innerText = "❌ " + data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = originalText;
                    
                    resendAlert.style.display = 'block';
                    resendAlert.style.backgroundColor = '#fee2e2';
                    resendAlert.style.color = '#991b1b';
                    resendAlert.style.borderColor = '#f87171';
                    resendAlert.innerText = "⚠️ Network error. Please try again.";
                });
            });
        });
        </script>
    </body>
    </html>