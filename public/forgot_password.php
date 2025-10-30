<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="login-wrapper">
    <form action="../actions/forgot-password-action.php" method="POST" class="login-form">
        <h1>Forgot Password</h1>
        <p>Enter your email and we’ll send a password reset link.</p>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" name="email" required placeholder="Enter your email...">
        </div>
        <button type="submit" name="send_reset">Send Reset Link</button>
        <p><a href="login.php">Back to Login</a></p>
    </form>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
