<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}

// Database connection
$serverName = "localhost";
$connectionInfo = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if($conn === false){
    die(print_r(sqlsrv_errors(), true));
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $ngo_id = $_SESSION['user_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $slots = $_POST['slots'] ?? 1;
    
    // Validate required fields
    if(empty($title) || empty($description)){
        $error_msg = "Title and Description are required!";
    } else {
        // Convert date format if needed
        if(!empty($event_date)){
            $event_date_obj = DateTime::createFromFormat('Y-m-d', $event_date);
            if($event_date_obj){
                $event_date_sql = $event_date_obj->format('Y-m-d');
            } else {
                $event_date_sql = null;
            }
        } else {
            $event_date_sql = null;
        }
        
        // Insert opportunity with DEFAULT status (Pending)
        $sql = "INSERT INTO opportunity (ngo_id, title, description, location, event_date, slots, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, GETDATE())";
        
        $params = array($ngo_id, $title, $description, $location, $event_date_sql, $slots);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if($stmt){
            $success_msg = "Opportunity posted successfully! It is now pending admin approval.";
            // Clear form
            $_POST = array();
        } else {
            $error_msg = "Failed to post opportunity: " . print_r(sqlsrv_errors(), true);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Post Opportunity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            display: flex;
            background: #f5f5f5;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: #1d3557;
            padding: 20px;
            position: fixed;
            height: 100vh;
        }
        .sidebar a {
            display: block;
            padding: 10px;
            margin: 5px 0;
            color: #f1faee;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: #457b9d;
            transform: translateX(5px);
        }
        .sidebar a.active {
            background: #457b9d;
            border-left: 4px solid #a8dadc;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            margin-left: 250px;
            max-width: calc(100% - 250px);
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box i {
            color: #2196f3;
            margin-right: 10px;
        }
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .form-header {
            background: linear-gradient(135deg, #1d3557 0%, #457b9d 100%);
            color: white;
            padding: 20px;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>

<body>

<div class="sidebar">
    <h4 style="color: white;">NGO Panel</h4>
    <hr style="color:white; margin-top: 15px;">

    <a href="ngo_dashboard.php">
        <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a href="ngo_profile.php">
        <i class="bi bi-person-circle"></i> Profile
    </a>
    <a href="view_volunteers.php">
        <i class="bi bi-people"></i> My Volunteers
    </a>
    <a href="create_news.php">
        <i class="bi bi-newspaper"></i> Apply Story Activity
    </a> 
    <a href="post_opportunity.php" class="active">
        <i class="bi bi-plus-circle"></i> Post Opportunity
    </a>
    <a href="view_opportunities.php">
        <i class="bi bi-list-task"></i> View Opportunities
    </a>

    <div style="position: absolute; bottom: 20px; width: 210px;">
        <hr style="color:white;">
        <a href="logout.php" style="color: #ff6b6b;">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>


<!-- CONTENT -->
<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Post Volunteer Opportunity</h2>
            <p class="text-muted">Create a new volunteer opportunity for your NGO</p>
        </div>
        <a href="view_opportunities.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <!-- Information Box -->
    <div class="info-box">
        <i class="bi bi-info-circle-fill"></i>
        <strong>Important Note:</strong> All opportunities require admin approval before they become visible to volunteers. 
        You will be notified once your opportunity is approved or rejected.
    </div>

    <!-- Success/Error Messages -->
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?php echo $success_msg; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $error_msg; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="form-container">
        <div class="form-header">
            <h4 class="mb-0">Opportunity Details</h4>
            <p class="mb-0 opacity-75">Fill in all required fields below</p>
        </div>
        
        <form method="POST" action="" class="p-4">
            <div class="row">
                <!-- Title -->
                <div class="col-md-12 mb-3">
                    <label class="form-label required">Title</label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                           required
                           placeholder="e.g., Beach Cleanup Volunteer, Food Distribution Helper">
                    <div class="form-text">Make it clear and descriptive</div>
                </div>

                <!-- Description -->
                <div class="col-md-12 mb-3">
                    <label class="form-label required">Description</label>
                    <textarea name="description" class="form-control" rows="4" required
                              placeholder="Describe the volunteer work, responsibilities, requirements, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <div class="form-text">Provide detailed information about the volunteer role</div>
                </div>

                <!-- Location -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                           placeholder="e.g., Kuala Lumpur, Petaling Jaya">
                    <div class="form-text">Where will the volunteering take place?</div>
                </div>

                <!-- Event Date -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Event Date</label>
                    <input type="date" name="event_date" class="form-control"
                           value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">Leave empty if it's an ongoing opportunity</div>
                </div>

                <!-- Slots -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Available Slots</label>
                    <input type="number" name="slots" class="form-control" min="1" max="1000"
                           value="<?php echo htmlspecialchars($_POST['slots'] ?? '10'); ?>">
                    <div class="form-text">How many volunteers do you need?</div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between">
                    <a href="view_opportunities.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-send-check"></i> Submit for Approval
                    </button>
                </div>
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <i class="bi bi-clock-history"></i> 
                        This opportunity will be pending until approved by an administrator.
                    </small>
                </div>
            </div>
        </form>
    </div>

    <!-- Process Flow -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Approval Process</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="p-3 rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <h6 class="mt-2">1. Submit</h6>
                    <p class="text-muted small">You fill and submit the form</p>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-circle bg-warning text-white d-inline-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <h6 class="mt-2">2. Pending</h6>
                    <p class="text-muted small">Admin reviews your submission</p>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-circle bg-success text-white d-inline-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h6 class="mt-2">3. Approved</h6>
                    <p class="text-muted small">Opportunity goes live</p>
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-circle bg-info text-white d-inline-flex align-items-center justify-content-center" 
                         style="width: 60px; height: 60px;">
                        <i class="bi bi-eye"></i>
                    </div>
                    <h6 class="mt-2">4. Visible</h6>
                    <p class="text-muted small">Volunteers can apply</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Set minimum date to today
    document.querySelector('input[type="date"]').min = new Date().toISOString().split('T')[0];
</script>

</body>
</html>

<?php
// Close connection
if(isset($conn)){
    sqlsrv_close($conn);
}
?>