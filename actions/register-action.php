<?php
include '../config/db.php';
session_start();

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If Composer autoload doesn't work, include PHPMailer manually
require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

if (isset($_POST['signup'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        // 🔹 Check if email already exists
        $check = $pdo->prepare("SELECT email FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists.";
            header("Location: ../public/register.php");
            exit;
        }

        // 🔹 Generate 6-digit verification code
        $verification_code = rand(100000, 999999);

        // 🔹 Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // 🔹 Insert into users (unverified)
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, address, verification_code, is_verified)
                               VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$full_name, $email, $hashedPassword, $phone_number, $address, $verification_code]);

        $userId = $pdo->lastInsertId();

        // 🔹 Insert into clients (optional)
        $stmt = $pdo->prepare("INSERT INTO clients (user_id, birth_date, gender, age, suffix, occupation)
                               VALUES (?, NULL, NULL, NULL, NULL, NULL)");
        $stmt->execute([$userId]);

        // 🔹 Send verification email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'alchonreyez@gmail.com'; // your Gmail    
            $mail->Password = 'urwbzscfmaynltzx'; // your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('alchonreyez@gmail.com', 'EyeMaster Clinic');
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'Verify your EyeMaster account';
            $mail->Body = "
                <div style='font-family:Arial, sans-serif; background:#f8f9fa; padding:20px; border-radius:10px;'>
                    <h2 style='color:#004aad;'>Welcome to EyeMaster Clinic!</h2>
                    <p>Hi <b>$full_name</b>,</p>
                    <p>Thank you for registering! Please verify your email using the code below:</p>
                    <h1 style='color:#004aad; text-align:center;'>$verification_code</h1>
                    <p style='text-align:center;'>Enter this code on the verification page to activate your account.</p>
                    <p>Thank you,<br><b>EyeMaster Clinic Team</b></p>
                </div>
            ";

            $mail->send();

            $_SESSION['email'] = $email;
            $_SESSION['success'] = "A verification code has been sent to your email.";
            header("Location: ../public/verify_email.php");
            exit;

        } catch (Exception $e) {
            $_SESSION['error'] = "Mailer Error: " . $mail->ErrorInfo;
            error_log("Mailer Error: " . $mail->ErrorInfo);
            header("Location: ../public/register.php");
            exit;
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header("Location: ../public/register.php");
        exit;
    }
}
?>
