<?php 
// 1. Start the session once at the top of the page
session_start(); 

// 2. CRITICAL SESSION LOCK (Use segmented key)
if (isset($_SESSION['client_id'])) {
    header("Location: home.php"); 
    exit(); 
}

// ✅ CLEAR UNWANTED MESSAGES (only show registration-specific errors)
// Remove success messages - they should only appear on login.php
unset($_SESSION['registration_success']);
unset($_SESSION['verification_success']);
unset($_SESSION['password_reset_success']);

// Only keep registration error if it exists
$registration_error = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Eye Master Clinic</title>
    <link rel="stylesheet" href="../assets/sign-up.css">
    <style>
        /* Additional inline styles for better UX */
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .password-strength.weak {
            color: #e74c3c;
        }
        
        .password-strength.medium {
            color: #f39c12;
        }
        
        .password-strength.strong {
            color: #27ae60;
        }
        
        .validation-message {
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }
        
        .validation-message.error {
            color: #e74c3c;
            display: block;
        }
        
        .validation-message.success {
            color: #27ae60;
            display: block;
        }
        
        .input-wrapper.error input {
            border: 2px solid #e74c3c;
            background-color: #fef2f2;
        }
        
        .input-wrapper.success input {
            border: 2px solid #27ae60;
        }
        
        .requirements-list {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            display: none;
        }
        
        .requirements-list.show {
            display: block;
        }
        
        .requirement {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirement.met {
            color: #27ae60;
        }
        
        .requirement.met::before {
            content: "✓";
            font-weight: bold;
        }
        
        .requirement::before {
            content: "○";
        }
    </style>
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

                <!-- ✅ ONLY SHOW REGISTRATION ERRORS HERE -->
                <?php if ($registration_error): ?>
                    <div class="alert alert-error">
                        ❌ <?= htmlspecialchars($registration_error); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form action="../actions/register-action.php" method="POST" id="signupForm">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <!-- Full Name -->
                    <div class="form-group full-width">
                        <label>Full Name <span class="required">*</span></label>
                        <div class="input-wrapper" id="nameWrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <input 
                                type="text" 
                                name="full_name" 
                                id="fullName"
                                placeholder="Juan Dela Cruz" 
                                required
                                minlength="3"
                                maxlength="100"
                            >
                        </div>
                        <span class="validation-message" id="nameError"></span>
                    </div>

                    <!-- Email & Phone Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <div class="input-wrapper" id="emailWrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                                </svg>
                                <input 
                                    type="email" 
                                    name="email" 
                                    id="email"
                                    placeholder="your.email@example.com" 
                                    required
                                >
                            </div>
                            <span class="validation-message" id="emailError"></span>
                        </div>

                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <div class="input-wrapper" id="phoneWrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                                </svg>
                                <input 
                                    type="tel" 
                                    name="phone_number" 
                                    id="phone"
                                    placeholder="09171234567" 
                                    required
                                    maxlength="11"
                                    pattern="09[0-9]{9}"
                                >
                            </div>
                            <span class="validation-message" id="phoneError"></span>
                        </div>
                    </div>

                    <!-- Password & Confirm Password Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <div class="input-wrapper" id="passwordWrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                                <input 
                                    type="password" 
                                    name="password" 
                                    id="password"
                                    placeholder="Enter strong password" 
                                    required
                                    minlength="8"
                                >
                                <button type="button" class="toggle-password" onclick="togglePassword('password')">
    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
    </svg>
</button>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                            <div class="requirements-list" id="passwordRequirements">
                                <div class="requirement" id="req-length">At least 8 characters</div>
                                <div class="requirement" id="req-uppercase">One uppercase letter</div>
                                <div class="requirement" id="req-lowercase">One lowercase letter</div>
                                <div class="requirement" id="req-number">One number</div>
                                <div class="requirement" id="req-special">One special character (!@#$%^&*)</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm Password <span class="required">*</span></label>
                            <div class="input-wrapper" id="confirmPasswordWrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                                <input 
                                    type="password" 
                                    name="confirm_password" 
                                    id="confirmPassword"
                                    placeholder="Re-enter password" 
                                    required
                                >
                                <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                                    <svg viewBox="0 0 24 24" width="20" height="20">
                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                                    </svg>
                                </button>
                            </div>
                            <span class="validation-message" id="confirmPasswordError"></span>
                        </div>
                    </div>

                    <!-- Address (Full Width) -->
                    <div class="form-group full-width">
                        <label>Complete Address <span class="required">*</span></label>
                        <div class="input-wrapper" id="addressWrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <textarea 
    name="address" 
    id="address"
    placeholder="House/Unit No., Street, Barangay, City, Province" 
    required
    rows="3"
    minlength="10"
    maxlength="500"
    style="width: 100%; padding: 12px 0 12px 32px; border: none; border-bottom: 1px solid #e0e0e0; font-size: 0.95rem; background: transparent; font-family: inherit; resize: vertical;"
></textarea>
                        </div>
                        <span class="validation-message" id="addressError"></span>
                        <div style="text-align: right; font-size: 12px; color: #666; margin-top: 4px;">
                            <span id="addressCount">0</span>/500 characters
                        </div>
                    </div>

                    <!-- Age, Gender, Occupation Row -->
                    <div class="form-row">
                        <div class="form-group">
    <label>Birth Date <span class="required">*</span></label>
    <div class="input-wrapper" id="birthdayWrapper">
        <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/>
        </svg>
        <input 
            type="date" 
            name="birth_date" 
            id="birthDate"
            required
            max="<?php echo date('Y-m-d'); ?>"
        >
        <input type="hidden" name="age" id="age" value="0">
    </div>
    <span class="validation-message" id="birthdayError"></span>
    <div style="font-size: 12px; color: #666; margin-top: 4px;">
        Age: <span id="calculatedAge">-</span> years old
    </div>
</div>

                        <div class="form-group">
                            <label>Gender <span class="required">*</span></label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                <select name="gender" id="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Occupation <span class="required">*</span></label>
                            <div class="input-wrapper" id="occupationWrapper">
                                <svg class="input-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                                </svg>
                                <input 
                                    type="text" 
                                    name="occupation" 
                                    id="occupation"
                                    placeholder="e.g., Teacher, Student" 
                                    required
                                    minlength="2"
                                    maxlength="100"
                                >
                            </div>
                            <span class="validation-message" id="occupationError"></span>
                        </div>
                    </div>

                    
<!-- Terms and Privacy - NEW DESIGN -->
<div class="terms-section-new">
    <div class="checkbox-group-new">
        <input type="checkbox" name="terms" id="terms" required>
        <label for="terms">
            I agree to the <a href="#" onclick="openModal('terms'); return false;" class="terms-link">Terms and Conditions</a>
        </label>
    </div>
    
    <div class="checkbox-group-new">
        <input type="checkbox" name="privacy" id="privacy" required>
        <label for="privacy">
            I accept the <a href="#" onclick="openModal('privacy'); return false;" class="terms-link">Privacy Policy</a>
        </label>
    </div>
</div>

<!-- Modal Container -->
<div id="termsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Modal Title</h2>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            <button type="button" class="modal-btn-secondary" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
    }

    .modal.show {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        width: 90%;
        max-width: 700px;
        max-height: 80vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5rem;
        color: #333;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        color: #999;
        cursor: pointer;
        transition: color 0.2s;
    }

    .modal-close:hover {
        color: #333;
    }

    .modal-body {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #555;
    }

    .modal-body h3 {
        color: #333;
        margin-top: 15px;
        margin-bottom: 10px;
    }

    .modal-body ul {
        margin-left: 20px;
        margin-bottom: 10px;
    }

    .modal-body li {
        margin-bottom: 8px;
    }

    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .modal-btn-secondary {
        padding: 10px 20px;
        background-color: #e0e0e0;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.95rem;
        transition: background-color 0.2s;
    }

    .modal-btn-secondary:hover {
        background-color: #d0d0d0;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @media (max-width: 600px) {
        .modal-content {
            width: 95%;
            max-height: 90vh;
        }
    }
</style>

<script>
    const termsContent = `
        <h3>Eye Master Clinic - Terms of Service</h3>
        <p><strong>Last Updated: January 2024</strong></p>
        
        <h3>1. Acceptance of Terms</h3>
        <p>By registering with Eye Master Clinic, you agree to these terms and conditions. If you do not agree, please do not register.</p>
        
        <h3>2. Services</h3>
        <p>Eye Master Clinic provides online appointment booking and eye care services. Our services are available to patients aged 5 years and above.</p>
        
        <h3>3. User Responsibilities</h3>
        <ul>
            <li>Provide accurate and complete information during registration</li>
            <li>Maintain confidentiality of your account credentials</li>
            <li>Notify us immediately of unauthorized account access</li>
            <li>Use our services only for lawful purposes</li>
        </ul>
        
        <h3>4. Appointment Booking</h3>
        <ul>
            <li>Appointments must be booked in advance</li>
            <li>Cancellations must be made at least 24 hours prior</li>
            <li>No-shows may result in rescheduling delays</li>
        </ul>
        
        <h3>5. Medical Information</h3>
        <p>All medical information provided is confidential and will be handled according to healthcare data protection standards.</p>
        
        <h3>6. Limitation of Liability</h3>
        <p>Eye Master Clinic is not liable for indirect, incidental, or consequential damages arising from your use of our services.</p>
        
        <h3>7. Changes to Terms</h3>
        <p>We reserve the right to modify these terms at any time. Continued use of our services constitutes acceptance of updated terms.</p>
    `;

    const privacyContent = `
        <h3>Eye Master Clinic - Privacy Policy</h3>
        <p><strong>Last Updated: January 2024</strong></p>
        
        <h3>1. Information We Collect</h3>
        <ul>
            <li>Personal information: Name, email, phone, address</li>
            <li>Health information: Medical history, eye conditions</li>
            <li>Usage data: Appointment history and interactions</li>
        </ul>
        
        <h3>2. How We Use Your Information</h3>
        <ul>
            <li>To process and manage your appointments</li>
            <li>To provide medical services and care</li>
            <li>To send appointment reminders and notifications</li>
            <li>To improve our services and website</li>
        </ul>
        
        <h3>3. Data Security</h3>
        <p>We implement industry-standard security measures to protect your personal and medical information from unauthorized access, alteration, or disclosure.</p>
        
        <h3>4. Data Sharing</h3>
        <p>We do not sell, trade, or rent your personal information to third parties. Information may be shared with healthcare professionals involved in your care.</p>
        
        <h3>5. Your Rights</h3>
        <ul>
            <li>Access your personal information</li>
            <li>Request correction of inaccurate data</li>
            <li>Request deletion of your data</li>
            <li>Opt-out of marketing communications</li>
        </ul>
        
        <h3>6. Cookies</h3>
        <p>Our website uses cookies to enhance user experience. You can control cookie settings through your browser.</p>
        
        <h3>7. Contact Us</h3>
        <p>For privacy concerns, contact us at: privacy@eyemasterclinic.com</p>
    `;

    function openModal(type) {
        const modal = document.getElementById('termsModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        
        if (type === 'terms') {
            modalTitle.textContent = 'Terms of Service';
            modalBody.innerHTML = termsContent;
        } else if (type === 'privacy') {
            modalTitle.textContent = 'Privacy Policy';
            modalBody.innerHTML = privacyContent;
        }
        
        modal.classList.add('show');
    }

    function closeModal() {
        const modal = document.getElementById('termsModal');
        modal.classList.remove('show');
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('termsModal');
        if (event.target === modal) {
            closeModal();
        }
    });

    // Update links to use modals
    document.addEventListener('DOMContentLoaded', function() {
        const termsLink = document.querySelector('a[href="terms.php"]');
        const privacyLink = document.querySelector('a[href="privacy.php"]');
        
        if (termsLink) {
            termsLink.href = '#';
            termsLink.onclick = (e) => { e.preventDefault(); openModal('terms'); };
        }
        
        if (privacyLink) {
            privacyLink.href = '#';
            privacyLink.onclick = (e) => { e.preventDefault(); openModal('privacy'); };
        }
    });
</script>
<button type="submit" class="submit-btn" id="submitBtn">
    CREATE ACCOUNT
</button>
<br>
                    <!-- Login Link -->
                    <div class="form-footer">
                        Already have an account? <a href="login.php">Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Real-time validation
        const fullName = document.getElementById('fullName');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        const address = document.getElementById('address');

        const occupation = document.getElementById('occupation');

        // Name validation
        fullName.addEventListener('blur', function() {
            const value = this.value.trim();
            const wrapper = document.getElementById('nameWrapper');
            const error = document.getElementById('nameError');
            
            if (value.length < 3) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Name must be at least 3 characters';
                error.classList.add('error');
                return false;
            }
            
            if (!/^[a-zA-ZñÑáéíóúÁÉÍÓÚ\s\-'\.]+$/.test(value)) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Name can only contain letters, spaces, hyphens, and apostrophes';
                error.classList.add('error');
                return false;
            }
            
            const parts = value.split(/\s+/);
            if (parts.length < 2) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Please enter both first and last name';
                error.classList.add('error');
                return false;
            }
            
            wrapper.classList.remove('error');
            wrapper.classList.add('success');
            error.classList.remove('error');
            error.textContent = '';
            return true;
        });

        // Email validation
        email.addEventListener('blur', function() {
            const value = this.value.trim();
            const wrapper = document.getElementById('emailWrapper');
            const error = document.getElementById('emailError');
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Please enter a valid email address';
                error.classList.add('error');
                return false;
            }
            
            wrapper.classList.remove('error');
            wrapper.classList.add('success');
            error.classList.remove('error');
            error.textContent = '';
            return true;
        });

        // Phone validation
        phone.addEventListener('input', function() {
            const value = this.value.replace(/\D/g, '');
            const wrapper = document.getElementById('phoneWrapper');
            const error = document.getElementById('phoneError');
            
            if (value && !value.startsWith('09')) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Phone must start with 09';
                error.classList.add('error');
                return false;
            }
            
            if (value && value.length !== 11) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Phone must be exactly 11 digits';
                error.classList.add('error');
                return false;
            }
            
            if (value.length === 11) {
                wrapper.classList.remove('error');
                wrapper.classList.add('success');
                error.classList.remove('error');
                error.textContent = '';
                return true;
            }
        });

        // Password strength checker
        password.addEventListener('focus', function() {
            document.getElementById('passwordRequirements').classList.add('show');
        });

        password.addEventListener('input', function() {
            const value = this.value;
            const strength = document.getElementById('passwordStrength');
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasUpper = /[A-Z]/.test(value);
            const hasLower = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
            
            // Update requirement indicators
            document.getElementById('req-length').classList.toggle('met', hasLength);
            document.getElementById('req-uppercase').classList.toggle('met', hasUpper);
            document.getElementById('req-lowercase').classList.toggle('met', hasLower);
            document.getElementById('req-number').classList.toggle('met', hasNumber);
            document.getElementById('req-special').classList.toggle('met', hasSpecial);
            
            // Calculate strength
            const metCount = [hasLength, hasUpper, hasLower, hasNumber, hasSpecial].filter(Boolean).length;
            
            if (metCount < 3) {
                strength.textContent = 'Weak password';
                strength.className = 'password-strength weak';
            } else if (metCount < 5) {
                strength.textContent = 'Medium password';
                strength.className = 'password-strength medium';
            } else {
                strength.textContent = 'Strong password';
                strength.className = 'password-strength strong';
            }
        });

        // Confirm password validation
        confirmPassword.addEventListener('input', function() {
            const wrapper = document.getElementById('confirmPasswordWrapper');
            const error = document.getElementById('confirmPasswordError');
            
            if (this.value !== password.value) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Passwords do not match';
                error.classList.add('error');
                return false;
            }
            
            wrapper.classList.remove('error');
            wrapper.classList.add('success');
            error.classList.remove('error');
            error.textContent = '';
            return true;
        });

        // Address validation
        address.addEventListener('input', function() {
            document.getElementById('addressCount').textContent = this.value.length;
        });

        address.addEventListener('blur', function() {
            const value = this.value.trim();
            const wrapper = document.getElementById('addressWrapper');
            const error = document.getElementById('addressError');
            
            if (value.length < 10) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Address must be at least 10 characters';
                error.classList.add('error');
                return false;
            }
            
            if (!/\d/.test(value)) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Address must include a street/house number';
                error.classList.add('error');
                return false;
            }
            
            wrapper.classList.remove('error');
            wrapper.classList.add('success');
            error.classList.remove('error');
            error.textContent = '';
            return true;
        });

