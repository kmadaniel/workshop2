<?php
// ========================================
// Enhanced Distribution Dashboard with Filters & Pagination
// index.php - Complete Backend - FIXED SQL GROUP BY
// ========================================

require_once 'config.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // ========================================
    // PAGINATION & FILTER PARAMETERS
    // ========================================
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10; // Items per page
    $offset = ($page - 1) * $per_page;

    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $disaster_filter = isset($_GET['disaster']) ? intval($_GET['disaster']) : 0;

    // ========================================
    // GET STATISTICS - CORRECTED TABLE NAMES
    // ========================================
    $stats_query = "
        SELECT 
            COUNT(*) as total_distributions,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Allocated' THEN 1 ELSE 0 END) as allocated,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as in_transit,
            SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM Distribution
    ";
    
    $result = $db->query($stats_query);
    
    if ($result) {
        $stats = $result->fetch_assoc();
        $result->free();
    } else {
        throw new Exception("Failed to fetch statistics: " . $db->error);
    }

    // ========================================
    // BUILD FILTERED QUERY - Use subquery approach
    // ========================================
    $where_conditions = [];
    $params = [];
    $types = '';

    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(v.name LIKE ? OR r.name LIKE ? OR dis.Disaster_Name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'sss';
    }

    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "d.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    // Disaster filter
    if ($disaster_filter > 0) {
        $where_conditions[] = "dis.disaster_id = ?";
        $params[] = $disaster_filter;
        $types .= 'i';
    }

    // Build WHERE clause
    $where_clause = '';
    if (count($where_conditions) > 0) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // ========================================
    // GET TOTAL COUNT FOR PAGINATION - FIXED
    // ========================================
    $count_query = "
        SELECT COUNT(DISTINCT d.distribution_id) as total
        FROM Distribution d
        LEFT JOIN victim v ON d.victim_id = v.victim_id
        LEFT JOIN RESOURCE r ON d.resource_id = r.Resource_ID
        LEFT JOIN Needs n ON d.victim_id = n.victim_id AND d.resource_id = n.resource_id
        LEFT JOIN Disaster dis ON n.disaster_id = dis.disaster_id
        {$where_clause}
    ";

    if (count($params) > 0) {
        $count_stmt = $db->prepare($count_query);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_rows = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $db->query($count_query);
        $total_rows = $count_result->fetch_assoc()['total'];
        $count_result->free();
    }

    $total_pages = ceil($total_rows / $per_page);

    // ========================================
    // GET FILTERED DISTRIBUTIONS WITH PAGINATION - COMPLETELY FIXED
    // Using subquery for volunteer count to avoid GROUP BY issues
    // ========================================
    $distributions_query = "
        SELECT 
            d.distribution_id,
            d.date,
            d.quantity_sent,
            d.status,
            v.name as victim_name,
            v.address as victim_address,
            r.name as resource_name,
            r.Unit as resource_unit,
            dis.Disaster_Name as disaster_name,
            dis.Location as disaster_location,
            (SELECT COUNT(DISTINCT dv2.volunteer_id) 
             FROM Distribution_volunteer dv2 
             WHERE dv2.distribution_id = d.distribution_id) as volunteer_count
        FROM Distribution d
        LEFT JOIN victim v ON d.victim_id = v.victim_id
        LEFT JOIN RESOURCE r ON d.resource_id = r.Resource_ID
        LEFT JOIN Needs n ON d.victim_id = n.victim_id AND d.resource_id = n.resource_id
        LEFT JOIN Disaster dis ON n.disaster_id = dis.disaster_id
        {$where_clause}
        ORDER BY d.date DESC, d.distribution_id DESC
        LIMIT ? OFFSET ?
    ";
    
    // Add pagination parameters
    $params[] = $per_page;
    $params[] = $offset;
    $types .= 'ii';

    if (count($params) > 0) {
        $dist_stmt = $db->prepare($distributions_query);
        $dist_stmt->bind_param($types, ...$params);
        $dist_stmt->execute();
        $dist_result = $dist_stmt->get_result();
        $recent_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);
        $dist_stmt->close();
    } else {
        // Fallback for when there are no filters (only pagination)
        $dist_result = $db->query($distributions_query);
        $recent_distributions = $dist_result->fetch_all(MYSQLI_ASSOC);
        $dist_result->free();
    }

    // ========================================
    // GET FILTER OPTIONS
    // ========================================
    
    // Status options
    $status_options = [
        'Pending',
        'Allocated',
        'Assigned',
        'In Transit',
        'Delivered',
        'Completed',
        'On Hold',
        'Cancelled'
    ];

    // Get all disasters for filter dropdown
    $disaster_query = "SELECT disaster_id, Disaster_Name FROM Disaster ORDER BY Disaster_Name ASC";
    $disaster_result = $db->query($disaster_query);
    $disaster_options = [];
    
    if ($disaster_result) {
        while ($row = $disaster_result->fetch_assoc()) {
            $disaster_options[$row['disaster_id']] = $row['Disaster_Name'];
        }
        $disaster_result->free();
    }

    // Set defaults for null values in stats
    foreach ($stats as $key => $value) {
        $stats[$key] = $value ?? 0;
    }

} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    
    // Set default values
    $stats = [
        'total_distributions' => 0,
        'pending' => 0,
        'allocated' => 0,
        'assigned' => 0,
        'in_transit' => 0,
        'delivered' => 0,
        'completed' => 0,
        'on_hold' => 0,
        'cancelled' => 0
    ];
    $recent_distributions = [];
    $total_rows = 0;
    $total_pages = 0;
    $page = 1;
    $search = '';
    $status_filter = '';
    $disaster_filter = 0;
    $status_options = [];
    $disaster_options = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Dashboard - Disaster Relief System</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Loading Dashboard</h3>
            <p>Please wait while we load your distribution data...</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Error Alert -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
                <p><small>Please check your database connection and table structure.</small></p>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h2>
                    <span class="animated-icon">📦</span> Distribution Dashboard
                </h2>
                <div class="header-actions">
                    <a href="create_distribution.php" class="btn btn-primary btn-pulse">
                        <i class="fas fa-plus"></i> Create New Distribution
                    </a>
                    <button id="refresh-btn" class="btn btn-info">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card animated-card">
                <h3>Total Distributions</h3>
                <div class="stat-value"><?php echo number_format($stats['total_distributions']); ?></div>
                <span class="stat-icon">📦</span>
            </div>
            
            <div class="stat-card pending animated-card">
                <h3>Pending</h3>
                <div class="stat-value" style="color: #f39c12;">
                    <?php echo number_format($stats['pending']); ?>
                </div>
                <span class="stat-icon">⏳</span>
            </div>
            
            <div class="stat-card assigned animated-card">
                <h3>Assigned</h3>
                <div class="stat-value" style="color: #9b59b6;">
                    <?php echo number_format($stats['assigned']); ?>
                </div>
                <span class="stat-icon">👤</span>
            </div>
            
            <div class="stat-card transit animated-card">
                <h3>In Transit</h3>
                <div class="stat-value" style="color: #3498db;">
                    <?php echo number_format($stats['in_transit']); ?>
                </div>
                <span class="stat-icon">🚚</span>
            </div>
            
            <div class="stat-card delivered animated-card">
                <h3>Delivered</h3>
                <div class="stat-value" style="color: #27ae60;">
                    <?php echo number_format($stats['delivered']); ?>
                </div>
                <span class="stat-icon">✅</span>
            </div>
            
            <div class="stat-card completed animated-card">
                <h3>Completed</h3>
                <div class="stat-value" style="color: #00b894;">
                    <?php echo number_format($stats['completed']); ?>
                </div>
                <span class="stat-icon">🎯</span>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card animated-card">
            <div class="card-header">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4">
                        <a href="create_distribution.php" class="btn btn-primary action-btn">
                            <i class="fas fa-plus fa-2x"></i><br>
                            Create Distribution
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="manage_volunteer.php" class="btn btn-success action-btn">
                            <i class="fas fa-users fa-2x"></i><br>
                            Manage Volunteers
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="reports.php" class="btn btn-warning action-btn">
                            <i class="fas fa-chart-bar fa-2x"></i><br>
                            View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card animated-card">
            <div class="card-header">
                <h2><i class="fas fa-filter"></i> Filters & Search</h2>
            </div>
            <div class="card-body">
                <form id="filter-form" method="GET" class="filter-form">
                    <div class="row">
                        <div class="col-4">
                            <div class="form-group">
                                <label for="search"><i class="fas fa-search"></i> Search</label>
                                <input type="text" id="search" name="search" class="form-control" 
                                       placeholder="Search by victim, resource, or disaster..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="status"><i class="fas fa-tag"></i> Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?php echo $status; ?>" 
                                            <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="disaster"><i class="fas fa-exclamation-triangle"></i> Disaster</label>
                                <select id="disaster" name="disaster" class="form-control">
                                    <option value="">All Disasters</option>
                                    <?php foreach ($disaster_options as $id => $name): ?>
                                        <option value="<?php echo $id; ?>" 
                                            <?php echo $disaster_filter == $id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="?" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                        <div class="results-count">
                            Showing <?php echo count($recent_distributions); ?> of <?php echo $total_rows; ?> distributions
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Distributions Table -->
        <div class="card animated-card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-list"></i> Recent Distributions
                </h2>
                <div class="header-badge">
                    <span class="badge badge-info"><?php echo $total_rows; ?> total</span>
                </div>
            </div>

            <div class="table-container">
                <?php if (count($recent_distributions) > 0): ?>
                <table id="distributions-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Disaster</th>
                            <th>Victim</th>
                            <th>Resource</th>
                            <th>Qty Sent</th>
                            <th>Volunteers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_distributions as $row): ?>
                        <tr class="table-row">
                            <td><strong>#<?php echo str_pad($row['distribution_id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            
                            <td>
                                <div class="date-cell">
                                    <div class="date-day"><?php echo date('d', strtotime($row['date'])); ?></div>
                                    <div class="date-month"><?php echo date('M', strtotime($row['date'])); ?></div>
                                    <div class="date-year"><?php echo date('Y', strtotime($row['date'])); ?></div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="disaster-cell">
                                    <strong><?php echo htmlspecialchars($row['disaster_name'] ?? 'N/A'); ?></strong><br>
                                    <small class="location-text">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['disaster_location'] ?? 'N/A'); ?>
                                    </small>
                                </div>
                            </td>
                            
                            <td>
                                <div class="victim-cell">
                                    <strong><?php echo htmlspecialchars($row['victim_name'] ?? 'N/A'); ?></strong><br>
                                    <small class="address-text">
                                        <?php echo htmlspecialchars($row['victim_address'] ?? 'N/A'); ?>
                                    </small>
                                </div>
                            </td>
                            
                            <td>
                                <div class="resource-cell">
                                    <strong><?php echo htmlspecialchars($row['resource_name'] ?? 'N/A'); ?></strong><br>
                                    <small class="unit-text">
                                        <?php echo htmlspecialchars($row['resource_unit'] ?? ''); ?>
                                    </small>
                                </div>
                            </td>
                            
                            <td>
                                <div class="quantity-cell">
                                    <span class="quantity-value"><?php echo $row['quantity_sent']; ?></span>
                                    <span class="quantity-unit"><?php echo htmlspecialchars($row['resource_unit'] ?? ''); ?></span>
                                </div>
                            </td>
                            
                            <td>
                                <?php if ($row['volunteer_count'] > 0): ?>
                                    <div class="volunteer-cell">
                                        <i class="fas fa-users"></i>
                                        <span class="volunteer-count"><?php echo $row['volunteer_count']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="volunteer-cell no-volunteers">
                                        <i class="fas fa-user-times"></i>
                                        <span>None</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php
                                $status_class = '';
                                switch($row['status']) {
                                    case 'Pending': $status_class = 'badge-pending'; break;
                                    case 'Allocated': $status_class = 'badge-allocated'; break;
                                    case 'Assigned': $status_class = 'badge-assigned'; break;
                                    case 'In Transit': $status_class = 'badge-transit'; break;
                                    case 'Delivered': $status_class = 'badge-delivered'; break;
                                    case 'Completed': $status_class = 'badge-completed'; break;
                                    case 'Cancelled': $status_class = 'badge-cancelled'; break;
                                    case 'On Hold': $status_class = 'badge-onhold'; break;
                                    default: $status_class = 'badge-pending';
                                }
                                ?>
                                <span class="badge <?php echo $status_class; ?>">
                                    <i class="fas fa-circle status-indicator"></i>
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <a href="view_distribution.php?id=<?php echo $row['distribution_id']; ?>" 
                                       class="btn btn-info btn-sm action-btn" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($row['status'] != 'Completed' && $row['status'] != 'Cancelled'): ?>
                                        <a href="update_status.php?id=<?php echo $row['distribution_id']; ?>" 
                                           class="btn btn-warning btn-sm action-btn" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-secondary btn-sm quick-view-btn" 
                                            data-id="<?php echo $row['distribution_id']; ?>" 
                                            title="Quick View">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link first">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link prev">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <h3>No distributions found</h3>
                    <p>Get started by creating your first distribution!</p>
                    <br>
                    <a href="create_distribution.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Distribution
                    </a>
                    <br><br>
                    <div class="empty-notes">
                        <p><strong>Note:</strong> Make sure you have:</p>
                        <ul>
                            <li>Created disaster records (Module 3)</li>
                            <li>Registered victims (Module 3)</li>
                            <li>Recorded and approved needs (Module 3)</li>
                            <li>Added resources to inventory (Module 2)</li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick View Modal -->
    <div id="quick-view-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Distribution Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="quick-view-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container"></div>

    <script>
        // Auto-refresh every 60 seconds
        let refreshInterval = setInterval(function() {
            showToast('Refreshing data...', 'info');
            setTimeout(() => {
                location.reload();
            }, 1000);
        }, 60000);
        
        // Loading screen
        window.addEventListener('load', function() {
            setTimeout(() => {
                document.getElementById('loading-screen').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('loading-screen').style.display = 'none';
                }, 500);
            }, 800);
        });
        
        // Refresh button
        document.getElementById('refresh-btn').addEventListener('click', function() {
            this.classList.add('refreshing');
            showToast('Refreshing data...', 'info');
            setTimeout(() => {
                location.reload();
            }, 500);
        });
        
        // Quick view modal
        const quickViewButtons = document.querySelectorAll('.quick-view-btn');
        const modal = document.getElementById('quick-view-modal');
        const modalContent = document.getElementById('quick-view-content');
        
        quickViewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const distributionId = this.getAttribute('data-id');
                
                // Show loading in modal
                modalContent.innerHTML = '<div class="loading-spinner small"></div><p>Loading distribution details...</p>';
                modal.style.display = 'block';
                
                // Simulate AJAX request (in real implementation, fetch from server)
                setTimeout(() => {
                    modalContent.innerHTML = `
                        <div class="distribution-details">
                            <div class="detail-row">
                                <div class="detail-label">Distribution ID</div>
                                <div class="detail-value">#${distributionId.padStart(4, '0')}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value"><span class="badge badge-pending">Pending</span></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Last Updated</div>
                                <div class="detail-value">${new Date().toLocaleDateString()}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Assigned Volunteers</div>
                                <div class="detail-value">2 volunteers</div>
                            </div>
                            <div class="detail-actions">
                                <a href="view_distribution.php?id=${distributionId}" class="btn btn-primary">View Full Details</a>
                                <a href="update_status.php?id=${distributionId}" class="btn btn-warning">Update Status</a>
                            </div>
                        </div>
                    `;
                }, 800);
            });
        });
        
        // Close modal
        document.querySelector('.modal-close').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${getToastIcon(type)}"></i>
                    <span>${message}</span>
                </div>
                <button class="toast-close">&times;</button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
            
            // Close button
            toast.querySelector('.toast-close').addEventListener('click', function() {
                toast.classList.add('fade-out');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            });
        }
        
        function getToastIcon(type) {
            switch(type) {
                case 'success': return 'check-circle';
                case 'error': return 'exclamation-circle';
                case 'warning': return 'exclamation-triangle';
                default: return 'info-circle';
            }
        }
        
        // Table row animations
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table-row');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(20px)';
                    row.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
        });
        
        // Filter form auto-submit on change (optional)
        document.getElementById('status').addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
        
        document.getElementById('disaster').addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    </script>
</body>
</html>