<?php
include '../actions/login-action.php';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    <div class="login-wrapper"> 

        <form class="login-form" action="../actions/login-action.php" method="POST">
            <h1>Login</h1>            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter Your Email..." autocomplete="on" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter Your Password..." autocomplete="off" required>
            </div>
            <button type="submit" name="login">Login</button>
            
            <p class="register-link" style="color: black;">
            Don't have an account? <a href="register.php" style="color: blue;">Sign Up</a>
            </p>
    </div>
    </form>
    <?php include '../includes/footer.php' ?>
</body>
</html>