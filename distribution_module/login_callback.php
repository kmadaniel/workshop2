<?php
// login_callback.php - For receiving volunteers from friend's login system
session_start();
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// API URL to verify volunteer
$API_URL = 'http://10.147.17.30:8000/api_volunteer.php';

function fetchDataFromAPI($url, $params = []) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $full_url = $url;
        if (!empty($params)) {
            $full_url .= '?' . http_build_query($params);
        }
        
        $response = @file_get_contents($full_url, false, $context);
        if ($response === FALSE) {
            return ['success' => false, 'error' => 'API server not responding'];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }
        
        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Check if we're receiving volunteer data from external login
$volunteer_id = $_GET['volunteer_id'] ?? $_POST['volunteer_id'] ?? 0;

if (!$volunteer_id) {
    // Try to get from session if redirecting within same session
    if (isset($_SESSION['volunteer_id'])) {
        $volunteer_id = $_SESSION['volunteer_id'];
    } else {
        // No volunteer ID, redirect back to login
        header("Location: http://10.147.17.30:8000/login.php");
        exit;
    }
}

// Verify volunteer exists via API
$apiResult = fetchDataFromAPI($API_URL, ['volunteer_id' => $volunteer_id]);

if ($apiResult['success'] && !empty($apiResult['data'])) {
    // Find the specific volunteer
    $foundVolunteer = null;
    foreach ($apiResult['data'] as $volunteer) {
        $apiVolunteerId = null;
        $possibleFields = ['VolunteerID', 'volunteer_id', 'volunteerId', 'id', 'volunteerID'];
        
        foreach ($possibleFields as $field) {
            if (isset($volunteer[$field]) && intval($volunteer[$field]) == $volunteer_id) {
                $foundVolunteer = $volunteer;
                break 2;
            }
        }
    }
    
    if ($foundVolunteer) {
        // Store in session
        $_SESSION['volunteer_id'] = $volunteer_id;
        $_SESSION['volunteer_name'] = $foundVolunteer['FullName'] ?? $foundVolunteer['fullName'] ?? 'Volunteer';
        $_SESSION['volunteer_email'] = $foundVolunteer['Email'] ?? $foundVolunteer['email'] ?? '';
        
        // Also store volunteer info for quick access
        $_SESSION['volunteer_info'] = [
            'volunteer_id' => $volunteer_id,
            'name' => $foundVolunteer['FullName'] ?? $foundVolunteer['fullName'] ?? 'Volunteer',
            'email' => $foundVolunteer['Email'] ?? $foundVolunteer['email'] ?? '',
            'phone' => $foundVolunteer['Phone'] ?? $foundVolunteer['phone'] ?? '',
            'address' => $foundVolunteer['Address'] ?? $foundVolunteer['address'] ?? '',
            'ngo_affiliation' => $foundVolunteer['AssignedNGO'] ?? $foundVolunteer['assignedNGO'] ?? '',
            'skill_category' => $foundVolunteer['SkillCategory'] ?? $foundVolunteer['skillCategory'] ?? '',
            'status' => $foundVolunteer['Status'] ?? $foundVolunteer['status'] ?? 'Active',
            'role' => 'Volunteer'
        ];
        
        // Redirect to your volunteer dashboard
        header("Location: volunteer_distribution.php");
        exit;
    }
}

// If verification fails, redirect back to login
header("Location: http://10.147.17.30:8000/login.php");
exit;
?>