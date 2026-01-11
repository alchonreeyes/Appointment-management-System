<?php
// actions/register-action.php
session_start();
include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php'; // Correct path to utility
require_once '../vendor/autoload.php'; // Load PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


if (isset($_POST['signup'])) {
    // --- 1. COLLECT AND VALIDATE INPUT ---
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $gender = trim($_POST['gender']);
    $age = intval($_POST['age']);
    $occupation = trim($_POST['occupation']);

    // Sa loob ng IF block kung saan mo nakuha ang user data:
    if ($user_data_from_db) {
    // I-decrypt ang data bago ito gamitin sa HTML inputs
    $client_profile_data['full_name'] = decrypt_data($user_data_from_db['full_name']);
    $client_profile_data['phone_number'] = decrypt_data($user_data_from_db['phone_number']);
}

    // --- (Keep your phone number validation logic here) ---
$digits = preg_replace('/\D+/', '', $phone_number);
// Validate format first: 09XXXXXXXXX (11 digits)
if (!preg_match('/^09\d{9}$/', $digits)) {
    $_SESSION['error'] = "Invalid phone number format. Must be 09XXXXXXXXX (11 digits).";
    header("Location: ../public/register.php");
    exit;
}
// Use cleaned number and encrypt for storage (variable used later when inserting)
$phone_number = $digits;
$encrypted_phone_number = encrypt_data($phone_number);

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

        // --- 2. ENCRYPT SENSITIVE DATA ---
        $encrypted_full_name = encrypt_data($full_name);
        $encrypted_phone_number = encrypt_data($phone_number);
        $encrypted_address = encrypt_data($address);
        $encrypted_occupation = encrypt_data($occupation);

        // 🔹 Generate 6-digit verification code
        $verification_code = rand(100000, 999999);

        // 🔹 Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // --- 3. INSERT INTO USERS TABLE ---
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, address, verification_code, is_verified)
                               VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$encrypted_full_name, $email, $hashedPassword, $encrypted_phone_number, $encrypted_address, $verification_code]);

        $userId = $pdo->lastInsertId();

        // --- 4. INSERT INTO CLIENTS TABLE (Profile Data) ---
        $stmt = $pdo->prepare("INSERT INTO clients (user_id, gender, age, occupation)
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $gender, $age, $encrypted_occupation]);

       // --- 5. SEND VERIFICATION EMAIL ---
$mail = new PHPMailer(true); 
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'alchonreyez@gmail.com'; 
    $mail->Password = 'urwbzscfmaynltzx';
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

    // ✅ Success - Email sent
    $_SESSION['email'] = $email;
    $_SESSION['success'] = "A verification code has been sent to your email.";
    header("Location: ../public/verify_email.php");
    exit;

} catch (Exception $e) {
    // ❌ Email failed - DELETE the user account
    $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteUser->execute([$userId]);
    
    $deleteClient = $pdo->prepare("DELETE FROM clients WHERE user_id = ?");
    $deleteClient->execute([$userId]);

    error_log("Mailer Error: " . $mail->ErrorInfo);
    $_SESSION['error'] = "Registration failed: Could not send verification email. Please check your email and try again.";
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