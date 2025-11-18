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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        /* Page Background */
        body {
            background-color: #FFF0F0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Layout Wrapper */
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

        /* Card Header */
        .card-header {
            background-color: #D94032;
            color: white;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative circles */
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
            position: relative;
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

        /* Card Body */
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

        /* Input Wrapper for Icon */
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
            padding: 12px 12px 12px 45px; /* Space for icon */
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: #D94032;
            outline: none;
        }

        /* Button */
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
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
            </div>
            <h1>Reset Password</h1>
            <p>Create a new strong password for your account</p>
        </div>

        <div class="card-body">
            <div class="separator">Set new credentials</div>

            <form action="../actions/reset-password-action.php" method="POST" class="login-form">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        
                        <input type="password" name="new_password" class="form-control" required placeholder="Enter new password...">
                    </div>
                </div>

                <button type="submit" name="reset_password" class="btn-submit">Update Password</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>