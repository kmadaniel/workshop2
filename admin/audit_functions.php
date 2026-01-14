<?php
// admin/audit_functions.php
class AuditLogger {
    private $conn;
    private $errorLogFile;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->errorLogFile = __DIR__ . '/audit_error_log.txt';
        
        // Create audit_log table if it doesn't exist (PostgreSQL version)
        $this->createTableIfNotExists();
    }
    
    private function createTableIfNotExists() {
        try {
            // Check if table exists
            $check = $this->conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'audit_log')");
            $exists = $check->fetchColumn();
            
            if (!$exists) {
                // Create table for PostgreSQL
                $sql = "CREATE TABLE audit_log (
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
                
                $this->conn->exec($sql);
                error_log("Audit table created successfully");
            }
        } catch (Exception $e) {
            error_log("Audit table creation failed: " . $e->getMessage());
        }
    }
    
    public function log($action_type, $description, $affected_table = null, $affected_id = null, $changes = null) {
        try {
            // Get user info from session
            $user_id = $_SESSION['user_id'] ?? null;
            $user_name = $_SESSION['username'] ?? 'system';
            
            // Get IP address
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Get user agent
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Prepare changes as JSON
            $changes_data = null;
            if ($changes) {
                $changes_data = json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            
            // Insert audit log (PostgreSQL version)
            $stmt = $this->conn->prepare("
                INSERT INTO audit_log 
                (user_id, user_name, action_type, action_description, ip_address, user_agent, affected_table, affected_id, changes_data)
                VALUES 
                (:user_id, :user_name, :action_type, :action_description, :ip_address, :user_agent, :affected_table, :affected_id, :changes_data)
            ");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':user_name' => $user_name,
                ':action_type' => $action_type,
                ':action_description' => $description,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent,
                ':affected_table' => $affected_table,
                ':affected_id' => $affected_id,
                ':changes_data' => $changes_data
            ]);
            
            return $this->conn->lastInsertId();
            
        } catch (Exception $e) {
            // Log error to file
            $error_msg = date('Y-m-d H:i:s') . " - Audit log error: " . $e->getMessage() . "\n";
            file_put_contents($this->errorLogFile, $error_msg, FILE_APPEND);
            return false;
        }
    }
    
    public function getLogs($filter = 'all', $limit = 100, $offset = 0) {
        try {
            $sql = "SELECT * FROM audit_log WHERE 1=1";
            $params = [];
            
            // Apply filters
            if ($filter === 'today') {
                $sql .= " AND DATE(created_at) = CURRENT_DATE";
            } elseif ($filter === 'admin') {
                $sql .= " AND action_type IN ('disaster_add', 'disaster_update', 'disaster_delete', 'victim_update', 'alert_sent')";
            } elseif ($filter === 'api') {
                $sql .= " AND action_type IN ('api_sync', 'api_bulk_sync', 'api_sync_single')";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get audit logs error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getLogById($log_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM audit_log WHERE id = ?");
            $stmt->execute([$log_id]);
            
            $log = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($log && $log['changes_data']) {
                $log['changes'] = json_decode($log['changes_data'], true);
            }
            
            return $log;
            
        } catch (Exception $e) {
            error_log("Get log details error: " . $e->getMessage());
            return null;
        }
    }
    
    public function getStats() {
        try {
            $stats = [];
            
            // Total logs
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM audit_log");
            $stats['total'] = $stmt->fetchColumn();
            
            // Today's logs
            $stmt = $this->conn->query("SELECT COUNT(*) as today FROM audit_log WHERE DATE(created_at) = CURRENT_DATE");
            $stats['today'] = $stmt->fetchColumn();
            
            // Admin actions
            $stmt = $this->conn->query("SELECT COUNT(*) as admin FROM audit_log WHERE action_type IN ('disaster_add', 'disaster_update', 'disaster_delete', 'victim_update', 'alert_sent')");
            $stats['admin'] = $stmt->fetchColumn();
            
            // API actions
            $stmt = $this->conn->query("SELECT COUNT(*) as api FROM audit_log WHERE action_type IN ('api_sync', 'api_bulk_sync', 'api_sync_single')");
            $stats['api'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get audit stats error: " . $e->getMessage());
            return ['total' => 0, 'today' => 0, 'admin' => 0, 'api' => 0];
        }
    }
}