// Birthday validation and age calculation
const birthDate = document.getElementById('birthDate');

birthDate.addEventListener('change', function() {
    const wrapper = document.getElementById('birthdayWrapper');
    const error = document.getElementById('birthdayError');
    const calculatedAgeSpan = document.getElementById('calculatedAge');
    const ageInput = document.getElementById('age');
    
    if (!this.value) {
        return;
    }
    
    // Calculate age
    const today = new Date();
    const birthDateObj = new Date(this.value);
    let age = today.getFullYear() - birthDateObj.getFullYear();
    const monthDiff = today.getMonth() - birthDateObj.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDateObj.getDate())) {
        age--;
    }
    
    // Validate age
    if (age < 5) {
        wrapper.classList.add('error');
        wrapper.classList.remove('success');
        error.textContent = 'Minimum age is 5 years. Patients under 5 require guardian registration.';
        error.classList.add('error');
        calculatedAgeSpan.textContent = age;
        ageInput.value = age;
        return false;
    }
    
    if (age > 120) {
        wrapper.classList.add('error');
        wrapper.classList.remove('success');
        error.textContent = 'Please enter a valid birth date.';
        error.classList.add('error');
        calculatedAgeSpan.textContent = age;
        ageInput.value = age;
        return false;
    }
    
    // Valid age
    wrapper.classList.remove('error');
    wrapper.classList.add('success');
    error.classList.remove('error');
    error.textContent = '';
    calculatedAgeSpan.textContent = age;
    ageInput.value = age; // Store age in hidden field
    return true;
});

        // Occupation validation
        occupation.addEventListener('blur', function() {
            const value = this.value.trim();
            const wrapper = document.getElementById('occupationWrapper');
            const error = document.getElementById('occupationError');
            
            if (value.length < 2) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Occupation must be at least 2 characters';
                error.classList.add('error');
                return false;
            }
            
            if (!/^[a-zA-Z0-9\s\-\/&,\.]+$/.test(value)) {
                wrapper.classList.add('error');
                wrapper.classList.remove('success');
                error.textContent = 'Occupation contains invalid characters';
                error.classList.add('error');
                return false;
            }
            
            wrapper.classList.remove('error');
            wrapper.classList.add('success');
            error.classList.remove('error');
            error.textContent = '';
            return true;
        });

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        // Form submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="btn-text">Creating Account...</span>';
        });

        // Progress bar
        const form = document.getElementById('signupForm');
        const progressBar = document.getElementById('progressBar');
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            input.addEventListener('input', updateProgress);
        });
        // Validate both checkboxes
const termsCheckbox = document.getElementById('terms');
const privacyCheckbox = document.getElementById('privacy');

document.getElementById('signupForm').addEventListener('submit', function(e) {
    if (!termsCheckbox.checked || !privacyCheckbox.checked) {
        e.preventDefault();
        alert('Please agree to both Terms and Conditions and Privacy Policy');
        return false;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="btn-text">Creating Account...</span>';
});

        function updateProgress() {
            let filledCount = 0;
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    if (input.checked) filledCount++;
                } else if (input.value.trim() !== '') {
                    filledCount++;
                }
            });
            
            const progress = (filledCount / inputs.length) * 100;
            progressBar.style.width = progress + '%';
        }
    </script>
</body>
</html>