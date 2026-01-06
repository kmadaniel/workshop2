<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$volunteer_id = filter_var($_GET['volunteer_id'] ?? null, FILTER_VALIDATE_INT);

if (!$volunteer_id || $volunteer_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid volunteer ID']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db || !is_object($db)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // Get volunteer's assignments
    $assignments_query = "
        SELECT dv.*, 
               d.date, 
               d.location, 
               d.status as distribution_status,
               d.distribution_id,
               (SELECT COUNT(*) FROM distribution_log dl WHERE dl.distribution_id = d.distribution_id AND dl.volunteer_id = dv.volunteer_id AND dl.status = 'completed') as completed_items,
               (SELECT COUNT(*) FROM distribution_log dl WHERE dl.distribution_id = d.distribution_id AND dl.volunteer_id = dv.volunteer_id) as total_items
        FROM distribution_volunteer dv
        JOIN distribution d ON dv.distribution_id = d.distribution_id
        WHERE dv.volunteer_id = ?
        ORDER BY d.date DESC
    ";
    
    $stmt = $db->prepare($assignments_query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $db->error);
    }
    
    $stmt->bind_param("i", $volunteer_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $assignments = [];
    
    while ($row = $result->fetch_assoc()) {
        $assignments[] = [
            'distribution_id' => $row['distribution_id'],
            'role' => $row['role'] ?? 'Volunteer',
            'status' => $row['status'] ?? 'Assigned',
            'date' => $row['date'],
            'location' => $row['location'] ?? 'N/A',
            'distribution_status' => $row['distribution_status'],
            'completed_items' => $row['completed_items'] ?? 0,
            'total_items' => $row['total_items'] ?? 0,
            'assigned_at' => $row['assigned_at'] ?? null
        ];
    }
    
    $stmt->close();
    
    // Get volunteer details from API
    $VOLUNTEER_API_URL = 'http://10.147.17.30:8000/api_volunteer.php';
    
    // Function to fetch from API
    function fetchFromAPI($url, $params = []) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "Accept: application/json\r\n" .
                           "User-Agent: DisasterReliefSystem/1.0\r\n"
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
                // Try cURL fallback
                if (function_exists('curl_init')) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $full_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_HTTPHEADER => ['Accept: application/json'],
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false
                    ]);
                    
                    $response = curl_exec($ch);
                    curl_close($ch);
                }
            }
            
            if ($response === FALSE) {
                return ['success' => false, 'error' => 'API server not responding'];
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()];
            }
            
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // Try to get volunteer details
    $volunteer_details = null;
    $api_result = fetchFromAPI($VOLUNTEER_API_URL, ['volunteer_id' => $volunteer_id]);
    
    if ($api_result['success']) {
        $api_data = $api_result['data'];
        
        // Handle different response formats
        if (isset($api_data['volunteers']) && is_array($api_data['volunteers'])) {
            foreach ($api_data['volunteers'] as $vol) {
                $api_vol_id = $vol['VolunteerID'] ?? $vol['volunteer_id'] ?? $vol['id'] ?? 0;
                if (intval($api_vol_id) == $volunteer_id) {
                    $volunteer_details = $vol;
                    break;
                }
            }
        } elseif (isset($api_data['data']) && is_array($api_data['data'])) {
            foreach ($api_data['data'] as $vol) {
                $api_vol_id = $vol['VolunteerID'] ?? $vol['volunteer_id'] ?? $vol['id'] ?? 0;
                if (intval($api_vol_id) == $volunteer_id) {
                    $volunteer_details = $vol;
                    break;
                }
            }
        } elseif (is_array($api_data) && isset($api_data['VolunteerID'])) {
            // Single volunteer response
            $api_vol_id = $api_data['VolunteerID'] ?? $api_data['volunteer_id'] ?? $api_data['id'] ?? 0;
            if (intval($api_vol_id) == $volunteer_id) {
                $volunteer_details = $api_data;
            }
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'volunteer_id' => $volunteer_id,
        'assignments' => $assignments,
        'total_assignments' => count($assignments),
        'volunteer_details' => $volunteer_details
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>