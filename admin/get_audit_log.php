<?php
// admin/get_audit_log.php
session_start();
require_once dirname(__DIR__) . '/db.php';

$filter = $_GET['filter'] ?? 'all';

try {
    $sql = "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 50";
    
    if ($filter === 'today') {
        $sql = "SELECT * FROM audit_log WHERE DATE(created_at) = CURRENT_DATE ORDER BY created_at DESC LIMIT 50";
    } elseif ($filter === 'admin') {
        $sql = "SELECT * FROM audit_log WHERE action_type IN ('disaster_add','disaster_update','disaster_delete','victim_update','alert_sent') ORDER BY created_at DESC LIMIT 50";
    } elseif ($filter === 'api') {
        $sql = "SELECT * FROM audit_log WHERE action_type LIKE '%api%' OR action_type LIKE '%sync%' ORDER BY created_at DESC LIMIT 50";
    }
    
    $stmt = $conn->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<style>
        .audit-table { width:100%; border-collapse:collapse; }
        .audit-table th { background:#f8f9fa; padding:12px; text-align:left; font-weight:600; color:#333; }
        .audit-table td { padding:10px; border-bottom:1px solid #eee; }
        .audit-table tr:hover { background:#f5f5f5; }
        .badge { padding:4px 8px; border-radius:4px; font-size:12px; }
        .badge-success { background:#d4edda; color:#155724; }
        .badge-info { background:#d1ecf1; color:#0c5460; }
        .badge-warning { background:#fff3cd; color:#856404; }
        .badge-danger { background:#f8d7da; color:#721c24; }
    </style>';
    
    echo '<div class="table-responsive">';
    echo '<table class="audit-table">';
    echo '<thead><tr>
            <th>ID</th>
            <th>Date & Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Description</th>
          </tr></thead>';
    echo '<tbody>';
    
    foreach ($logs as $log) {
        $date = date('d M Y', strtotime($log['created_at']));
        $time = date('H:i:s', strtotime($log['created_at']));
        
        // Determine badge type
        $badge_class = 'badge-info';
        if (strpos($log['action_type'], 'add') !== false) $badge_class = 'badge-success';
        if (strpos($log['action_type'], 'update') !== false) $badge_class = 'badge-warning';
        if (strpos($log['action_type'], 'delete') !== false) $badge_class = 'badge-danger';
        if (strpos($log['action_type'], 'api') !== false) $badge_class = 'badge-info';
        
        echo '<tr>';
        echo '<td><strong>#' . $log['id'] . '</strong></td>';
        echo '<td>
                <div>' . $date . '</div>
                <small style="color:#888;">' . $time . '</small>
              </td>';
        echo '<td>' . htmlspecialchars($log['user_name'] ?? 'System') . '</td>';
        echo '<td><span class="badge ' . $badge_class . '">' . htmlspecialchars($log['action_type']) . '</span></td>';
        echo '<td>' . htmlspecialchars(substr($log['action_description'], 0, 80)) . 
             (strlen($log['action_description']) > 80 ? '...' : '') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
    
    echo '<div style="text-align:center;padding:15px;color:#666;background:#f8f9fa;border-radius:5px;margin-top:10px;">';
    echo '<i class="fas fa-info-circle"></i> Showing ' . count($logs) . ' audit logs';
    if ($filter !== 'all') {
        echo ' (filtered by: ' . htmlspecialchars($filter) . ')';
    }
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div style="color:red;padding:20px;background:#ffebee;border-radius:5px;">';
    echo '<h4>❌ Error</h4>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>