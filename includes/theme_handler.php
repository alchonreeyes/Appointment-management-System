<?php
// Get user's theme preference
function getUserTheme($user_id, $conn) {
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    }
    
    $stmt = $conn->prepare("SELECT theme_preference FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && isset($user['theme_preference'])) {
            $_SESSION['theme'] = $user['theme_preference'];
            return $user['theme_preference'];
        }
    }
    
    return 'light'; // Default
}

$current_theme = getUserTheme($_SESSION['user_id'], $conn);
?>