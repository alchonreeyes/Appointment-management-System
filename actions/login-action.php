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
        header("Location: ../public/index.php");
        exit;
    } else {
        header("Location: ../public/login.php?error=1");
        exit;
    }
}
