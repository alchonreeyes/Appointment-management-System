<?php
include '../config/db.php';
session_start();

// ✅ Check if token exists in URL
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = "Invalid verification link.";
    header("Location: login.php");
    exit;
}

$token = $_GET['token'];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // ✅ Find user by token
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ? AND is_verified = 0");
    $stmt->execute([$token]);

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ✅ Mark as verified and clear token
        $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        // ✅ Use the correct session variable name that login.php expects
        $_SESSION['verification_success'] = "Email verified successfully! You can now log in.";
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['error'] = "Invalid or expired verification link. Please register again.";
        header("Location: login.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Verification error: " . $e->getMessage();
    header("Location: login.php");
    exit;
}
?>