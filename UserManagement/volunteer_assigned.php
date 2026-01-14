<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer"){
    header("Location: login.php");
    exit();
}

$volunteer_id = $_SESSION['user_id']; // ID volunteer yang login

/* DB CONNECTION */
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

// ============================
// HANDLE APPLY BUTTON (kalau user click apply)
// ============================
if(isset($_POST['apply_opportunity'])) {
    $opportunity_id = $_POST['opportunity_id'];
    
    // Check kalau sudah apply sebelum ni
    $checkSql = "SELECT * FROM opportunity_volunteer 
                 WHERE opportunity_id = ? AND volunteer_id = ?";
    $checkParams = array($opportunity_id, $volunteer_id);
    $checkStmt = sqlsrv_query($conn, $checkSql, $checkParams);
    
    if(sqlsrv_has_rows($checkStmt)) {
        $message = "error:You have already applied for this opportunity!";
    } else {
        // Check slots available
        $slotSql = "SELECT slots FROM opportunity WHERE opportunity_id = ?";
        $slotParams = array($opportunity_id);
        $slotStmt = sqlsrv_query($conn, $slotSql, $slotParams);
        $slotRow = sqlsrv_fetch_array($slotStmt, SQLSRV_FETCH_ASSOC);
        $available_slots = $slotRow['slots'];
        
        // Count sudah berapa orang apply
        $countSql = "SELECT COUNT(*) as total FROM opportunity_volunteer 
                     WHERE opportunity_id = ?";
        $countParams = array($opportunity_id);
        $countStmt = sqlsrv_query($conn, $countSql, $countParams);
        $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
        $applied_count = $countRow['total'];
        
        if($applied_count >= $available_slots) {
            $message = "error:Sorry, all slots have been filled!";
        } else {
            // Insert ke opportunity_volunteer
            $insertSql = "INSERT INTO opportunity_volunteer 
                          (opportunity_id, volunteer_id, assigned_at) 
                          VALUES (?, ?, GETDATE())";
            $insertParams = array($opportunity_id, $volunteer_id);
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
            
            if($insertStmt) {
                $message = "success:Successfully applied for the opportunity!";
            } else {
                $message = "error:Failed to apply. Please try again.";
            }
        }
    }
}

// ============================
// FETCH VOLUNTEER INFO
// ============================
$volunteerSql = "SELECT 
                    v.FullName,
                    v.SkillCategory,
                    v.AssignedNGO,
                    n.NGOName
                FROM Volunteer v
                LEFT JOIN NGO n ON v.AssignedNGO = n.NGOID
                WHERE v.VolunteerID = ?";
$volunteerParams = array($volunteer_id);
$volunteerStmt = sqlsrv_query($conn, $volunteerSql, $volunteerParams);
$volunteer = sqlsrv_fetch_array($volunteerStmt, SQLSRV_FETCH_ASSOC);

