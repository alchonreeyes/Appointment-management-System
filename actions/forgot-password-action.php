<?php
session_start();
include '../config/db.php';
require_once '../config/SecurityHelper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

if (isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $_SESSION['error'] = "Email is required.";
        header("Location: ../public/forgot_password.php");
        exit;
    }
    
    $db = new Database();
    $pdo = $db->getConnection();
    
    // ✅ SECURITY CHECK: Rate limiting
    $security = new SecurityHelper($pdo);
    $rateCheck = $security->checkRateLimit($email, 'password_reset');
    
    if (!$rateCheck['allowed']) {
        if ($rateCheck['reason'] === 'blocked') {
            $_SESSION['error'] = "⛔ Your IP has been blocked due to suspicious activity. Please contact support.";
        } else {
            $_SESSION['error'] = $rateCheck['message'];
        }
        header("Location: ../public/forgot_password.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // ✅ Check if email is verified
        if ($user['is_verified'] == 0) {
            $_SESSION['error'] = "Please verify your email first before resetting password.";
            $_SESSION['unverified_email'] = $email;
            header("Location: ../public/forgot_password.php?unverified=1&email=" . urlencode($email));
            exit;
        }
        
        // Generate token and expiry (valid for 15 minutes)
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $update = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $update->execute([$token, $expiry, $email]);

        // Prepare email with your live domain
        $resetLink = "http://eyemasteropticalclinic.great-site.net/public/reset_password.php?token=$token";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alchonreyez@gmail.com';
            $mail->Password = 'sdgpjusyveqfzxti';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('alchonreyez@gmail.com', 'EyeMaster Clinic');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Reset your password - EyeMaster Clinic";
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8f9fa; padding: 20px; border-radius: 10px;'>
                    <div style='background: #D94032; color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                        <h2 style='margin: 0;'>Password Reset Request</h2>
                    </div>
                    <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
                        <p>Hello,</p>
                        <p>We received a request to reset your password. Click the button below to proceed:</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='$resetLink' style='background: #D94032; color: white; padding: 14px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600;'>
                                Reset Password
                            </a>
                        </div>
                        <p style='color: #666; font-size: 13px;'>Or copy this link: <br><code style='background: #f1f1f1; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-top: 5px;'>$resetLink</code></p>
                        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                        <p style='color: #999; font-size: 12px;'><strong>⏰ This link will expire in 15 minutes.</strong></p>
                        <p style='color: #999; font-size: 12px;'>If you didn't request this, please ignore this email. Your password will remain unchanged.</p>
                    </div>
                </div>
            ";
            
            $mail->send();

            $_SESSION['success'] = "✅ Reset link sent to your email! Check your inbox.";
            header("Location: ../public/login.php");
            exit;

        } catch (Exception $e) {
            error_log("Password reset email error: " . $mail->ErrorInfo);
            $_SESSION['error'] = "Failed to send email. Please try again later.";
            header("Location: ../public/forgot_password.php");
            exit;
        }
    } else {
        // ⚠️ SECURITY: Don't reveal if email exists or not
        $_SESSION['success'] = "✅ If an account exists with that email, a reset link has been sent.";
        header("Location: ../public/login.php");
        exit;
    }
}
?>