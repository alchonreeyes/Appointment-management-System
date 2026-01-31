<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../public/register.php");
    exit;
}

// 1. ADDED 'birth_date' to the required fields array
$required_fields = ['full_name', 'email', 'password', 'phone_number', 'address', 'gender', 'age', 'occupation', 'birth_date'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        $_SESSION['error'] = "Missing required field: " . $field;
        header("Location: ../public/register.php");
        exit;
    }
    
    if ($field === 'age') {
        if (empty($_POST[$field]) || $_POST[$field] <= 0) {
            $_SESSION['error'] = "Invalid age value.";
            header("Location: ../public/register.php");
            exit;
        }
    } else {
        if (empty(trim($_POST[$field]))) {
            $_SESSION['error'] = "Missing required field: " . $field;
            header("Location: ../public/register.php");
            exit;
        }
    }
}

include '../config/db.php';
require_once __DIR__ . '/../config/encryption_util.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $_SESSION['error'] = "PHPMailer not found.";
    header("Location: ../public/register.php");
    exit;
}

$full_name = trim($_POST['full_name']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$phone_number = trim($_POST['phone_number']);
$address = trim($_POST['address']);
$gender = trim($_POST['gender']);
$age = intval($_POST['age']);
$occupation = trim($_POST['occupation']);
// 2. CAPTURED the birth_date from POST
$birth_date = $_POST['birth_date'];

// ===== ADD THIS ENTIRE VALIDATION BLOCK =====
// Validate birth_date is not empty
if (empty($birth_date)) {
    $_SESSION['error'] = "Birth date is required.";
    header("Location: ../public/register.php");
    exit;
}

// Validate date format (YYYY-MM-DD)
$dateObj = DateTime::createFromFormat('Y-m-d', $birth_date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $birth_date) {
    $_SESSION['error'] = "Invalid birth date format. Please use the date picker.";
    header("Location: ../public/register.php");
    exit;
}

// Check if date is not in the future
if ($dateObj > new DateTime()) {
    $_SESSION['error'] = "Birth date cannot be in the future.";
    header("Location: ../public/register.php");
    exit;
}

// Auto-calculate age from birth_date
$today = new DateTime();
$calculatedAge = $today->diff($dateObj)->y;

// Validate age requirements (16-100 years)
if ($calculatedAge < 16) {
    $_SESSION['error'] = "You must be at least 16 years old to register.";
    header("Location: ../public/register.php");
    exit;
}

if ($calculatedAge > 100) {
    $_SESSION['error'] = "Invalid birth date. Age cannot exceed 100 years.";
    header("Location: ../public/register.php");
    exit;
}

// Use calculated age instead of POST age
$age = intval($calculatedAge);
// ===== END OF VALIDATION BLOCK =====
$digits = preg_replace('/\D+/', '', $phone_number);
if (!preg_match('/^09\d{9}$/', $digits)) {
    $_SESSION['error'] = "Invalid phone number format.";
    header("Location: ../public/register.php");
    exit;
}
$phone_number = $digits;

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $check = $pdo->prepare("SELECT email FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $_SESSION['error'] = "Email already exists.";
        header("Location: ../public/register.php");
        exit;
    }

    $encrypted_full_name = encrypt_data($full_name);
    $encrypted_phone_number = encrypt_data($phone_number);
    $encrypted_address = encrypt_data($address);
    $encrypted_occupation = encrypt_data($occupation);
    // 3. ENCRYPTED the birth_date to match your other PII fields
    $encrypted_birth_date = encrypt_data($birth_date);

    $verification_token = bin2hex(random_bytes(32));
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, address, verification_token, is_verified)
                           VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$encrypted_full_name, $email, $hashedPassword, $encrypted_phone_number, $encrypted_address, $verification_token]);

    $userId = $pdo->lastInsertId();

    // 4. UPDATED the clients INSERT statement to include the birth_date column and value
    $stmt = $pdo->prepare("INSERT INTO clients (user_id, gender, age, occupation, birth_date)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $gender, $age, $encrypted_occupation, $encrypted_birth_date]);

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
        $mail->addAddress($email, $full_name);
        $mail->isHTML(true);
        $mail->Subject = 'Verify your EyeMaster account';
        
        $verification_link = "http://eyemasteropticalclinic.great-site.net/public/verify_email.php?token=" . $verification_token;

        $mail->Body = "
            <div style='font-family:Arial, sans-serif; background:#f8f9fa; padding:20px; border-radius:10px;'>
                <h2 style='color:#004aad;'>Welcome to EyeMaster Clinic!</h2>
                <p>Hi <b>$full_name</b>,</p>
                <p>Thank you for registering! Click the button below to verify your email:</p>
                <div style='text-align:center; margin:30px 0;'>
                    <a href='$verification_link' style='background:#004aad; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                        Verify Email Address
                    </a>
                </div>
                <p style='color:#666; font-size:12px;'>Or copy this link: $verification_link</p>
                <p style='color:#999; font-size:11px;'>This link will expire in 24 hours.</p>
            </div>
        ";

        if (!$mail->send()) {
            throw new Exception("Email sending failed");
        }

        $_SESSION['registration_success'] = "Registration successful! Please check your email and click the verification link to activate your account.";
        header("Location: ../public/login.php");
        exit;

    } catch (Exception $e) {
        try {
            $deleteClient = $pdo->prepare("DELETE FROM clients WHERE user_id = ?");
            $deleteClient->execute([$userId]);
            
            $deleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteUser->execute([$userId]);
        } catch (PDOException $deleteError) {
            error_log("Failed to delete user: " . $deleteError->getMessage());
        }

        error_log("Mailer Error: " . $mail->ErrorInfo);
        $_SESSION['error'] = "Registration failed: Unable to send verification email. Please try again.";
        header("Location: ../public/register.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Registration failed: " . $e->getMessage();
    header("Location: ../public/register.php");
    exit;
}
?>