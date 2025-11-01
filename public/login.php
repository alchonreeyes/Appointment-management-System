<?php include '../actions/login-action.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login</title>
</head>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', sans-serif;
        background: #f8f9fa;
        min-height: 100vh;
    }

    .login-container {
        max-width: 900px;
        margin: 50px auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        border-radius: 20px;
        overflow: hidden;
    }

    .login-image {
        background: linear-gradient(135deg, #ff4b2b 0%, #ff416c 100%);
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        position: relative;
    }

    .login-image h2 {
        font-size: 2.5em;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }

    .login-image p {
        text-align: center;
        line-height: 1.6;
        position: relative;
        z-index: 1;
    }

    .login-form {
        background: white;
        padding: 40px;
    }

    .form-header {
        margin-bottom: 30px;
        text-align: center;
    }

    .form-header h1 {
        color: #ff416c;
        font-size: 1.8em;
        margin-bottom: 10px;
    }

    .input-group {
        margin-bottom: 25px;
    }

    .input-group label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }

    .input-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #eee;
        border-radius: 8px;
        font-size: 16px;
        transition: all 0.3s;
    }

    .input-group input:focus {
        border-color: #ff416c;
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 65, 108, 0.1);
    }

    .remember-forgot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .remember-me {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .remember-me input[type="checkbox"] {
        accent-color: #ff416c;
    }

    .forgot-password {
        color: #ff416c;
        text-decoration: none;
    }

    .login-button {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #ff4b2b 0%, #ff416c 100%);
        border: none;
        border-radius: 8px;
        color: white;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.3s;
    }

    .login-button:hover {
        transform: translateY(-2px);
    }

    .register-link {
        text-align: center;
        margin-top: 20px;
    }

    .register-link a {
        color: #ff416c;
        text-decoration: none;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .login-container {
            grid-template-columns: 1fr;
            margin: 20px;
        }

        .login-image {
            padding: 30px;
        }
    }
</style>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="login-container">
        <div class="login-image">
            <h2>Welcome Back!</h2>
            <p>Log in to access your eye care services and manage your appointments with ease.</p>
        </div>
        
        <div class="login-form">
            <div class="form-header">
                <h1>Client Login</h1>
                <p>Please enter your credentials</p>
            </div>

            <form action="../actions/login-action.php" method="POST">
                <div class="input-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="login-button">Log In</button>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>
</body>
</html>
