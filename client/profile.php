<?php
session_start();


// Database connection
require '../config/db_mysqli.php'; // Adjust path as needed

$user_id = $_SESSION['user_id'];

// Fetch user data with client details
$query = "SELECT u.*, c.birth_date, c.gender, c.age, c.suffix, c.occupation 
          FROM users u 
          LEFT JOIN clients c ON u.id = c.user_id 
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user exists
if (!$user) {
    header("Location: ../public/login.php");
    exit();
}

// Ensure all fields have default values to avoid null warnings
$user['full_name'] = $user['full_name'] ?? '';
$user['email'] = $user['email'] ?? '';
$user['phone_number'] = $user['phone_number'] ?? '';
$user['address'] = $user['address'] ?? '';
$user['birth_date'] = $user['birth_date'] ?? '';
$user['gender'] = $user['gender'] ?? '';
$user['age'] = $user['age'] ?? '';
$user['suffix'] = $user['suffix'] ?? '';
$user['occupation'] = $user['occupation'] ?? '';    

// Get user initials for avatar
$name_parts = explode(' ', $user['full_name']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user['full_name'], 0, 2));
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $suffix = trim($_POST['suffix']);
    $occupation = trim($_POST['occupation']);
    
    // Update users table
    $update_user = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE id = ?";
    $stmt_user = $conn->prepare($update_user);
    $stmt_user->bind_param("ssssi", $full_name, $email, $phone_number, $address, $user_id);
    
    // Check if client record exists
    $check_client = "SELECT client_id FROM clients WHERE user_id = ?";
    $stmt_check = $conn->prepare($check_client);
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $client_exists = $stmt_check->get_result()->fetch_assoc();
    
    if ($client_exists) {
        // Update existing client record
        $update_client = "UPDATE clients SET birth_date = ?, gender = ?, age = ?, suffix = ?, occupation = ? WHERE user_id = ?";
        $stmt_client = $conn->prepare($update_client);
        $stmt_client->bind_param("ssissi", $birth_date, $gender, $age, $suffix, $occupation, $user_id);
        $stmt_client->execute();
    } else {
        // Insert new client record
        $insert_client = "INSERT INTO clients (user_id, birth_date, gender, age, suffix, occupation) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_client = $conn->prepare($insert_client);
        $stmt_client->bind_param("ississ", $user_id, $birth_date, $gender, $age, $suffix, $occupation);
        $stmt_client->execute();
    }
    
    if ($stmt_user->execute()) {
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $error_message = "Failed to update profile.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password_hash'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt_pass = $conn->prepare($update_password);
            $stmt_pass->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt_pass->execute()) {
                $_SESSION['success_message'] = "Password changed successfully!";
                header("Location: profile.php");
                exit();
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="./style/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="link-section">
        <a href="../public/home.php"><i class="fa-solid fa-house"></i></a>
        <a href="#" class="side-toggle"><i class="fa-solid fa-bars"></i></a>
    </div>

    <div class="profile">
        <div class="profile-details">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <div class="avatar-circle">
                        <?php echo $initials; ?>
                    </div>
                </div>
                <div class="profile-header-info">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="user-id">ID: <?php echo $user['id']; ?></p>
                    <span class="user-badge">CLIENT</span>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Information Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Profile Information
                </h2>

                <form method="POST" action="" class="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">FULL NAME <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="suffix">SUFFIX</label>
                            <input type="text" id="suffix" name="suffix" 
                                   value="<?php echo htmlspecialchars($user['suffix']); ?>" 
                                   placeholder="Jr., Sr., III, etc.">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">EMAIL ADDRESS <span class="required">*</span></label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone_number">PHONE NUMBER</label>
                            <input type="text" id="phone_number" name="phone_number" 
                                   value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date">BIRTH DATE</label>
                            <input type="date" id="birth_date" name="birth_date" 
                                   value="<?php echo htmlspecialchars($user['birth_date']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="age">AGE</label>
                            <input type="number" id="age" name="age" 
                                   value="<?php echo htmlspecialchars($user['age']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">GENDER</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo $user['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $user['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo $user['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="occupation">OCCUPATION</label>
                            <input type="text" id="occupation" name="occupation" 
                                   value="<?php echo htmlspecialchars($user['occupation']); ?>">
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">ADDRESS</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fa-solid fa-pen-to-square"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Section -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fa-solid fa-lock"></i>
                    Change Password
                </h2>

                <form method="POST" action="" class="profile-form">
                    <div class="form-group">
                        <label for="current_password">CURRENT PASSWORD <span class="required">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" required>
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">NEW PASSWORD <span class="required">*</span></label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" required>
                                <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">CONFIRM NEW PASSWORD <span class="required">*</span></label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fa-solid fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Logout Section -->
            <div class="profile-section logout-section">
                <a href="../public/logout.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>