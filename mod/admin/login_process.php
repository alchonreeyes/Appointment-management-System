<?php
// FILE: admin/login_process.php
session_name("ADMIN_SESSION"); // Pangalanan ang session
session_start();

include '../config/db.php'; // Siguraduhin tama ang path sa database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $db = new Database();
    $conn = $db->getConnection();

    // 1. Check Admin Credentials
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Verify Password (Plain text check base sa SQL mo)
    if ($user && $user['password'] === $password) {
        
        // SET SESSION VARIABLES
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['admin_name'] = $user['name'];

        // Redirect sa Profile
        header("Location: profile.php");
        exit;

    } else {
        // Mali ang password - Ibalik sa Login Page
        header("Location: login.php?error=Incorrect email or password");
        exit;
    }
}
?>