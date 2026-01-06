<?php
// ============================================
// logout_api.php
// Distribution System - Logout Handler
// Location: http://10.147.17.116/distribution_module/
// ============================================

session_start();

// Get user info before destroying session
$user_name = $_SESSION['user_name'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Log the logout
error_log("User logged out: $user_name (ID: $user_id)");

// Redirect to main system homepage
header("Location: http://10.147.17.30:8000/");
exit();
?>