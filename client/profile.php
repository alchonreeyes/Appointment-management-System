<?php
session_start();

// --- 1. SESSION SEGMENTATION CHECK ---
if (!isset($_SESSION['client_id'])) {
    header("Location: ../public/login.php");
    exit();
}

// --- 2. DATABASE & UTILITIES SETUP ---
require '../config/db.php'; 
require_once '../config/encryption_util.php'; 

$db = new Database();
$pdo = $db->getConnection();
$user_id = $_SESSION['client_id']; 

$error_message = '';
$success_message = '';
$user = []; 

// =======================================================
// 3. HANDLE PROFILE UPDATE (PDO + ENCRYPTION + VALIDATION)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? ''); 
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $suffix = trim($_POST['suffix'] ?? '');
    
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // ✅ AGE VALIDATION (1-120 only)
    if ($age < 1 || $age > 120) {
        $_SESSION['error_message'] = "Please enter a valid age between 1 and 120.";
        header("Location: profile.php");
        exit();
    }
    
    // ✅ PHONE NUMBER VALIDATION (11 digits, starts with 09)
    $phone_clean = preg_replace('/\s+/', '', $phone_number);
    if (!preg_match('/^09\d{9}$/', $phone_clean)) {
        $_SESSION['error_message'] = "Please enter a valid phone number (09XXXXXXXXX).";
        header("Location: profile.php");
        exit();
    }
    
    // ENCRYPT bago i-save sa database
    $encrypted_full_name = encrypt_data($full_name);
    $encrypted_phone_number = encrypt_data($phone_number);
    $encrypted_address = encrypt_data($address);
    $encrypted_occupation = encrypt_data($occupation);

    try {
        $pdo->beginTransaction();

        $update_user_stmt = $pdo->prepare("
            UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE id = ?
        ");
        $update_user_stmt->execute([
            $encrypted_full_name,
            $email, 
            $encrypted_phone_number,
            $encrypted_address,
            $user_id
        ]);

        $update_client_stmt = $pdo->prepare("
            UPDATE clients 
            SET birth_date = ?, gender = ?, age = ?, suffix = ?, occupation = ? 
            WHERE user_id = ?
        ");
        $update_client_stmt->execute([
            $birth_date, $gender, $age, $suffix, $encrypted_occupation, $user_id
        ]);
        // return noting
        
        $pdo->commit();
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Update failed: " . $e->getMessage();
        header("Location: profile.php");
        exit();
    }
}

// =======================================================
// 4. HANDLE PASSWORD CHANGE
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Password length validation
    if (strlen($new_password) < 6) {
        $_SESSION['error_message'] = "New password must be at least 6 characters.";
        header("Location: profile.php");
        exit();
    }
    
    $fetch_hash_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $fetch_hash_stmt->execute([$user_id]);
    $user_pass_data = $fetch_hash_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_pass_data && password_verify($current_password, $user_pass_data['password_hash'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT); 
            $update_password = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt_pass = $pdo->prepare($update_password);
            
            if ($stmt_pass->execute([$hashed_password, $user_id])) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            }
        } else {
            $_SESSION['error_message'] = "New passwords do not match.";
            header("Location: profile.php");
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Current password is incorrect.";
        header("Location: profile.php");
        exit();
    }
}

// =======================================================
// 5. FETCH AND DECRYPT USER DATA
// =======================================================
try {
    $query = "SELECT u.*, c.birth_date, c.gender, c.age, c.suffix, c.occupation 
              FROM users u 
              LEFT JOIN clients c ON u.id = c.user_id 
              WHERE u.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user_encrypted = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_encrypted) {
        session_destroy();
        header("Location: ../public/login.php");
        exit();
    }
    
    // I-initialize ang $user array para sa HTML
    $user = $user_encrypted;

    // --- CRITICAL DECRYPTION STEP ---
    $user['full_name']    = decrypt_data($user_encrypted['full_name'] ?? '');
    $user['phone_number'] = decrypt_data($user_encrypted['phone_number'] ?? '');
    $user['address']      = decrypt_data($user_encrypted['address'] ?? ''); 
    $user['occupation']   = decrypt_data($user_encrypted['occupation'] ?? '');  
 
} catch (Exception $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
}

