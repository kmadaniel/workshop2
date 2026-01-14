<?php
// admin/simple_test.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/db.php';

echo "<h2>Simple Direct Test</h2>";

// 1. Test connection
echo "<h3>1. Testing Database Connection</h3>";
try {
    $test = $conn->query("SELECT 1");
    echo "<p style='color: green;'>✅ Connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Check audit_log table
echo "<h3>2. Checking audit_log Table</h3>";
try {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM audit_log")->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total records: <strong>" . $count['cnt'] . "</strong></p>";
    
    // Show sample
    $sample = $conn->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if ($sample) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Action</th><th>User</th><th>Description</th><th>Date</th></tr>";
        foreach ($sample as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['action_type']}</td>";
            echo "<td>{$row['user_name']}</td>";
            echo "<td>" . substr($row['action_description'], 0, 50) . "...</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Table error: " . $e->getMessage() . "</p>";
}

// 3. Test AJAX endpoint directly
echo "<h3>3. Testing Direct AJAX Call</h3>";
echo '<button onclick="testAjax()">Test AJAX Call</button>';
echo '<div id="ajaxResult" style="margin-top: 20px; padding: 10px; background: #f0f0f0;"></div>';

echo '<script>
function testAjax() {
    document.getElementById("ajaxResult").innerHTML = "Loading...";
    
    // Try different URLs
    const urls = [
        "get_audit_log.php?filter=all&t=" + Date.now(),
        "../admin/get_audit_log.php?filter=all&t=" + Date.now(),
        "admin/get_audit_log.php?filter=all&t=" + Date.now()
    ];
    
    console.log("Testing URLs:", urls);
    
    // Try first URL
    fetch(urls[0])
        .then(response => {
            console.log("Response status:", response.status);
            return response.text();
        })
        .then(html => {
            document.getElementById("ajaxResult").innerHTML = 
                "<h4>Success!</h4><pre>" + html.substring(0, 500) + "...</pre>";
        })
        .catch(error => {
            console.error("Error:", error);
            document.getElementById("ajaxResult").innerHTML = 
                "Error: " + error.message;
        });
}
</script>';

// 4. Check if session is needed
echo "<h3>4. Session Information</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>