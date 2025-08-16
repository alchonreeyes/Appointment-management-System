<?php
include '../config/db.php';
session_start();

if (isset($_POST['signup'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);

    try {
        $db = new Database();
        $pdo = $db->getConnection();

        // hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone_number, address) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $email, $hashedPassword, $phone_number, $address]);

        $_SESSION['success'] = "Account created successfully. Please login.";
        header("Location: ../public/login.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header("Location: ../public/register.php");
        exit;
    }
}
