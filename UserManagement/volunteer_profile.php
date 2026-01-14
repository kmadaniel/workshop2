<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";


// DISTRIBUTION SYSTEM URL - CORRECTED
// ============================
$distribution_url = "http://10.147.17.154:8000/distribution_module/login_callback.php?volunteer_id=" . $user_id;


// ============================
// FETCH VOLUNTEER DATA WITH NGO INFO
// ============================
$sql = "SELECT 
            v.VolunteerID, 
            v.FullName, 
            v.Email, 
            v.Phone, 
            v.Address, 
            v.PasswordHash, 
            v.SkillCategory,
            v.AssignedNGO,
            v.Status,
            n.NGOName,
            n.RegistrationNo
        FROM Volunteer v
        LEFT JOIN NGO n ON v.AssignedNGO = n.NGOID
        WHERE v.VolunteerID = ?";
        
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Convert skill string to array for checkbox display
$currentSkills = [];
if (!empty($row['SkillCategory'])) {
    // Split by comma and trim
    $currentSkills = array_map('trim', explode(',', $row['SkillCategory']));
}

// ============================
// FETCH AVAILABLE NGOS FOR DROPDOWN
// ============================
$ngoList = [];
$ngoQuery = "SELECT NGOID, NGOName FROM NGO WHERE Status = 'active' ORDER BY NGOName ASC";
$ngoResult = sqlsrv_query($conn, $ngoQuery);

if ($ngoResult) {
    while ($ngo = sqlsrv_fetch_array($ngoResult, SQLSRV_FETCH_ASSOC)) {
        $ngoList[] = $ngo;
    }
}

