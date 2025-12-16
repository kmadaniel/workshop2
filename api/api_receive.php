<?php

// Get API key
$apiKey = 'kunci_ikmal';

// Build URL with query parameters
$params = [
    'key' => $apiKey,
    'action' => 'list',
    'table' => 'distribution',
    'limit' => 100,
    'offset' => 0
];

// USE THE NEW API
$url = "http://localhost:8000/workshop2/api/mysql_new.php?" . http_build_query($params);

echo "<h3>Requesting URL:</h3>";
echo "<pre>" . htmlspecialchars($url) . "</pre>";
echo "<hr>";

// Make HTTP GET request using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Check for cURL errors
if ($curlError) {
    echo "<h3>cURL Error:</h3>";
    echo "<pre>" . htmlspecialchars($curlError) . "</pre>";
    exit;
}

// VIEW RAW JSON RESPONSE
echo "<h3>HTTP Status Code: $httpCode</h3>";
echo "<h3>Raw JSON Response:</h3>";
echo "<pre>";
echo htmlspecialchars($response);
echo "</pre>";
echo "<hr>";

// Check if request was successful
if ($httpCode !== 200 || $response === false) {
    echo 'Error: Unable to fetch data (HTTP Code: ' . $httpCode . ')';
    exit;
}

// Decode JSON response
$responseData = json_decode($response, true);

// VIEW DECODED ARRAY
echo "<h3>Decoded Array:</h3>";
echo "<pre>";
print_r($responseData);
echo "</pre>";
echo "<hr>";

// Check if status is success
if (!isset($responseData['status']) || $responseData['status'] !== 'success') {
    echo "API Error: " . ($responseData['message'] ?? 'Unknown error');
    exit;
}

$distributionData = $responseData['data'] ?? [];

// VIEW DISTRIBUTION DATA
echo "<h3>Distribution Data:</h3>";
echo "<pre>";
print_r($distributionData);
echo "</pre>";

?>