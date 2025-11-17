<?php
// Start the session
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to home.php
header("Location: home.php");
exit();
?>