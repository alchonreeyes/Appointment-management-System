<?php
include '../config/db.php';
session_start();

if (!isset($_GET['token'])) {
    echo "Invalid request.";
    exit;
}

$token = $_GET['token'];
$db = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Invalid or expired token.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="login-wrapper">
    <form action="../actions/reset-password-action.php" method="POST" class="login-form">
        <h1>Reset Password</h1>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required placeholder="Enter new password...">
        </div>
        <button type="submit" name="reset_password">Update Password</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
