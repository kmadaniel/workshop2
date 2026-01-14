<?php
// admin/debug_audit.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>Audit Log Debug Page</h2>";
echo "<p>Use this page to troubleshoot audit log issues.</p>";

// Include database connection - CORRECT PATH
$dbPath = dirname(__DIR__) . '/db.php';
if (!file_exists($dbPath)) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red;'>";
    echo "<h3>❌ CRITICAL: db.php not found!</h3>";
    echo "<p>Looking for: $dbPath</p>";
    echo "</div>";
    exit;
}

echo "✅ db.php found at: $dbPath<br>";

require_once $dbPath;

if (!isset($conn)) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red;'>";
    echo "<h3>❌ CRITICAL: \$conn is not set!</h3>";
    echo "<p>Check your db.php file for errors</p>";
    echo "</div>";
    exit;
}

echo "✅ \$conn is set<br>";

try {
    // Test connection
    $conn->query("SELECT 1");
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red;'>";
    echo "<h3>❌ Database connection failed!</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
    exit;
}

// Check table existence (PostgreSQL version)
$check = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'audit_log')");
$table_exists = $check->fetchColumn();

if (!$table_exists) {
    echo "<div style='color: red; padding: 20px; border: 2px solid red;'>";
    echo "<h3>❌ CRITICAL: audit_log table doesn't exist in PostgreSQL!</h3>";
    echo "<p>Run this SQL in your PostgreSQL database:</p>";
    echo "<pre>";
    echo "CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(255) NULL,
    action_type VARCHAR(50) NOT NULL,
    action_description TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    affected_table VARCHAR(100) NULL,
    affected_id INT NULL,
    changes_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_created_at ON audit_log(created_at);
CREATE INDEX idx_audit_action_type ON audit_log(action_type);
CREATE INDEX idx_audit_affected_table ON audit_log(affected_table);";
    echo "</pre>";
    echo "</div>";
    exit;
}

// Check record count
$count = $conn->query("SELECT COUNT(*) as count FROM audit_log")->fetchColumn();
echo "<p>Total audit logs: <strong>$count</strong></p>";

// Try to add a test log
echo "<h3>Test Log Creation:</h3>";
$test_log_id = $logger->log('debug_test', 'Test log from debug page', 'debug', 999, ['test' => 'debug data']);
if ($test_log_id) {
    echo "<p style='color: green;'>✅ Test log created successfully (ID: $test_log_id)</p>";
} else {
    echo "<p style='color: red;'>❌ Failed to create test log</p>";
}

// Show recent logs
echo "<h3>Recent Logs (Last 20):</h3>";
$logs = $logger->getLogs('all', 20, 0);

if (empty($logs)) {
    echo "<p style='color: orange;'>⚠️ No logs found in database via AuditLogger class</p>";
    
    // Try direct query
    echo "<h4>Direct Query Results:</h4>";
    $direct_logs = $conn->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($direct_logs)) {
        echo "<p>No logs found via direct query either.</p>";
        echo "<p>Try adding a test log first.</p>";
    } else {
        echo "<p>Found " . count($direct_logs) . " logs via direct query.</p>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Action</th><th>Description</th><th>User</th><th>Date</th></tr>";
        foreach ($direct_logs as $log) {
            echo "<tr>";
            echo "<td>{$log['id']}</td>";
            echo "<td>{$log['action_type']}</td>";
            echo "<td>{$log['action_description']}</td>";
            echo "<td>{$log['user_name']}</td>";
            echo "<td>{$log['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>Found " . count($logs) . " logs via AuditLogger class.</p>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>ID</th><th>Action</th><th>Description</th><th>User</th><th>Date</th></tr>";
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>{$log['id']}</td>";
        echo "<td>{$log['action_type']}</td>";
        echo "<td>" . substr($log['action_description'], 0, 50) . "...</td>";
        echo "<td>{$log['user_name']}</td>";
        echo "<td>{$log['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check error log
echo "<h3>Error Log:</h3>";
$error_log_path = __DIR__ . '/audit_error_log.txt';
if (file_exists($error_log_path)) {
    $error_log_content = file_get_contents($error_log_path);
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo substr($error_log_content, -5000); // Last 5000 characters
    echo "</pre>";
} else {
    echo "<p>No error log file found.</p>";
}

// Test database connection
echo "<h3>Database Connection Test:</h3>";
try {
    $test = $conn->query("SELECT 1 as test");
    $result = $test->fetch(PDO::FETCH_ASSOC);
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    echo "<p>Database: " . $conn->getAttribute(PDO::ATTR_CONNECTION_STATUS) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}
?>