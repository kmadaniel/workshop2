<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$disasters = [];
$approved_needs = [];
$error = '';
$success = '';
$schema_errors = [];

/* ----------------------------------------
   VALIDATE DATABASE SCHEMA
---------------------------------------- */
// Check distribution table columns
$required_dist_columns = ['distribution_id', 'disaster_id', 'date', 'status', 'comments'];
$table_check = $db->query("DESCRIBE distribution");
if ($table_check) {
    $existing_columns = [];
    while ($row = $table_check->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    $missing_dist_columns = array_diff($required_dist_columns, $existing_columns);
    if (!empty($missing_dist_columns)) {
        $schema_errors[] = "Missing columns in 'distribution' table: " . implode(', ', $missing_dist_columns);
    }
}

// Check if needs table has distribution_id column
$needs_check = $db->query("SHOW COLUMNS FROM needs LIKE 'distribution_id'");
if ($needs_check && $needs_check->num_rows == 0) {
    $schema_errors[] = "'distribution_id' column missing in 'needs' table";
}

// Check if resource table has quantity_reserved column
$resource_check = $db->query("SHOW COLUMNS FROM resource LIKE 'quantity_reserved'");
if ($resource_check && $resource_check->num_rows == 0) {
    $schema_errors[] = "'quantity_reserved' column missing in 'resource' table";
}

// Display schema errors if any
if (!empty($schema_errors)) {
    $error = "<strong>Database Schema Errors Detected:</strong><br>";
    $error .= "<ul>";
    foreach ($schema_errors as $schema_error) {
        $error .= "<li>$schema_error</li>";
    }
    $error .= "</ul>";
    $error .= "<div class='mt-2'><strong>Solution:</strong> Run the following SQL commands:</div>";
    $error .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
    $error .= "-- Add distribution_id to needs table\n";
    $error .= "ALTER TABLE needs ADD COLUMN distribution_id INT NULL;\n";
    $error .= "ALTER TABLE needs ADD FOREIGN KEY (distribution_id) REFERENCES distribution(distribution_id) ON DELETE SET NULL;\n\n";
    $error .= "-- Add quantity_reserved to resource table\n";
    $error .= "ALTER TABLE resource ADD COLUMN quantity_reserved INT DEFAULT 0;\n\n";
    $error .= "-- Add status column to distribution if missing\n";
    $error .= "ALTER TABLE distribution ADD COLUMN status VARCHAR(50) DEFAULT 'Planning';\n\n";
    $error .= "-- Add comments column to distribution if missing\n";
    $error .= "ALTER TABLE distribution ADD COLUMN comments TEXT NULL;\n";
    $error .= "</pre>";
}

/* ----------------------------------------
   UPDATE NEEDS TO APPROVED STATUS (IF REQUESTED)
---------------------------------------- */
if (isset($_GET['update_to_approved']) && isset($_GET['disaster_id'])) {
    try {
        $update_query = "UPDATE needs SET status = 'Approved' WHERE disaster_id = ? AND status = 'fulfilled'";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param("i", $_GET['disaster_id']);
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $success = "✅ Updated $affected_rows needs to 'Approved' status successfully!";
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error updating needs: " . $e->getMessage();
    }
}

/* ----------------------------------------
   FETCH ACTIVE DISASTERS WITH APPROVED NEEDS
---------------------------------------- */
try {
    // Get disasters that have approved needs
    $disasters_query = "
        SELECT DISTINCT d.disaster_id, d.Disaster_Name, d.Location, 
               COUNT(n.need_id) as approved_needs_count
        FROM disaster d
        LEFT JOIN needs n ON d.disaster_id = n.disaster_id 
            AND n.status = 'Approved'
        WHERE n.need_id IS NOT NULL
        GROUP BY d.disaster_id, d.Disaster_Name, d.Location
        HAVING COUNT(n.need_id) > 0
        ORDER BY d.disaster_id DESC
    ";
    $result = $db->query($disasters_query);
    $disasters = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
} catch (Exception $e) {
    $error = "Error loading disasters: " . $e->getMessage();
}

/* ----------------------------------------
   GENERATE NEXT DISTRIBUTION ID
---------------------------------------- */
function generateDistributionId($db) {
    // Check the last distribution ID
    $query = "SELECT MAX(distribution_id) as max_id FROM distribution";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['max_id']) {
            $last_id = $row['max_id'];
            $number = intval($last_id) + 1;
            return $number; // Return numeric ID as per your table structure
        }
    }
    
    return 1; // Start from 1
}

