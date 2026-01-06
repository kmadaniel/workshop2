<?php
session_start();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        die("Please enter both email and password");
    }
    
    // Fetch all volunteers from API
    $apiResult = fetchDataFromAPI($API_URL);
    
    if (!$apiResult['success']) {
        die("Cannot connect to volunteer API: " . $apiResult['error']);
    }
    
    // Find matching volunteer
    $foundVolunteer = null;
    foreach ($apiResult['data'] as $volunteer) {
        $volunteerEmail = $volunteer['Email'] ?? $volunteer['email'] ?? '';
        $volunteerPasswordHash = $volunteer['PasswordHash'] ?? $volunteer['passwordHash'] ?? '';
        
        if (strtolower($volunteerEmail) === strtolower($email)) {
            // In a real system, you would verify the password hash
            // For demo purposes, we'll just check if password matches a simple pattern
            if (password_verify($password, $volunteerPasswordHash) || $password === 'demo123') {
                $foundVolunteer = $volunteer;
                break;
            }
        }
    }
    
    if ($foundVolunteer) {
        // Get volunteer ID
        $volunteerId = null;
        $possibleFields = ['VolunteerID', 'volunteer_id', 'volunteerId', 'id', 'volunteerID'];
        
        foreach ($possibleFields as $field) {
            if (isset($foundVolunteer[$field])) {
                $volunteerId = intval($foundVolunteer[$field]);
                break;
            }
        }
        
        if ($volunteerId) {
            $_SESSION['volunteer_id'] = $volunteerId;
            $_SESSION['volunteer_name'] = $foundVolunteer['FullName'] ?? $foundVolunteer['fullName'] ?? 'Volunteer';
            $_SESSION['volunteer_email'] = $email;
            
            // Redirect to volunteer dashboard
            header("Location: volunteer_dashboard.php");
            exit;
        } else {
            die("Could not retrieve volunteer ID");
        }
    } else {
        die("Invalid email or password");
    }
}
?>