<?php 
session_start();

// <<< CRITICAL SESSION LOCK (Rule A) >>>
if (isset($_SESSION['client_id'])) {
    // Redirect authenticated client to their home page
    header("Location: home.php"); 
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        /* Page Background */
        body {
            background-color: #FFF0F0; /* Light pink from your screenshot */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Layout Wrapper to center the card between Nav and Footer */
        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        /* The Card Container */
        .auth-card {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(211, 69, 53, 0.15);
            overflow: hidden;
        }

        /* Card Header (Red Area) */
        .card-header {
            background-color: #D94032; /* The specific red tone */
            color: white;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden; /* Clips the decorative circles */
        }

        /* Decorative background circles in header */
        .header-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .shape-1 { width: 200px; height: 200px; top: -100px; left: -50px; }
        .shape-2 { width: 150px; height: 150px; bottom: -50px; right: -20px; }

        /* Icon Circle */
        .header-icon-circle {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
            position: relative; /* To sit above shapes */
            z-index: 2;
        }

        .card-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }

        .card-header p {
            margin: 10px 0 0;
            font-size: 14px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Card Body (White Area) */
        .card-body {
            padding: 40px;
        }

        .separator {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .separator::before, .separator::after {
            content: "";
            display: block;
            height: 1px;
            background: #eee;
            position: absolute;
            top: 50%;
            width: 30%;
        }
        .separator::before { left: 0; }
        .separator::after { right: 0; }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group label span {
            color: #D94032;
        }

        /* Input with Icon Wrapper */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper svg {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            width: 18px;
            height: 18px;
        }

        .form-control {
            width: 100%;
            padding: 12px 12px 12px 45px; /* Left padding for icon */
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box; /* Important for padding */
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #D94032;
            outline: none;
        }

        /* Button Styling */
        .btn-submit {
            background-color: #D94032;
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background-color: #b93529;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link:hover {
            color: #D94032;
        }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="auth-card">
        <div class="card-header">
            <div class="header-shape shape-1"></div>
            <div class="header-shape shape-2"></div>
            
            <div class="header-icon-circle">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
            <h1>Forgot Password</h1>
            <p>Join us for better eye care experience</p>
        </div>

        <div class="card-body">
            <div class="separator">Enter details to reset</div>

            <form action="../actions/forgot-password-action.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address <span>*</span></label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        
                        <input type="email" id="email" name="email" class="form-control" required placeholder="ExampleName@gmail.com">
                    </div>
                </div>

                <button type="submit" name="send_reset" class="btn-submit">Send Reset Link</button>
                
                <a href="login.php" class="back-link">Back to Login</a>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>