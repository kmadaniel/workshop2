<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'connection.php';

// Fetch NGO name for header
$current_ngo_id = $_SESSION['user_id'];
$ngo_sql = "SELECT NGOName FROM NGO WHERE NGOID = ?";
$ngo_stmt = sqlsrv_query($conn, $ngo_sql, array($current_ngo_id));
$ngo_row = sqlsrv_fetch_array($ngo_stmt, SQLSRV_FETCH_ASSOC);
$ngo_name = $ngo_row['NGOName'] ?? $_SESSION['name'];

// --- HANDLE FORM SUBMISSION (CREATE NEW) ---
$success_message = '';
$error_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a delete request
    if(isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];
        
        // Get image URL to delete file
        $get_image_sql = "SELECT ImageURL FROM [UserManagement].[dbo].[News] WHERE NewsID = ? AND CreatedBy = ?";
        $get_image_params = array($delete_id, $ngo_name);
        $get_image_stmt = sqlsrv_query($conn, $get_image_sql, $get_image_params);
        
        if($get_image_stmt && sqlsrv_fetch($get_image_stmt)) {
            $image_url = sqlsrv_get_field($get_image_stmt, 0);
            
            // Delete the image file if exists
            if($image_url && file_exists($image_url)) {
                unlink($image_url);
            }
        }
        
        // Delete from database
        $delete_sql = "DELETE FROM [UserManagement].[dbo].[News] WHERE NewsID = ? AND CreatedBy = ?";
        $delete_params = array($delete_id, $ngo_name);
        $delete_stmt = sqlsrv_prepare($conn, $delete_sql, $delete_params);
        
        if(sqlsrv_execute($delete_stmt)) {
            $success_message = "Story deleted successfully!";
        } else {
            $error_message = "Failed to delete story.";
        }
        
        sqlsrv_free_stmt($delete_stmt);
    }
    // Check if it's an update request
    elseif(isset($_POST['update_id'])) {
        $update_id = $_POST['update_id'];
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if(!empty($title) && !empty($description)) {
            $update_sql = "UPDATE [UserManagement].[dbo].[News] 
                          SET Title = ?, Description = ?, UpdatedAt = GETDATE() 
                          WHERE NewsID = ? AND CreatedBy = ?";
            $update_params = array($title, $description, $update_id, $ngo_name);
            
            $update_stmt = sqlsrv_prepare($conn, $update_sql, $update_params);
            
            if(sqlsrv_execute($update_stmt)) {
                $success_message = "Story updated successfully!";
            } else {
                $error_message = "Failed to update story.";
            }
            
            sqlsrv_free_stmt($update_stmt);
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    // Handle new story creation
    else {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $created_by = $_POST['created_by'] ?? '';
        
        // Handle image upload
        $image_url = NULL;
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/news/";
            if(!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
            
            if(in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Check if image file is actual image
                $check = getimagesize($_FILES['image']['tmp_name']);
                if($check !== false) {
                    if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_url = $target_file;
                    } else {
                        $error_message = "Failed to upload image.";
                    }
                } else {
                    $error_message = "File is not a valid image.";
                }
            } else {
                $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
            }
        }
        
        // Insert into database (only if no error)
        if(empty($error_message) && !empty($title) && !empty($description)) {
            $sql = "INSERT INTO [UserManagement].[dbo].[News] 
                    (Title, Description, ImageURL, CreatedBy, CreatedAt) 
                    VALUES (?, ?, ?, ?, GETDATE())";
            
            $params = array($title, $description, $image_url, $created_by);
            
            $stmt = sqlsrv_prepare($conn, $sql, $params);
            
            if(sqlsrv_execute($stmt)) {
                $success_message = "News story created successfully!";
                
                // Clear form values after successful submission
                unset($_POST);
            } else {
                $errors = sqlsrv_errors();
                $error_message = "Database error: " . $errors[0]['message'];
            }
            
            sqlsrv_free_stmt($stmt);
        } elseif(empty($title) || empty($description)) {
            $error_message = "Please fill in all required fields.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Apply Story Activity - VolunteerHub</title>
    
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

        .form-control:disabled {
            background-color: #f5f5f5 !important;
            color: #666 !important;
            cursor: not-allowed;
            border-color: #ddd;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        /* Image Preview */
        .image-preview-container {
            margin-top: 15px;
            text-align: center;
        }

        .image-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px dashed var(--border-color);
            padding: 10px;
            margin: 0 auto 15px;
            display: none;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 180px;
            border-radius: 5px;
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

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-top: 40px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h3 {
            color: var(--text-dark);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* News Cards */
        .news-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .news-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .news-image {
            height: 200px;
            width: 100%;
            overflow: hidden;
        }

        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .news-card:hover .news-image img {
            transform: scale(1.05);
        }

        .news-content {
            padding: 25px;
        }

        .news-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .news-description {
            color: var(--text-light);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .news-author {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .news-date {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Action Buttons for News Cards */
        .news-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 2;
        }

        .news-card:hover .news-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .edit-btn {
            background: rgba(33, 150, 243, 0.9);
            color: white;
        }

        .edit-btn:hover {
            background: var(--accent-color);
            transform: scale(1.1);
        }

        .delete-btn {
            background: rgba(244, 67, 54, 0.9);
            color: white;
        }

        .delete-btn:hover {
            background: var(--danger-color);
            transform: scale(1.1);
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

        /* Modal Styles */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px 30px;
            border-bottom: none;
        }

        .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 20px 30px;
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
            
            .news-cards {
                grid-template-columns: 1fr;
            }
            
            .news-actions {
                opacity: 1;
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
                    <a href="ngo_create_news.php" class="nav-link active">
                        <i class="fas fa-newspaper"></i>
                        <span>Apply Story Activity</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="ngo_post_opportunity.php" class="nav-link">
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
                    <h1><i class="fas fa-newspaper me-3"></i>Apply Story Activity</h1>
                    <p class="profile-subtitle">Share your NGO's activities, fire incidents, and relief efforts with the community</p>
                </div>
                <div class="ngo-badge">
                    <i class="fas fa-bullhorn me-2"></i>STORY SHARING
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

            <!-- Form Container -->
            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-edit me-2"></i>Create News Story</h2>
                    <p>Fill in the details below to share your organization's activities</p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="newsForm">
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <label for="title" class="form-label required">Story Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   placeholder="e.g., Fire Relief Operation in Central District" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Created By</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($ngo_name); ?>" readonly>
                            <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($ngo_name); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label required">Story Details</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="6" placeholder="Describe the incident, your organization's response, challenges faced, and impact made..." 
                                  required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <div class="text-end mt-2">
                            <small class="text-muted" id="charCount">0 characters</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="form-label">Featured Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" 
                               onchange="previewImage(event)">
                        <small class="text-muted d-block mt-2">Upload an image that represents your story (JPG, PNG, GIF - Max 2MB)</small>
                        
                        <div class="image-preview-container">
                            <div class="image-preview" id="imagePreview">
                                <img id="previewImage" src="#" alt="Preview">
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Story
                        </button>
                        <button type="reset" class="btn btn-secondary" onclick="clearImagePreview()">
                            <i class="fas fa-eraser me-2"></i>Clear Form
                        </button>
                        <a href="#recent-stories" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>View Recent Stories
                        </a>
                    </div>
                </form>
            </div>

            <!-- Recent Stories -->
            <div class="table-container" id="recent-stories">
                <div class="table-header">
                    <h3><i class="fas fa-history me-2"></i>Recent Stories</h3>
                    <div>
                        <span class="badge badge-primary">
                            <i class="fas fa-filter me-1"></i>Showing your stories
                        </span>
                    </div>
                </div>

                <?php
                // Query to get recent news from database
                $sql = "SELECT TOP 6 
                        NewsID, Title, Description, ImageURL, CreatedBy, 
                        FORMAT(CreatedAt, 'dd MMM yyyy HH:mm') as FormattedDate 
                        FROM [UserManagement].[dbo].[News] 
                        WHERE CreatedBy = ? 
                        ORDER BY CreatedAt DESC";
                
                $stmt = sqlsrv_prepare($conn, $sql, array($ngo_name));
                
                if($stmt && sqlsrv_execute($stmt)) {
                    $has_news = false;
                    
                    echo '<div class="news-cards" id="newsCardsContainer">';
                    
                    while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $has_news = true;
                        $description = htmlspecialchars($row['Description']);
                        $short_description = strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                        ?>
                        
                        <div class="news-card" id="news-<?php echo $row['NewsID']; ?>">
                            <!-- Action Buttons -->
                            <div class="news-actions">
                                <button class="action-btn edit-btn" 
                                        onclick="openEditModal(<?php echo $row['NewsID']; ?>, '<?php echo addslashes($row['Title']); ?>', '<?php echo addslashes($row['Description']); ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete-btn" 
                                        onclick="confirmDelete(<?php echo $row['NewsID']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="news-image">
                                <?php if($row['ImageURL'] != NULL && file_exists($row['ImageURL'])): ?>
                                    <img src="<?php echo $row['ImageURL']; ?>" alt="<?php echo htmlspecialchars($row['Title']); ?>">
                                <?php else: ?>
                                    <div style="background: linear-gradient(135deg, #f5f5f5, #e0e0e0); height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-newspaper fa-3x" style="color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="news-content">
                                <h3 class="news-title"><?php echo htmlspecialchars($row['Title']); ?></h3>
                                <p class="news-description"><?php echo $short_description; ?></p>
                                <div class="news-meta">
                                    <div class="news-author">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?php echo htmlspecialchars($row['CreatedBy']); ?></span>
                                    </div>
                                    <div class="news-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo $row['FormattedDate']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                    }
                    
                    echo '</div>';
                    
                    if(!$has_news) {
                        echo '<div class="empty-state">
                                <i class="fas fa-newspaper"></i>
                                <h4>No Stories Yet</h4>
                                <p class="mb-4">You haven\'t created any news stories yet. Start sharing your activities!</p>
                              </div>';
                    }
                    
                    sqlsrv_free_stmt($stmt);
                } else {
                    echo '<div class="alert alert-warning">Unable to fetch stories data.</div>';
                }
                
                sqlsrv_close($conn);
                ?>
            </div>
        </main>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Story</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="update_id" id="editNewsId">
                        <div class="mb-3">
                            <label class="form-label required">Story Title</label>
                            <input type="text" class="form-control" name="title" id="editTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Story Details</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Story</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="deleteForm">
                    <div class="modal-body">
                        <input type="hidden" name="delete_id" id="deleteNewsId">
                        <p>Are you sure you want to delete this story? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Story</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for description
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length + ' characters';
        });
        
        // Trigger initial count
        description.dispatchEvent(new Event('input'));

        // Image preview functionality
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('previewImage');
            const previewContainer = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function clearImagePreview() {
            document.getElementById('image').value = '';
            document.getElementById('previewImage').src = '#';
            document.getElementById('imagePreview').style.display = 'none';
        }

        // File size validation
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if(file && file.size > maxSize) {
                alert('❌ File size must be less than 2MB');
                this.value = '';
                clearImagePreview();
            }
        });

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

        // Form validation for new story
        document.getElementById('newsForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            
            if (!title || !description) {
                e.preventDefault();
                alert('⚠️ Please fill in all required fields.');
                return false;
            }
            
            if (description.length < 50) {
                e.preventDefault();
                alert('📝 Description should be at least 50 characters long.');
                return false;
            }
            
            return true;
        });

        // Edit modal functions
        function openEditModal(newsId, title, description) {
            document.getElementById('editNewsId').value = newsId;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }

        // Delete confirmation
        function confirmDelete(newsId) {
            document.getElementById('deleteNewsId').value = newsId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Form validation for edit
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const title = document.getElementById('editTitle').value.trim();
            const description = document.getElementById('editDescription').value.trim();
            
            if (!title || !description) {
                e.preventDefault();
                alert('⚠️ Please fill in all required fields.');
                return false;
            }
            
            if (description.length < 50) {
                e.preventDefault();
                alert('📝 Description should be at least 50 characters long.');
                return false;
            }
            
            return true;
        });

        // Smooth scroll to recent stories
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Handle form submissions without page reload (AJAX style)
        document.getElementById('editForm').addEventListener('submit', function(e) {
            // The form will still submit normally and reload page
            // But we show the success message via PHP
        });

        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            // The form will still submit normally and reload page
            // But we show the success message via PHP
        });

        // Refresh page after form submissions to show updated data
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || performance.getEntriesByType("navigation")[0].type === 'back_forward') {
                location.reload();
            }
        });
    </script>
</body>
</html>