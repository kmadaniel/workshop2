<?php
// ========================================
// ASSIGN VOLUNTEERS TO DISTRIBUTION
// User Story 4.2: Assign Volunteers to Distribution
// ========================================

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

$distribution_id = $_GET['distribution_id'] ?? null;
$error = '';
$success = '';

// If no distribution_id in URL, redirect to distribution_main.php
if (!$distribution_id) {
    header("Location: distribution_main.php");
    exit;
}

/* ----------------------------------------
   GET DISTRIBUTION PLAN DETAILS
---------------------------------------- */
try {
    $query = "
        SELECT d.*, dis.Disaster_Name, dis.Location as disaster_area 
        FROM distribution d
        JOIN disaster dis ON d.disaster_id = dis.disaster_id
        WHERE d.distribution_id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $distribution_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $distribution = $result->fetch_assoc();
    $stmt->close();
    
    if (!$distribution) {
        throw new Exception("Distribution plan not found!");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $distribution = null;
}

/* ----------------------------------------
   GET AVAILABLE VOLUNTEERS IN DISASTER AREA
   Filter by: NGO, assigned area
---------------------------------------- */
$available_volunteers = [];

if ($distribution) {
    try {
        // Get disaster location to filter volunteers
        $disaster_location = $distribution['disaster_area'] ?? 'All Areas';
        
        // Get available volunteers in the disaster area
        $volunteers_query = "
            SELECT v.*, 
                   CASE 
                       WHEN v.availability_status IS NULL OR v.availability_status = '' THEN 'Available'
                       ELSE v.availability_status 
                   END as availability_status,
                   CASE 
                       WHEN v.ngo_affiliation IS NULL OR v.ngo_affiliation = '' THEN 'Various'
                       ELSE v.ngo_affiliation 
                   END as ngo_affiliation
            FROM volunteer v
            WHERE (v.availability_status IS NULL 
                   OR v.availability_status = '' 
                   OR v.availability_status = 'Available'
                   OR v.availability_status = 'Standby')
            ORDER BY v.name ASC
        ";
        
        $result = $db->query($volunteers_query);
        $all_volunteers = $result->fetch_all(MYSQLI_ASSOC);
        
        // Filter out already assigned volunteers to THIS distribution
        $assigned_query = "
            SELECT dv.volunteer_id 
            FROM distribution_volunteer dv 
            WHERE dv.distribution_id = ?
        ";
        $stmt = $db->prepare($assigned_query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $assigned_result = $stmt->get_result();
        $assigned_ids = [];
        while ($row = $assigned_result->fetch_assoc()) {
            $assigned_ids[] = $row['volunteer_id'];
        }
        $stmt->close();
        
        // Filter available volunteers
        foreach ($all_volunteers as $volunteer) {
            if (!in_array($volunteer['volunteer_id'], $assigned_ids)) {
                $available_volunteers[] = $volunteer;
            }
        }
        
    } catch (Exception $e) {
        $error .= "<br>Error loading volunteers: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET CURRENTLY ASSIGNED VOLUNTEERS
---------------------------------------- */
$assigned_volunteers = [];
if ($distribution) {
    try {
        $assigned_query = "
            SELECT dv.*, v.name, v.phone, v.ngo_affiliation, v.email
            FROM distribution_volunteer dv
            JOIN volunteer v ON dv.volunteer_id = v.volunteer_id
            WHERE dv.distribution_id = ?
            ORDER BY dv.role, v.name
        ";
        
        $stmt = $db->prepare($assigned_query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $assigned_volunteers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        $error .= "<br>Error loading assigned volunteers: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET NGOs FOR FILTERING
   Based on your example: BSM Melaka, APM, MRA
---------------------------------------- */
$ngos = [];
try {
    $ngo_query = "SELECT DISTINCT ngo_affiliation FROM volunteer WHERE ngo_affiliation IS NOT NULL AND ngo_affiliation != '' ORDER BY ngo_affiliation";
    $result = $db->query($ngo_query);
    while ($row = $result->fetch_assoc()) {
        $ngos[] = $row['ngo_affiliation'];
    }
    $result->free();
} catch (Exception $e) {
    $ngos = ['All', 'BSM Melaka', 'APM', 'MRA', 'Red Crescent', 'UNHCR'];
}

/* ----------------------------------------
   FORM SUBMISSION: ASSIGN VOLUNTEERS
---------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_volunteers'])) {
        $selected_volunteers = $_POST['selected_volunteers'] ?? [];
        $roles = $_POST['roles'] ?? [];
        
        try {
            if (empty($selected_volunteers)) {
                throw new Exception("Please select at least one volunteer.");
            }
            
            $db->begin_transaction();
            
            foreach ($selected_volunteers as $volunteer_id) {
                $role = $roles[$volunteer_id] ?? 'Distributor';
                
                // Check if already assigned
                $check_query = "SELECT id FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows == 0) {
                    // AS PER DESIGN: Assigns volunteers
                    $assign_query = "
                        INSERT INTO distribution_volunteer (distribution_id, volunteer_id, role, status) 
                        VALUES (?, ?, ?, 'Assigned')
                    ";
                    $assign_stmt = $db->prepare($assign_query);
                    $assign_stmt->bind_param("iis", $distribution_id, $volunteer_id, $role);
                    $assign_stmt->execute();
                    $assign_stmt->close();
                    
                    // AS PER DESIGN: Updates volunteer status
                    $update_volunteer_query = "UPDATE volunteer SET availability_status = 'Assigned' WHERE volunteer_id = ?";
                    $update_volunteer_stmt = $db->prepare($update_volunteer_query);
                    $update_volunteer_stmt->bind_param("i", $volunteer_id);
                    $update_volunteer_stmt->execute();
                    $update_volunteer_stmt->close();
                    
                    // Get volunteer details for WhatsApp message
                    $vol_query = "SELECT name, phone FROM volunteer WHERE volunteer_id = ?";
                    $vol_stmt = $db->prepare($vol_query);
                    $vol_stmt->bind_param("i", $volunteer_id);
                    $vol_stmt->execute();
                    $vol_result = $vol_stmt->get_result();
                    $volunteer = $vol_result->fetch_assoc();
                    $vol_stmt->close();
                    
                    // Generate WhatsApp message
                    $whatsapp_message = generateWhatsAppMessage($volunteer, $distribution, $role);
                    
                    // In a real system, you would send this via WhatsApp API
                    // For now, we'll store it in session for display
                    $_SESSION['whatsapp_messages'][] = [
                        'volunteer' => $volunteer['name'],
                        'phone' => $volunteer['phone'],
                        'message' => $whatsapp_message
                    ];
                }
                $check_stmt->close();
            }
            
            // AS PER DESIGN: Updates distribution status to 'Assigned'
            $update_dist_query = "UPDATE distribution SET status = 'Assigned' WHERE distribution_id = ?";
            $update_dist_stmt = $db->prepare($update_dist_query);
            $update_dist_stmt->bind_param("i", $distribution_id);
            $update_dist_stmt->execute();
            $update_dist_stmt->close();
            
            $db->commit();
            
            $success = "✅ " . count($selected_volunteers) . " volunteer(s) assigned successfully!";
            $success .= "<br><strong>WhatsApp/SMS notifications have been prepared for sending.</strong>";
            $success .= "<br><small>Distribution status updated to: <span class='badge badge-assigned'>Assigned</span></small>";
            
            // Refresh data
            $assigned_volunteers = [];
            $available_volunteers = [];
            
            // Re-fetch assigned volunteers
            $stmt = $db->prepare($assigned_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assigned_volunteers = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Re-fetch available volunteers
            $result = $db->query($volunteers_query);
            $all_volunteers = $result->fetch_all(MYSQLI_ASSOC);
            $assigned_ids = array_column($assigned_volunteers, 'volunteer_id');
            $available_volunteers = array_filter($all_volunteers, function($v) use ($assigned_ids) {
                return !in_array($v['volunteer_id'], $assigned_ids);
            });
            
            // Store success messages for display
            if (!isset($_SESSION['whatsapp_messages'])) {
                $_SESSION['whatsapp_messages'] = [];
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
    
    // Handle removing volunteer
    if (isset($_POST['remove_volunteer'])) {
        $volunteer_id = $_POST['volunteer_id'];
        
        try {
            $remove_query = "DELETE FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $remove_stmt = $db->prepare($remove_query);
            $remove_stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $remove_stmt->execute();
            
            // Update volunteer status back to Available
            $update_volunteer_query = "UPDATE volunteer SET availability_status = 'Available' WHERE volunteer_id = ?";
            $update_volunteer_stmt = $db->prepare($update_volunteer_query);
            $update_volunteer_stmt->bind_param("i", $volunteer_id);
            $update_volunteer_stmt->execute();
            
            $success = "✅ Volunteer removed from assignment and marked as Available.";
            
            // Refresh data
            $stmt = $db->prepare($assigned_query);
            $stmt->bind_param("i", $distribution_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $assigned_volunteers = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

/* ----------------------------------------
   GENERATE WHATSAPP MESSAGE
   Exactly as per User Story 4.2
---------------------------------------- */
function generateWhatsAppMessage($volunteer, $distribution, $role) {
    $date = isset($distribution['date']) ? date('d/m/Y', strtotime($distribution['date'])) : date('d/m/Y');
    $time = isset($distribution['date']) ? date('h:i A', strtotime($distribution['date'])) : '4:00 PM';
    
    // Extract location from comments or use default
    $location = 'Dewan Serbaguna Masjid Tanah'; // Default as per example
    if (!empty($distribution['comments'])) {
        $comments = $distribution['comments'];
        if (preg_match('/Location:\s*(.+)/i', $comments, $matches)) {
            $location = trim($matches[1]);
        }
    }
    
    $message = "Assalamualaikum {$volunteer['name']},\n\n";
    $message .= "Tugasan Agihan Bantuan\n";
    $message .= "📅 {$date}, {$time}\n";
    $message .= "📍 {$location}\n";
    $message .= "👥 Peranan: {$role}\n";
    $message .= "📦 Mangsa: 50 keluarga\n\n";
    $message .= "Sila hadir 15 minit awal. Bawa:\n";
    $message .= "✓ Vest sukarelawan\n";
    $message .= "✓ Sarung tangan\n";
    $message .= "✓ Topeng muka\n\n";
    
    $message .= "Hubungi Encik Ahmad: 06-XXX XXXX\n\n";
    $message .= "Terima kasih! - JKM Melaka";
    
    return $message;
}

// Start session for WhatsApp messages
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Volunteers to Distribution - Disaster Relief System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Keep existing CSS but add enhancements */
        .volunteer-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .volunteer-card:hover {
            border-color: #3498db;
            background-color: #f0f8ff;
            transform: translateY(-2px);
        }
        .volunteer-card.selected {
            border-color: #27ae60;
            background-color: #e8f6f3;
            box-shadow: 0 4px 8px rgba(39, 174, 96, 0.2);
        }
        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
            margin: 2px;
        }
        .badge-coordinator { background: #3498db; color: white; }
        .badge-packer { background: #9b59b6; color: white; }
        .badge-distributor { background: #2ecc71; color: white; }
        .badge-driver { background: #f39c12; color: white; }
        .filter-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        .whatsapp-preview {
            background: #25d366;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            white-space: pre-wrap;
            display: none;
            border: 1px solid #1da851;
        }
        .volunteer-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            font-size: 1.2em;
        }
        .distribution-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .role-selection {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ddd;
            display: none;
        }
        .volunteer-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .whatsapp-icon {
            color: #25d366;
            margin-right: 8px;
        }
        .send-whatsapp-btn {
            background: #25d366;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            margin-top: 10px;
        }
        .send-whatsapp-btn:hover {
            background: #1da851;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="header">
            <h1><i class="fas fa-users"></i> Assign Volunteers to Distribution</h1>
            <p>Assign volunteers to distribution tasks and send WhatsApp notifications</p>
            <div style="margin-top: 15px;">
                <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> View Distribution Details
                </a>
                <a href="distribution_main.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!$distribution): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Distribution plan not found. Please check the distribution ID.
            </div>
        <?php else: ?>
            <!-- Distribution Header - Showing all details -->
            <div class="distribution-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div>
                        <h2 style="margin: 0; color: white;">
                            <i class="fas fa-box"></i> Distribution: DIST<?php echo str_pad($distribution_id, 4, '0', STR_PAD_LEFT); ?>
                        </h2>
                        <p style="margin: 5px 0 0 0; opacity: 0.9;">
                            <?php echo htmlspecialchars($distribution['Disaster_Name']); ?> - <?php echo htmlspecialchars($distribution['disaster_area']); ?>
                        </p>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 8px;">
                        <span class="badge" style="background: #fff; color: #2c3e50; font-size: 1.1em;">
                            <?php echo $distribution['status']; ?>
                        </span>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div>
                        <div style="font-size: 0.9em; opacity: 0.8;">Date & Time</div>
                        <div style="font-size: 1.1em; font-weight: bold;">
                            <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
                            <i class="far fa-clock" style="margin-left: 15px;"></i> <?php echo date('h:i A', strtotime($distribution['date'])); ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.9em; opacity: 0.8;">Location</div>
                        <div style="font-size: 1.1em; font-weight: bold;">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php 
                            if (!empty($distribution['comments']) && preg_match('/Location:\s*(.+)/i', $distribution['comments'], $matches)) {
                                echo htmlspecialchars(trim($matches[1]));
                            } else {
                                echo 'Dewan Serbaguna Masjid Tanah';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.9em; opacity: 0.8;">Volunteers</div>
                        <div style="font-size: 1.1em; font-weight: bold;">
                            <i class="fas fa-user-check"></i> <?php echo count($assigned_volunteers); ?> assigned
                            <span style="margin-left: 15px;">
                                <i class="fas fa-user-plus"></i> <?php echo count($available_volunteers); ?> available
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Assignment Form -->
            <div class="card-3d">
                <h2><i class="fas fa-step-forward"></i> Step 1: Select Volunteers</h2>
                
                <!-- Filter Section - As per User Story: Filter by NGO, assigned area -->
                <div class="filter-section">
                    <h4><i class="fas fa-filter"></i> Filter Volunteers</h4>
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Filter by NGO</label>
                            <select class="form-control" id="ngo_filter">
                                <option value="all">All NGOs</option>
                                <?php foreach ($ngos as $ngo): ?>
                                <option value="<?php echo htmlspecialchars(strtolower(str_replace(' ', '_', $ngo))); ?>">
                                    <?php echo htmlspecialchars($ngo); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Filter by Area</label>
                            <select class="form-control" id="area_filter">
                                <option value="all">All Areas</option>
                                <option value="alor_gajah">Alor Gajah</option>
                                <option value="masjid_tanah">Masjid Tanah</option>
                                <option value="melaka_tengah">Melaka Tengah</option>
                                <option value="jasin">Jasin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Filter by Availability</label>
                            <select class="form-control" id="availability_filter">
                                <option value="all">All Status</option>
                                <option value="available">Available</option>
                                <option value="standby">Standby</option>
                                <option value="assigned">Assigned</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <form method="POST" id="assign-volunteers-form">
                    <input type="hidden" name="assign_volunteers" value="1">
                    
                    <!-- Multi-select volunteers section -->
                    <div style="max-height: 400px; overflow-y: auto; padding: 15px; border: 2px solid #ddd; border-radius: 8px; background: #fff;">
                        <?php if (empty($available_volunteers)): ?>
                            <div class="empty-state" style="text-align: center; padding: 40px;">
                                <i class="fas fa-users-slash" style="font-size: 3em; color: #bdc3c7; margin-bottom: 15px;"></i>
                                <h3>No Available Volunteers</h3>
                                <p>All volunteers are already assigned to this distribution.</p>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 15px;">
                                <label style="display: flex; align-items: center; font-weight: bold; color: #27ae60;">
                                    <input type="checkbox" id="select-all-volunteers" style="margin-right: 10px; transform: scale(1.3);">
                                    <span><i class="fas fa-check-square"></i> Select All (<?php echo count($available_volunteers); ?> available volunteers)</span>
                                </label>
                            </div>
                            
                            <?php foreach ($available_volunteers as $volunteer): 
                                $volunteer_initial = strtoupper(substr($volunteer['name'], 0, 1));
                                $ngo_class = strtolower(str_replace(' ', '_', $volunteer['ngo_affiliation']));
                                $role_class = strtolower($volunteer['main_role'] ?? 'distributor');
                            ?>
                            <div class="volunteer-card" 
                                 data-ngo="<?php echo $ngo_class; ?>"
                                 data-role="<?php echo $role_class; ?>"
                                 data-availability="<?php echo strtolower($volunteer['availability_status']); ?>">
                                <label style="display: block; cursor: pointer;">
                                    <div class="volunteer-info">
                                        <input type="checkbox" 
                                               name="selected_volunteers[]" 
                                               value="<?php echo $volunteer['volunteer_id']; ?>" 
                                               class="volunteer-checkbox"
                                               data-volunteer-id="<?php echo $volunteer['volunteer_id']; ?>"
                                               data-volunteer-name="<?php echo htmlspecialchars($volunteer['name']); ?>"
                                               data-volunteer-phone="<?php echo htmlspecialchars($volunteer['phone']); ?>"
                                               style="margin-right: 15px; transform: scale(1.3);"
                                               onchange="updateRoleSelect(this)">
                                        <div class="volunteer-avatar">
                                            <?php echo $volunteer_initial; ?>
                                        </div>
                                        <div style="flex-grow: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <strong style="font-size: 1.1em;"><?php echo htmlspecialchars($volunteer['name']); ?></strong>
                                                <span style="background: #e3f2fd; color: #1976d2; padding: 3px 10px; border-radius: 12px; font-size: 0.85em;">
                                                    <?php echo htmlspecialchars($volunteer['ngo_affiliation']); ?>
                                                </span>
                                            </div>
                                            <div style="color: #666; margin: 5px 0;">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer['phone']); ?>
                                                <?php if (!empty($volunteer['email'])): ?>
                                                    <span style="margin-left: 15px;">
                                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($volunteer['email']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.9em; color: <?php echo $volunteer['availability_status'] == 'Available' ? '#27ae60' : '#f39c12'; ?>;">
                                                <i class="fas fa-circle" style="font-size: 0.7em;"></i> <?php echo $volunteer['availability_status']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                                
                                <!-- Role Selection - As per User Story: Assign roles -->
                                <div class="role-selection" id="role-selection-<?php echo $volunteer['volunteer_id']; ?>">
                                    <label style="font-weight: bold; color: #2c3e50;">Assign Role:</label>
                                    <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" 
                                                   name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                   value="Coordinator" 
                                                   class="role-radio"
                                                   onchange="updateWhatsAppPreview()">
                                            <span class="role-badge badge-coordinator" style="margin-left: 5px;">
                                                <i class="fas fa-user-tie"></i> Coordinator
                                            </span>
                                        </label>
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" 
                                                   name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                   value="Packer" 
                                                   class="role-radio"
                                                   onchange="updateWhatsAppPreview()">
                                            <span class="role-badge badge-packer" style="margin-left: 5px;">
                                                <i class="fas fa-box"></i> Packer
                                            </span>
                                        </label>
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" 
                                                   name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                   value="Distributor" 
                                                   class="role-radio" 
                                                   checked
                                                   onchange="updateWhatsAppPreview()">
                                            <span class="role-badge badge-distributor" style="margin-left: 5px;">
                                                <i class="fas fa-truck"></i> Distributor
                                            </span>
                                        </label>
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" 
                                                   name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                   value="Driver" 
                                                   class="role-radio"
                                                   onchange="updateWhatsAppPreview()">
                                            <span class="role-badge badge-driver" style="margin-left: 5px;">
                                                <i class="fas fa-car"></i> Driver
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="fas fa-check-circle"></i> 
                            <strong>Selected:</strong> <span id="selected-count">0</span> volunteers
                        </div>
                    </div>

                    <!-- WhatsApp Preview - As per User Story -->
                    <div class="whatsapp-preview" id="whatsapp-preview">
                        <!-- Preview will be inserted here by JavaScript -->
                    </div>

                    <div class="form-actions mt-4" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-success btn-lg" id="assign-button" disabled>
                            <i class="fab fa-whatsapp"></i> Assign Volunteers & Send WhatsApp
                        </button>
                        <a href="create_distribution.php" class="btn btn-warning">
                            <i class="fas fa-arrow-left"></i> Back to Planning
                        </a>
                        <a href="distribution_main.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </div>
                </form>
            </div>

            <!-- Currently Assigned Volunteers -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="card-3d mt-4">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">
                        <i class="fas fa-user-check"></i> Currently Assigned Volunteers
                        <span class="badge badge-info"><?php echo count($assigned_volunteers); ?></span>
                    </h2>
                    <div>
                        <a href="javascript:void(0)" onclick="printAssignment()" class="btn btn-secondary btn-sm">
                            <i class="fas fa-print"></i> Print Assignment List
                        </a>
                    </div>
                </div>
                
                <div class="table-container">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="padding: 12px; background: #f8f9fa;">Volunteer</th>
                                <th style="padding: 12px; background: #f8f9fa;">Contact</th>
                                <th style="padding: 12px; background: #f8f9fa;">Assigned Role</th>
                                <th style="padding: 12px; background: #f8f9fa;">NGO</th>
                                <th style="padding: 12px; background: #f8f9fa;">Status</th>
                                <th style="padding: 12px; background: #f8f9fa;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_volunteers as $assignment): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center;">
                                        <div class="volunteer-avatar" style="width: 35px; height: 35px; font-size: 1em;">
                                            <?php echo strtoupper(substr($assignment['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($assignment['name']); ?></strong>
                                            <div style="font-size: 0.85em; color: #666;">
                                                VOL<?php echo str_pad($assignment['volunteer_id'], 4, '0', STR_PAD_LEFT); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($assignment['phone']); ?></div>
                                    <?php if (!empty($assignment['email'])): ?>
                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($assignment['email']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span class="role-badge badge-<?php echo strtolower($assignment['role']); ?>">
                                        <?php echo $assignment['role']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo htmlspecialchars($assignment['ngo_affiliation']); ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span class="badge badge-assigned">
                                        <?php echo $assignment['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="remove_volunteer" value="1">
                                        <input type="hidden" name="volunteer_id" value="<?php echo $assignment['volunteer_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Remove this volunteer from assignment?')">
                                            <i class="fas fa-user-times"></i> Remove
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-info btn-sm mt-1" 
                                            onclick="resendWhatsApp(<?php echo $assignment['volunteer_id']; ?>, '<?php echo htmlspecialchars($assignment['name']); ?>', '<?php echo htmlspecialchars($assignment['phone']); ?>', '<?php echo $assignment['role']; ?>')">
                                        <i class="fab fa-whatsapp"></i> Resend WhatsApp
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="grid-3 mt-4">
                <div class="stat-card">
                    <div class="stat-label"><i class="fas fa-users"></i> Total Volunteers</div>
                    <div class="stat-number"><?php echo count($all_volunteers); ?></div>
                    <div class="stat-desc">In system database</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label"><i class="fas fa-user-check"></i> Assigned</div>
                    <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                    <div class="stat-desc">To this distribution</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label"><i class="fas fa-user-plus"></i> Available</div>
                    <div class="stat-number"><?php echo count($available_volunteers); ?></div>
                    <div class="stat-desc">Ready for assignment</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Volunteer selection and filtering
        const volunteerCards = document.querySelectorAll('.volunteer-card');
        const checkboxes = document.querySelectorAll('.volunteer-checkbox');
        const ngoFilter = document.getElementById('ngo_filter');
        const areaFilter = document.getElementById('area_filter');
        const availabilityFilter = document.getElementById('availability_filter');
        const assignButton = document.getElementById('assign-button');
        const whatsappPreview = document.getElementById('whatsapp-preview');
        const selectAllCheckbox = document.getElementById('select-all-volunteers');
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.volunteer-checkbox:checked');
            document.getElementById('selected-count').textContent = selected.length;
            assignButton.disabled = selected.length === 0;
            
            // Update WhatsApp preview
            updateWhatsAppPreview();
        }
        
        // Show role selection when volunteer is selected
        function updateRoleSelect(checkbox) {
            const card = checkbox.closest('.volunteer-card');
            const volunteerId = checkbox.getAttribute('data-volunteer-id');
            const roleSelection = card.querySelector('.role-selection');
            
            card.classList.toggle('selected', checkbox.checked);
            roleSelection.style.display = checkbox.checked ? 'block' : 'none';
            
            updateSelectedCount();
        }
        
        // Filter volunteers
        function filterVolunteers() {
            const ngoValue = ngoFilter.value;
            const areaValue = areaFilter.value;
            const availabilityValue = availabilityFilter.value;
            
            volunteerCards.forEach(card => {
                const ngo = card.getAttribute('data-ngo');
                const availability = card.getAttribute('data-availability');
                let show = true;
                
                if (ngoValue !== 'all' && ngo !== ngoValue) {
                    show = false;
                }
                
                if (availabilityValue !== 'all' && availability !== availabilityValue) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Generate WhatsApp preview
        function updateWhatsAppPreview() {
            const selectedCheckboxes = document.querySelectorAll('.volunteer-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                whatsappPreview.style.display = 'none';
                return;
            }
            
            // Take first volunteer as example
            const firstVolunteer = selectedCheckboxes[0];
            const volunteerName = firstVolunteer.getAttribute('data-volunteer-name');
            const volunteerPhone = firstVolunteer.getAttribute('data-volunteer-phone');
            const volunteerId = firstVolunteer.getAttribute('data-volunteer-id');
            
            // Find selected role
            const roleRadio = document.querySelector(`input[name="roles[${volunteerId}]"]:checked`);
            const role = roleRadio ? roleRadio.value : 'Distributor';
            
            // Generate message as per User Story
            const message = `Assalamualaikum ${volunteerName},

Tugasan Agihan Bantuan
📅 <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>, <?php echo date('h:i A', strtotime($distribution['date'])); ?>
📍 Dewan Serbaguna Masjid Tanah
👥 Peranan: ${role}
📦 Mangsa: 50 keluarga

Sila hadir 15 minit awal. Bawa:
✓ Vest sukarelawan
✓ Sarung tangan
✓ Topeng muka

Hubungi Encik Ahmad: 06-XXX XXXX

Terima kasih! - JKM Melaka`;
            
            whatsappPreview.innerHTML = `
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0;"><i class="fab fa-whatsapp"></i> WhatsApp Preview</h4>
                    <button type="button" class="send-whatsapp-btn" onclick="sendWhatsApp('${volunteerPhone}', \`${message}\`)">
                        <i class="fab fa-whatsapp"></i> Test Send to ${volunteerName}
                    </button>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; font-size: 0.95em;">
                    ${message.replace(/\n/g, '<br>')}
                </div>
            `;
            whatsappPreview.style.display = 'block';
        }
        
        // Select all volunteers
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    updateRoleSelect(checkbox);
                });
            });
        }
        
        // Event listeners for filters
        ngoFilter.addEventListener('change', filterVolunteers);
        areaFilter.addEventListener('change', filterVolunteers);
        availabilityFilter.addEventListener('change', filterVolunteers);
        
        // Event listeners for checkboxes
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateRoleSelect(this);
            });
        });
        
        // Role selection change updates preview
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('role-radio')) {
                updateWhatsAppPreview();
            }
        });
        
        // Simulate WhatsApp sending
        function sendWhatsApp(phone, message) {
            const whatsappUrl = `https://wa.me/${phone.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
            
            alert(`WhatsApp will open for ${phone}.\nIn production, this would be sent automatically via WhatsApp Business API.`);
        }
        
        // Resend WhatsApp for assigned volunteer
        function resendWhatsApp(volunteerId, volunteerName, volunteerPhone, role) {
            const message = `Assalamualaikum ${volunteerName},

Tugasan Agihan Bantuan
📅 <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>, <?php echo date('h:i A', strtotime($distribution['date'])); ?>
📍 Dewan Serbaguna Masjid Tanah
👥 Peranan: ${role}
📦 Mangsa: 50 keluarga

Sila hadir 15 minit awal. Bawa:
✓ Vest sukarelawan
✓ Sarung tangan
✓ Topeng muka

Hubungi Encik Ahmad: 06-XXX XXXX

Terima kasih! - JKM Melaka`;
            
            if (confirm(`Resend WhatsApp to ${volunteerName}?`)) {
                sendWhatsApp(volunteerPhone, message);
            }
        }
        
        // Print assignment list
        function printAssignment() {
            window.print();
        }
        
        // Confirm before submitting
        document.getElementById('assign-volunteers-form').addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.volunteer-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Please select at least one volunteer.');
                return;
            }
            
            if (!confirm(`Assign ${selected} volunteer(s) and send WhatsApp notifications?\n\nThis will:\n1. Assign volunteers to distribution\n2. Update volunteer status to "Assigned"\n3. Send WhatsApp/SMS notifications\n4. Update distribution status to "Assigned"`)) {
                e.preventDefault();
            }
        });
        
        // Initial setup
        updateSelectedCount();
        filterVolunteers();
        
        // Auto-update preview when page loads if there are selected checkboxes
        window.addEventListener('load', function() {
            const selected = document.querySelectorAll('.volunteer-checkbox:checked');
            if (selected.length > 0) {
                updateWhatsAppPreview();
            }
        });
    </script>
</body>
</html>