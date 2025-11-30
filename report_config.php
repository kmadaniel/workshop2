<?php
// report_config.php
// Database configuration using PDO (Compatible with PostgreSQL and MySQL)

class Database {
    // CHANGE THESE VARIABLES TO MATCH YOUR POSTGRES SETUP
    private $host = "localhost";
    private $db_name = "report"; // Your PostgreSQL Database Name
    private $username = "postgres";   // Your PostgreSQL Username
    private $password = "123123"; // Your PostgreSQL Password
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            // DSN for PostgreSQL. 
            // If you migrate to MySQL later, change 'pgsql' to 'mysql' and remove 'port=5432'
            $dsn = "pgsql:host=" . $this->host . ";port=5432;dbname=" . $this->db_name;
            
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>