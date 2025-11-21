<?php
require_once 'config.php';
 

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get statistics - using simple queries that should work
    $stats = [];
    $queries = [
        'total_distributions' => "SELECT COUNT(*) as count FROM distribution",
        'pending_distributions' => "SELECT COUNT(*) as count FROM distribution WHERE status = 'pending'",
        'assigned_distributions' => "SELECT COUNT(*) as count FROM distribution WHERE status = 'assigned'",
        'completed_distributions' => "SELECT COUNT(*) as count FROM distribution WHERE status = 'completed'"
    ];

    foreach ($queries as $key => $query) {
        $result = $db->query($query);
        if ($result) {
            $stats[$key] = $result->fetch_assoc()['count'];
            $result->free();
        } else {
            $stats[$key] = 0;
        }
    }

    // Get recent distributions - simplified without joins first
    $recent_query = "SELECT * FROM distribution ORDER BY date DESC LIMIT 10";
    $result = $db->query($recent_query);
    if ($result) {
        $recent_distributions = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } else {
        $recent_distributions = [];
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stats = [
        'total_distributions' => 0,
        'pending_distributions' => 0,
        'assigned_distributions' => 0,
        'completed_distributions' => 0
    ];
    $recent_distributions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Management System - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Disaster Management System</h1>
            <p>Distribution Module Dashboard</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $error; ?>
                <p><small>Running in demo mode with sample data</small></p>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">Total Distributions</div>
                <div class="stat-number"><?php echo $stats['total_distributions']; ?></div>
                <div class="stat-desc">All time distributions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending</div>
                <div class="stat-number"><?php echo $stats['pending_distributions']; ?></div>
                <div class="stat-desc">Awaiting action</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Assigned</div>
                <div class="stat-number"><?php echo $stats['assigned_distributions']; ?></div>
                <div class="stat-desc">Volunteers assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-number"><?php echo $stats['completed_distributions']; ?></div>
                <div class="stat-desc">Successfully delivered</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card-3d">
            <h2>Quick Actions</h2>
            <div class="grid-3">
                <a href="create_distribution.php" class="btn btn-primary">Create New Distribution</a>
                <a href="assign_volunteer.php" class="btn btn-success">Assign Volunteers</a>
                <a href="reports.php" class="btn btn-warning">View Reports</a>
            </div>
        </div>

        <!-- Recent Distributions -->
        <div class="card-3d">
            <h2>Recent Distributions</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Quantity Sent</th>
                            <th>Comments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recent_distributions) > 0): ?>
                            <?php foreach ($recent_distributions as $distribution): ?>
                            <tr class="fade-in">
                                <td>#<?php echo $distribution['distribution_id']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($distribution['date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $distribution['status']; ?>">
                                        <?php echo ucfirst($distribution['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $distribution['quantity_sent']; ?></td>
                                <td><?php echo htmlspecialchars(substr($distribution['comments'] ?? '', 0, 50)); ?></td>
                                <td>
                                    <a href="view_distribution.php?id=<?php echo $distribution['distribution_id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    <a href="update_status.php?id=<?php echo $distribution['distribution_id']; ?>" class="btn btn-warning btn-sm">Update</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    No distributions found. <a href="create_distribution.php">Create the first distribution</a>
                                    <p><small>If you just set up the database, you need to create some distributions first.</small></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>