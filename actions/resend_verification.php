<?php
session_start();
header('Content-Type: application/json');

include '../config/db.php';
require_once '../config/SecurityHelper.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // ✅ SECURITY CHECK: Rate limiting
    $security = new SecurityHelper($pdo);
    $rateCheck = $security->checkRateLimit($email, 'resend_verification');
    
    if (!$rateCheck['allowed']) {
        echo json_encode([
            'success' => false, 
            'message' => $rateCheck['message'],
            'blocked' => $rateCheck['reason'] === 'blocked'
        ]);
        exit;
    }
    
    // Check if user exists and is unverified
    $stmt = $pdo->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // ⚠️ SECURITY: Don't reveal if email exists or not
        echo json_encode([
            'success' => true, 
            'message' => 'If this email is registered, a verification link has been sent.'
        ]);
        exit;
    }
    
    if ($user['is_verified'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Email already verified']);
        exit;
    }
    
    // Generate new token
    $verification_token = bin2hex(random_bytes(32));
    
    // Update token in database
    $updateStmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
    $updateStmt->execute([$verification_token, $user['id']]);
    
    // Send email
    $mail = new PHPMailer(true);
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
    $mail->Subject = 'Verify your EyeMaster account';
    
    $verification_link = "http://localhost/appointment-management-system/public/verify_email.php?token=" . $verification_token;
    
    $mail->Body = "
        <div style='font-family:Arial, sans-serif; background:#f8f9fa; padding:20px; border-radius:10px;'>
            <h2 style='color:#004aad;'>Verify Your Email</h2>
            <p>Click the button below to verify your email:</p>
            <div style='text-align:center; margin:30px 0;'>
                <a href='$verification_link' style='background:#004aad; color:white; padding:12px 30px; text-decoration:none; border-radius:5px; display:inline-block;'>
                    Verify Email Address
                </a>
            </div>
            <p style='color:#666; font-size:12px;'>Or copy this link: $verification_link</p>
        </div>
    ";
    
    $mail->send();
    
    echo json_encode(['success' => true, 'message' => 'Verification email sent successfully']);
    
} catch (Exception $e) {
    error_log("Resend verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again later.']);
}
?>