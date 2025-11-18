<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "✅ PHP is working<br>";

$distribution_id = $_GET['id'] ?? null;
echo "Distribution ID: " . ($distribution_id ?? 'Not provided') . "<br>";

if (!$distribution_id) {
    die("❌ No distribution ID provided");
}

try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Database connected<br>";
    
    // Test if distribution exists
    $check_query = "SELECT distribution_id FROM distribution WHERE distribution_id = ?";
    $stmt = $db->prepare($check_query);
    $stmt->bind_param("i", $distribution_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "✅ Distribution found<br>";
    } else {
        die("❌ Distribution not found");
    }
    $stmt->close();
    
} catch (Exception $e) {
    die("❌ Database error: " . $e->getMessage());
}

echo "✅ All checks passed - page should load now<br>";
?>