// ============================
// FETCH ALL OPEN OPPORTUNITIES + ASSIGNED VOLUNTEERS + SLOTS INFO
// ============================
$sql = "
SELECT 
    o.*,
    -- Count berapa orang sudah apply untuk opportunity ini
    (SELECT COUNT(*) FROM opportunity_volunteer ov 
     WHERE ov.opportunity_id = o.opportunity_id) as applied_count,
    -- Check jika volunteer ini sudah apply
    (SELECT CASE WHEN EXISTS (
        SELECT 1 FROM opportunity_volunteer ov2 
        WHERE ov2.opportunity_id = o.opportunity_id 
        AND ov2.volunteer_id = ?) 
        THEN 1 ELSE 0 END) as has_applied,
    -- List nama volunteers yang sudah apply
    STUFF((
        SELECT ', ' + v.FullName
        FROM opportunity_volunteer ov
        JOIN Volunteer v ON ov.volunteer_id = v.VolunteerID
        WHERE ov.opportunity_id = o.opportunity_id
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'),1,2,'') AS Volunteers
FROM opportunity o
WHERE o.status = 'Open'
ORDER BY o.created_at DESC
";

$params = array($volunteer_id);
$stmt = sqlsrv_query($conn, $sql, $params);
if($stmt === false){
    die(print_r(sqlsrv_errors(), true));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Opportunities - Volunteer</title>
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
        
        /* ========== SIDEBAR STYLING ========== */
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
        
        /* ========== MAIN CONTENT ========== */
        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .welcome-section {
               background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); /* Sudah hijau */
        color: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        
        .card-title {
            color: #27ae60;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .opportunity-card {
            border-left: 4px solid #27ae60;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .opportunity-card:hover {
            background: #e8f5e9;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-apply {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-apply:hover:not(:disabled) {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(39, 174, 96, 0.3);
        }
        
        .btn-apply:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .btn-applied {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .skill-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .volunteers-list {
            background: #e3f2fd;
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 14px;
        }
        
        .slot-info {
            background: #f0f7ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .slot-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 5px 0 10px 0;
            overflow: hidden;
        }
        
        .slot-fill {
            height: 100%;
            background: #27ae60;
            border-radius: 4px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
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
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>Volunteer Panel</h4>
        <a href="volunteer_dashboard.php">🏠 Dashboard</a>
        <a href="volunteer_profile.php">👤 Profile</a>
        <a href="volunteer_assigned.php" class="active">🔍 View Opportunities</a>
        <a href="my_tasks.php">📋 My Tasks</a>
        <a href="volunteer_reports.php">📊 My Reports</a>
        <a href="main_page.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <div class="content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h3>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>! 👋</h3>
            <p>Browse and apply for available opportunities</p>
            <?php if(isset($volunteer['NGOName']) && $volunteer['NGOName']): ?>
                <p>Assigned NGO: <strong><?= htmlspecialchars($volunteer['NGOName']) ?></strong></p>
            <?php endif; ?>
            <div class="skill-badge">
                Skill: <?= htmlspecialchars($volunteer['SkillCategory'] ?? 'Not specified') ?>
            </div>
        </div>

        <!-- Display messages -->
        <?php if(isset($message)): 
            $msg_parts = explode(":", $message);
            $msg_type = $msg_parts[0];
            $msg_text = $msg_parts[1];
        ?>
            <div class="alert-<?= ($msg_type == 'success') ? 'success' : 'error' ?>">
                <?= htmlspecialchars($msg_text) ?>
            </div>
        <?php endif; ?>

        <!-- Available Opportunities Section -->
        <div class="dashboard-card">
            <h5 class="card-title">🔍 Available Opportunities</h5>
            <p class="text-muted mb-4">Apply directly for opportunities that interest you</p>
            
            <?php 
            if($stmt === false) {
                echo "<div class='alert-error'>Error loading opportunities.</div>";
            } else {
                $has_data = false;
                
                while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                    $has_data = true;
                    
                    // Calculate available slots
                    $total_slots = $row['slots'];
                    $applied_count = $row['applied_count'];
                    $available_slots = $total_slots - $applied_count;
                    $slot_percentage = ($total_slots > 0) ? ($applied_count / $total_slots) * 100 : 0;
                    
                    // Format dates
                    $event_date = $row['event_date'] instanceof DateTime ? 
                        $row['event_date']->format('M d, Y') : 
                        date('M d, Y', strtotime($row['event_date']));
                    $created_at = $row['created_at'] instanceof DateTime ? 
                        $row['created_at']->format('M d, Y') : 
                        date('M d, Y', strtotime($row['created_at']));
                    
                    // Check if user already applied
                    $has_applied = $row['has_applied'] == 1;
            ?>
            <div class="opportunity-card">
                <h5><?= htmlspecialchars($row['title']) ?></h5>
                <p class="text-muted"><?= htmlspecialchars($row['description']) ?></p>
                
                <!-- Slots Information -->
                <div class="slot-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>🎯 Slots Information:</strong><br>
                            <small>
                                Total Slots: <strong><?= $total_slots ?></strong><br>
                                Applied: <strong><?= $applied_count ?></strong><br>
                                Available: <strong><?= $available_slots ?></strong>
                            </small>
                        </div>
                        <div class="col-md-6">
                            <strong>Slot Fill Rate:</strong>
                            <div class="slot-bar">
                                <div class="slot-fill" style="width: <?= $slot_percentage ?>%"></div>
                            </div>
                            <small><?= round($slot_percentage, 1) ?>% filled</small>
                        </div>
                    </div>
                </div>
                
                <!-- Opportunity Details -->
                <div class="row mt-3">
                    <div class="col-md-4">
                        <strong>📍 Location</strong><br>
                        <?= htmlspecialchars($row['location']) ?>
                    </div>
                    
                    <div class="col-md-4">
                        <strong>📅 Event Date</strong><br>
                        <?= $event_date ?>
                    </div>
                    
                    <div class="col-md-4">
                        <strong>📝 Posted On</strong><br>
                        <?= $created_at ?>
                    </div>
                </div>
                
                <!-- Volunteers List -->
                <?php if($row['Volunteers']): ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <strong>👥 Volunteers Applied:</strong>
                        <div class="volunteers-list">
                            <?= htmlspecialchars($row['Volunteers']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Apply Button -->
                <div class="row mt-4">
                    <div class="col-md-12 text-end">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="opportunity_id" value="<?= $row['opportunity_id'] ?>">
                            <?php if($has_applied): ?>
                                <button type="button" class="btn-applied" disabled>
                                    ✅ Already Applied
                                </button>
                            <?php elseif($available_slots <= 0): ?>
                                <button type="button" class="btn-apply" disabled>
                                    ❌ No Slots Available
                                </button>
                            <?php else: ?>
                                <button type="submit" name="apply_opportunity" class="btn-apply">
                                    📝 Apply Now (<?= $available_slots ?> slots left)
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
                
                // If no opportunities found
                if(!$has_data) {
                    echo '<div class="alert alert-info">
                            <h5>📭 No Opportunities Available</h5>
                            <p class="mb-0">There are currently no open opportunities in the system.</p>
                          </div>';
                }
            }
            ?>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('[class*="alert-"]');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Highlight active sidebar link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.sidebar a');
            
            links.forEach(link => {
                const linkHref = link.getAttribute('href');
                if (linkHref === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>

<?php 
// Clean up resources
if(isset($stmt)) sqlsrv_free_stmt($stmt);
if(isset($volunteerStmt)) sqlsrv_free_stmt($volunteerStmt);
sqlsrv_close($conn); 
?>