<?php
require_once 'config.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['volunteer_id']) && !isset($_SESSION['user_id'])) {
    header("Location: http://10.147.17.30:8000/login_gateway.php");
    exit;
}

$user_id = isset($_SESSION['volunteer_id']) ? $_SESSION['volunteer_id'] : $_SESSION['user_id'];

// Fetch notifications
$notifications = [];
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Mark all as read when page loads
if (!empty($notifications)) {
    try {
        $update_query = "UPDATE notifications SET read_status = 1 WHERE user_id = ? AND read_status = 0";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    } catch (Exception $e) {
        // Silently continue
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Disaster Relief System</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Include your header and sidebar here -->
    
    <main class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>All Notifications</h3>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications</h3>
                        <p>You don't have any notifications yet.</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item" style="padding: 15px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h4 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                    <p style="margin: 0; color: #666;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                </div>
                                <small style="color: #999;"><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>