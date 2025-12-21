<?php
session_start();

// --- SECURITY CHECK ---
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

// Fetch NGO name for header
$current_ngo_id = $_SESSION['user_id'];
$ngo_sql = "SELECT NGOName FROM NGO WHERE NGOID = ?";
$ngo_stmt = sqlsrv_query($conn, $ngo_sql, array($current_ngo_id));
$ngo_row = sqlsrv_fetch_array($ngo_stmt, SQLSRV_FETCH_ASSOC);
$ngo_name = $ngo_row['NGOName'] ?? $_SESSION['name'];

// Handle form submission
$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $ngo_id = $_SESSION['user_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $location = $_POST['location'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $slots = $_POST['slots'] ?? 1;
    
    // Validate required fields
    if(empty($title) || empty($description)){
        $error_message = "❌ Title and Description are required!";
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
            $success_message = "✅ Opportunity posted successfully! It is now pending admin approval.";
            // Clear form
            $_POST = array();
        } else {
            $error_message = "❌ Failed to post opportunity: " . print_r(sqlsrv_errors(), true);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --primary-light: #4caf50;
            --secondary-color: #ff9800;
            --accent-color: #2196f3;
            --bg-light: #f5f7fa;
            --card-bg: #ffffff;
            --text-dark: #2c3e50;
            --text-light: #546e7a;
            --border-color: #e0e0e0;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* System Header */
        .system-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            padding: 0 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            font-size: 28px;
            color: #a5d6a7;
        }

        .logo-text h1 {
            font-size: 22px;
            margin: 0;
            font-weight: 600;
            color: white;
        }

        .logo-text small {
            font-size: 12px;
            opacity: 0.8;
            color: #c8e6c9;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 15px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }

        .user-info {
            line-height: 1.3;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
            color: #c8e6c9;
        }

        /* Main Layout */
        .main-wrapper {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            color: white;
            padding: 25px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 0 20px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: white;
        }

        .nav-menu {
            list-style: none;
            padding: 0 15px;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 15px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 4px solid var(--secondary-color);
        }

        .nav-link i {
            width: 24px;
            font-size: 1.1rem;
            text-align: center;
        }

        .logout-link {
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }

        .logout-link .nav-link {
            color: #ffccbc;
        }

        .logout-link .nav-link:hover {
            background: rgba(244, 67, 54, 0.2);
            color: #ffccbc;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            background: var(--bg-light);
            overflow-y: auto;
        }

        /* Profile Header */
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 25px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .profile-title h1 {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .ngo-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.2);
        }

        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 20px;
            margin-bottom: 10px;
            min-width: 350px;
            animation: slideInRight 0.3s ease;
            border-left: 5px solid var(--success-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .toast.error {
            border-left-color: var(--danger-color);
        }

        .toast i {
            font-size: 1.5rem;
        }

        .toast.success i {
            color: var(--success-color);
        }

        .toast.error i {
            color: var(--danger-color);
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Form Container */
        .form-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid var(--border-color);
        }

        .form-header h2 {
            font-size: 1.8rem;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .form-header p {
            color: var(--text-light);
            font-size: 1rem;
        }

        /* Form Styles */
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .required::after {
            content: " *";
            color: var(--danger-color);
        }

        .form-control, .form-select, .form-file {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-control:focus, .form-select:focus, .form-file:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid var(--info-color);
        }

        .info-box h6 {
            color: var(--text-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }

        .info-box p {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        /* Process Flow */
        .process-flow {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-top: 40px;
        }

        .process-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .process-header h3 {
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }

        .process-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .process-step {
            text-align: center;
            padding: 25px 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .process-step:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .step-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: white;
        }

        .step-1 .step-icon { background: linear-gradient(135deg, var(--info-color), #0d8aee); }
        .step-2 .step-icon { background: linear-gradient(135deg, var(--warning-color), #e68900); }
        .step-3 .step-icon { background: linear-gradient(135deg, var(--success-color), #27ae60); }
        .step-4 .step-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

        .step-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .step-desc {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-bottom: 0;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 16px 35px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #1b5e20 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(46, 125, 50, 0.4);
        }

        .btn-secondary {
            background: white;
            color: var(--text-dark);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--primary-light);
            transform: translateY(-3px);
        }

        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }

        /* Badge */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
        }

        .badge-primary {
            background-color: rgba(46, 125, 50, 0.1) !important;
            color: var(--primary-color) !important;
            border: 1px solid rgba(46, 125, 50, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: relative;
                margin-bottom: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .form-container {
                padding: 25px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .process-steps {
                grid-template-columns: 1fr;
            }
            
            .toast {
                min-width: 280px;
                left: 20px;
                right: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- System Header -->
    <header class="system-header">
        <div class="header-container">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-hands-helping"></i>
                </div>
                <div class="logo-text">
                    <h1>NGO Panel</h1>
                    <small>VolunteerHub - Disaster Relief System</small>
                </div>
            </div>
            
            <div class="header-controls">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo !empty($ngo_name) ? strtoupper(substr($ngo_name, 0, 1)) : 'N'; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($ngo_name); ?></div>
                        <div class="user-role">NGO Organization</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>NGO Panel</h2>
                <p>Disaster Relief Management</p>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="ngo_dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_view_volunteer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>My Volunteers</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_create_news.php" class="nav-link">
                        <i class="fas fa-newspaper"></i>
                        <span>Apply Story Activity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_post_opportunity.php" class="nav-link active">
                        <i class="fas fa-bullhorn"></i>
                        <span>Post Opportunity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_view_opportunities.php" class="nav-link">
                        <i class="fas fa-eye"></i>
                        <span>View Opportunities</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="distribution.php" class="nav-link">
                        <i class="fas fa-box-open"></i>
                        <span>Distribution</span>
                    </a>
                </li>
                
                <li class="nav-item logout-link">
                    <a href="main_page.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-title">
                    <h1><i class="fas fa-bullhorn me-3"></i>Post Volunteer Opportunity</h1>
                    <p class="profile-subtitle">Create new volunteer opportunities for your NGO's activities</p>
                </div>
                <div class="ngo-badge">
                    <i class="fas fa-users me-2"></i>VOLUNTEER RECRUITMENT
                </div>
            </div>

            <!-- Toast Notification -->
            <?php if($success_message): ?>
            <div class="toast-container">
                <div class="toast success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if($error_message): ?>
            <div class="toast-container">
                <div class="toast error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error!</strong>
                        <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <h6><i class="fas fa-info-circle"></i> Important Notice</h6>
                <p>
                    All opportunities require admin approval before they become visible to volunteers. 
                    You will be notified once your opportunity is approved or rejected. 
                    Please ensure all information is accurate and complete.
                </p>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-plus-circle me-2"></i>Opportunity Details</h2>
                    <p>Fill in all required information to create a new volunteer opportunity</p>
                </div>
                
                <form method="POST" action="" id="opportunityForm">
                    <div class="row">
                        <!-- Title -->
                        <div class="col-md-12 mb-4">
                            <label for="title" class="form-label required">
                                <i class="fas fa-heading me-2 text-primary"></i>Opportunity Title
                            </label>
                            <input type="text" name="title" class="form-control" id="title"
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                   required
                                   placeholder="e.g., Beach Cleanup Volunteer, Food Distribution Helper">
                            <small class="text-muted mt-2 d-block">
                                Make it clear and descriptive to attract volunteers
                            </small>
                        </div>

                        <!-- Description -->
                        <div class="col-md-12 mb-4">
                            <label for="description" class="form-label required">
                                <i class="fas fa-align-left me-2 text-primary"></i>Description
                            </label>
                            <textarea name="description" class="form-control" id="description" rows="5" required
                                      placeholder="Describe the volunteer work, responsibilities, requirements, and benefits..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">
                                    Provide detailed information about the volunteer role
                                </small>
                                <small class="text-muted" id="descCharCount">0 characters</small>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Location -->
                            <div class="col-md-6 mb-4">
                                <label for="location" class="form-label">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>Location
                                </label>
                                <input type="text" name="location" class="form-control" id="location"
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                       placeholder="e.g., Kuala Lumpur, Petaling Jaya">
                                <small class="text-muted mt-2 d-block">
                                    Where will the volunteering take place?
                                </small>
                            </div>

                            <!-- Event Date -->
                            <div class="col-md-6 mb-4">
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-2 text-primary"></i>Event Date
                                </label>
                                <input type="date" name="event_date" class="form-control" id="event_date"
                                       value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                                       min="<?php echo date('Y-m-d'); ?>">
                                <small class="text-muted mt-2 d-block">
                                    Leave empty if it's an ongoing opportunity
                                </small>
                            </div>
                        </div>

                        <!-- Slots -->
                        <div class="col-md-6 mb-4">
                            <label for="slots" class="form-label">
                                <i class="fas fa-users me-2 text-primary"></i>Available Slots
                            </label>
                            <div class="input-group">
                                <input type="number" name="slots" class="form-control" id="slots" 
                                       min="1" max="1000" step="1"
                                       value="<?php echo htmlspecialchars($_POST['slots'] ?? '10'); ?>">
                                <span class="input-group-text">volunteers</span>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                How many volunteers do you need for this opportunity?
                            </small>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="view_opportunities.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-eraser me-2"></i>Clear Form
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                        </button>
                    </div>
                </form>
            </div>

            <!-- Process Flow -->
            <div class="process-flow">
                <div class="process-header">
                    <h3><i class="fas fa-sitemap me-2"></i>Approval Process</h3>
                    <p class="text-muted">This is how your opportunity will be processed</p>
                </div>
                
                <div class="process-steps">
                    <div class="process-step step-1">
                        <div class="step-icon">
                            <i class="fas fa-edit"></i>
                        </div>
                        <h4 class="step-title">1. Submission</h4>
                        <p class="step-desc">You fill and submit the opportunity form</p>
                    </div>
                    
                    <div class="process-step step-2">
                        <div class="step-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 class="step-title">2. Review</h4>
                        <p class="step-desc">Admin reviews your submission for approval</p>
                    </div>
                    
                    <div class="process-step step-3">
                        <div class="step-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 class="step-title">3. Approval</h4>
                        <p class="step-desc">Opportunity is approved and goes live</p>
                    </div>
                    
                    <div class="process-step step-4">
                        <div class="step-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h4 class="step-title">4. Live</h4>
                        <p class="step-desc">Volunteers can view and apply for opportunity</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for description
        const description = document.getElementById('description');
        const charCount = document.getElementById('descCharCount');
        
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length + ' characters';
        });
        
        // Trigger initial count
        description.dispatchEvent(new Event('input'));

        // Set minimum date to today
        const eventDateInput = document.getElementById('event_date');
        if(eventDateInput) {
            eventDateInput.min = new Date().toISOString().split('T')[0];
        }

        // Auto-hide toast after 5 seconds
        setTimeout(function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                toast.style.transform = 'translateX(100%)';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            });
        }, 5000);

        // Form validation
        document.getElementById('opportunityForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const slots = document.getElementById('slots').value;
            
            if (!title || !description) {
                e.preventDefault();
                showAlert('⚠️ Please fill in all required fields.', 'warning');
                return false;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                showAlert('📝 Title should be at least 5 characters long', 'warning');
                return false;
            }
            
            if (description.length < 20) {
                e.preventDefault();
                showAlert('📝 Description should be at least 20 characters long', 'warning');
                return false;
            }
            
            if (slots < 1 || slots > 1000) {
                e.preventDefault();
                showAlert('👥 Please enter a valid number of slots (1-1000)', 'warning');
                return false;
            }
            
            // Show confirmation for submission
            if(!confirm('Are you sure you want to submit this opportunity for approval?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Custom alert function
        function showAlert(message, type = 'warning') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.form-container').prepend(alertDiv);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if(alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Auto-clear form after successful submission if success message exists
        <?php if($success_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Clear form fields
            document.getElementById('title').value = '';
            document.getElementById('description').value = '';
            document.getElementById('location').value = '';
            document.getElementById('event_date').value = '';
            document.getElementById('slots').value = '10';
            charCount.textContent = '0 characters';
        });
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Close connection
if(isset($conn)){
    sqlsrv_close($conn);
}
?>