// ============================
// HANDLE PROFILE UPDATE
// ============================
if (isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = $_POST['address'] ?? null;
    $status = $_POST['status'];
    
    // Get selected skills as array
    $skillsArray = $_POST['skills'] ?? [];
    
    // Validate at least one skill selected
    if (empty($skillsArray)) {
        $msg = "Please select at least one skill!";
        $msg_type = "error";
    } else {
        // Convert array to comma-separated string
        $skills = implode(", ", $skillsArray);
        
        // Check if volunteer can change NGO
        $assignedNGO = $row['AssignedNGO'];
        if (isset($_POST['assignedNGO']) && !empty($_POST['assignedNGO'])) {
            $assignedNGO = $_POST['assignedNGO'];
        }
        
        // Password update is optional
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            if (strlen($password) < 6) {
                $msg = "Password must be at least 6 characters long!";
                $msg_type = "error";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE Volunteer SET 
                                FullName = ?, 
                                Email = ?, 
                                Phone = ?, 
                                Address = ?, 
                                PasswordHash = ?, 
                                SkillCategory = ?,
                                AssignedNGO = ?,
                                Status = ?
                              WHERE VolunteerID = ?";
                $update_params = array($name, $email, $phone, $address, $passwordHash, $skills, $assignedNGO, $status, $user_id);
            }
        } else {
            $update_sql = "UPDATE Volunteer SET 
                            FullName = ?, 
                            Email = ?, 
                            Phone = ?, 
                            Address = ?, 
                            SkillCategory = ?,
                            AssignedNGO = ?,
                            Status = ?
                          WHERE VolunteerID = ?";
            $update_params = array($name, $email, $phone, $address, $skills, $assignedNGO, $status, $user_id);
        }
        
        // Execute update if no password validation error
        if (empty($msg) || $msg_type != "error") {
            $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
            
            if ($update_stmt === false) {
                $msg = "Failed to update profile: " . print_r(sqlsrv_errors(), true);
                $msg_type = "error";
            } else {
                $_SESSION['name'] = $name;
                $msg = "Profile updated successfully! Skills: " . htmlspecialchars($skills);
                $msg_type = "success";
                
                // Refresh the data
                $row['FullName'] = $name;
                $row['Email'] = $email;
                $row['Phone'] = $phone;
                $row['Address'] = $address;
                $row['SkillCategory'] = $skills;
                $row['AssignedNGO'] = $assignedNGO;
                $row['Status'] = $status;
                
                // Update current skills array
                $currentSkills = $skillsArray;
                
                // Refresh NGO name if changed
                if ($assignedNGO != $row['AssignedNGO']) {
                    $ngoQuery = "SELECT NGOName FROM NGO WHERE NGOID = ?";
                    $ngoParams = array($assignedNGO);
                    $ngoStmt = sqlsrv_query($conn, $ngoQuery, $ngoParams);
                    if ($ngoStmt && $ngoRow = sqlsrv_fetch_array($ngoStmt, SQLSRV_FETCH_ASSOC)) {
                        $row['NGOName'] = $ngoRow['NGOName'];
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Profile - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* [Keep all existing CSS styles from previous code] */
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
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .sidebar a.active {
            background: rgba(255,255,255,0.3);
            font-weight: 500;
        }
        
        .content {
            flex: 1;
            padding: 30px;
            margin-left: 250px;
            overflow-y: auto;
            min-height: 100vh;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .profile-header h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 300px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid #27ae60;
        }

        .toast.toast-success {
            border-left-color: #27ae60;
        }

        .toast.toast-error {
            border-left-color: #e74c3c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 18px;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            height: 100%;
        }

        .profile-card h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f1f1;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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
            padding: 12px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .form-label {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-group {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .input-group:focus-within {
            border-color: #27ae60;
            box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.2);
        }

        .input-group-text {
            background: #f8f9fa;
            border: none;
            color: #7f8c8d;
        }

        .form-control {
            border: none;
            padding: 12px 15px;
        }

        .form-control:focus {
            box-shadow: none;
        }

        .password-toggle-btn {
            border: none;
            background: #f8f9fa;
            color: #7f8c8d;
            cursor: pointer;
        }

        .password-toggle-btn:hover {
            color: #27ae60;
        }

        .ngo-info-box {
            background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #c8e6c9;
        }

        .ngo-info-title {
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ngo-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .ngo-info-item {
            display: flex;
            flex-direction: column;
        }

        .ngo-info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .ngo-info-value {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }

        .skill-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin: 2px;
        }

        .skill-badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 5px;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }
        
        .badge-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .security-tips {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #27ae60;
        }

        .security-tips h6 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-tips ul {
            padding-left: 20px;
            margin: 0;
        }

        .security-tips li {
            color: #7f8c8d;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .volunteer-badge {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            margin-left: 15px;
        }

        .profile-title {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        /* Checkbox Styles for Skills */
        .checkbox-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e1e5e9;
            margin-top: 10px;
        }

        .checkbox-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .checkbox-row:last-child {
            margin-bottom: 0;
        }

        .checkbox-label {
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            position: relative;
            background: white;
            border: 1px solid #e0e0e0;
        }

        .checkbox-label:hover {
            background: rgba(39, 174, 96, 0.05);
            border-color: #27ae60;
        }

        .checkbox-label input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            width: 0;
            height: 0;
        }

        .checkbox-custom {
            position: relative;
            height: 18px;
            width: 18px;
            background-color: white;
            border: 2px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            flex-shrink: 0;
            transition: all 0.2s;
        }

        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom {
            background-color: #27ae60;
            border-color: #27ae60;
        }

        .checkbox-custom:after {
            content: "";
            position: absolute;
            display: none;
            left: 5px;
            top: 2px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-label input[type="checkbox"]:checked ~ .checkbox-custom:after {
            display: block;
        }

        .skill-item {
            display: flex;
            flex-direction: column;
        }

        .skill-item strong {
            font-size: 14px;
            color: #333;
            margin-bottom: 2px;
        }

        .skill-item small {
            font-size: 12px;
            color: #666;
            line-height: 1.3;
        }

        .skills-display {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

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
            
            .ngo-info-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-label {
                min-width: 100%;
            }
            
            .checkbox-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h4>Volunteer Panel</h4>
        <a href="volunteer_dashboard.php">🏠 Dashboard</a>
        <a href="volunteer_profile.php" class="active">👤 Profile</a>
        
        <a href="<?php echo $distribution_url; ?>" 
           target="_blank"
           class="distribution-link">
           🚚 My Tasks (Distribution System)
        </a>

        <a href="volunteer_reports.php">📊 My Reports</a>
        <a href="main_page.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <div class="content">
        <?php if ($msg): ?>
        <div class="toast-container">
            <div class="toast <?php echo $msg_type == 'success' ? 'toast-success' : 'toast-error'; ?>">
                <div class="toast-content">
                    <strong><?php echo $msg_type == 'success' ? 'Success!' : 'Error!'; ?></strong>
                    <p style="margin: 5px 0 0 0; font-size: 14px;"><?php echo htmlspecialchars($msg); ?></p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-title">
                <h2>
                    <i class="fas fa-user-edit"></i>
                    Edit Volunteer Profile
                </h2>
                <span class="volunteer-badge">VOLUNTEER</span>
            </div>
            <p class="text-muted mb-0">Manage your volunteer information and settings</p>
            <div class="mt-3">
                <div class="skill-badge-container">
                    <?php if (!empty($currentSkills)): ?>
                        <?php foreach ($currentSkills as $skill): ?>
                            <span class="skill-badge">
                                <i class="fas fa-star me-1"></i>
                                <?= htmlspecialchars(trim($skill)) ?>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="skill-badge">No skills specified</span>
                    <?php endif; ?>
                </div>
                <?php 
                $status = $row['Status'] ?? 'active';
                $badgeClass = ($status == 'active') ? 'badge-success' : 
                             (($status == 'busy') ? 'badge-warning' : 
                             (($status == 'on leave') ? 'badge-info' : 'badge-secondary'));
                ?>
                <span class="badge <?= $badgeClass ?> ms-2 mt-2">
                    <i class="fas fa-user-clock me-1"></i>
                    Status: <?= ucwords(htmlspecialchars($status)) ?>
                </span>
            </div>
        </div>

        <div class="ngo-info-box">
            <div class="ngo-info-title">
                <i class="fas fa-building"></i>
                Assigned NGO Information
            </div>
            <div class="ngo-info-grid">
                <div class="ngo-info-item">
                    <span class="ngo-info-label">NGO Name</span>
                    <span class="ngo-info-value"><?= htmlspecialchars($row['NGOName'] ?? 'Not assigned') ?></span>
                </div>
                <div class="ngo-info-item">
                    <span class="ngo-info-label">Registration No.</span>
                    <span class="ngo-info-value"><?= htmlspecialchars($row['RegistrationNo'] ?? 'N/A') ?></span>
                </div>
                <div class="ngo-info-item">
                    <span class="ngo-info-label">Your Role</span>
                    <span class="ngo-info-value">Volunteer (View Only)</span>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="profile-card">
                    <h5><i class="fas fa-edit"></i> Edit Profile Information</h5>
                    
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($row['Phone'] ?? ''); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </span>
                                    <input type="text" name="address" value="<?= htmlspecialchars($row['Address'] ?? ''); ?>" 
                                           class="form-control" placeholder="Enter your address">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Skills Checkbox Section -->
                        <div class="mb-3">
                            <label class="form-label">What can you help with? <span class="text-danger">*</span></label>
                            <div class="checkbox-group">
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Medical / First Aid" 
                                            <?= in_array('Medical / First Aid', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Medical / First Aid</strong>
                                            <small>Doctor, nurse, paramedic, first aider</small>
                                        </div>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Search & Rescue" 
                                            <?= in_array('Search & Rescue', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Search & Rescue</strong>
                                            <small>Find and save people in emergencies</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Technical Support" 
                                            <?= in_array('Technical Support', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Technical Support</strong>
                                            <small>IT, electrician, technician, repair</small>
                                        </div>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Logistics / Supplies" 
                                            <?= in_array('Logistics / Supplies', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Logistics / Supplies</strong>
                                            <small>Manage, pack, and deliver supplies</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Driving" 
                                            <?= in_array('Driving', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Driving</strong>
                                            <small>Car, van, lorry, or ambulance driver</small>
                                        </div>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Food Services" 
                                            <?= in_array('Food Services', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Food Services</strong>
                                            <small>Cooking, packing, or distributing food</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Communication" 
                                            <?= in_array('Communication', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Communication</strong>
                                            <small>Radio, phone, social media, translator</small>
                                        </div>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Counseling / Support" 
                                            <?= in_array('Counseling / Support', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Counseling / Support</strong>
                                            <small>Emotional support, counseling, listening</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Admin / Office Work" 
                                            <?= in_array('Admin / Office Work', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Admin / Office Work</strong>
                                            <small>Paperwork, data entry, coordination</small>
                                        </div>
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="Physical Labour" 
                                            <?= in_array('Physical Labour', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>Physical Labour</strong>
                                            <small>Heavy lifting, setup, cleaning</small>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="checkbox-row">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="skills[]" value="General Volunteer" 
                                            <?= in_array('General Volunteer', $currentSkills) ? 'checked' : '' ?>>
                                        <span class="checkbox-custom"></span>
                                        <div class="skill-item">
                                            <strong>General Volunteer</strong>
                                            <small>Ready to help with any task needed</small>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-info-circle"></i> Select all that apply to you. Your skills will be visible to NGOs.
                            </small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Availability Status <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user-clock"></i>
                                    </span>
                                    <select name="status" class="form-control" required>
                                        <option value="active" <?= ($row['Status'] == 'active') ? 'selected' : '' ?>>Active (Available for tasks)</option>
                                        <option value="busy" <?= ($row['Status'] == 'busy') ? 'selected' : '' ?>>Busy (Limited availability)</option>
                                        <option value="on leave" <?= ($row['Status'] == 'on leave') ? 'selected' : '' ?>>On Leave (Temporary unavailable)</option>
                                        <option value="inactive" <?= ($row['Status'] == 'inactive') ? 'selected' : '' ?>>Inactive (Not available)</option>
                                    </select>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    This status will be visible to NGOs when assigning tasks
                                </small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Change Assigned NGO (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-exchange-alt"></i>
                                    </span>
                                    <select name="assignedNGO" class="form-control">
                                        <option value="">-- Keep Current NGO --</option>
                                        <?php foreach ($ngoList as $ngo) { ?>
                                            <option value="<?= htmlspecialchars($ngo['NGOID']) ?>" 
                                                <?= ($row['AssignedNGO'] == $ngo['NGOID']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ngo['NGOName']) ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Note: Changing NGO will affect your dashboard view and available opportunities
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Change Password (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" id="password" 
                                       class="form-control" placeholder="Enter new password (min 6 characters)">
                                <button class="btn password-toggle-btn" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <small class="text-muted mt-2 d-block">Leave empty if you don't want to change password</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <div>
                                <button type="submit" name="update" class="btn btn-success px-4">
                                    <i class="fas fa-save me-2"></i> Update Profile
                                </button>
                                <a href="volunteer_dashboard.php" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                                </a>
                            </div>
                            <a href="#" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                                <i class="fas fa-trash-alt me-2"></i> Delete Account
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="profile-card">
                    <h5><i class="fas fa-user-circle"></i> Profile Summary</h5>
                    
                    <div class="text-center mb-4">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($row['FullName'], 0, 1)); ?>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($row['FullName']); ?></h5>
                        <p class="text-muted"><?= htmlspecialchars($row['Email']); ?></p>
                        <div class="skills-display">
                            <?php if (!empty($currentSkills)): ?>
                                <?php foreach ($currentSkills as $skill): ?>
                                    <span class="skill-badge"><?= htmlspecialchars(trim($skill)) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="skill-badge">No skills set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-id-badge text-success"></i>
                                Volunteer ID
                            </span>
                            <span class="info-value">#<?= htmlspecialchars($user_id); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-phone text-success"></i>
                                Phone
                            </span>
                            <span class="info-value"><?= htmlspecialchars($row['Phone'] ?? 'Not set'); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-building text-success"></i>
                                Assigned NGO
                            </span>
                            <span class="info-value"><?= htmlspecialchars($row['NGOName'] ?? 'Not assigned'); ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-user-clock text-success"></i>
                                Status
                            </span>
                            <span class="info-value">
                                <?php 
                                $status = $row['Status'] ?? 'active';
                                $badgeClass = ($status == 'active') ? 'badge-success' : 
                                             (($status == 'busy') ? 'badge-warning' : 
                                             (($status == 'on leave') ? 'badge-info' : 'badge-secondary'));
                                ?>
                                <span class="badge <?= $badgeClass ?>">
                                    <?= ucwords(htmlspecialchars($status)) ?>
                                </span>
                            </span>
                        </li>
                        <li class="info-item">
                            <span class="info-label">
                                <i class="fas fa-calendar-alt text-success"></i>
                                Member Since
                            </span>
                            <span class="info-value">-</span>
                        </li>
                    </ul>
                    
                    <div class="security-tips">
                        <h6><i class="fas fa-shield-alt"></i> Security Tips</h6>
                        <ul>
                            <li>Use a strong, unique password</li>
                            <li>Never share your login credentials</li>
                            <li>Log out when using public computers</li>
                            <li>Update your skills regularly</li>
                            <li>Update your status to reflect availability</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var toggleIcon = document.getElementById("toggleIcon");
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            }
        }

        setTimeout(function() {
            var toast = document.querySelector('.toast');
            if(toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);

        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const status = document.querySelector('select[name="status"]').value;
            const password = document.getElementById('password').value.trim();
            
            // Check skills selection
            const skillCheckboxes = document.querySelectorAll('input[name="skills[]"]:checked');
            
            if (!name || !email || !phone || !status) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return false;
            }
            
            if (skillCheckboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one skill');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
            
            const phoneRegex = /^[0-9\-\+\s\(\)]{10,}$/;
            if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                e.preventDefault();
                alert('Please enter a valid phone number');
                return false;
            }
            
            if (password !== '' && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            const ngoSelect = document.querySelector('select[name="assignedNGO"]');
            const currentNGO = "<?= $row['AssignedNGO'] ?>";
            
            if (ngoSelect.value && ngoSelect.value !== currentNGO) {
                const newNGOName = ngoSelect.options[ngoSelect.selectedIndex].text;
                if (!confirm(`Are you sure you want to change your assigned NGO to "${newNGOName}"?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            const statusSelect = document.querySelector('select[name="status"]');
            const currentStatus = "<?= $row['Status'] ?>";
            
            if (statusSelect.value && statusSelect.value !== currentStatus) {
                const newStatus = statusSelect.options[statusSelect.selectedIndex].text;
                if (!confirm(`Are you sure you want to change your status to "${newStatus}"?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // Show selected skills count
            const selectedSkillsCount = skillCheckboxes.length;
            if (!confirm(`You have selected ${selectedSkillsCount} skill(s). Continue updating profile?`)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Add hover effect for skill checkboxes
        document.querySelectorAll('.checkbox-label').forEach(label => {
            label.addEventListener('mouseenter', function() {
                if (!this.querySelector('input').checked) {
                    this.style.backgroundColor = 'rgba(39, 174, 96, 0.08)';
                    this.style.borderColor = '#27ae60';
                }
            });
            
            label.addEventListener('mouseleave', function() {
                if (!this.querySelector('input').checked) {
                    this.style.backgroundColor = '';
                    this.style.borderColor = '#e0e0e0';
                }
            });
        });
    </script>

</body>
</html>