/* ----------------------------------------
   GET APPROVED NEEDS FOR SELECTED DISASTER
---------------------------------------- */
if (isset($_GET['disaster_id']) && is_numeric($_GET['disaster_id'])) {
    $disaster_id = $_GET['disaster_id'];
    
    try {
        $needs_query = "
            SELECT n.need_id, n.victim_id, n.resource_id, n.quantity_needed, n.priority, n.status,
                   v.name as victim_name, v.address,
                   r.name as resource_name, r.type, r.unit,
                   r.quantity_available as resource_available
            FROM needs n
            JOIN victim v ON n.victim_id = v.victim_id
            JOIN resource r ON n.resource_id = r.resource_id
            WHERE n.disaster_id = ? AND n.status = 'Approved'
            ORDER BY 
                CASE n.priority
                    WHEN 'Urgent' THEN 1
                    WHEN 'High' THEN 2
                    WHEN 'Medium' THEN 3
                    WHEN 'Low' THEN 4
                END,
                n.need_id ASC
        ";
        
        $stmt = $db->prepare($needs_query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $db->error);
        }
        
        $stmt->bind_param("i", $disaster_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result === false) {
            throw new Exception("Query failed: " . $stmt->error);
        }
        
        $approved_needs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // If no approved needs found, show what's available
        if (empty($approved_needs)) {
            $debug_query = "
                SELECT n.need_id, n.victim_id, n.resource_id, n.quantity_needed, n.priority, n.status,
                       v.name as victim_name,
                       r.name as resource_name
                FROM needs n
                JOIN victim v ON n.victim_id = v.victim_id
                JOIN resource r ON n.resource_id = r.resource_id
                WHERE n.disaster_id = ?
                ORDER BY n.need_id ASC
            ";
            
            $debug_stmt = $db->prepare($debug_query);
            $debug_stmt->bind_param("i", $disaster_id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            $all_needs = $debug_result->fetch_all(MYSQLI_ASSOC);
            $debug_stmt->close();
            
            if (!empty($all_needs)) {
                $error .= "<br><strong>Found " . count($all_needs) . " needs for this disaster, but none are 'Approved'.</strong>";
                $status_counts = [];
                foreach ($all_needs as $need) {
                    $status = $need['status'];
                    if (!isset($status_counts[$status])) {
                        $status_counts[$status] = 0;
                    }
                    $status_counts[$status]++;
                }
                $error .= "<br>Current statuses: ";
                foreach ($status_counts as $status => $count) {
                    $error .= "$status ($count), ";
                }
                $error .= "<br>Click the 'Update Needs' button below to change 'fulfilled' to 'Approved'.";
            }
        }
        
    } catch (Exception $e) {
        $error = "Error loading approved needs: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET PPS LOCATIONS
---------------------------------------- */
$pps_locations = [
    'Dewan Serbaguna Masjid Tanah',
    'Sekolah Kebangsaan Alor Gajah',
    'Dewan Komuniti Taman Seri Bayu',
    'Balai Raya Kampung Baru',
    'Gelanggang Futsal Bandaraya'
];

/* ----------------------------------------
   FORM SUBMISSION: CREATE DISTRIBUTION PLAN
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_distribution'])) {
    
    // Check schema before processing
    if (!empty($schema_errors)) {
        $error = "Cannot create distribution: Database schema errors exist. Please fix schema first.";
    } else {
        
        $disaster_id = $_POST['disaster_id'];
        $distribution_date = $_POST['distribution_date'];
        $distribution_time = $_POST['distribution_time'];
        $location = $_POST['location'];
        $coordinator_name = $_POST['coordinator_name'];
        $coordinator_contact = $_POST['coordinator_contact'];
        $selected_needs = $_POST['selected_needs'] ?? [];
        $estimated_duration = $_POST['estimated_duration'];
        $volunteers_needed = $_POST['volunteers_needed'];
        $comments = $_POST['comments'] ?? '';
        
        try {
            // Validate selected needs
            if (empty($selected_needs)) {
                throw new Exception("Please select at least one approved need.");
            }
            
            // Check if all selected needs are approved
            $placeholders = str_repeat('?,', count($selected_needs) - 1) . '?';
            $check_needs_query = "SELECT COUNT(*) as count FROM needs WHERE need_id IN ($placeholders) AND status != 'Approved'";
            $check_stmt = $db->prepare($check_needs_query);
            $check_stmt->bind_param(str_repeat('i', count($selected_needs)), ...$selected_needs);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if ($check_data['count'] > 0) {
                throw new Exception("Some selected needs are not approved.");
            }
            
            // Calculate total resources needed
            $resources_query = "
                SELECT n.resource_id, r.name, SUM(n.quantity_needed) as total_needed, 
                       r.quantity_available, r.unit
                FROM needs n
                JOIN resource r ON n.resource_id = r.resource_id
                WHERE n.need_id IN ($placeholders)
                GROUP BY n.resource_id
            ";
            
            $stmt = $db->prepare($resources_query);
            $stmt->bind_param(str_repeat('i', count($selected_needs)), ...$selected_needs);
            $stmt->execute();
            $resources_result = $stmt->get_result();
            $resources_needed = $resources_result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Check resource availability
            $insufficient_resources = [];
            foreach ($resources_needed as $resource) {
                if ($resource['total_needed'] > $resource['quantity_available']) {
                    $insufficient_resources[] = $resource;
                }
            }
            
            // Show warning but continue if resources insufficient
            if (!empty($insufficient_resources)) {
                $warning = "⚠️ Insufficient resources: ";
                foreach ($insufficient_resources as $resource) {
                    $warning .= "{$resource['name']} (Needed: {$resource['total_needed']}, Available: {$resource['quantity_available']}), ";
                }
                $error = $warning . " You can still proceed with partial distribution.";
            }
            
            // Start transaction
            $db->begin_transaction();
            
            // Generate distribution ID
            $distribution_id = generateDistributionId($db);
            
            // Create distribution record
            $combined_datetime = $distribution_date . ' ' . $distribution_time . ':00';
            
            $distribution_query = "
                INSERT INTO distribution (
                    distribution_id, disaster_id, date, 
                    status, comments, quantity_sent
                ) VALUES (?, ?, ?, 'Planning', ?, 0)
            ";
            
            // Prepare comments with all plan details
            $plan_details = "DISTRIBUTION PLAN\n";
            $plan_details .= "=================\n";
            $plan_details .= "Time: $distribution_date at $distribution_time\n";
            $plan_details .= "Location: $location\n";
            $plan_details .= "Coordinator: $coordinator_name ($coordinator_contact)\n";
            $plan_details .= "Estimated Duration: {$estimated_duration} hours\n";
            $plan_details .= "Volunteers Needed: $volunteers_needed\n";
            $plan_details .= "Selected Needs: " . implode(', ', $selected_needs) . "\n";
            if (!empty($comments)) {
                $plan_details .= "Additional Comments: $comments\n";
            }
            $plan_details .= "Plan Created: " . date('Y-m-d H:i:s');
            
            $dist_stmt = $db->prepare($distribution_query);
            $dist_stmt->bind_param(
                "iiss",
                $distribution_id,
                $disaster_id,
                $combined_datetime,
                $plan_details
            );
            
            if (!$dist_stmt->execute()) {
                throw new Exception("Error creating distribution plan: " . $dist_stmt->error);
            }
            $dist_stmt->close();
            
            // Update the needs table to link them to this distribution
            foreach ($selected_needs as $need_id) {
                $update_need_query = "UPDATE needs SET distribution_id = ?, status = 'Scheduled' WHERE need_id = ?";
                $update_stmt = $db->prepare($update_need_query);
                $update_stmt->bind_param("ii", $distribution_id, $need_id);
                if (!$update_stmt->execute()) {
                    throw new Exception("Error linking need $need_id to distribution: " . $update_stmt->error);
                }
                $update_stmt->close();
            }
            
            // Update resource quantities - reserve resources
            foreach ($resources_needed as $resource) {
                // Calculate how much we can actually reserve
                $quantity_to_reserve = min($resource['total_needed'], $resource['quantity_available']);
                
                if ($quantity_to_reserve > 0) {
                    $update_query = "
                        UPDATE resource 
                        SET quantity_reserved = quantity_reserved + ?,
                            quantity_available = quantity_available - ?
                        WHERE resource_id = ? AND quantity_available >= ?
                    ";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->bind_param("iiii", 
                        $quantity_to_reserve,
                        $quantity_to_reserve,
                        $resource['resource_id'],
                        $quantity_to_reserve
                    );
                    
                    if (!$update_stmt->execute()) {
                        throw new Exception("Failed to reserve resources for " . $resource['name']);
                    }
                    
                    if ($update_stmt->affected_rows == 0) {
                        throw new Exception("Could not reserve sufficient quantity for " . $resource['name']);
                    }
                    
                    $update_stmt->close();
                }
            }
            
            $db->commit();
            
            // Generate a display ID for user (DIST001 format)
            $display_id = 'DIST' . str_pad($distribution_id, 3, '0', STR_PAD_LEFT);
            
            $success = "✅ Distribution plan created successfully!";
            $success .= "<br><strong>Distribution ID: $display_id (Database ID: $distribution_id)</strong>";
            $success .= "<br><small>Date: $distribution_date at $distribution_time</small>";
            $success .= "<br><small>Location: $location</small>";
            $success .= "<br><small>Families: " . count(array_unique(array_column($approved_needs, 'victim_id'))) . "</small>";
            $success .= "<br><small>Total Needs: " . count($selected_needs) . "</small>";
            
            $success .= "<br><div class='mt-3'>";
            $success .= "<a href='assign_volunteer.php?distribution_id=$distribution_id' class='btn btn-success'>Assign Volunteers</a> ";
            $success .= "<a href='view_distribution.php?id=$distribution_id' class='btn btn-info'>View Details</a> ";
            $success .= "<a href='create_distribution.php' class='btn btn-secondary'>Create Another</a>";
            $success .= "</div>";
            
        } catch (Exception $e) {
            if (isset($db) && method_exists($db, 'rollback')) {
                $db->rollback();
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Distribution Plan</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="/WORKSHOP2/css/header.css">
     <style>
        .need-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .need-card:hover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }
        .need-card.selected {
            border-color: #27ae60;
            background-color: #e8f6f3;
        }
        .priority-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        .badge-urgent {
            background: #e74c3c;
        }
        .badge-high {
            background: #f39c12;
        }
        .badge-medium {
            background: #3498db;
        }
        .badge-low {
            background: #2ecc71;
        }
        .select-all-container {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-approved {
            background: #27ae60;
            color: white;
        }
        .status-fulfilled {
            background: #3498db;
            color: white;
        }
        .debug-box {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .debug-title {
            font-weight: bold;
            color: #666;
            margin-bottom: 10px;
        }
        .update-button {
            margin-top: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .schema-error {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        /* Action Cards */
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #e0e6ed;
            display: block;
            color: #2c3e50;
            height: 100%;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            border-color: #3498db;
        }
        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 20px;
        }
        .action-card h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1rem;
        }
        .action-card p {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin: 0;
        }
        .action-card:hover h4 {
            color: #3498db;
        }
        /* Top Action Buttons - Horizontal Layout */
        .top-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .top-action-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #e0e6ed;
            display: block;
            color: #2c3e50;
            height: 100%;
        }
        .top-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        .top-action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: white;
            font-size: 18px;
        }
        .top-action-card h5 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .top-action-card:hover h5 {
            color: #3498db;
        }
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e0e6ed;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            border-color: #3498db;
            transform: translateY(-3px);
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .stat-number {
            color: #2c3e50;
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-desc {
            color: #95a5a6;
            font-size: 0.8rem;
        }
        /* Back to Main Button */
        .back-to-main {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
        }
        .back-to-main:hover {
            text-decoration: underline;
        }
        /* Post-creation Action Buttons */
        .post-creation-actions {
            background: #e8f6f3;
            border: 2px solid #27ae60;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .post-creation-actions h4 {
            color: #27ae60;
            margin-bottom: 15px;
        }
        .post-creation-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create Distribution Plan</h1>
            <p>Organize relief operations by creating distribution plans for approved needs</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ✨ TOP ACTION BUTTONS -->
        <div class="top-actions-grid">
            
            <!-- Back to Dashboard -->
            <a href="distribution_main.php" class="top-action-card" style="text-decoration: none;">
                <div class="top-action-icon" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d);">
                    <i class="fas fa-home"></i>
                </div>
                <h5>Dashboard</h5>
            </a>
        </div>

        <!-- Schema Status -->
        <?php if (!empty($schema_errors)): ?>
        <div class="schema-error">
            <h3 style="color: #856404; margin-top: 0;">⚠️ Database Schema Issues</h3>
            <p>Your database needs to be updated before you can create distribution plans.</p>
            <?php echo $error; ?>
        </div>
        <?php else: ?>
        <!-- Debug Info -->
        <div class="debug-box">
            <div class="debug-title">System Status:</div>
            <div>✅ Database schema validated successfully</div>
            <div>✅ Using existing database tables: <code>distribution</code>, <code>needs</code>, <code>resource</code></div>
            <div>✅ Next Distribution ID: <strong><?php echo 'DIST' . str_pad(generateDistributionId($db), 3, '0', STR_PAD_LEFT); ?></strong></div>
            <div>ℹ️ Needs must have <strong>status = 'Approved'</strong> to appear here.</div>
        </div>
        <?php endif; ?>

        <?php if (empty($schema_errors)): ?>
        <!-- Step 1: Select Disaster -->
        <div class="card-3d">
            <h2>Step 1: Select Disaster</h2>
            <form method="GET" action="" id="disaster-selection-form">
                <div class="form-group">
                    <label class="form-label">Select Disaster *</label>
                    <select class="form-control" name="disaster_id" required onchange="this.form.submit()">
                        <option value="">Choose a disaster...</option>
                        <?php if (empty($disasters)): ?>
                            <option value="" disabled>No disasters with approved needs found</option>
                        <?php else: ?>
                            <?php foreach ($disasters as $disaster): ?>
                            <option value="<?php echo $disaster['disaster_id']; ?>"
                                    <?php echo ($_GET['disaster_id'] ?? '') == $disaster['disaster_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disaster['Disaster_Name']); ?>
                                - <?php echo htmlspecialchars($disaster['Location']); ?>
                                (<?php echo $disaster['approved_needs_count']; ?> approved needs)
                            </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small class="form-text">
                        <?php if (empty($disasters)): ?>
                            <span style="color: #e74c3c;">
                                No disasters with approved needs found. First create needs and set status to 'Approved'.
                            </span>
                        <?php else: ?>
                            Only disasters with approved needs are shown
                        <?php endif; ?>
                    </small>
                </div>
                
                <?php if (isset($_GET['disaster_id']) && empty($approved_needs)): ?>
                <div class="update-button">
                    <a href="?disaster_id=<?php echo $_GET['disaster_id']; ?>&update_to_approved=1" 
                       class="btn btn-warning btn-sm"
                       onclick="return confirm('This will update all needs for this disaster from fulfilled to Approved. Continue?')">
                        🔄 Update Needs to "Approved" Status
                    </a>
                    <small class="form-text text-muted">
                        Click this button if you see "No approved needs" but have needs with status="fulfilled"
                    </small>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if (isset($_GET['disaster_id']) && !empty($approved_needs)): 
            // Get selected disaster info
            $selected_disaster = null;
            foreach ($disasters as $disaster) {
                if ($disaster['disaster_id'] == $_GET['disaster_id']) {
                    $selected_disaster = $disaster;
                    break;
                }
            }
        ?>
        <!-- Step 2: Select Approved Needs -->
        <div class="card-3d">
            <h2>
                Step 2: Select Approved Needs
                <?php if ($selected_disaster): ?>
                <span style="font-size: 0.8em; color: #7f8c8d;">
                    for <?php echo htmlspecialchars($selected_disaster['Disaster_Name']); ?>
                </span>
                <?php endif; ?>
                <span class="status-badge status-approved" style="font-size: 0.7em;">
                    <?php echo count($approved_needs); ?> approved needs
                </span>
            </h2>
            
            <form method="POST" id="create-distribution-form">
                <input type="hidden" name="create_distribution" value="1">
                <input type="hidden" name="disaster_id" value="<?php echo $_GET['disaster_id']; ?>">
                
                <div class="select-all-container">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="select-all-needs" class="need-checkbox" style="margin-right: 10px;">
                        <strong>Select All (<?php echo count($approved_needs); ?> approved needs)</strong>
                    </label>
                    <div style="margin-top: 5px; font-size: 0.9em; color: #666;">
                        <?php 
                        $unique_victims = array_unique(array_column($approved_needs, 'victim_id'));
                        echo "Affecting " . count($unique_victims) . " families";
                        ?>
                    </div>
                </div>
                
                <div style="max-height: 400px; overflow-y: auto; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <?php 
                    // Group needs by victim
                    $victim_needs = [];
                    foreach ($approved_needs as $need) {
                        $victim_id = $need['victim_id'];
                        if (!isset($victim_needs[$victim_id])) {
                            $victim_needs[$victim_id] = [
                                'victim_name' => $need['victim_name'],
                                'address' => $need['address'],
                                'needs' => []
                            ];
                        }
                        $victim_needs[$victim_id]['needs'][] = $need;
                    }
                    
                    foreach ($victim_needs as $victim_id => $victim_data): 
                    ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                        <div style="font-weight: bold; margin-bottom: 8px;">
                            👨‍👩‍👧‍👦 <?php echo htmlspecialchars($victim_data['victim_name']); ?>
                        </div>
                        <div style="font-size: 0.9em; color: #666; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($victim_data['address']); ?>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px;">
                            <?php foreach ($victim_data['needs'] as $need): 
                                $badge_class = 'badge-' . strtolower($need['priority']);
                            ?>
                            <div class="need-card" onclick="toggleNeed(<?php echo $need['need_id']; ?>)">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" 
                                           name="selected_needs[]" 
                                           value="<?php echo $need['need_id']; ?>" 
                                           class="need-checkbox need-<?php echo $need['need_id']; ?>"
                                           data-quantity="<?php echo $need['quantity_needed']; ?>"
                                           data-resource-id="<?php echo $need['resource_id']; ?>"
                                           data-resource-name="<?php echo htmlspecialchars($need['resource_name']); ?>"
                                           data-unit="<?php echo htmlspecialchars($need['unit']); ?>"
                                           data-available="<?php echo $need['resource_available']; ?>"
                                           onchange="updateResourceSummary()"
                                           style="margin-right: 10px;">
                                    <div style="flex-grow: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($need['resource_name']); ?></span>
                                            <span class="priority-badge <?php echo $badge_class; ?>">
                                                <?php echo $need['priority']; ?>
                                            </span>
                                        </div>
                                        <div style="margin: 5px 0;">
                                            <strong><?php echo $need['quantity_needed']; ?> <?php echo $need['unit']; ?></strong>
                                            <span style="color: #666; font-size: 0.9em;">
                                                (<?php echo $need['type']; ?>)
                                            </span>
                                        </div>
                                        <div style="font-size: 0.85em; color: <?php echo $need['resource_available'] >= $need['quantity_needed'] ? '#27ae60' : '#e74c3c'; ?>;">
                                            Stock: <?php echo $need['resource_available']; ?> <?php echo $need['unit']; ?> available
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-3">
                    <div class="alert alert-info">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                            <span>Selected: <strong id="selected-count">0</strong> needs</span>
                            <span>Families: <strong id="selected-families">0</strong> families</span>
                            <span>Resources: <strong id="selected-resources">0</strong> types</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h3>Step 3: Distribution Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Distribution Date *</label>
                            <input type="date" class="form-control" name="distribution_date" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Distribution Time *</label>
                            <input type="time" class="form-control" name="distribution_time" required 
                                   value="14:00">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Estimated Duration (hours) *</label>
                            <input type="number" class="form-control" name="estimated_duration" 
                                   min="1" max="8" required 
                                   value="2">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Volunteers Needed *</label>
                            <input type="number" class="form-control" name="volunteers_needed" 
                                   min="2" max="20" required 
                                   value="4">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Location/PPS *</label>
                            <select class="form-control" name="location" required>
                                <option value="">Select location...</option>
                                <?php foreach ($pps_locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>">
                                    <?php echo $loc; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Coordinator Name *</label>
                            <input type="text" class="form-control" name="coordinator_name" required 
                                   placeholder="e.g., Encik Ahmad">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Coordinator Contact *</label>
                            <input type="text" class="form-control" name="coordinator_contact" required 
                                   placeholder="06-XXXX XXXX">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Additional Comments</label>
                        <textarea class="form-control" name="comments" rows="2" 
                                  placeholder="Any special instructions or notes..."></textarea>
                    </div>
                </div>

                <div class="mt-4" id="resource-summary-section">
                    <h3>Step 4: Resource Summary</h3>
                    <div class="alert alert-warning" id="insufficient-warning" style="display: none;">
                        ⚠️ Insufficient resources available. You can still create a partial distribution plan.
                    </div>
                    
                    <div class="table-container">
                        <table class="resource-table" id="resource-summary" style="width: 100%;">
                            <thead>
                                <tr style="background-color: #f8f9fa;">
                                    <th style="padding: 10px; border-bottom: 1px solid #dee2e6;">Resource</th>
                                    <th style="padding: 10px; border-bottom: 1px solid #dee2e6;">Required</th>
                                    <th style="padding: 10px; border-bottom: 1px solid #dee2e6;">Available</th>
                                    <th style="padding: 10px; border-bottom: 1px solid #dee2e6;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="resource-summary-body">
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                                        Select needs above to see resource requirements
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-success btn-lg" id="create-button" disabled>
                        <span style="margin-right: 8px;">✓</span>
                        Create Distribution Plan
                    </button>
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <a href="distribution_main.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </form>
        </div>
        <?php elseif (isset($_GET['disaster_id'])): ?>
            <div class="alert alert-warning">
                <h4>⚠️ No approved needs found for this disaster</h4>
                <p>To create a distribution plan, you need needs with status = <strong>'Approved'</strong>.</p>
                
                <?php
                // Show statistics for current disaster
                if (isset($_GET['disaster_id'])) {
                    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled,
                        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending
                    FROM needs WHERE disaster_id = ?";
                    
                    $stats_stmt = $db->prepare($stats_query);
                    $stats_stmt->bind_param("i", $_GET['disaster_id']);
                    $stats_stmt->execute();
                    $stats_result = $stats_stmt->get_result();
                    $stats = $stats_result->fetch_assoc();
                    $stats_stmt->close();
                    
                    if ($stats['total'] > 0) {
                        echo "<div class='mt-3'>";
                        echo "<h5>Current Needs Status:</h5>";
                        echo "<ul>";
                        echo "<li>Total needs: " . $stats['total'] . "</li>";
                        if ($stats['approved'] > 0) {
                            echo "<li>✅ Approved: " . $stats['approved'] . "</li>";
                        }
                        if ($stats['fulfilled'] > 0) {
                            echo "<li>📦 Fulfilled: " . $stats['fulfilled'] . "</li>";
                        }
                        if ($stats['pending'] > 0) {
                            echo "<li>⏳ Pending: " . $stats['pending'] . "</li>";
                        }
                        echo "</ul>";
                        
                        if ($stats['fulfilled'] > 0) {
                            echo '<div class="mt-3">';
                            echo '<a href="?disaster_id=' . $_GET['disaster_id'] . '&update_to_approved=1" class="btn btn-warning">';
                            echo '🔄 Update ' . $stats['fulfilled'] . ' fulfilled needs to "Approved" status';
                            echo '</a>';
                            echo '<small class="form-text d-block mt-1">This allows you to create a new distribution plan for previously fulfilled needs.</small>';
                            echo '</div>';
                        }
                        echo "</div>";
                    } else {
                        echo "<p>No needs found for this disaster. Create needs first.</p>";
                    }
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="grid-3 mt-4">
            <div class="stat-card">
                <div class="stat-label">Active Disasters</div>
                <div class="stat-number"><?php echo count($disasters); ?></div>
                <div class="stat-desc">With approved needs</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Approved Needs</div>
                <div class="stat-number">
                    <?php 
                    if (isset($_GET['disaster_id']) && !empty($approved_needs)) {
                        echo count($approved_needs);
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="stat-desc">Ready for distribution</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Next ID</div>
                <div class="stat-number" style="font-size: 1.1rem; color: #2c3e50;">
                    DIST<?php echo str_pad(generateDistributionId($db), 3, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="stat-desc">Auto-generated</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle need selection
        function toggleNeed(needId) {
            const checkbox = document.querySelector(`.need-${needId}`);
            const card = checkbox.closest('.need-card');
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
            updateResourceSummary();
        }
        
        // Select all needs
        const selectAllCheckbox = document.getElementById('select-all-needs');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_needs[]"]');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    const card = checkbox.closest('.need-card');
                    if (card) {
                        card.classList.toggle('selected', this.checked);
                    }
                });
                updateResourceSummary();
            });
        }
        
        // Update resource summary
        function updateResourceSummary() {
            const checkboxes = document.querySelectorAll('input[name="selected_needs[]"]:checked');
            const selectedCount = checkboxes.length;
            
            // Calculate selected families (unique victims)
            const selectedFamilies = new Set();
            // Calculate resource types
            const resourceTypes = new Set();
            
            checkboxes.forEach(checkbox => {
                const familyDiv = checkbox.closest('[style*="background: #f9f9f9"]');
                if (familyDiv) {
                    const victimName = familyDiv.querySelector('div[style*="font-weight: bold"]').textContent;
                    selectedFamilies.add(victimName.trim().replace('👨‍👩‍👧‍👦 ', ''));
                }
                resourceTypes.add(checkbox.dataset.resourceId);
            });
            
            const selectedCountEl = document.getElementById('selected-count');
            const selectedFamiliesEl = document.getElementById('selected-families');
            const selectedResourcesEl = document.getElementById('selected-resources');
            
            if (selectedCountEl) selectedCountEl.textContent = selectedCount;
            if (selectedFamiliesEl) selectedFamiliesEl.textContent = selectedFamilies.size;
            if (selectedResourcesEl) selectedResourcesEl.textContent = resourceTypes.size;
            
            // Calculate resource requirements
            const resources = {};
            checkboxes.forEach(checkbox => {
                const resourceId = checkbox.dataset.resourceId;
                const resourceName = checkbox.dataset.resourceName;
                const quantity = parseInt(checkbox.dataset.quantity);
                const unit = checkbox.dataset.unit;
                const available = parseInt(checkbox.dataset.available);
                
                if (!resources[resourceId]) {
                    resources[resourceId] = {
                        name: resourceName,
                        total: 0,
                        unit: unit,
                        available: available,
                        resourceId: resourceId
                    };
                }
                resources[resourceId].total += quantity;
            });
            
            // Update resource summary table
            const tbody = document.getElementById('resource-summary-body');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (Object.keys(resources).length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                            Select needs above to see resource requirements
                        </td>
                    </tr>
                `;
                const warningEl = document.getElementById('insufficient-warning');
                if (warningEl) warningEl.style.display = 'none';
                
                const createBtn = document.getElementById('create-button');
                if (createBtn) createBtn.disabled = true;
                return;
            }
            
            let hasInsufficient = false;
            
            for (const [resourceId, data] of Object.entries(resources)) {
                const status = data.total <= data.available ? 'Sufficient' : 'Insufficient';
                
                if (status === 'Insufficient') {
                    hasInsufficient = true;
                }
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                        <strong>${data.name}</strong>
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                        ${data.total} ${data.unit}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                        ${data.available} ${data.unit}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                        <span style="color: ${status === 'Sufficient' ? '#27ae60' : '#e74c3c'}; font-weight: bold;">
                            ${status === 'Sufficient' ? '✓ Sufficient' : '✗ Insufficient'}
                        </span>
                    </td>
                `;
                tbody.appendChild(row);
            }
            
            // Show/hide insufficient warning
            const warningEl = document.getElementById('insufficient-warning');
            if (warningEl) {
                warningEl.style.display = hasInsufficient ? 'block' : 'none';
            }
            
            // Enable/disable create button
            const createBtn = document.getElementById('create-button');
            if (createBtn) {
                createBtn.disabled = selectedCount === 0;
            }
        }
        
        // Auto-submit form if only one disaster
        document.addEventListener('DOMContentLoaded', function() {
            const disasterSelect = document.querySelector('select[name="disaster_id"]');
            if (disasterSelect && disasterSelect.options.length === 2) {
                disasterSelect.selectedIndex = 1;
                disasterSelect.form.submit();
            }
            
            // Initial update if we have needs
            if (document.querySelector('input[name="selected_needs[]"]')) {
                updateResourceSummary();
            }
        });
    </script>
</body>
</html>