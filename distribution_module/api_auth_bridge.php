<?php
// api_auth_bridge.php
session_start();

// Clear any existing sessions to prevent conflicts
$_SESSION = array();

// Get admin data from API (you might receive this via POST/GET/URL parameters)
$admin_id = $_POST['AdminID'] ?? $_GET['AdminID'] ?? $_REQUEST['AdminID'] ?? null;
$admin_name = $_POST['FullName'] ?? $_GET['FullName'] ?? $_REQUEST['FullName'] ?? null;
$admin_email = $_POST['Email'] ?? $_GET['Email'] ?? $_REQUEST['Email'] ?? null;
$admin_role = $_POST['Role'] ?? $_GET['Role'] ?? $_REQUEST['Role'] ?? 'admin';
$admin_phone = $_POST['Phone'] ?? $_GET['Phone'] ?? $_REQUEST['Phone'] ?? '';

if ($admin_id && $admin_name && $admin_email) {
    // Clear any existing volunteer sessions
    unset($_SESSION['volunteer_id']);
    unset($_SESSION['volunteer_name']);
    unset($_SESSION['volunteer_email']);
    
    // Set admin session with BOTH naming conventions for compatibility
    $_SESSION['AdminID'] = $admin_id;
    $_SESSION['FullName'] = $admin_name;
    $_SESSION['Email'] = $admin_email;
    $_SESSION['Role'] = $admin_role;
    $_SESSION['Phone'] = $admin_phone;
    
    // Also set the standard naming for your distribution system
    $_SESSION['user_id'] = $admin_id;
    $_SESSION['user_name'] = $admin_name;
    $_SESSION['user_email'] = $admin_email;
    $_SESSION['user_role'] = $admin_role;
    $_SESSION['admin_phone'] = $admin_phone;
    $_SESSION['admin_api_verified'] = true;
    $_SESSION['auth_time'] = time();
    
    // Redirect to dashboard
    header("Location: distribution_main.php");
    exit;
} else {
    // Invalid data - redirect to login
    error_log("API Auth Failed: Missing data. AdminID: $admin_id, FullName: $admin_name, Email: $admin_email");
    header("Location: http://10.147.17.30:8000/login.php?error=invalid_api_auth");
    exit;
}
?>
