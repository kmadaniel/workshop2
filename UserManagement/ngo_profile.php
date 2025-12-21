<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "ngo") {
    header("Location: login.php");
    exit();
}

require_once "connection.php"; // SQL Server connection

$user_id = $_SESSION['user_id'];
$message = "";

// ============================
// FETCH NGO DATA
// ============================
$sql = "SELECT * FROM NGO WHERE NGOID = ?";
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// ============================
// HANDLE PROFILE UPDATE
// ============================
if (isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'] ?? '';
    $status = isset($_POST['status']) ? 'active' : 'inactive';
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        // Hash the new password
        $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_sql = "UPDATE NGO SET NGOName = ?, Email = ?, Phone = ?, Address = ?, Status = ?, PasswordHash = ? WHERE NGOID = ?";
        $update_params = array($name, $email, $phone, $address, $status, $passwordHash, $user_id);
    } else {
        $update_sql = "UPDATE NGO SET NGOName = ?, Email = ?, Phone = ?, Address = ?, Status = ? WHERE NGOID = ?";
        $update_params = array($name, $email, $phone, $address, $status, $user_id);
    }

    $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);

    if ($update_stmt === false) {
        $message = "❌ Failed to update profile: " . print_r(sqlsrv_errors(), true);
    } else {
        $_SESSION['name'] = $name; // update session
        
        // Refresh the data
        $row['NGOName'] = $name;
        $row['Email'] = $email;
        $row['Phone'] = $phone;
        $row['Address'] = $address;
        $row['Status'] = $status;
        
        $message = "✅ Profile updated successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Profile - VolunteerHub</title>
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

        /* Message Alert */
        .alert-message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border-left: 5px solid var(--success-color);
            color: #155724;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border-left: 5px solid var(--danger-color);
            color: #721c24;
        }

        /* Profile Cards */
        .profile-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .card-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .card-header h2 {
            font-size: 1.8rem;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 10px;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

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

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        .readonly-field {
            background-color: #f5f5f5 !important;
            color: #666 !important;
            cursor: not-allowed;
            border-color: #ddd;
        }

        /* Password Field */
        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
        }

        /* Status Toggle */
        .status-container {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 70px;
            height: 36px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
            transition: .4s;
            border-radius: 36px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 28px;
            width: 28px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        input:checked + .toggle-slider {
            background: linear-gradient(90deg, #2ecc71 0%, #27ae60 100%);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(34px);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .status-active {
            color: var(--success-color);
        }

        .status-inactive {
            color: var(--danger-color);
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

        /* Profile Summary Sidebar */
        .profile-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-light);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: var(--text-dark);
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

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
            
            .profile-card {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .status-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .profile-header h1 {
                font-size: 1.6rem;
            }
            
            .card-header h2 {
                font-size: 1.5rem;
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
                        <?php echo !empty($row['NGOName']) ? strtoupper(substr($row['NGOName'], 0, 1)) : 'N'; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo !empty($row['NGOName']) ? htmlspecialchars($row['NGOName']) : 'NGO User'; ?></div>
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
                    <a href="ngo_profile.php" class="nav-link active">
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
                    <h1>Edit NGO Profile</h1>
                    <p class="profile-subtitle">Manage your organization information and settings</p>
                </div>
                <div class="ngo-badge">
                    <i class="fas fa-hands-helping me-2"></i>NGO ORGANIZATION
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (!empty($message)) { ?>
                <div class="alert-message <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-error' ?>">
                    <i class="fas <?= strpos($message, '✅') !== false ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php } ?>

            <div class="row">
                <!-- Left Column: Edit Form -->
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="card-header">
                            <h2>Organization Information</h2>
                            <p>Update your NGO details and account settings</p>
                        </div>
                        
                        <form method="POST" action="" id="profileForm">
                            <div class="form-grid">
                                <!-- Registration Number (Readonly) -->
                                <div class="form-group">
                                    <label class="form-label">Registration Number</label>
                                    <input type="text" 
                                           value="<?= htmlspecialchars($row['RegistrationNo'] ?? '') ?>" 
                                           class="form-control readonly-field" 
                                           readonly
                                           placeholder="NGO Registration Number">
                                </div>

                                <!-- Current Status -->
                                <div class="form-group">
                                    <label class="form-label">Current Status</label>
                                    <div class="status-indicator">
                                        <span class="<?= ($row['Status'] ?? 'active') == 'active' ? 'status-active' : 'status-inactive' ?>">
                                            <?= ucfirst($row['Status'] ?? 'active') ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- NGO Name -->
                                <div class="form-group full-width">
                                    <label class="form-label required">Organization Name</label>
                                    <input type="text" 
                                           name="name" 
                                           value="<?= htmlspecialchars($row['NGOName'] ?? '') ?>" 
                                           class="form-control" 
                                           required
                                           placeholder="Enter your NGO name">
                                </div>

                                <!-- Email Address -->
                                <div class="form-group">
                                    <label class="form-label required">Email Address</label>
                                    <input type="email" 
                                           name="email" 
                                           value="<?= htmlspecialchars($row['Email'] ?? '') ?>" 
                                           class="form-control" 
                                           required
                                           placeholder="organization@email.com">
                                </div>

                                <!-- Phone Number -->
                                <div class="form-group">
                                    <label class="form-label required">Phone Number</label>
                                    <input type="tel" 
                                           name="phone" 
                                           value="<?= !empty($row['Phone']) && $row['Phone'] != '-' ? htmlspecialchars($row['Phone']) : '' ?>" 
                                           class="form-control" 
                                           required
                                           placeholder="+60 12-345 6789">
                                </div>

                                <!-- Address -->
                                <div class="form-group full-width">
                                    <label class="form-label">Organization Address</label>
                                    <textarea name="address" 
                                              class="form-control" 
                                              rows="3"
                                              placeholder="Enter full organization address"><?= htmlspecialchars($row['Address'] ?? '') ?></textarea>
                                </div>

                                <!-- Account Status Toggle -->
                                <div class="form-group full-width">
                                    <label class="form-label">Account Status Control</label>
                                    <div class="status-container">
                                        <span class="status-label">Toggle Status:</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   name="status" 
                                                   id="statusToggle" 
                                                   <?= (($row['Status'] ?? 'active') == 'active') ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <div class="status-indicator">
                                            <span class="status-text" id="statusText"></span>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Green = Active (able to post opportunities), Red = Inactive
                                    </small>
                                </div>

                                <!-- Password Change -->
                                <div class="form-group full-width">
                                    <label class="form-label">Change Password</label>
                                    <div class="password-container">
                                        <input type="password" 
                                               name="password" 
                                               id="password" 
                                               class="form-control" 
                                               placeholder="Enter new password (minimum 6 characters)"
                                               autocomplete="new-password">
                                        <button type="button" class="toggle-password" onclick="togglePassword()">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Leave empty to keep your current password
                                    </small>
                                </div>
                            </div>

                            <!-- ACTION BUTTONS -->
                            <div class="action-buttons">
                                <button type="submit" name="update" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                                <a href="ngo_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Right Column: Profile Summary -->
                <div class="col-lg-4">
                    <div class="profile-summary">
                        <div class="text-center mb-4">
                            <div class="profile-avatar-large">
                                <?php echo !empty($row['NGOName']) ? strtoupper(substr($row['NGOName'], 0, 1)) : 'N'; ?>
                            </div>
                            <h5 class="mb-1"><?= htmlspecialchars($row['NGOName'] ?? 'NGO User'); ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($row['Email'] ?? ''); ?></p>
                        </div>
                        
                        <ul class="info-list">
                            <li class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-id-badge text-primary"></i>
                                    NGO ID
                                </span>
                                <span class="info-value">#<?= htmlspecialchars($user_id); ?></span>
                            </li>
                            <li class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-phone text-primary"></i>
                                    Phone
                                </span>
                                <span class="info-value">
                                    <?= !empty($row['Phone']) && $row['Phone'] != '-' ? htmlspecialchars($row['Phone']) : 'Not set' ?>
                                </span>
                            </li>
                            <li class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-tag text-primary"></i>
                                    Status
                                </span>
                                <span class="badge rounded-pill <?= ($row['Status'] ?? 'active') == 'active' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= ucfirst($row['Status'] ?? 'active'); ?>
                                </span>
                            </li>
                            <li class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-file-alt text-primary"></i>
                                    Registration No
                                </span>
                                <span class="info-value"><?= htmlspecialchars($row['RegistrationNo'] ?? 'Not set'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Status toggle functionality
        const statusToggle = document.getElementById('statusToggle');
        const statusText = document.getElementById('statusText');

        function updateStatusDisplay() {
            if (statusToggle.checked) {
                statusText.textContent = 'Active';
                statusText.className = 'status-active';
            } else {
                statusText.textContent = 'Inactive';
                statusText.className = 'status-inactive';
            }
        }

        statusToggle.addEventListener('change', updateStatusDisplay);
        
        // Initialize status display
        window.addEventListener('DOMContentLoaded', function() {
            updateStatusDisplay();
        });

        // Password toggle
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggleButton = document.querySelector('.toggle-password i');
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleButton.className = "fas fa-eye-slash";
            } else {
                passwordField.type = "password";
                toggleButton.className = "fas fa-eye";
            }
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const password = document.getElementById('password').value.trim();
            
            // Basic validation
            if (!name || !email || !phone) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            // Phone validation (Malaysian format)
            const phoneRegex = /^(\+?6?01)[0-46-9]-*[0-9]{7,8}$/;
            const cleanPhone = phone.replace(/\s+/g, '');
            if (!phoneRegex.test(cleanPhone)) {
                e.preventDefault();
                alert('Please enter a valid Malaysian phone number (e.g., 012-3456789)');
                return false;
            }
            
            // Password validation
            if (password !== '' && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            // Confirm status change
            const currentStatus = "<?= $row['Status'] ?? 'active' ?>";
            const newStatus = statusToggle.checked ? 'active' : 'inactive';
            
            if (currentStatus !== newStatus) {
                const confirmMessage = `Are you sure you want to change your account status to "${newStatus}"?\n\nActive: Can post opportunities\nInactive: Cannot post opportunities`;
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });

        // Phone number formatting
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.startsWith('60')) {
                value = '+' + value;
            } else if (value.startsWith('0')) {
                value = '+6' + value;
            } else if (!value.startsWith('+')) {
                value = '+60' + value;
            }
            
            // Format with spaces
            if (value.length > 3) {
                value = value.slice(0, 3) + ' ' + value.slice(3);
            }
            if (value.length > 7) {
                value = value.slice(0, 7) + '-' + value.slice(7);
            }
            if (value.length > 12) {
                value = value.slice(0, 12) + ' ' + value.slice(12);
            }
            
            e.target.value = value;
        });
    </script>
</body>
</html>