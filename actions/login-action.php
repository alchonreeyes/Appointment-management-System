<?php
session_start();
include '../config/db.php';

$db = new Database();
$pdo = $db->getConnection();

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if($user['is_verified'] == 0){
            $_SESSION['error'] = 'please verify your email before logging in';
            header('Location: ../public/login.php');
            exit;
        }

         if ($user['role'] === 'admin' || $user['role'] === 'staff') {
            header('Location: ../admin/dashboard.php');
            exit;
        } else {
            // Redirect based on roleE
            header("Location: ../public/home.php");
            exit;
        }

    } else {
        header("Location: ../public/login.php?error=1");
        exit;
    }
}       