<?php
session_start();
include '../config/db.php';

if (isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password'];

    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);

        $update = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $update->execute([$hashed, $user['id']]);

        $_SESSION['success'] = "Password updated successfully!";
        header("Location: ../public/login.php");
        exit;
    } else {
        $_SESSION['error'] = "Invalid or expired token.";
        header("Location: ../public/reset_password.php?token=$token");
        exit;
    }
}
?>
