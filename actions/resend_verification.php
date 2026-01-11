<?php
session_start();
include '../config/db.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['email'])) {
    header("Location: ../public/login.php");
    exit;
}

if (isset($_POST['resend'])) {
    $email = $_SESSION['email'];
    $new_code = rand(100000, 999999);

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        // Update code in database
        $stmt = $pdo->prepare("UPDATE users SET verification_code = ? WHERE email = ?");
        $stmt->execute([$new_code, $email]);

        // Get user name
        $user = $pdo->prepare("SELECT full_name FROM users WHERE email = ?");
        $user->execute([$email]);
        $userData = $user->fetch(PDO::FETCH_ASSOC);

        // Send email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'alchonreyez@gmail.com';
        $mail->Password = 'urwbzscfmaynltzx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('alchonreyez@gmail.com', 'EyeMaster Clinic');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'New Verification Code';
        $mail->Body = "<h1>$new_code</h1><p>Your new verification code.</p>";

        $mail->send();
        $_SESSION['success'] = "New code sent to your email!";

    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to resend code.";
    }

    header("Location: ../public/verify_email.php");
    exit;
}