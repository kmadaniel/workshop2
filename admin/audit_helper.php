<?php
// ============================================
// audit_helper.php - FIXED VERSION
// ============================================

class AuditHelper {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Log an action using the stored procedure
     */
    public function log($action_type, $description, $affected_table = null, $affected_id = null, $changes = null) {
        try {
            // Get current user from session
            $user_id = $_SESSION['user_id'] ?? null;
            $user_name = $_SESSION['user_name'] ?? 'Unknown';
            
            // Prepare changes as JSON
            $changes_json = null;
            if ($changes !== null) {
                $changes_json = json_encode($changes, JSON_UNESCAPED_UNICODE);
            }
            
            // Get IP and User Agent
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // FIX: Use CALL with named parameters
            $sql = "CALL log_system_audit(
                :action_type,       -- p_action_type (1st)
                :description,       -- p_action_description (2nd)
                :user_id,           -- p_user_id (3rd, optional)
                :user_name,         -- p_user_name (4th, optional)
                :ip_address,        -- p_ip_address (5th, optional)
                :user_agent,        -- p_user_agent (6th, optional)
                :affected_table,    -- p_affected_table (7th, optional)
                :affected_id,       -- p_affected_id (8th, optional)
                :changes_data       -- p_changes_data (9th, optional)
            )";
            
            $stmt = $this->conn->prepare($sql);
            
            // FIX: Create variables first, then bind
            // Required parameters
            $stmt->bindValue(':action_type', $action_type, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            
            // Optional parameters - use bindValue instead of bindParam
            $stmt->bindValue(':user_id', $user_id, $user_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':user_name', $user_name, PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $ip_address, $ip_address ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':user_agent', $user_agent, $user_agent ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':affected_table', $affected_table, $affected_table ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':affected_id', $affected_id, $affected_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':changes_data', $changes_json, $changes_json ? PDO::PARAM_STR : PDO::PARAM_NULL);
            
            $stmt->execute();
            
            error_log("✅ Audit logged: $action_type - $description");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ Audit log error: " . $e->getMessage());
            error_log("❌ Error details: " . $e->getTraceAsString());
            
            // Fallback to direct insert
            return $this->fallbackLog($action_type, $description, $affected_table, $affected_id, $changes);
        }
    }
    
    /**
     * Fallback method if procedure fails
     */
    private function fallbackLog($action_type, $description, $affected_table, $affected_id, $changes) {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            $user_name = $_SESSION['user_name'] ?? 'Unknown';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $changes_json = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;
            
            $sql = "INSERT INTO audit_log (
                action_type, action_description, user_id, user_name,
                ip_address, user_agent, affected_table, affected_id, changes_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $action_type,
                $description,
                $user_id,
                $user_name,
                $ip_address,
                $user_agent,
                $affected_table,
                $affected_id,
                $changes_json
            ]);
            
            error_log("✅ Fallback audit logged: $action_type");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ Fallback also failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * SIMPLER VERSION - Try this if above doesn't work
     */
    public function logSimple($action_type, $description, $affected_table = null, $affected_id = null, $changes = null) {
        try {
            // Prepare all values
            $user_id = $_SESSION['user_id'] ?? null;
            $user_name = $_SESSION['user_name'] ?? 'Unknown';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $changes_json = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;
            
            // SIMPLE: Use execute with array
            $sql = "CALL log_system_audit(?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            
            $stmt->execute([
                $action_type,
                $description,
                $user_id,
                $user_name,
                $ip_address,
                $user_agent,
                $affected_table,
                $affected_id,
                $changes_json
            ]);
            
            error_log("✅ Audit logged (simple): $action_type");
            return true;
            
        } catch (Exception $e) {
            error_log("❌ Simple audit error: " . $e->getMessage());
            return $this->fallbackLog($action_type, $description, $affected_table, $affected_id, $changes);
        }
    }
    
    /**
     * Get recent audit logs
     */
    public function getRecentLogs($limit = 50) {
        $sql = "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Test the connection
     */
    public function testConnection() {
        try {
            $result = $this->conn->query("SELECT 1 as test");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>