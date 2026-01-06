<?php
// ============================================
// verify_admin_session.php
// Distribution System - Verifies Main System Admin
// Location: http://10.147.17.116/distribution_module/
// ============================================

session_start();

// API URL for admin verification
$ADMIN_API_URL = 'http://10.147.17.30:8000/api_admin.php'; // This is correct

// Check for required parameters
if (!isset($_GET['admin_id']) || !isset($_GET['token'])) {
    die("Missing authentication parameters. Please login from main system.");
}

$admin_id = intval($_GET['admin_id']);
$token = $_GET['token'];
$admin_name = isset($_GET['admin_name']) ? urldecode($_GET['admin_name']) : 'Admin User';
$admin_email = isset($_GET['admin_email']) ? urldecode($_GET['admin_email']) : 'admin@disasterrelief.org';
$timestamp = isset($_GET['timestamp']) ? intval($_GET['timestamp']) : time();
$current_time = time();

// Validate token expiry (10 minutes for safety)
if (($current_time - $timestamp) > 600) {
    die("Session expired. Please login again from main system.");
}

// Function to fetch admin data from API
function fetchAdminDataFromAPI() {
    $url = 'http://10.147.17.30:8000/api_admin.php';
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            error_log("API call failed: " . ($error['message'] ?? 'Unknown error'));
            return null;
        }
        
        $data = json_decode($response, true);
        
        // Check if valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response from API");
            return null;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("API Exception: " . $e->getMessage());
        return null;
    }
}

// Try to fetch admin data from API
$admin_data = fetchAdminDataFromAPI();
$api_verified = false;

if ($admin_data && is_array($admin_data)) {
    // Find the specific admin by ID
    $admin_found = null;
    foreach ($admin_data as $admin) {
        if (isset($admin['AdminID']) && $admin['AdminID'] == $admin_id) {
            $admin_found = $admin;
            break;
        }
    }
    
    if ($admin_found) {
        // API verification successful
        $api_verified = true;
        $admin_name = $admin_found['FullName'] ?? $admin_name;
        $admin_email = $admin_found['Email'] ?? $admin_email;
        $admin_phone = $admin_found['Phone'] ?? '';
        
        error_log("API verification SUCCESS for admin ID: $admin_id");
    } else {
        error_log("API verification FAILED: Admin ID $admin_id not found in API data");
    }
} else {
    error_log("API call FAILED or returned invalid data");
}

// Set session variables for distribution system
$_SESSION['user_id'] = $admin_id;
$_SESSION['user_name'] = $admin_name;
$_SESSION['user_email'] = $admin_email;
$_SESSION['user_role'] = 'admin';
$_SESSION['user_avatar'] = strtoupper(substr($admin_name, 0, 2));
$_SESSION['volunteer_id'] = 0;

// Mark API verification status
$_SESSION['admin_api_verified'] = $api_verified;
$_SESSION['admin_api_token'] = $token;
$_SESSION['admin_api_timestamp'] = $timestamp;

if ($api_verified && isset($admin_phone)) {
    $_SESSION['admin_phone'] = $admin_phone;
}

// Store bridge info
$_SESSION['auth_source'] = 'main_system_bridge';
$_SESSION['auth_time'] = $current_time;

// Log the successful authentication
error_log("Distribution system login: $admin_name (ID: $admin_id) - API Verified: " . ($api_verified ? 'YES' : 'NO'));

// Redirect to distribution dashboard
header("Location: distribution_main.php");
exit();
?>