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
// 3. HANDLE PROFILE UPDATE (PDO + ENCRYPTION)
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
    
    // ENCRYPT bago i-save sa database
    $encrypted_full_name = encrypt_data($full_name);
    $encrypted_phone_number = encrypt_data($phone_number);
    $encrypted_address = encrypt_data($address);

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
            $birth_date, $gender, $age, $suffix, $occupation, $user_id
        ]);
        
        $pdo->commit();
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = "Update failed: " . $e->getMessage();
    }
}

// =======================================================
// 4. HANDLE PASSWORD CHANGE
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
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
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}

// =======================================================
// 5. FETCH AND DECRYPT USER DATA (DITO ANG SOLUSYON)
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
    // Binabago natin ang "gibberish" phone number para maging readable numbers
    $user['full_name']    = decrypt_data($user_encrypted['full_name'] ?? '');
    $user['phone_number'] = decrypt_data($user_encrypted['phone_number'] ?? '');
    $user['address']      = decrypt_data($user_encrypted['address'] ?? ''); 
    $user['occupation']   = decrypt_data($user_encrypted['occupation'] ?? '');  
 
} catch (Exception $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
}

// --- 6. HANDLE MESSAGES & INITIALS ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
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
                            <input type="text" name="age" value="<?= htmlspecialchars($user['age']) ?>" inputmode="numeric" pattern="[0-9]*" maxlength="3">
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
</body>
</html>