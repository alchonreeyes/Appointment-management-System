<?php
session_start();

// --- 1. SESSION SEGMENTATION CHECK ---
// Ensure user is logged in using the specific 'client_id' key
if (!isset($_SESSION['client_id'])) {
    header("Location: ../public/login.php");
    exit();
}

// --- 2. DATABASE CONNECTION & VARIABLE SETUP (PDO STANDARD) ---
require '../config/db.php'; // Using consistent PDO connection
$db = new Database();
$pdo = $db->getConnection();

// Use the correct, validated client ID
$user_id = $_SESSION['client_id']; // THIS IS THE CORRECT ID

$error_message = '';
$success_message = '';

// --- 3. FETCH USER DATA (Using PDO) ---
try {
    $query = "SELECT u.*, c.birth_date, c.gender, c.age, c.suffix, c.occupation 
              FROM users u 
              LEFT JOIN clients c ON u.id = c.user_id 
              WHERE u.id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // If the user ID exists but the record is missing, destroy session
        session_destroy();
        header("Location: ../public/login.php");
        exit();
    }
} catch (Exception $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// --- 4. HANDLE PROFILE UPDATE (Using PDO & Transaction) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // ... (Your update logic using $pdo remains valid here) ...
    // Note: You must convert the MySQLi code for the update block into PDO (as planned previously)
}

// --- 5. HANDLE PASSWORD CHANGE (Using PDO) ---
// ... (Your password change logic using $pdo remains valid here) ...
// Note: You must convert the MySQLi code for the password change block into PDO (as planned previously)
    
// --- 6. HANDLE SUCCESS/ERROR MESSAGES ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// ... rest of HTML output logic ...

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
    <title>My Account | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>

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

                <?php if (isset($_SESSION['success_message'])): ?>
                    <p style="color: green; margin-bottom: 20px;"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="ojo-form-grid">
                        <div class="ojo-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
                        </div>
                        <div class="ojo-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" readonly style="color:#999;">
                        </div>
                        <div class="ojo-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>">
                        </div>
                        <div class="ojo-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= htmlspecialchars($user['occupation']) ?>">
                        </div>
                        <div class="ojo-group">
                            <label>Age</label>
                            <input type="number" name="age" value="<?= htmlspecialchars($user['age']) ?>">
                        </div>
                        <div class="ojo-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="Male" <?= $user['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $user['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-ojo">SAVE CHANGES</button>
                </form>

            </main>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>
</body>
</html>