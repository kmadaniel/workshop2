<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$disasters = [];
$needs = [];
$statistics = [];

// API Configuration
$NEEDS_API_URL = 'http://192.168.195.116:3000/ikmal.php';
$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';

/* ----------------------------------------
   FETCH DATA FROM API
---------------------------------------- */
function fetchDataFromAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'error' => $error];
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP Error: $httpCode"];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON response: ' . json_last_error_msg()];
    }
    
    return ['success' => true, 'data' => $data];
}

// Fetch needs API data
$needsApiResult = fetchDataFromAPI($NEEDS_API_URL);

// Fetch disaster API data from your friend's system
$disasterApiResult = fetchDataFromAPI($DISASTER_API_URL);

if (!$disasterApiResult['success']) {
    $error = "Disaster API Error: " . $disasterApiResult['error'];
}

/* ----------------------------------------
   BULK ACTIONS (Approve/Reject Multiple)
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_needs = $_POST['selected_needs'] ?? [];
    
    if (empty($selected_needs)) {
        $error = "Please select at least one need to perform bulk action.";
    } else {
        try {
            $db->begin_transaction();
            
            $placeholders = str_repeat('?,', count($selected_needs) - 1) . '?';
            
            if ($action === 'approve') {
                $query = "UPDATE needs SET status = 'Approved' WHERE need_id IN ($placeholders)";
                $stmt = $db->prepare($query);
                $stmt->bind_param(str_repeat('i', count($selected_needs)), ...$selected_needs);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                $success = "✅ Successfully approved $affected need(s)!";
            } 
            elseif ($action === 'reject') {
                $query = "UPDATE needs SET status = 'Rejected' WHERE need_id IN ($placeholders)";
                $stmt = $db->prepare($query);
                $stmt->bind_param(str_repeat('i', count($selected_needs)), ...$selected_needs);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                $success = "✅ Successfully rejected $affected need(s)!";
            }
            
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error performing bulk action: " . $e->getMessage();
        }
    }
}

/* ----------------------------------------
   SINGLE NEED STATUS UPDATE
---------------------------------------- */
if (isset($_GET['update_status']) && isset($_GET['need_id']) && isset($_GET['new_status'])) {
    $need_id = intval($_GET['need_id']);
    $new_status = $_GET['new_status'];
    
    $valid_statuses = ['Pending', 'Approved', 'Rejected'];
    
    if (in_array($new_status, $valid_statuses)) {
        try {
            $update_query = "UPDATE needs SET status = ? WHERE need_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("si", $new_status, $need_id);
            
            if ($stmt->execute()) {
                $success = "✅ Need #$need_id status updated to '$new_status'!";
            }
            $stmt->close();
        } catch (Exception $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } else {
        $error = "Invalid status value.";
    }
}

/* ----------------------------------------
   GET ALL DISASTERS FROM API
---------------------------------------- */
if ($disasterApiResult['success'] && !empty($disasterApiResult['data'])) {
    $apiDisasters = $disasterApiResult['data'];
    
    // Process API disasters and merge with local needs data
    foreach ($apiDisasters as $apiDisaster) {
        // Map API fields to your system's field names
        $disaster_id = $apiDisaster['disaster_id'] ?? null;
        
        if ($disaster_id) {
            // Count needs from your local database for this disaster
            try {
                $count_query = "
                    SELECT 
                        COUNT(*) as total_needs,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
                    FROM needs
                    WHERE disaster_id = ?
                ";
                
                $stmt = $db->prepare($count_query);
                $stmt->bind_param("i", $disaster_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $counts = $result->fetch_assoc();
                $stmt->close();
                
                // Merge API data with local counts
                $disasters[] = [
                    'disaster_id' => $disaster_id,
                    'Disaster_Name' => $apiDisaster['disaster_name'] ?? 'Unknown',
                    'Location' => $apiDisaster['district'] ?? 'Unknown',
                    'Disaster_Type' => $apiDisaster['severity'] ?? 'Unknown',
                    'description' => $apiDisaster['description'] ?? '',
                    'status' => $apiDisaster['status'] ?? 'Unknown',
                    'start_date' => $apiDisaster['start_date'] ?? null,
                    'end_date' => $apiDisaster['end_date'] ?? null,
                    'affected_people' => $apiDisaster['affected_people'] ?? 0,
                    'alert_message' => $apiDisaster['alert_message'] ?? '',
                    'total_needs' => $counts['total_needs'] ?? 0,
                    'pending' => $counts['pending'] ?? 0,
                    'approved' => $counts['approved'] ?? 0,
                    'rejected' => $counts['rejected'] ?? 0
                ];
            } catch (Exception $e) {
                // Skip this disaster if there's an error
                continue;
            }
        }
    }
} else {
    // Fallback to local database if API fails
    try {
        $disasters_query = "
            SELECT d.disaster_id, d.Disaster_Name, d.Location, d.Disaster_Type,
                   COUNT(n.need_id) as total_needs,
                   SUM(CASE WHEN n.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                   SUM(CASE WHEN n.status = 'Approved' THEN 1 ELSE 0 END) as approved,
                   SUM(CASE WHEN n.status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM disaster d
            LEFT JOIN needs n ON d.disaster_id = n.disaster_id
            GROUP BY d.disaster_id
            ORDER BY d.disaster_id DESC
        ";
        
        $result = $db->query($disasters_query);
        $disasters = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } catch (Exception $e) {
        $error = "Error loading disasters: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET NEEDS FOR SELECTED DISASTER
---------------------------------------- */
$selected_disaster_id = $_GET['disaster_id'] ?? null;
$selected_status_filter = $_GET['status_filter'] ?? 'Pending';

if ($selected_disaster_id) {
    try {
        $needs_query = "
            SELECT n.need_id, n.victim_id, n.resource_id, n.disaster_id,
                   n.quantity_needed, n.priority, n.status, n.distribution_id,
                   v.name as victim_name, v.address, v.age, v.family_size,
                   r.name as resource_name, r.type as resource_type, r.unit,
                   r.quantity_available
            FROM needs n
            JOIN victim v ON n.victim_id = v.victim_id
            JOIN resource r ON n.resource_id = r.resource_id
            WHERE n.disaster_id = ?
        ";
        
        if ($selected_status_filter !== 'all') {
            $needs_query .= " AND n.status = ?";
        }
        
        $needs_query .= " ORDER BY 
            CASE n.priority
                WHEN 'Urgent' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                WHEN 'Low' THEN 4
            END,
            n.need_id ASC
        ";
        
        $stmt = $db->prepare($needs_query);
        
        if ($selected_status_filter !== 'all') {
            $stmt->bind_param("is", $selected_disaster_id, $selected_status_filter);
        } else {
            $stmt->bind_param("i", $selected_disaster_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $needs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
            FROM needs
            WHERE disaster_id = ?
        ";
        
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->bind_param("i", $selected_disaster_id);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $statistics = $stats_result->fetch_assoc();
        $stats_stmt->close();
        
    } catch (Exception $e) {
        $error = "Error loading needs: " . $e->getMessage();
    }
}

/* ----------------------------------------
   HELPER FUNCTIONS
---------------------------------------- */
function getStatusBadgeClass($status) {
    $classes = [
        'Pending' => 'badge-pending',
        'Approved' => 'badge-approved',
        'Rejected' => 'badge-rejected',
        'Active' => 'badge-approved',
        'Under Control' => 'badge-pending',
        'Ended' => 'badge-rejected'
    ];
    return $classes[$status] ?? 'badge-default';
}

function getPriorityBadgeClass($priority) {
    $classes = [
        'Urgent' => 'badge-urgent',
        'High' => 'badge-high',
        'Medium' => 'badge-medium',
        'Low' => 'badge-low'
    ];
    return $classes[$priority] ?? 'badge-default';
}

function getSeverityBadgeClass($severity) {
    $classes = [
        'Low' => 'badge-low',
        'Medium' => 'badge-medium',
        'High' => 'badge-high'
    ];
    return $classes[$severity] ?? 'badge-default';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Needs - Disaster Relief Distribution System</title>
    <link rel="stylesheet" href="../css/needs.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="header">
            <div class="header-content">
                <h1><i class="fas fa-clipboard-check"></i> Manage Needs</h1>
                <p>Approve/reject needs before creating distributions</p>
            </div>
            <div class="header-actions">
                <a href="distribution_main.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <?php if ($selected_disaster_id && $statistics && $statistics['approved'] > 0): ?>
                    <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Create Distribution Plan
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Status Banner -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <?php if ($disasterApiResult['success']): ?>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.5em;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1em;">Disaster API Connected</div>
                        <div style="font-size: 0.9em; opacity: 0.9;"><?php echo count($disasterApiResult['data']); ?> disasters loaded from external system</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.5em;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1em;">Disaster API Error</div>
                        <div style="font-size: 0.9em; opacity: 0.9;">Using local database fallback</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($needsApiResult['success']): ?>
            <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle" style="font-size: 1.5em;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1em;">Needs API Connected</div>
                        <div style="font-size: 0.9em; opacity: 0.9;">Additional data available</div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle" style="font-size: 1.5em;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 1.1em;">Needs API Unavailable</div>
                        <div style="font-size: 0.9em; opacity: 0.9;">Using local data only</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Disaster Selection Section -->
        <div class="card-3d" style="margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div>
                    <h2><i class="fas fa-exclamation-triangle"></i> Select Disaster</h2>
                    <p>Choose a disaster to manage its needs (Data from external API)</p>
                </div>
                <?php if ($disasterApiResult['success']): ?>
                <div style="background: #e8f5e9; color: #2e7d32; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-database"></i> External API Active
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($disasters)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No disasters found</h3>
                    <p>No disaster data available from the API or local database.</p>
                </div>
            <?php else: ?>
                <div class="table-container" style="max-height: 400px;">
                    <table class="needs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Disaster Name</th>
                                <th>District</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Affected</th>
                                <th>Total Needs</th>
                                <th>Pending</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($disasters as $disaster): ?>
                            <tr>
                                <td><strong>#<?php echo $disaster['disaster_id']; ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($disaster['Disaster_Name']); ?></div>
                                    <?php if (!empty($disaster['description'])): ?>
                                    <div style="font-size: 0.85em; color: #7f8c8d; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($disaster['description']); ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($disaster['Location']); ?></td>
                                <td>
                                    <span class="<?php echo getSeverityBadgeClass($disaster['Disaster_Type']); ?>">
                                        <?php echo htmlspecialchars($disaster['Disaster_Type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo getStatusBadgeClass($disaster['status'] ?? 'Unknown'); ?>">
                                        <?php echo htmlspecialchars($disaster['status'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo number_format($disaster['affected_people']); ?></strong> people
                                </td>
                                <td><strong><?php echo $disaster['total_needs']; ?></strong></td>
                                <td>
                                    <?php if ($disaster['pending'] > 0): ?>
                                        <span class="badge-pending"><?php echo $disaster['pending']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['approved'] > 0): ?>
                                        <span class="badge-approved"><?php echo $disaster['approved']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($disaster['rejected'] > 0): ?>
                                        <span class="badge-rejected"><?php echo $disaster['rejected']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bdc3c7;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?disaster_id=<?php echo $disaster['disaster_id']; ?>" 
                                       class="btn-sm btn-primary" style="text-decoration: none;">
                                        <i class="fas fa-eye"></i> View Needs
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_disaster_id && !empty($statistics)): ?>
        <!-- Get selected disaster info for display -->
        <?php 
        $selected_disaster_info = null;
        foreach ($disasters as $d) {
            if ($d['disaster_id'] == $selected_disaster_id) {
                $selected_disaster_info = $d;
                break;
            }
        }
        ?>
        
        <?php if ($selected_disaster_info): ?>
        <!-- Selected Disaster Info Card -->
        <div class="card-3d" style="margin-bottom: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
            <h3 style="color: white; margin-bottom: 15px;">
                <i class="fas fa-info-circle"></i> Selected Disaster Information
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Disaster Name</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Disaster_Name']); ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">District</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Location']); ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Severity</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['Disaster_Type']); ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Status</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo htmlspecialchars($selected_disaster_info['status'] ?? 'Unknown'); ?></div>
                </div>
                <div>
                    <div style="opacity: 0.9; font-size: 0.9em;">Affected People</div>
                    <div style="font-weight: 600; font-size: 1.1em;"><?php echo number_format($selected_disaster_info['affected_people']); ?></div>
                </div>
            </div>
            <?php if (!empty($selected_disaster_info['alert_message'])): ?>
            <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: 8px;">
                <strong><i class="fas fa-bell"></i> Alert:</strong> <?php echo htmlspecialchars($selected_disaster_info['alert_message']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-label">Total Needs</div>
                <div class="stat-value"><?php echo $statistics['total']; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
                <div class="stat-value"><?php echo $statistics['pending']; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label"><i class="fas fa-check-circle"></i> Approved</div>
                <div class="stat-value"><?php echo $statistics['approved']; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected</div>
                <div class="stat-value"><?php echo $statistics['rejected']; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" id="filter-form">
                <input type="hidden" name="disaster_id" value="<?php echo $selected_disaster_id; ?>">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Disaster</label>
                        <select class="form-control" name="disaster_id" onchange="this.form.submit()">
                            <?php foreach ($disasters as $disaster): ?>
                            <option value="<?php echo $disaster['disaster_id']; ?>"
                                    <?php echo $selected_disaster_id == $disaster['disaster_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disaster['Disaster_Name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status Filter</label>
                        <select class="form-control" name="status_filter" onchange="this.form.submit()">
                            <option value="all" <?php echo $selected_status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $selected_status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $selected_status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $selected_status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" 
                                onclick="window.location.href='?disaster_id=<?php echo $selected_disaster_id; ?>'">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Form -->
        <form method="POST" id="bulk-action-form">
            <div class="bulk-actions-bar" id="bulk-actions-bar">
                <div>
                    <strong><i class="fas fa-check-square"></i> 
                    <span id="selected-count">0</span> need(s) selected</strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="bulk_action" value="approve" 
                            class="btn btn-success"
                            onclick="return confirm('Approve selected needs?')">
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                    <button type="submit" name="bulk_action" value="reject" 
                            class="btn btn-danger"
                            onclick="return confirm('Reject selected needs?')">
                        <i class="fas fa-times"></i> Reject Selected
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times"></i> Clear Selection
                    </button>
                </div>
            </div>

            <!-- Needs Table -->
            <div class="card-3d">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">
                        <i class="fas fa-list"></i> Needs List 
                        <span style="font-size: 0.7em; color: #7f8c8d;">
                            (<?php echo count($needs); ?> needs)
                        </span>
                    </h2>
                    <div>
                        <label style="display: flex; align-items: center; font-weight: 600; gap: 10px;">
                            <input type="checkbox" id="select-all" style="transform: scale(1.2);">
                            Select All (<?php echo count($needs); ?> needs)
                        </label>
                    </div>
                </div>
                
                <?php if (empty($needs)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No needs found</h3>
                        <p>
                            <?php if ($selected_status_filter !== 'all'): ?>
                                No <?php echo strtolower($selected_status_filter); ?> needs for this disaster.
                            <?php else: ?>
                                No needs registered for this disaster yet.
                            <?php endif; ?>
                        </p>
                        <?php if ($selected_status_filter !== 'all'): ?>
                            <a href="?disaster_id=<?php echo $selected_disaster_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear Filter
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="needs-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="select-all-header">
                                    </th>
                                    <th>ID</th>
                                    <th>Victim</th>
                                    <th>Resource</th>
                                    <th>Quantity</th>
                                    <th>Available</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($needs as $need): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_needs[]" 
                                               value="<?php echo $need['need_id']; ?>"
                                               class="need-checkbox">
                                    </td>
                                    <td><strong>#<?php echo $need['need_id']; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($need['victim_name']); ?></div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">
                                            <i class="fas fa-home"></i> <?php echo htmlspecialchars($need['address']); ?>
                                        </div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">
                                            <i class="fas fa-users"></i> Family: <?php echo $need['family_size']; ?> people
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($need['resource_name']); ?></div>
                                        <div style="font-size: 0.85em; color: #7f8c8d;">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($need['resource_type']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo $need['quantity_needed']; ?></strong> 
                                        <?php echo htmlspecialchars($need['unit']); ?>
                                    </td>
                                    <td style="color: <?php echo $need['quantity_available'] >= $need['quantity_needed'] ? '#27ae60' : '#e74c3c'; ?>;">
                                        <i class="fas fa-<?php echo $need['quantity_available'] >= $need['quantity_needed'] ? 'check' : 'exclamation-triangle'; ?>"></i>
                                        <?php echo $need['quantity_available']; ?> <?php echo htmlspecialchars($need['unit']); ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo getPriorityBadgeClass($need['priority']); ?>">
                                            <?php echo $need['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo getStatusBadgeClass($need['status']); ?>">
                                            <?php echo $need['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($need['status'] !== 'Approved'): ?>
                                                <button type="button" class="btn-sm btn-approve" 
                                                        onclick="updateStatus(<?php echo $need['need_id']; ?>, 'Approved')"
                                                        title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($need['status'] !== 'Rejected'): ?>
                                                <button type="button" class="btn-sm btn-reject" 
                                                        onclick="updateStatus(<?php echo $need['need_id']; ?>, 'Rejected')"
                                                        title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($need['status'] !== 'Pending'): ?>
                                                <button type="button" class="btn-sm btn-pending" 
                                                        onclick="updateStatus(<?php echo $need['need_id']; ?>, 'Pending')"
                                                        title="Set to Pending">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($statistics['approved'] > 0): ?>
                        <div class="quick-actions">
                            <a href="create_distribution_plan.php?disaster_id=<?php echo $selected_disaster_id; ?>" 
                               class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Create Distribution Plan
                                <span class="badge-approved" style="margin-left: 10px;">
                                    <?php echo $statistics['approved']; ?> approved needs
                                </span>
                            </a>
                            <a href="distribution_main.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert" style="background: #fff3cd; color: #856404; margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No approved needs yet.</strong> Approve at least one need to create a distribution plan.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>

        <!-- Quick Links Footer -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6;">
            <h3><i class="fas fa-link"></i> Quick Links</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <a href="distribution_main.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-label" style="color: #3498db;"><i class="fas fa-tachometer-alt"></i> Dashboard</div>
                    <div style="font-size: 1.1em; margin-top: 10px; color: #2c3e50;">View Distribution Dashboard</div>
                </a>
                <a href="create_distribution.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-label" style="color: #27ae60;"><i class="fas fa-plus-circle"></i> Create Distribution</div>
                    <div style="font-size: 1.1em; margin-top: 10px; color: #2c3e50;">Plan New Distribution</div>
                </a>
            </div>
        </div>

        <!-- API Debug Section (Optional - Remove in production) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
            <h3><i class="fas fa-bug"></i> API Debug Information</h3>
            
            <div style="margin-top: 15px;">
                <h4>Disaster API Response:</h4>
                <pre style="background: white; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 300px;"><?php echo json_encode($disasterApiResult, JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <div style="margin-top: 15px;">
                <h4>Needs API Response:</h4>
                <pre style="background: white; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 300px;"><?php echo json_encode($needsApiResult, JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <div style="margin-top: 15px;">
                <h4>Processed Disasters:</h4>
                <pre style="background: white; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 300px;"><?php echo json_encode($disasters, JSON_PRETTY_PRINT); ?></pre>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsBar();
        });
        
        document.getElementById('select-all-header')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.need-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            document.getElementById('select-all').checked = this.checked;
            updateBulkActionsBar();
        });
        
        // Update bulk actions bar when checkboxes change
        document.querySelectorAll('.need-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsBar);
        });
        
        function updateBulkActionsBar() {
            const checkedBoxes = document.querySelectorAll('.need-checkbox:checked');
            const count = checkedBoxes.length;
            const bulkBar = document.getElementById('bulk-actions-bar');
            const countSpan = document.getElementById('selected-count');
            
            if (count > 0) {
                bulkBar.classList.add('active');
                countSpan.textContent = count;
            } else {
                bulkBar.classList.remove('active');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.need-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            document.getElementById('select-all').checked = false;
            document.getElementById('select-all-header').checked = false;
            updateBulkActionsBar();
        }
        
        function updateStatus(needId, newStatus) {
            if (confirm(`Change need #${needId} status to '${newStatus}'?`)) {
                window.location.href = `?disaster_id=<?php echo $selected_disaster_id ?? ''; ?>&update_status=1&need_id=${needId}&new_status=${newStatus}`;
            }
        }
        
        // Initialize bulk actions bar state
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkActionsBar();
        });
    </script>
</body>
</html>