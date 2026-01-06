<?php
// test_victim_api.php
$VICTIM_API_URL = 'http://10.147.17.116:8000/needs.php';

// Get raw response
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

echo "<h1>Testing Victim API: " . htmlspecialchars($VICTIM_API_URL) . "</h1>";

$response = @file_get_contents($VICTIM_API_URL, false, $context);
if ($response === FALSE) {
    echo "<p style='color: red;'>Failed to fetch API</p>";
} else {
    echo "<h2>Raw Response:</h2>";
    echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    echo "<h2>JSON Decode:</h2>";
    $data = json_decode($response, true);
    echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    var_dump($data);
    echo "</pre>";
    
    echo "<h2>JSON Last Error:</h2>";
    echo "<p>" . json_last_error_msg() . "</p>";
    
    if (is_array($data)) {
        echo "<h2>First Item:</h2>";
        echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
        print_r($data[0] ?? $data);
        echo "</pre>";
    }
}
?>