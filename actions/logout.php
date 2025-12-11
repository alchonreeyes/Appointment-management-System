// actions/logout.php (ROBUST AND SECURE VERSION)
<?php
// 1. Start the session to prepare for destruction
session_start();

// 2. Clear all session variables
$_SESSION = array();

// 3. Destroy the session cookie completely (CRITICAL STEP)
// This ensures the browser cannot reuse the old PHPSESSID
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session file on the server
session_destroy();

// 5. Redirect to a secure public entry point (Client Home)
header("Location: ../public/home.php"); 
exit();
?>