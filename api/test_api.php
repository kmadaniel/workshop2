// Test file: test_api.php
<?php
$url = "http://localhost:8000/workshop2/api/mysql.php?key=kunci_ikmal&action=list&table=distribution&limit=10&offset=0";

echo "Testing URL: " . $url . "<br><br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

echo "HTTP Code: " . $httpCode . "<br>";
echo "Error: " . ($error ?: "None") . "<br>";
echo "Response: <pre>" . htmlspecialchars($response) . "</pre>";

// curl_close($ch); // Removed - deprecated in PHP 8.0+
?>