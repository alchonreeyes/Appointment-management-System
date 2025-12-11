

<?php 
session_start();
include '../actions/register-action.php';
// <<< CRITICAL SESSION LOCK (Rule A) >>>
if (isset($_SESSION['user_id'])) {
    // Redirect authenticated client to their home page
    header("Location: home.php"); 
    exit(); 
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up</title>
    <link rel="stylesheet" href="../assets/sign-up.css">
</head>
<body>
    <?php include '../includes/navbar.php'?>
    <div class="signup-wrapper">
        <div class="signup-container">
            <!-- Header -->
            <div class="signup-header">
                <div class="header-content">
                    <div class="signup-icon">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </div>
                    <h1>Create Account</h1>
                    <p>Join us for better eye care experience</p>
                </div>
            </div>

            <!-- Form Body -->
            <div class="signup-body">
                <!-- Progress Indicator -->
                <div class="form-progress">
                    <div class="progress-text">Complete your registration</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>
                </div>

                <form action="../actions/register-action.php" method="POST" id="signupForm">
                    
                    <!-- Full Name -->
                    <div class="form-group full-width">
                        <label>Full Name <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <input 
                                type="text" 
                                name="full_name" 
                                placeholder="Juan Dela Cruz" 
                                required
                            >
                        </div>
                    </div>

                    <!-- Email & Phone Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                                <input 
                                    type="email" 
                                    name="email" 
                                    placeholder="your.email@example.com" 
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                                <input 
    type="tel" 
    name="phone_number" 
    placeholder="09123456789" 
    maxlength="11"
    pattern="09[0-9]{9}"
    title="Must start with 09 and be exactly 11 digits (e.g., 09123456789)."
    required
>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
    <div class="form-group">
        <label>Gender <span class="required">*</span></label>
        <div class="input-wrapper">
            <select name="gender" required style="padding: 12px; border-radius: 8px;">
                <option value="">Select Gender...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Age <span class="required">*</span></label>
        <div class="input-wrapper">
            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 17h2v-4h4v-2h-4V7h-2v4H7v2h4v4zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
            </svg>
            <input 
                type="number" 
                name="age" 
                placeholder="Enter Age" 
                min="1"
                max="120"
                required
            >
        </div>
    </div>
</div>

<div class="form-group full-width">
    <label>Occupation <span class="required">*</span></label>
    <div class="input-wrapper">
        <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 12c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6 6H6c-1.1 0-2 .9-2 2v1h16v-1c0-1.1-.9-2-2-2z"/>
        </svg>
        <input 
            type="text" 
            name="occupation" 
            placeholder="Enter your occupation" 
            required
        >
    </div>
</div>

                    <!-- Password -->
                    <div class="form-group full-width">
                        <label>Password <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                            </svg>
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                placeholder="Create a strong password" 
                                required
                            >
                            <svg class="toggle-password" id="togglePassword" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </div>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>

                    <!-- Address -->
                    <div class="form-group full-width">
                        <label>Address <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <input 
                                type="text" 
                                name="address" 
                                placeholder="123 Street, City, Province" 
                                required
                            >
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="terms-section">
                        <div class="terms-title">📋 Terms & Agreement</div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                            </label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="policy" name="policy" required>
                            <label for="policy">
                                I accept the General Use of Services and understand the clinic's policies
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="signup" class="submit-btn">
                        Create My Account
                    </button>

                    <!-- Login Link -->
                    <div class="login-link">
                        Already have an account? <a href="login.php">Sign In</a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'?>
    <script>
        // Password Toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'text') {
                this.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
            } else {
                this.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
            }
        });

        // Password Strength Checker
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const strengthContainer = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthContainer.classList.remove('show');
                return;
            }

            strengthContainer.classList.add('show');

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            strengthBar.className = 'strength-bar';
            
            if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#e74c3c';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#f39c12';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#27ae60';
            }
        });

        // Progress Bar Update
        const form = document.getElementById('signupForm');
        const progressBar = document.getElementById('progressBar');
        const inputs = form.querySelectorAll('input[required]');

        function updateProgress() {
            let filled = 0;
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    if (input.checked) filled++;
                } else {
                    if (input.value.trim() !== '') filled++;
                }
            });
            
            const progress = (filled / inputs.length) * 100;
            progressBar.style.width = progress + '%';
        }

        inputs.forEach(input => {
            input.addEventListener('input', updateProgress);
            input.addEventListener('change', updateProgress);
        });

        // Form Validation
        form.addEventListener('submit', function(e) {
            const terms = document.getElementById('terms');
            const policy = document.getElementById('policy');

            if (!terms.checked || !policy.checked) {
                e.preventDefault();
                alert('Please accept all terms and conditions to continue');
                return false;
            }
        });
        // FILE: register.php (inside <script>)

// Phone Number Input Filter
const phoneInput = document.querySelector('input[name="phone_number"]');

phoneInput.addEventListener('input', function() {
    // 1. Remove non-digit characters
    this.value = this.value.replace(/[^0-9]/g, '');
    
    // 2. Format as 09XXXXXXXXX and ensure length
    if (this.value.length > 11) {
        this.value = this.value.slice(0, 11);
    }
});

// ... rest of your existing script code ...
    </script>
</body>
</html>