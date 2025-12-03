<?php
// ========================================
// PostgreSQL Connection (Remote via SSH)
// ========================================
$pg_host = "localhost";
$pg_port = "5432";  // ✅ CHANGED from 5433 to 5432
$pg_dbname = "victimdisaster";
$pg_user = "postgres";
$pg_pass = "0212";

try {
    $pg_conn = new PDO("pgsql:host=$pg_host;port=$pg_port;dbname=$pg_dbname", $pg_user, $pg_pass);
    $pg_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ PostgreSQL connected successfully!<br>";
    
    // Test query to verify connection
    $test = $pg_conn->query("SELECT current_database(), current_user");
    $result = $test->fetch(PDO::FETCH_ASSOC);
    echo "Connected to database: <b>{$result['current_database']}</b> as user: <b>{$result['current_user']}</b><br>";
    
} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ PostgreSQL Connection Failed</h3>";
    echo "<p><b>Error:</b> " . $e->getMessage() . "</p>";
    
    // Check if SSH tunnel is active
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<div style='background:#fff3cd;padding:15px;border-left:4px solid #ffc107;'>";
        echo "<h4>⚠️ SSH Tunnel Not Active</h4>";
        echo "<p><b>You need to setup SSH tunnel first:</b></p>";
        echo "<ol>";
        echo "<li><b>Open PuTTY</b></li>";
        echo "<li><b>Host Name:</b> 10.147.47.116</li>";
        echo "<li><b>Port:</b> 22</li>";
        echo "<li><b>Go to:</b> Connection → SSH → Tunnels</li>";
        echo "<li><b>Source port:</b> 5432</li>";
        echo "<li><b>Destination:</b> localhost:5432</li>";
        echo "<li><b>Click 'Add'</b></li>";
        echo "<li><b>Click 'Open'</b> and login with username: dina\\safee</li>";
        echo "<li><b>Keep PuTTY window open</b></li>";
        echo "<li><b>Refresh this page</b></li>";
        echo "</ol>";
        echo "<p><b>OR use command line:</b></p>";
        echo "<code>ssh -L 5432:localhost:5432 dina\\safee@10.147.47.116</code>";
        echo "</div>";
    }
    exit;
}
?>
