<?php
// ============================================
// bridge_to_distribution.php
// Main System → Distribution System Bridge
// UPDATED WITH CORRECT IP: 10.147.17.154:8000
// ============================================

session_start();

// Check if user is admin in main system
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: login.php");
    exit();
}

// Get the current admin ID and details
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin User';
$admin_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'admin@disasterrelief.org';

// Create a secure session token
$session_token = bin2hex(random_bytes(32));
$timestamp = time();

// Store token in session
$_SESSION['distribution_token'] = $session_token;
$_SESSION['distribution_token_time'] = $timestamp;

// CORRECTED URL - Using your actual distribution system IP
$distribution_url = "http://10.147.17.154:8000/distribution_module/verify_admin_session.php?" . http_build_query([
    'admin_id' => $admin_id,
    'admin_name' => urlencode($admin_name),
    'admin_email' => urlencode($admin_email),
    'token' => $session_token,
    'timestamp' => $timestamp,
    'source' => 'main_system_bridge'
]);

// Log the bridge attempt
error_log("Bridge created for admin ID: $admin_id, Name: $admin_name");
error_log("Redirecting to: $distribution_url");

// Redirect
header("Location: " . $distribution_url);
exit();
?>