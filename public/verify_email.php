<?php
include '../config/db.php';
session_start();

if (!isset($_SESSION['email'])) {
    header("Location: register.php");
    exit;
}

if (isset($_POST['verify'])) {
    $code = trim($_POST['verification_code']);
    $email = $_SESSION['email'];

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND verification_code = ?");
        $stmt->execute([$email, $code]);

        if ($stmt->rowCount() > 0) {
            // ✅ Mark as verified
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE email = ?");
            $update->execute([$email]);

            $_SESSION['success'] = "Email verified successfully. You can now log in.";
            unset($_SESSION['email']);
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error'] = "Invalid verification code. Please try again.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error verifying email: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link rel="stylesheet" href="../assets/verify.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="verify-wrapper">
        <form method="POST" class="verify-form">
            <h2>Verify Your Email</h2>
            <p>We’ve sent a 6-digit code to your email. Enter it below to verify your account.</p>

            <input type="text" name="verification_code" placeholder="Enter verification code" maxlength="6" required>

            <button type="submit" name="verify">Verify Email</button>

            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
            <?php elseif (isset($_SESSION['success'])): ?>
                <p class="success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
            <?php endif; ?>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
