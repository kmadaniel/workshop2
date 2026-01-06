<?php
// fetch_api.php - Helper for AJAX API calls
header('Content-Type: application/json');

$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/needs.php';

$type = $_GET['type'] ?? '';

function fetchDataFromAPI($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            return ['success' => false, 'error' => 'Server not responding'];
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

if ($type === 'disaster') {
    $result = fetchDataFromAPI($DISASTER_API_URL);
} elseif ($type === 'victim') {
    $result = fetchDataFromAPI($VICTIM_API_URL);
} else {
    $result = ['success' => false, 'error' => 'Invalid type specified'];
}

echo json_encode($result);