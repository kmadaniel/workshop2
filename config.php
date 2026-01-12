<?php
$host = "localhost";
$user = "root";
$pass = "Frero@2950";
$db   = "distribution";

// Turn on error reporting for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Attempt database connection
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
    
    // REMOVE OR COMMENT THIS LINE - it breaks JSON responses
    // echo "✅ MySQL Connected<br>";
    
} catch (Exception $e) {
    // Only show errors in non-API contexts
    if (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
        die("
            <h2 style='color:red;'>❌ Database Connection Failed</h2>
            <p><b>Error Message:</b> " . $e->getMessage() . "</p>
            <p><b>Possible Causes:</b></p>
            <ul>
                <li>MySQL Server is not running</li>
                <li>Incorrect username or password</li>
                <li>Database '$db' does not exist</li>
                <li>Port is blocked or changed</li>
            </ul>
        ");
    } else {
        // For API calls, return JSON error
        header("Content-Type: application/json");
        echo json_encode([
            "status" => "error",
            "message" => "Database connection failed: " . $e->getMessage()
        ]);
        exit;
    }
}

// Database class for backward compatibility
class Database {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function prepare($query) {
        return $this->conn->prepare($query);
    }
}
?>


