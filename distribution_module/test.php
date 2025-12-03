<?php
echo "<h2>Connection Test</h2>";

// Test SSH Tunnel
$socket = @fsockopen('localhost', 5432, $errno, $errstr, 5);
if ($socket) {
    echo "✅ SSH Tunnel is active (port 5432 is open)<br>";
    fclose($socket);
} else {
    die("❌ SSH Tunnel is NOT active. Setup PuTTY first!<br>");
}

// Test PostgreSQL
try {
    $pg_conn = new PDO("pgsql:host=localhost;port=5432;dbname=victimdisaster", "postgres", "0212");
    echo "✅ PostgreSQL connected!<br>";
    
    // List tables
    $stmt = $pg_conn->query("SELECT tablename FROM pg_tables WHERE schemaname='public'");
    echo "<h3>Tables in database:</h3><ul>";
    while ($row = $stmt->fetch()) {
        echo "<li>{$row['tablename']}</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ PostgreSQL error: " . $e->getMessage();
}
?>