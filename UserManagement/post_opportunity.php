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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Opportunity - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #34495e;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar a:hover {
            background: #34495e;
            color: white;
        }
        
        .sidebar a.active {
            background: #3498db;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .page-title {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .form-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            margin: -30px -30px 30px -30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            color: white;
            text-decoration: none;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .process-flow {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .process-step {
            text-align: center;
            padding: 15px;
        }
        
        .step-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }
        
        .step-1 { background: #3498db; color: white; }
        .step-2 { background: #f39c12; color: white; }
        .step-3 { background: #2ecc71; color: white; }
        .step-4 { background: #9b59b6; color: white; }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .form-header {
                margin: -20px -20px 20px -20px;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4>NGO Panel</h4>
        <hr style="color:white;">

        <a href="ngo_dashboard.php">🏠 Dashboard</a>
        <a href="ngo_profile.php">👤 Profile</a>
        <a href="ngo_view_volunteer.php">👥 My Volunteers</a>
        <a href="create_news.php">📝 Apply Story Activity</a> 
        <a href="post_opportunity.php" class="active">📢 Post Opportunity</a>
        <a href="view_opportunities.php">📋 View Opportunities</a>
        <a href="distribution.php">📦 Distribution</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="page-title">Post Volunteer Opportunity</h2>
                    <p class="subtitle">Create a new volunteer opportunity for your NGO</p>
                </div>
                <a href="view_opportunities.php" class="btn-secondary">
                    ← Back to List
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

            <!-- Form Container -->
            <div class="form-container">
                <div class="form-header">
                    <h4 class="mb-0" style="color: white;">Opportunity Details</h4>
                    <p class="mb-0 opacity-75" style="color: white;">Fill in all required fields below</p>
                </div>
                
                <form method="POST" action="">
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
                            <a href="view_opportunities.php" class="btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="submit" class="btn-primary px-4">
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
            <div class="process-flow">
                <h5 class="mb-4" style="color: #2c3e50;">Approval Process</h5>
                <div class="row text-center">
                    <div class="col-md-3 process-step">
                        <div class="step-icon step-1">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <h6 class="mt-2">1. Submit</h6>
                        <p class="text-muted small">You fill and submit the form</p>
                    </div>
                    <div class="col-md-3 process-step">
                        <div class="step-icon step-2">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <h6 class="mt-2">2. Pending</h6>
                        <p class="text-muted small">Admin reviews your submission</p>
                    </div>
                    <div class="col-md-3 process-step">
                        <div class="step-icon step-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h6 class="mt-2">3. Approved</h6>
                        <p class="text-muted small">Opportunity goes live</p>
                    </div>
                    <div class="col-md-3 process-step">
                        <div class="step-icon step-4">
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
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if (!title || !description) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                alert('Title should be at least 5 characters long');
                return false;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                alert('Description should be at least 20 characters long');
                return false;
            }
            
            return true;
        });
    </script>

</body>
</html>

<?php
// Close connection
if(isset($conn)){
    sqlsrv_close($conn);
}
?>