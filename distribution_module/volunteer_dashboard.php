<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// In a real system, this would come from session/login
// For demo purposes, let's assume volunteer_id = 1
$volunteer_id = 1; // Default volunteer for demo

// Check if viewing a specific volunteer (for admin purposes)
if (isset($_GET['volunteer_id']) && is_numeric($_GET['volunteer_id'])) {
    $volunteer_id = $_GET['volunteer_id'];
}

$upcoming_assignments = [];
$past_assignments = [];
$volunteer_info = [];
$error = '';
$success = '';

/* ----------------------------------------
   GET VOLUNTEER INFORMATION
---------------------------------------- */
try {
    $volunteer_query = "SELECT * FROM volunteer WHERE volunteer_id = ?";
    $stmt = $db->prepare($volunteer_query);
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $volunteer_info = $result->fetch_assoc();
    $stmt->close();
    
    if (!$volunteer_info) {
        throw new Exception("Volunteer not found!");
    }
    
} catch (Exception $e) {
    $error = "Error loading volunteer information: " . $e->getMessage();
}

/* ----------------------------------------
   GET UPCOMING ASSIGNMENTS
---------------------------------------- */
if ($volunteer_info) {
    try {
        $upcoming_query = "
            SELECT 
                dv.id as assignment_id,
                dv.distribution_id,
                dv.role as assignment_role,
                dv.status as assignment_status,
                dv.assigned_timestamp,
                d.date as distribution_date,
                d.status as distribution_status,
                dis.Disaster_Name,
                dis.Location as disaster_location,
                'Coordinator Name' as coordinator_name,
                '06-XXXX XXXX' as coordinator_contact
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            JOIN disaster dis ON d.disaster_id = dis.disaster_id
            WHERE dv.volunteer_id = ? 
            AND dv.status IN ('Assigned', 'Confirmed')
            AND d.date >= CURDATE()
            ORDER BY d.date ASC, dv.role
        ";
        
        $stmt = $db->prepare($upcoming_query);
        $stmt->bind_param("i", $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upcoming_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error .= "<br>Error loading upcoming assignments: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET PAST ASSIGNMENTS
---------------------------------------- */
if ($volunteer_info) {
    try {
        $past_query = "
            SELECT 
                dv.id as assignment_id,
                dv.distribution_id,
                dv.role as assignment_role,
                dv.status as assignment_status,
                dv.assigned_timestamp,
                d.date as distribution_date,
                d.status as distribution_status,
                dis.Disaster_Name,
                dis.Location as disaster_location,
                'Coordinator Name' as coordinator_name
            FROM distribution_volunteer dv
            JOIN distribution d ON dv.distribution_id = d.distribution_id
            JOIN disaster dis ON d.disaster_id = dis.disaster_id
            WHERE dv.volunteer_id = ? 
            AND d.date < CURDATE()
            ORDER BY d.date DESC
            LIMIT 10
        ";
        
        $stmt = $db->prepare($past_query);
        $stmt->bind_param("i", $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $past_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error .= "<br>Error loading past assignments: " . $e->getMessage();
    }
}

/* ----------------------------------------
   HANDLE ATTENDANCE CONFIRMATION
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = $_POST['assignment_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    try {
        if (!$assignment_id || !$action) {
            throw new Exception("Invalid request.");
        }
        
        // Verify assignment belongs to this volunteer
        $verify_query = "SELECT id FROM distribution_volunteer WHERE id = ? AND volunteer_id = ?";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->bind_param("ii", $assignment_id, $volunteer_id);
        $verify_stmt->execute();
        
        if ($verify_stmt->get_result()->num_rows === 0) {
            throw new Exception("Assignment not found or doesn't belong to you.");
        }
        $verify_stmt->close();
        
        if ($action === 'confirm') {
            $update_query = "UPDATE distribution_volunteer SET status = 'Confirmed' WHERE id = ?";
            $message = "Attendance confirmed successfully!";
            $success_type = 'success';
        } elseif ($action === 'cancel') {
            $update_query = "UPDATE distribution_volunteer SET status = 'Cancelled' WHERE id = ?";
            $message = "Attendance cancelled. Admin has been notified.";
            $success_type = 'warning';
            
            // In real system, you would notify admin here
            error_log("Volunteer {$volunteer_info['name']} cancelled assignment {$assignment_id}");
        } else {
            throw new Exception("Invalid action.");
        }
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bind_param("i", $assignment_id);
        $update_stmt->execute();
        
        $success = "<div class='alert alert-{$success_type}'>✅ {$message}</div>";
        
        // Refresh assignments
        $stmt = $db->prepare($upcoming_query);
        $stmt->bind_param("i", $volunteer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $upcoming_assignments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - Assignments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Styles */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e5eb;
        }
        
        .welcome-section h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .welcome-section p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .date-time-section {
            text-align: right;
        }
        
        .date-time-section .date {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .date-time-section .time {
            font-size: 24px;
            font-weight: 700;
            color: #3498db;
            margin-top: 5px;
        }
        
        /* Main Content Layout */
        .dashboard-content {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-icon {
            width: 70px;
            height: 70px;
            background-color: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 30px;
        }
        
        .profile-info h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 5px;
        }
        
        .profile-info p {
            color: #7f8c8d;
            font-size: 15px;
        }
        
        .volunteer-id {
            display: inline-block;
            background-color: #f0f7ff;
            color: #3498db;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .contact-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .contact-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
        }
        
        .quick-actions h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            background-color: #f8fafc;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            color: #2c3e50;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-btn i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .action-btn:hover {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .action-btn:hover i {
            color: white;
        }
        
        /* Stats Cards */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 25px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.upcoming {
            border-top: 5px solid #3498db;
        }
        
        .stat-card.confirmed {
            border-top: 5px solid #2ecc71;
        }
        
        .stat-card.pending {
            border-top: 5px solid #f39c12;
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card.upcoming .stat-number {
            color: #3498db;
        }
        
        .stat-card.confirmed .stat-number {
            color: #2ecc71;
        }
        
        .stat-card.pending .stat-number {
            color: #f39c12;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Assignments Section */
        .assignments-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .section-header h2 {
            color: #2c3e50;
            font-size: 24px;
        }
        
        .add-assignment-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.3s;
        }
        
        .add-assignment-btn i {
            margin-right: 8px;
        }
        
        .add-assignment-btn:hover {
            background-color: #2980b9;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e1e5eb;
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            color: #7f8c8d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }
        
        .tab:hover {
            color: #3498db;
        }
        
        .assignments-list {
            min-height: 200px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 15px;
            color: #ecf0f1;
        }
        
        .empty-state h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: #7f8c8d;
        }
        
        .empty-state p {
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Assignment Cards */
        .assignment-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        }
        
        .assignment-card.confirmed {
            border-left-color: #2ecc71;
        }
        
        .assignment-card.pending {
            border-left-color: #f39c12;
        }
        
        .assignment-card.completed {
            border-left-color: #95a5a6;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .assignment-title {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .assignment-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-upcoming {
            background-color: #e1f5fe;
            color: #0288d1;
        }
        
        .status-confirmed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .status-completed {
            background-color: #f5f5f5;
            color: #616161;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
        }
        
        .detail-item i {
            color: #3498db;
            width: 20px;
            margin-right: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #7f8c8d;
            margin-right: 5px;
        }
        
        .assignment-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-button {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .confirm-btn {
            background-color: #2ecc71;
            color: white;
        }
        
        .confirm-btn:hover {
            background-color: #27ae60;
        }
        
        .decline-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .decline-btn:hover {
            background-color: #c0392b;
        }
        
        .details-btn {
            background-color: #3498db;
            color: white;
        }
        
        .details-btn:hover {
            background-color: #2980b9;
        }
        
        /* ADD THIS - Start Distribution Button */
        .execute-btn {
            background-color: #9b59b6;
            color: white;
        }
        
        .execute-btn:hover {
            background-color: #8e44ad;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .back-link i {
            margin-right: 8px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Role Badge */
        .role-badge {
            display: inline-block;
            background-color: #e1f5fe;
            color: #0288d1;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        /* Full Page Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: white;
            width: 100%;
            height: 100%;
            max-width: none;
            max-height: none;
            border-radius: 0;
            padding: 30px;
            box-shadow: none;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5eb;
        }
        
        .modal-header h3 {
            color: #2c3e50;
            font-size: 28px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 32px;
            color: #7f8c8d;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #e74c3c;
        }
        
        /* Form layout for full page */
        #assignmentForm {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            height: calc(100% - 100px);
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 16px;
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 1px solid #e1e5eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        /* Modal actions at bottom */
        .modal-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            padding-top: 30px;
            border-top: 1px solid #e1e5eb;
            margin-top: 20px;
        }
        
        .btn {
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 16px;
            min-width: 120px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .modal {
                padding: 10px;
            }
            
            .modal-content {
                padding: 20px;
            }
            
            #assignmentForm {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .modal-header h3 {
                font-size: 24px;
            }
            
            .btn {
                padding: 12px 24px;
                min-width: 100px;
                font-size: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-time-section {
                text-align: left;
                margin-top: 15px;
            }
            
            .stats-section {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                width: 100%;
                text-align: center;
            }
            
            .assignment-details {
                grid-template-columns: 1fr;
            }
            
            .assignment-actions {
                flex-wrap: wrap;
            }
            
            .modal {
                padding: 0;
            }
            
            .modal-content {
                padding: 15px;
            }
            
            #assignmentForm {
                gap: 15px;
            }
            
            .btn {
                padding: 10px 20px;
                min-width: 90px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Back Link -->
        <a href="distribution_main.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Main
        </a>
        
        <!-- Header Section -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Welcome back, Muhammad Farhan</h1>
                <p>Volunteer ID: VOL0301</p>
            </div>
            
            <div class="date-time-section">
                <div class="date">Friday, December 5, 2025</div>
                <div class="time" id="current-time">12:46:23 AM</div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-section">
            <div class="stat-card upcoming">
                <div class="stat-number" id="upcoming-count">2</div>
                <div class="stat-label">Upcoming Assignments</div>
            </div>
            
            <div class="stat-card confirmed">
                <div class="stat-number" id="confirmed-count">1</div>
                <div class="stat-label">Confirmed</div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-number" id="pending-count">1</div>
                <div class="stat-label">Pending Confirmation</div>
            </div>
        </div>
        
        <div class="dashboard-content">
            <!-- Left Column: Profile & Quick Actions -->
            <div class="left-column">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="profile-info">
                            <h2>Muhammad Farhan</h2>
                            <div class="role-badge">Driver</div>
                        </div>
                    </div>
                    
                    <div class="volunteer-id">VOL0301</div>
                    
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>012-3456789</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-id-badge"></i>
                            <span>Main Role: Driver</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>Member since: Jan 2024</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-star"></i>
                            <span>Total Hours: 142 hrs</span>
                        </div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <h3>Quick Actions</h3>
                    <button class="action-btn" onclick="openAddAssignmentModal()">
                        <i class="fas fa-plus-circle"></i> Add New Assignment
                    </button>
                    <button class="action-btn" onclick="switchTab('upcoming')">
                        <i class="fas fa-calendar-alt"></i> View Upcoming
                    </button>
                    <button class="action-btn" onclick="switchTab('past')">
                        <i class="fas fa-history"></i> View Past Assignments
                    </button>
                    <button class="action-btn">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                    <button class="action-btn">
                        <i class="fas fa-cog"></i> Account Settings
                    </button>
                </div>
            </div>
            
            <!-- Right Column: Assignments Section -->
            <div class="right-column">
                <div class="assignments-section">
                    <div class="section-header">
                        <h2>Assignment Management</h2>
                        <button class="add-assignment-btn" onclick="openAddAssignmentModal()">
                            <i class="fas fa-plus"></i> Add Assignment
                        </button>
                    </div>
                    
                    <div class="tabs">
                        <div class="tab active" onclick="switchTab('upcoming')">Upcoming Assignments</div>
                        <div class="tab" onclick="switchTab('past')">Past Assignments</div>
                        <div class="tab" onclick="switchTab('confirmed')">Confirmed</div>
                        <div class="tab" onclick="switchTab('pending')">Pending Confirmation</div>
                    </div>
                    
                    <div class="assignments-list">
                        <!-- Upcoming Assignments (Default Tab) -->
                        <div id="upcoming-tab" class="tab-content">
                            <!-- Assignment Card 1 -->
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">Food Delivery to Elderly Center</h3>
                                    <span class="assignment-status status-upcoming">Upcoming</span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span>Dec 10, 2025</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span>9:00 AM - 12:00 PM</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span>Seri Kembangan</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Coordinator:</span>
                                        <span>Amina Hassan</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST2024001</span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <!-- START DISTRIBUTION BUTTON -->
                                    <button class="action-button execute-btn" onclick="startDistribution(2024001)">
                                        <i class="fas fa-play-circle"></i> Start Distribution
                                    </button>
                                    
                                    <button class="action-button confirm-btn" onclick="confirmAssignment(1)">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                    <button class="action-button decline-btn" onclick="declineAssignment(1)">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                    <button class="action-button details-btn" onclick="viewAssignmentDetails(1)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Assignment Card 2 -->
                            <div class="assignment-card confirmed">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">Medical Supply Transport</h3>
                                    <span class="assignment-status status-confirmed">Confirmed</span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span>Dec 15, 2025</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span>2:00 PM - 5:00 PM</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span>Kuala Lumpur General Hospital</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Coordinator:</span>
                                        <span>Dr. Lee Wei</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST2024002</span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <!-- START DISTRIBUTION BUTTON -->
                                    <button class="action-button execute-btn" onclick="startDistribution(2024002)">
                                        <i class="fas fa-play-circle"></i> Start Distribution
                                    </button>
                                    
                                    <button class="action-button details-btn" onclick="viewAssignmentDetails(2)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button class="action-button decline-btn" onclick="declineAssignment(2)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Past Assignments -->
                        <div id="past-tab" class="tab-content" style="display:none;">
                            <div class="assignment-card completed">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">Community Center Supply Run</h3>
                                    <span class="assignment-status status-completed">Completed</span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span>Nov 28, 2025</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span>10:00 AM - 1:00 PM</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span>Petaling Jaya Community Center</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-star"></i>
                                        <span class="detail-label">Hours Completed:</span>
                                        <span>3 hours</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST2023128</span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <button class="action-button details-btn" onclick="viewDistributionReport(2023128)">
                                        <i class="fas fa-chart-bar"></i> View Report
                                    </button>
                                    <button class="action-button confirm-btn">
                                        <i class="fas fa-file-alt"></i> Generate Certificate
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Confirmed Assignments -->
                        <div id="confirmed-tab" class="tab-content" style="display:none;">
                            <div class="assignment-card confirmed">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">Medical Supply Transport</h3>
                                    <span class="assignment-status status-confirmed">Confirmed</span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span>Dec 15, 2025</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span>2:00 PM - 5:00 PM</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span>Kuala Lumpur General Hospital</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Coordinator:</span>
                                        <span>Dr. Lee Wei</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST2024002</span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <!-- START DISTRIBUTION BUTTON -->
                                    <button class="action-button execute-btn" onclick="startDistribution(2024002)">
                                        <i class="fas fa-play-circle"></i> Start Distribution
                                    </button>
                                    
                                    <button class="action-button details-btn" onclick="viewAssignmentDetails(2)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button class="action-button decline-btn" onclick="declineAssignment(2)">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pending Confirmation -->
                        <div id="pending-tab" class="tab-content" style="display:none;">
                            <div class="assignment-card pending">
                                <div class="assignment-header">
                                    <h3 class="assignment-title">Disaster Relief Material Transport</h3>
                                    <span class="assignment-status status-pending">Pending</span>
                                </div>
                                
                                <div class="assignment-details">
                                    <div class="detail-item">
                                        <i class="fas fa-calendar"></i>
                                        <span class="detail-label">Date:</span>
                                        <span>Dec 20, 2025</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span>8:00 AM - 4:00 PM</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span>Klang Valley Relief Center</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Coordinator:</span>
                                        <span>Relief Operations Team</span>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <span class="detail-label">Distribution ID:</span>
                                        <span>DIST2024004</span>
                                    </div>
                                </div>
                                
                                <div class="assignment-actions">
                                    <!-- START DISTRIBUTION BUTTON -->
                                    <button class="action-button execute-btn" onclick="startDistribution(2024004)">
                                        <i class="fas fa-play-circle"></i> Start Distribution
                                    </button>
                                    
                                    <button class="action-button confirm-btn" onclick="confirmAssignment(4)">
                                        <i class="fas fa-check"></i> Confirm
                                    </button>
                                    <button class="action-button decline-btn" onclick="declineAssignment(4)">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                    <button class="action-button details-btn" onclick="viewAssignmentDetails(4)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Assignment Modal - Full Page Layout -->
    <div id="addAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Assignment</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="assignmentForm">
                <div class="form-group">
                    <label for="assignmentTitle">Assignment Title *</label>
                    <input type="text" id="assignmentTitle" class="form-control" placeholder="e.g., Food Delivery to Community Center" required>
                </div>
                
                <div class="form-group">
                    <label for="assignmentDate">Date *</label>
                    <input type="date" id="assignmentDate" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="startTime">Start Time *</label>
                    <input type="time" id="startTime" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="endTime">End Time *</label>
                    <input type="time" id="endTime" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="assignmentLocation">Location *</label>
                    <input type="text" id="assignmentLocation" class="form-control" placeholder="Enter location" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="assignmentDescription">Description</label>
                    <textarea id="assignmentDescription" class="form-control" rows="6" placeholder="Provide details about the assignment"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="assignmentCoordinator">Coordinator Contact</label>
                    <input type="text" id="assignmentCoordinator" class="form-control" placeholder="Coordinator name and contact">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Assignment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateString = now.toLocaleDateString('en-US', options);
            
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            const timeString = `${hours}:${minutes}:${seconds} ${ampm}`;
            
            document.querySelector('.date-time-section .date').textContent = dateString;
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Initialize time and update every second
        updateTime();
        setInterval(updateTime, 1000);
        
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Add active class to clicked tab
            event.target.classList.add('active');
            
            // Update stats based on tab
            updateStats();
        }
        
        // Modal functions for full-page modal
        function openAddAssignmentModal() {
            const modal = document.getElementById('addAssignmentModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            
            // Focus on first input field with slight delay for smooth animation
            setTimeout(() => {
                document.getElementById('assignmentTitle').focus();
            }, 100);
        }
        
        function closeModal() {
            const modal = document.getElementById('addAssignmentModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addAssignmentModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            const modal = document.getElementById('addAssignmentModal');
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                closeModal();
            }
        });
        
        // Handle form submission with validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const title = document.getElementById('assignmentTitle').value.trim();
            const date = document.getElementById('assignmentDate').value;
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;
            const location = document.getElementById('assignmentLocation').value.trim();
            
            // Validation
            if (!title || !date || !startTime || !endTime || !location) {
                alert('Please fill in all required fields (marked with *).');
                return;
            }
            
            // Validate time
            if (startTime >= endTime) {
                alert('End time must be after start time.');
                return;
            }
            
            // Validate date (cannot be in the past)
            const selectedDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('Assignment date cannot be in the past.');
                return;
            }
            
            // Prepare assignment data
            const assignmentData = {
                title: title,
                date: date,
                startTime: startTime,
                endTime: endTime,
                location: location,
                description: document.getElementById('assignmentDescription').value.trim(),
                coordinator: document.getElementById('assignmentCoordinator').value.trim()
            };
            
            // In a real application, you would submit to server here
            console.log('New Assignment Data:', assignmentData);
            
            // Show success message
            alert('Assignment added successfully!');
            
            // Close modal
            closeModal();
            
            // Reset form
            document.getElementById('assignmentForm').reset();
            
            // Set default values again
            const todayStr = new Date().toISOString().split('T')[0];
            document.getElementById('assignmentDate').value = todayStr;
            document.getElementById('startTime').value = '09:00';
            document.getElementById('endTime').value = '17:00';
            
            // In a real app, you would refresh the assignments list from server
            // updateAssignmentsList();
        });
        
        // Assignment actions
        function confirmAssignment(assignmentId) {
            if (confirm("Are you sure you want to confirm this assignment?")) {
                alert("Assignment confirmed successfully!");
                // In a real app, update the assignment status via API
                updateStats();
            }
        }
        
        function declineAssignment(assignmentId) {
            if (confirm("Are you sure you want to decline/cancel this assignment?")) {
                alert("Assignment declined/cancelled.");
                // In a real app, update the assignment status via API
                updateStats();
            }
        }
        
        function viewAssignmentDetails(assignmentId) {
            alert(`Viewing details for assignment #${assignmentId}. In a real application, this would show more detailed information.`);
        }
        
        // Start Distribution Function - User Story 4.4
        function startDistribution(distributionId) {
            const distId = distributionId.toString().padStart(6, '0');
            
            if (confirm(`🚀 Start Distribution #DIST${distId}?\n\nThis will open the Execute Distribution interface where you can:\n• Scan victim IC / Enter Victim ID\n• View approved needs\n• Check items as distributed\n• Capture signature/photo\n• Submit distribution`)) {
                
                // In real app: window.location.href = `execute_distribution.php?distribution_id=${distributionId}`;
                
                // For demo: Show the Execute Distribution interface
                showExecuteDistributionModal(distributionId);
            }
        }
        
        // Execute Distribution Modal - User Story 4.4 Interface
        function showExecuteDistributionModal(distributionId) {
            const distId = distributionId.toString().padStart(6, '0');
            
            // Create modal for Execute Distribution
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.id = 'executeDistModal';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 500px; height: auto; max-height: 90vh;">
                    <div class="modal-header">
                        <h3>Execute Distribution</h3>
                        <button class="close-modal" onclick="closeExecuteModal()">&times;</button>
                    </div>
                    
                    <div style="padding: 20px;">
                        <div style="text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                            <h4 style="color: #2c3e50; margin-bottom: 5px;">DISTRIBUTION: DIST${distId}</h4>
                            <p style="color: #7f8c8d; font-size: 14px;">Volunteer: Muhammad Farhan (VOL0301)</p>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <p style="font-weight: 600; margin-bottom: 10px; color: #2c3e50;">
                                <i class="fas fa-qrcode"></i> Scan Victim IC or Enter Victim ID:
                            </p>
                            <input type="text" id="victimIdInput" placeholder="Enter V2024001 or scan IC" 
                                   style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; margin-bottom: 10px;">
                            <button onclick="simulateVictimScan()" style="width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-camera"></i> Scan IC QR Code
                            </button>
                        </div>
                        
                        <div id="victimInfoSection" style="display: none; margin-bottom: 20px; padding: 15px; background: #e8f4fd; border-radius: 8px;">
                            <h4 style="color: #2c3e50; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user-check"></i> Victim Found
                            </h4>
                            <p style="font-weight: 600; color: #2c3e50; margin-bottom: 5px;">Siti Aminah binti Ahmad</p>
                            <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 15px;">Victim ID: V2024001 | Family Size: 5</p>
                            
                            <div style="margin-top: 15px;">
                                <p style="font-weight: 600; color: #2c3e50; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-list-check"></i> Approved Needs:
                                </p>
                                <div style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 6px;">
                                    <label style="display: flex; align-items: center; cursor: pointer; justify-content: space-between;">
                                        <span style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" id="item1" style="margin-right: 5px;">
                                            <span>Beras</span>
                                        </span>
                                        <span style="font-weight: 600; color: #2c3e50;">10 kg</span>
                                    </label>
                                </div>
                                <div style="padding: 10px; background: white; border-radius: 6px;">
                                    <label style="display: flex; align-items: center; cursor: pointer; justify-content: space-between;">
                                        <span style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" id="item2" style="margin-right: 5px;">
                                            <span>Pampers</span>
                                        </span>
                                        <span style="font-weight: 600; color: #2c3e50;">3 pek</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="distributionActions" style="display: none;">
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px;">
                                <button onclick="markItemsDistributed()" style="padding: 12px; background: #2ecc71; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <i class="fas fa-check-circle"></i> Mark Distributed
                                </button>
                                <button onclick="takeDistributionPhoto()" style="padding: 12px; background: #3498db; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <i class="fas fa-camera"></i> Take Photo
                                </button>
                                <button onclick="captureVictimSignature()" style="padding: 12px; background: #9b59b6; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <i class="fas fa-signature"></i> Signature
                                </button>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button onclick="closeExecuteModal()" style="flex: 1; padding: 15px; background: #95a5a6; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button onclick="submitDistribution(${distributionId})" id="submitDistBtn" style="flex: 2; padding: 15px; background: #2ecc71; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: none;">
                                <i class="fas fa-paper-plane"></i> Submit Distribution
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Helper functions for Execute Distribution
        function closeExecuteModal() {
            const modal = document.getElementById('executeDistModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = 'auto';
            }
        }
        
        function simulateVictimScan() {
            // Simulate scanning/entering victim ID
            document.getElementById('victimIdInput').value = 'V2024001';
            
            // Show victim info
            setTimeout(() => {
                document.getElementById('victimInfoSection').style.display = 'block';
                document.getElementById('distributionActions').style.display = 'block';
                document.getElementById('submitDistBtn').style.display = 'block';
                
                // Auto-check items after 1 second
                setTimeout(() => {
                    markItemsDistributed();
                }, 1000);
            }, 500);
        }
        
        function markItemsDistributed() {
            document.getElementById('item1').checked = true;
            document.getElementById('item2').checked = true;
            
            // Show success animation
            const checkboxes = document.querySelectorAll('#victimInfoSection input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.parentElement.parentElement.style.background = '#e8f6ef';
                cb.parentElement.parentElement.style.borderLeft = '4px solid #2ecc71';
            });
            
            // Show notification
            const notification = document.createElement('div');
            notification.innerHTML = '<div style="position: fixed; top: 20px; right: 20px; background: #2ecc71; color: white; padding: 10px 15px; border-radius: 5px; z-index: 9999;">✓ Items marked as distributed</div>';
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
        
        function takeDistributionPhoto() {
            alert('📸 Camera opened for distribution photo...\nPhoto would be saved to distribution record.');
        }
        
        function captureVictimSignature() {
            alert('✍️ Signature capture interface opened...\nVictim would sign on screen for verification.');
        }
        
        function submitDistribution(distributionId) {
            const distId = distributionId.toString().padStart(6, '0');
            
            if (confirm(`✅ Submit Distribution #DIST${distId}?\n\nThis will:\n1. Update needs to "Fulfilled"\n2. Deduct inventory (Beras: -10kg, Pampers: -3pek)\n3. Send SMS to victim\n4. Record distribution in system`)) {
                
                // Show success message
                alert(`🎉 Distribution #DIST${distId} submitted successfully!\n\n✅ Needs updated to Fulfilled\n✅ Inventory deducted\n✅ SMS sent to victim\n✅ Distribution recorded\n\nReady for next victim...`);
                
                // Close modal
                closeExecuteModal();
                
                // Show success notification
                setTimeout(() => {
                    alert('📱 SMS Sent to Victim:\n"Bantuan telah diterima. Terima kasih."');
                }, 500);
            }
        }
        
        function viewDistributionReport(distributionId) {
            alert(`📊 Distribution Report #DIST${distributionId.toString().padStart(6, '0')}\n\n• Date: Nov 28, 2025\n• Location: Petaling Jaya Community Center\n• Volunteers: 5\n• Victims Helped: 25 families\n• Items Distributed: 250kg food, 50 packs supplies\n• Status: Completed`);
        }
        
        // Update stats based on current assignments
        function updateStats() {
            // In a real app, these would be fetched from a server/database
            // For demo purposes, we'll use static values
            const upcomingCount = document.querySelectorAll('#upcoming-tab .assignment-card').length;
            const confirmedCount = document.querySelectorAll('#confirmed-tab .assignment-card').length;
            const pendingCount = document.querySelectorAll('#pending-tab .assignment-card').length;
            
            document.getElementById('upcoming-count').textContent = upcomingCount;
            document.getElementById('confirmed-count').textContent = confirmedCount;
            document.getElementById('pending-count').textContent = pendingCount;
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize stats
            updateStats();
            
            // Set default values for assignment form
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('assignmentDate');
            if (dateInput) {
                dateInput.min = today;
                dateInput.value = today;
            }
            
            // Set default times
            const startTimeInput = document.getElementById('startTime');
            const endTimeInput = document.getElementById('endTime');
            if (startTimeInput) startTimeInput.value = '09:00';
            if (endTimeInput) endTimeInput.value = '17:00';
            
            // Add form validation styles
            const formControls = document.querySelectorAll('.form-control[required]');
            formControls.forEach(control => {
                control.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#e74c3c';
                    this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.1)';
                });
                
                control.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.style.borderColor = '#2ecc71';
                        this.style.boxShadow = '0 0 0 3px rgba(46, 204, 113, 0.1)';
                    }
                });
            });
        });
    </script>
</body>
</html>