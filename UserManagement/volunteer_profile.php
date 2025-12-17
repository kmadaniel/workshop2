<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];
$message = "";

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

// ============================
// FETCH AVAILABLE NGOS FOR DROPDOWN (IF ALLOWED TO CHANGE)
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
    $skill = $_POST['skill'];
    
    // Check if volunteer can change NGO (optional feature)
    $assignedNGO = $row['AssignedNGO']; // Keep original by default
    if (isset($_POST['assignedNGO']) && !empty($_POST['assignedNGO'])) {
        $assignedNGO = $_POST['assignedNGO'];
    }
    
    // Password update is optional
    if (!empty($_POST['password'])) {
        $password = $_POST['password'];
        if (strlen($password) < 6) {
            $message = "❌ Password must be at least 6 characters long";
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $update_sql = "UPDATE Volunteer SET 
                            FullName = ?, 
                            Email = ?, 
                            Phone = ?, 
                            Address = ?, 
                            PasswordHash = ?, 
                            SkillCategory = ?,
                            AssignedNGO = ?
                          WHERE VolunteerID = ?";
            $update_params = array($name, $email, $phone, $address, $passwordHash, $skill, $assignedNGO, $user_id);
        }
    } else {
        $update_sql = "UPDATE Volunteer SET 
                        FullName = ?, 
                        Email = ?, 
                        Phone = ?, 
                        Address = ?, 
                        SkillCategory = ?,
                        AssignedNGO = ?
                      WHERE VolunteerID = ?";
        $update_params = array($name, $email, $phone, $address, $skill, $assignedNGO, $user_id);
    }
    
    // Execute update if no password validation error
    if (empty($message) || strpos($message, '❌') === false) {
        $update_stmt = sqlsrv_query($conn, $update_sql, $update_params);
        
        if ($update_stmt === false) {
            $message = "❌ Failed to update profile: " . print_r(sqlsrv_errors(), true);
        } else {
            $_SESSION['name'] = $name; // update session
            $message = "✅ Profile updated successfully!";
            
            // Refresh the data
            $row['FullName'] = $name;
            $row['Email'] = $email;
            $row['Phone'] = $phone;
            $row['Address'] = $address;
            $row['SkillCategory'] = $skill;
            $row['AssignedNGO'] = $assignedNGO;
            
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Profile - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            border-bottom: 2px solid #27ae60;
            padding-bottom: 10px;
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
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #27ae60;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
        }
        
        .readonly-field {
            background-color: #f5f5f5 !important;
            color: #666 !important;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #219653 0%, #27ae60 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 15px 30px;
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
        }
        
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
            color: #777;
            cursor: pointer;
            font-size: 18px;
        }
        
        .volunteer-badge {
            display: inline-block;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .profile-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .ngo-info-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .ngo-info-title {
            color: #27ae60;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ngo-info-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
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
            margin-top: 5px;
        }
        
        .ngo-select-container {
            background: #e8f5e9;
            border: 2px dashed #4caf50;
            border-radius: 10px;
            padding: 20px;
            margin-top: 10px;
        }
        
        .ngo-select-label {
            color: #2e7d32;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            
            .container {
                padding: 20px;
            }
            
            .profile-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .ngo-info-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4>Volunteer Panel</h4>
        <a href="volunteer_dashboard.php">🏠 Dashboard</a>
        <a href="volunteer_profile.php" class="active">👤 Profile</a>
        <a href="volunteer_assigned.php">🔍 View Opportunities</a>
        <a href="my_tasks.php">📋 My Tasks</a>
        <a href="volunteer_reports.php">📊 My Reports</a>
        <a href="logout.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container">
            <div class="profile-header">
                <div class="profile-title">
                    <h2>Edit Volunteer Profile</h2>
                    <span class="volunteer-badge">VOLUNTEER</span>
                </div>
                <div class="skill-badge">
                    Skill: <?= htmlspecialchars($row['SkillCategory'] ?? 'Not specified') ?>
                </div>
            </div>
            
            <?php if ($message) { ?>
                <div class="message <?= strpos($message, '✅') !== false ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php } ?>
            
            <!-- Assigned NGO Info Box -->
            <div class="ngo-info-box">
                <div class="ngo-info-title">
                    <span>🏢 Assigned NGO Information</span>
                </div>
                <div class="ngo-info-content">
                    <div class="info-item">
                        <span class="info-label">NGO Name</span>
                        <span class="info-value"><?= htmlspecialchars($row['NGOName'] ?? 'Not assigned') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Registration No</span>
                        <span class="info-value"><?= htmlspecialchars($row['RegistrationNo'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Your Role</span>
                        <span class="info-value">Volunteer (View Only)</span>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="" id="profileForm">
                <div class="form-group">
                    <label>Full Name <span style="color: #e74c3c;">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($row['FullName']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address <span style="color: #e74c3c;">*</span></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($row['Email']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number <span style="color: #e74c3c;">*</span></label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($row['Phone']) ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($row['Address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Skill Category <span style="color: #e74c3c;">*</span></label>
                    <select name="skill" class="form-control" required>
                        <option value="">-- Select Your Skill --</option>
                        <option value="Medical" <?= ($row['SkillCategory'] == 'Medical') ? 'selected' : '' ?>>Medical</option>
                        <option value="Helper" <?= ($row['SkillCategory'] == 'Helper') ? 'selected' : '' ?>>Helper</option>
                        <option value="Rescue" <?= ($row['SkillCategory'] == 'Rescue') ? 'selected' : '' ?>>Rescue</option>
                        <option value="Logistics" <?= ($row['SkillCategory'] == 'Logistics') ? 'selected' : '' ?>>Logistics</option>
                        <option value="Technical" <?= ($row['SkillCategory'] == 'Technical') ? 'selected' : '' ?>>Technical</option>
                        <option value="Driver" <?= ($row['SkillCategory'] == 'Driver') ? 'selected' : '' ?>>Driver</option>
                        <option value="Food Supply" <?= ($row['SkillCategory'] == 'Food Supply') ? 'selected' : '' ?>>Food Supply</option>
                    </select>
                </div>
                
                <!-- NGO Selection (Optional - if allowed to change) -->
                <div class="form-group ngo-select-container">
                    <div class="ngo-select-label">
                        <span>🔄 Change Assigned NGO (Optional)</span>
                    </div>
                    <select name="assignedNGO" class="form-control">
                        <option value="">-- Keep Current NGO --</option>
                        <?php foreach ($ngoList as $ngo) { ?>
                            <option value="<?= htmlspecialchars($ngo['NGOID']) ?>" 
                                <?= ($row['AssignedNGO'] == $ngo['NGOID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ngo['NGOName']) ?>
                            </option>
                        <?php } ?>
                    </select>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Note: Changing NGO will affect your dashboard view and available opportunities
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Change Password (Optional)</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" 
                               class="form-control" placeholder="Enter new password (min 6 characters)">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                    </div>
                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                        Leave empty to keep current password
                    </small>
                </div>
                
                <div class="form-group" style="display: flex; gap: 15px; margin-top: 30px;">
                    <button type="submit" name="update" class="btn-primary">Update Profile</button>
                    <a href="volunteer_dashboard.php" class="btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleButton = document.querySelector('.toggle-password');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = '🙈';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = '👁️';
            }
        }
        
        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const skill = document.querySelector('select[name="skill"]').value;
            const password = document.getElementById('password').value.trim();
            
            if (!name || !email || !phone || !skill) {
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
            
            // Phone validation
            const phoneRegex = /^[0-9\-\+\s\(\)]{10,}$/;
            if (!phoneRegex.test(phone.replace(/\s/g, ''))) {
                e.preventDefault();
                alert('Please enter a valid phone number');
                return false;
            }
            
            // Password validation (if provided)
            if (password !== '' && password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
            
            // NGO change confirmation
            const ngoSelect = document.querySelector('select[name="assignedNGO"]');
            const currentNGO = "<?= $row['AssignedNGO'] ?>";
            
            if (ngoSelect.value && ngoSelect.value !== currentNGO) {
                const newNGOName = ngoSelect.options[ngoSelect.selectedIndex].text;
                if (!confirm(`Are you sure you want to change your assigned NGO to "${newNGOName}"?`)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    </script>

</body>
</html>