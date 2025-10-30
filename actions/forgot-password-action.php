<?php
session_start();
include '../config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

if (isset($_POST['send_reset'])) {
    $email = trim($_POST['email']);
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate token and expiry (valid for 15 minutes)
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

        $update = $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $update->execute([$token, $expiry, $email]);

        // Prepare email
        $resetLink = "http://localhost/appointment-management-system/public/reset_password.php?token=$token";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alchonreyez@gmail.com';
            $mail->Password = 'fojwnzlcxrkqquhs';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->setFrom('alchonreyez@gmail.com', 'EyeMaster Clinic');
            $mail->addAddress($email, $user['full_name']);
            $mail->isHTML(true);
            $mail->Subject = "Reset your password";
            $mail->Body = "
                <h2>Password Reset Request</h2>
                <p>Click the link below to reset your password:</p>
                <a href='$resetLink'>Reset Password</a>
                <p>This link will expire in 15 minutes.</p>
            ";
            $mail->send();

            $_SESSION['success'] = "Reset link sent to your email!";
            header("Location: ../public/login.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = "Email failed to send. {$mail->ErrorInfo}";
            header("Location: ../public/forgot_password.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "No account found with that email.";
        header("Location: ../public/forgot_password.php");
        exit;
    }
}
?>