// --- 6. HANDLE MESSAGES ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$name_parts = explode(' ', $user['full_name'] ?? '');
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user['full_name'] ?? 'U', 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Success/Error Modal Styles */
        .notification-modal {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
        }

        .notification-modal.show {
            display: block;
        }

        .notification-content {
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            border-left: 4px solid;
        }

        .notification-content.success {
            border-left-color: #10b981;
        }

        .notification-content.error {
            border-left-color: #ef4444;
        }

        .notification-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .notification-content.success .notification-icon {
            color: #10b981;
        }

        .notification-content.error .notification-icon {
            color: #ef4444;
        }

        .notification-text {
            color: #1f2937;
            font-weight: 500;
            font-size: 14px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .notification-modal.hiding {
            animation: slideOutRight 0.3s ease-out;
        }

        /* Input Error Styling */
        .input-error {
            border: 2px solid #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .error-text {
            color: #ef4444;
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .error-text.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>

    <!-- Notification Modal -->
    <div id="notificationModal" class="notification-modal">
        <div class="notification-content" id="notificationContent">
            <div class="notification-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="notification-text" id="notificationText"></div>
        </div>
    </div>

    <div class="ojo-container">
        
        <div class="account-header">
            <h1>MY ACCOUNT</h1>
        </div>

        <div class="account-grid">
            <nav class="account-menu">
                <ul>
                    <li><a href="profile.php" class="active">Account Details</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="settings.php">Settings</a></li>
                    <li><a href="../actions/logout.php" style="color: #e74c3c;">Log out</a></li>
                </ul>
            </nav>

            <main class="account-content">
                
                <h3>Personal Information</h3>

                <form method="POST" action="" id="profileForm">
                    <div class="ojo-form-grid">
                        <div class="ojo-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="ojo-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly style="color:#999;">
                        </div>
                        <div class="ojo-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" id="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>" maxlength="11" required>
                            <span class="error-text" id="phone_error">Please enter valid phone (09XXXXXXXXX)</span>
                        </div>
                        <div class="ojo-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= htmlspecialchars($user['occupation']) ?>" required>
                        </div>
                        <div class="ojo-group">
                            <label>Age</label>
                            <input type="number" name="age" id="age" value="<?= htmlspecialchars($user['age']) ?>" min="1" max="120" required>
                            <span class="error-text" id="age_error">Age must be between 1 and 120</span>
                        </div>
                        <div class="ojo-group">
                            <label>Gender</label>
                            <input type="text" name="gender" value="<?= htmlspecialchars($user['gender'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-ojo">SAVE CHANGES</button>
                </form>

            </main>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        // Show notification if message exists
        <?php if ($success_message): ?>
            showNotification('<?= addslashes($success_message) ?>', 'success');
        <?php endif; ?>

        <?php if ($error_message): ?>
            showNotification('<?= addslashes($error_message) ?>', 'error');
        <?php endif; ?>

        function showNotification(message, type) {
            const modal = document.getElementById('notificationModal');
            const content = document.getElementById('notificationContent');
            const text = document.getElementById('notificationText');
            const icon = content.querySelector('.notification-icon i');

            // Set message
            text.textContent = message;

            // Set type (success or error)
            content.className = 'notification-content ' + type;
            
            if (type === 'success') {
                icon.className = 'fas fa-check-circle';
            } else {
                icon.className = 'fas fa-exclamation-circle';
            }

            // Show modal
            modal.classList.add('show');

            // Auto-hide after 3 seconds
            setTimeout(() => {
                modal.classList.add('hiding');
                setTimeout(() => {
                    modal.classList.remove('show', 'hiding');
                }, 300);
            }, 3000);
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            let isValid = true;

            // Age validation
            const ageInput = document.getElementById('age');
            const ageError = document.getElementById('age_error');
            const age = parseInt(ageInput.value);

            if (isNaN(age) || age < 1 || age > 120) {
                e.preventDefault();
                ageInput.classList.add('input-error');
                ageError.classList.add('show');
                isValid = false;
            } else {
                ageInput.classList.remove('input-error');
                ageError.classList.remove('show');
            }

            // Phone validation
            const phoneInput = document.getElementById('phone_number');
            const phoneError = document.getElementById('phone_error');
            const phone = phoneInput.value.replace(/\s/g, '');
            const phoneRegex = /^09\d{9}$/;

            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                phoneInput.classList.add('input-error');
                phoneError.classList.add('show');
                isValid = false;
            } else {
                phoneInput.classList.remove('input-error');
                phoneError.classList.remove('show');
            }

            if (!isValid) {
                showNotification('Please correct the errors before submitting.', 'error');
            }
        });

        // Real-time age validation
        document.getElementById('age').addEventListener('input', function() {
            const age = parseInt(this.value);
            const ageError = document.getElementById('age_error');

            if (this.value && (isNaN(age) || age < 1 || age > 120)) {
                this.classList.add('input-error');
                ageError.classList.add('show');
            } else {
                this.classList.remove('input-error');
                ageError.classList.remove('show');
            }
        });

        // Real-time phone validation
        document.getElementById('phone_number').addEventListener('input', function() {
            const phone = this.value.replace(/\s/g, '');
            const phoneError = document.getElementById('phone_error');
            const phoneRegex = /^09\d{9}$/;

            if (this.value && !phoneRegex.test(phone)) {
                this.classList.add('input-error');
                phoneError.classList.add('show');
            } else {
                this.classList.remove('input-error');
                phoneError.classList.remove('show');
            }
        });
    </script>
</body>
</html>