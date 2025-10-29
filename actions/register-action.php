<?php
include '../config/db.php';
session_start();

// Import PHPMailer (make sure you've installed it via Composer or manual include)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // adjust path if needed

if (isset($_POST['signup'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        // Check if email exists
        $check = $pdo->prepare("SELECT email FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists.";
            header("Location: ../public/register.php");
            exit;
        }

        // Generate verification code
        $verification_code = rand(100000, 999999);

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert into users (unverified)
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, address, verification_code, is_verified)
                               VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$full_name, $email, $hashedPassword, $phone_number, $address, $verification_code]);

        $userId = $pdo->lastInsertId();

        // Insert into clients
        $stmt = $pdo->prepare("INSERT INTO clients (user_id, birth_date, gender, age, suffix, occupation)
                               VALUES (?, NULL, NULL, NULL, NULL, NULL)");
        $stmt->execute([$userId]);

        // Send verification email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'youremail@gmail.com'; // your Gmail
            $mail->Password = 'your-app-password'; // generated from Google account (App password)
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('youremail@gmail.com', 'EyeMaster Clinic');
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'Verify your email address';
            $mail->Body = "
                <h2>Welcome to EyeMaster Clinic!</h2>
                <p>Your verification code is:</p>
                <h3 style='color:#004aad;'>$verification_code</h3>
                <p>Please enter this code to verify your email.</p>
            ";

            $mail->send();

            $_SESSION['email'] = $email;
            header("Location: ../public/verify_email.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Could not send verification email. Error: {$mail->ErrorInfo}";
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
