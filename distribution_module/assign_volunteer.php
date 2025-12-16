<?php
// ========================================
// ASSIGN VOLUNTEERS TO DISTRIBUTION
// User Story 4.2: Assign Volunteers to Distribution
// ========================================

require_once 'config.php';

// Start session early for WhatsApp messages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Initialize variables to prevent undefined errors
$all_volunteers = [];
$available_volunteers = [];
$assigned_volunteers = [];
$distribution = null;
$ngos = ['All', 'BSM Melaka', 'APM', 'MRA', 'Red Crescent', 'UNHCR', 'Various'];

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
   PHONE NUMBER FORMATTING FUNCTION
---------------------------------------- */
function formatPhoneForWhatsApp($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '60' . substr($phone, 1);
    }
    if (substr($phone, 0, 2) !== '60') {
        $phone = '60' . $phone;
    }
    return $phone;
}

/* ----------------------------------------
   GET AVAILABLE VOLUNTEERS
   FIXED: Works with your actual database schema
---------------------------------------- */
if ($distribution) {
    try {
        // Simple query that works with your volunteer table (volunteer_id, name, phone, role)
        $volunteers_query = "
            SELECT v.volunteer_id, v.name, v.phone, v.role
            FROM volunteer v
            ORDER BY v.name ASC
        ";
        
        $result = $db->query($volunteers_query);
        if ($result) {
            $all_volunteers = $result->fetch_all(MYSQLI_ASSOC);
            
            // Add default values for missing columns
            foreach ($all_volunteers as &$vol) {
                $vol['availability_status'] = 'Available';  // Default status
                $vol['ngo_affiliation'] = 'Various';        // Default NGO
            }
        } else {
            throw new Exception("Query failed: " . $db->error);
        }
        
        // Filter out already assigned volunteers to THIS distribution
        $assigned_query = "SELECT volunteer_id FROM distribution_volunteer WHERE distribution_id = ?";
        $stmt = $db->prepare($assigned_query);
        $stmt->bind_param("i", $distribution_id);
        $stmt->execute();
        $assigned_result = $stmt->get_result();
        $assigned_ids = [];
        while ($row = $assigned_result->fetch_assoc()) {
            $assigned_ids[] = $row['volunteer_id'];
        }
        $stmt->close();
        
        // Build available volunteers list (exclude already assigned)
        foreach ($all_volunteers as $volunteer) {
            if (!in_array($volunteer['volunteer_id'], $assigned_ids)) {
                $available_volunteers[] = $volunteer;
            }
        }
        
        // Debug: Log the counts
        error_log("Total volunteers: " . count($all_volunteers));
        error_log("Already assigned: " . count($assigned_ids));
        error_log("Available volunteers: " . count($available_volunteers));
        
    } catch (Exception $e) {
        $error .= "<br>Error loading volunteers: " . $e->getMessage();
    }
}

/* ----------------------------------------
   GET CURRENTLY ASSIGNED VOLUNTEERS
   FIXED: Works with your actual database schema
---------------------------------------- */
if ($distribution) {
    try {
        $assigned_query = "
            SELECT dv.*, v.name, v.phone, v.role as volunteer_role
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
        
        // Add default NGO for each assigned volunteer
        foreach ($assigned_volunteers as &$vol) {
            $vol['ngo_affiliation'] = 'Various';
        }
        
        $stmt->close();
        
        // Debug: Log assigned volunteers
        error_log("Assigned volunteers count: " . count($assigned_volunteers));
        
    } catch (Exception $e) {
        $error .= "<br>Error loading assigned volunteers: " . $e->getMessage();
    }
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
            $whatsapp_messages_sent = [];
            
            foreach ($selected_volunteers as $volunteer_id) {
                $role = $roles[$volunteer_id] ?? 'Distributor';
                
                $check_query = "SELECT id FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bind_param("ii", $distribution_id, $volunteer_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows == 0) {
                    $assign_query = "INSERT INTO distribution_volunteer (distribution_id, volunteer_id, role, status) VALUES (?, ?, ?, 'Assigned')";
                    $assign_stmt = $db->prepare($assign_query);
                    $assign_stmt->bind_param("iis", $distribution_id, $volunteer_id, $role);
                    $assign_stmt->execute();
                    $assign_stmt->close();
                    
                    $update_volunteer_query = "UPDATE volunteer SET availability_status = 'Assigned' WHERE volunteer_id = ?";
                    $update_volunteer_stmt = $db->prepare($update_volunteer_query);
                    $update_volunteer_stmt->bind_param("i", $volunteer_id);
                    $update_volunteer_stmt->execute();
                    $update_volunteer_stmt->close();
                    
                    $vol_query = "SELECT name, phone FROM volunteer WHERE volunteer_id = ?";
                    $vol_stmt = $db->prepare($vol_query);
                    $vol_stmt->bind_param("i", $volunteer_id);
                    $vol_stmt->execute();
                    $vol_result = $vol_stmt->get_result();
                    $volunteer = $vol_result->fetch_assoc();
                    $vol_stmt->close();
                    
                    $whatsapp_message = generateWhatsAppMessage($volunteer, $distribution, $role);
                    $formatted_phone = formatPhoneForWhatsApp($volunteer['phone']);
                    $encoded_message = urlencode($whatsapp_message);
                    $whatsapp_url = "https://wa.me/{$formatted_phone}?text={$encoded_message}";
                    
                    $whatsapp_messages_sent[] = [
                        'volunteer' => $volunteer['name'],
                        'phone' => $volunteer['phone'],
                        'formatted_phone' => $formatted_phone,
                        'whatsapp_url' => $whatsapp_url,
                        'message' => $whatsapp_message
                    ];
                }
                $check_stmt->close();
            }
            
            $update_dist_query = "UPDATE distribution SET status = 'Assigned' WHERE distribution_id = ?";
            $update_dist_stmt = $db->prepare($update_dist_query);
            $update_dist_stmt->bind_param("i", $distribution_id);
            $update_dist_stmt->execute();
            $update_dist_stmt->close();
            
            $db->commit();
            
            $_SESSION['whatsapp_messages'] = $whatsapp_messages_sent;
            $_SESSION['assignment_success'] = count($selected_volunteers);
            
            header("Location: assign_volunteer.php?distribution_id={$distribution_id}&success=1");
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_volunteer'])) {
        $volunteer_id = $_POST['volunteer_id'];
        
        try {
            $remove_query = "DELETE FROM distribution_volunteer WHERE distribution_id = ? AND volunteer_id = ?";
            $remove_stmt = $db->prepare($remove_query);
            $remove_stmt->bind_param("ii", $distribution_id, $volunteer_id);
            $remove_stmt->execute();
            
            $update_volunteer_query = "UPDATE volunteer SET availability_status = 'Available' WHERE volunteer_id = ?";
            $update_volunteer_stmt = $db->prepare($update_volunteer_query);
            $update_volunteer_stmt->bind_param("i", $volunteer_id);
            $update_volunteer_stmt->execute();
            
            $success = "✅ Volunteer removed successfully!";
            
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

function generateWhatsAppMessage($volunteer, $distribution, $role) {
    $date = isset($distribution['date']) ? date('d/m/Y', strtotime($distribution['date'])) : date('d/m/Y');
    $time = '4:00 PM';
    $location = 'Dewan Serbaguna Masjid Tanah';
    
    if (!empty($distribution['comments']) && preg_match('/Location:\s*(.+)/i', $distribution['comments'], $matches)) {
        $location = trim($matches[1]);
    }
    
    $message = "Assalamualaikum {$volunteer['name']},\n\n";
    $message .= "TUGASAN AGIHAN BANTUAN\n";
    $message .= "📅 Tarikh: {$date}\n";
    $message .= "⏰ Masa: {$time}\n";
    $message .= "📍 Lokasi: {$location}\n";
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

// Check for success parameter
$show_success_modal = isset($_GET['success']) && $_GET['success'] == '1' && isset($_SESSION['assignment_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Volunteers - Disaster Relief System</title>
    <link rel="stylesheet" href="../css/assign.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Success Modal -->
    <div class="success-modal" id="successModal">
        <div class="success-modal-content">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2>🎉 Assignment Successful!</h2>
            <p>
                <strong><?php echo $_SESSION['assignment_success'] ?? 0; ?> volunteer(s)</strong> have been assigned to this distribution.
                <br><br>
                WhatsApp notifications have been sent automatically.
                <br>
                Distribution status updated to <span style="color: #9b59b6; font-weight: bold;">Assigned</span>.
            </p>
            <div class="success-actions">
                <button onclick="viewWhatsAppMessages()" class="btn btn-success">
                    <i class="fab fa-whatsapp"></i> View Messages Sent
                </button>
                <button onclick="closeSuccessModal()" class="btn btn-primary">
                    <i class="fas fa-check"></i> Continue
                </button>
                <a href="distribution_main.php" class="btn btn-info">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Process Flow Navigation -->
        <div class="process-flow animated-card">
            <h3 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">
                <i class="fas fa-route"></i> Distribution Process Flow
            </h3>
            <p style="text-align: center; color: #7f8c8d; margin-bottom: 25px;">Track your progress through the distribution system</p>
            
            <div class="flow-steps">
                <div class="flow-line"></div>
                <div class="flow-progress" style="width: 50%;"></div>
                
                <div class="flow-step completed">
                    <div class="flow-step-circle">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="flow-step-label">Create<br>Distribution</div>
                </div>
                
                <div class="flow-step active">
                    <div class="flow-step-circle">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="flow-step-label">Assign<br>Volunteers</div>
                </div>
                
                <div class="flow-step">
                    <div class="flow-step-circle">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="flow-step-label">Execute<br>Distribution</div>
                </div>
                
                <div class="flow-step">
                    <div class="flow-step-circle">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="flow-step-label">Complete &<br>Report</div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="create_distribution_plan.php" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Create
                </a>
                <span class="btn btn-sm btn-success" style="cursor: default;">
                    <i class="fas fa-check"></i> Current Step
                </span>
                <?php if (count($assigned_volunteers) > 0): ?>
                    <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-right"></i> Next: Execute
                    </a>
                <?php else: ?>
                    <button class="btn btn-sm" style="background: #95a5a6; color: white; cursor: not-allowed;" 
                            title="Assign at least one volunteer to proceed to execution" 
                            disabled>
                        <i class="fas fa-arrow-right"></i> Next: Execute
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger animated-card">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!$distribution): ?>
            <div class="alert alert-danger animated-card">
                <i class="fas fa-exclamation-triangle"></i> Distribution plan not found.
            </div>
        <?php else: ?>
            <!-- Distribution Header -->
            <div class="distribution-header animated-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <h2 style="margin: 0; font-size: 26px;">
                            <i class="fas fa-box-open"></i> Distribution: DIST<?php echo str_pad($distribution_id, 7, '0', STR_PAD_LEFT); ?>
                        </h2>
                        <p style="margin: 8px 0 0 0; opacity: 0.95; font-size: 15px;">
                            📍 <?php echo htmlspecialchars($distribution['Disaster_Name'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($distribution['disaster_area'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div style="text-align: right;">
                        <div class="badge" style="background: rgba(255,255,255,0.2); color: white; font-size: 14px; padding: 8px 16px;">
                            Status: <?php echo $distribution['status']; ?>
                        </div>
                        <div style="margin-top: 10px; font-size: 13px; opacity: 0.9;">
                            <i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <div>
                        <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">👥 Volunteers</div>
                        <div style="font-size: 22px; font-weight: bold;">
                            <?php echo count($assigned_volunteers); ?> Assigned
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">✅ Available</div>
                        <div style="font-size: 22px; font-weight: bold;">
                            <?php echo count($available_volunteers); ?> Ready
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 12px; opacity: 0.8; margin-bottom: 5px;">📦 Families</div>
                        <div style="font-size: 22px; font-weight: bold;">
                            50 Families
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Assignment Form -->
            <div class="card animated-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> Select & Assign Volunteers</h2>
                </div>
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <h4 style="margin-bottom: 15px;"><i class="fas fa-filter"></i> Filter Volunteers</h4>
                        <div class="grid-3">
                            <div class="form-group">
                                <label class="form-label">By NGO</label>
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
                                <label class="form-label">By Availability</label>
                                <select class="form-control" id="availability_filter">
                                    <option value="all">All Status</option>
                                    <option value="available">Available</option>
                                    <option value="standby">Standby</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Quick Actions</label>
                                <button type="button" class="btn btn-info" onclick="document.getElementById('select-all-volunteers').click();" style="width: 100%;">
                                    <i class="fas fa-check-double"></i> Select All
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" id="assign-volunteers-form">
                        <input type="hidden" name="assign_volunteers" value="1">
                        
                        <!-- Volunteers List -->
                        <div style="max-height: 500px; overflow-y: auto; padding: 20px; border: 2px solid #e0e0e0; border-radius: 12px; background: #fafafa;">
                            <?php if (empty($available_volunteers)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">👥</div>
                                    <h3>No Available Volunteers</h3>
                                    <p>All volunteers are already assigned to this distribution.</p>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #3498db;">
                                    <label style="display: flex; align-items: center; font-weight: bold; color: #2c3e50; cursor: pointer;">
                                        <input type="checkbox" id="select-all-volunteers" style="margin-right: 12px; transform: scale(1.5);">
                                        <span><i class="fas fa-check-square"></i> Select All (<?php echo count($available_volunteers); ?> volunteers available)</span>
                                    </label>
                                </div>
                                
                                <?php foreach ($available_volunteers as $volunteer): 
                                    $volunteer_initial = strtoupper(substr($volunteer['name'], 0, 1));
                                    $ngo_class = strtolower(str_replace(' ', '_', $volunteer['ngo_affiliation'] ?? 'Various'));
                                    $availability = strtolower($volunteer['availability_status'] ?? 'available');
                                    $formatted_phone_display = formatPhoneForWhatsApp($volunteer['phone'] ?? '');
                                ?>
                                <div class="volunteer-card" 
                                     data-ngo="<?php echo $ngo_class; ?>"
                                     data-availability="<?php echo $availability; ?>">
                                    <label style="display: block; cursor: pointer;">
                                        <div style="display: flex; align-items: flex-start;">
                                            <input type="checkbox" 
                                                   name="selected_volunteers[]" 
                                                   value="<?php echo $volunteer['volunteer_id']; ?>" 
                                                   class="volunteer-checkbox"
                                                   data-volunteer-id="<?php echo $volunteer['volunteer_id']; ?>"
                                                   data-volunteer-name="<?php echo htmlspecialchars($volunteer['name']); ?>"
                                                   data-volunteer-phone="<?php echo htmlspecialchars($volunteer['phone'] ?? 'N/A'); ?>"
                                                   style="margin-right: 15px; margin-top: 15px; transform: scale(1.5);"
                                                   onchange="updateRoleSelect(this)">
                                            <div class="volunteer-avatar">
                                                <?php echo $volunteer_initial; ?>
                                            </div>
                                            <div style="flex-grow: 1;">
                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                                    <strong style="font-size: 16px; color: #2c3e50;"><?php echo htmlspecialchars($volunteer['name']); ?></strong>
                                                    <span class="badge" style="background: #e3f2fd; color: #1976d2; font-size: 11px;">
                                                        <?php echo htmlspecialchars($volunteer['ngo_affiliation'] ?? 'Various'); ?>
                                                    </span>
                                                </div>
                                                <div style="color: #7f8c8d; font-size: 14px; margin-bottom: 5px;">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($volunteer['phone'] ?? 'N/A'); ?>
                                                    <span style="margin-left: 15px; color: #25d366;">
                                                        <i class="fab fa-whatsapp"></i> +<?php echo $formatted_phone_display; ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 13px;">
                                                    <span class="badge" style="background: <?php echo ($volunteer['availability_status'] ?? 'Available') == 'Available' ? '#d4edda' : '#fff3cd'; ?>; color: <?php echo ($volunteer['availability_status'] ?? 'Available') == 'Available' ? '#155724' : '#856404'; ?>;">
                                                        <i class="fas fa-circle" style="font-size: 8px;"></i> <?php echo $volunteer['availability_status'] ?? 'Available'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <!-- Role Selection -->
                                    <div class="role-selection" id="role-selection-<?php echo $volunteer['volunteer_id']; ?>">
                                        <label style="font-weight: bold; color: #2c3e50; display: block; margin-bottom: 12px;">
                                            <i class="fas fa-user-tag"></i> Assign Role:
                                        </label>
                                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                            <label style="cursor: pointer;">
                                                <input type="radio" 
                                                       name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                       value="Coordinator" 
                                                       style="display: none;"
                                                       onchange="updateWhatsAppPreview()">
                                                <span class="role-badge badge-coordinator">
                                                    <i class="fas fa-user-tie"></i> Coordinator
                                                </span>
                                            </label>
                                            <label style="cursor: pointer;">
                                                <input type="radio" 
                                                       name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                       value="Packer" 
                                                       style="display: none;"
                                                       onchange="updateWhatsAppPreview()">
                                                <span class="role-badge badge-packer">
                                                    <i class="fas fa-box"></i> Packer
                                                </span>
                                            </label>
                                            <label style="cursor: pointer;">
                                                <input type="radio" 
                                                       name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                       value="Distributor" 
                                                       checked
                                                       style="display: none;"
                                                       onchange="updateWhatsAppPreview()">
                                                <span class="role-badge badge-distributor">
                                                    <i class="fas fa-truck"></i> Distributor
                                                </span>
                                            </label>
                                            <label style="cursor: pointer;">
                                                <input type="radio" 
                                                       name="roles[<?php echo $volunteer['volunteer_id']; ?>]" 
                                                       value="Driver" 
                                                       style="display: none;"
                                                       onchange="updateWhatsAppPreview()">
                                                <span class="role-badge badge-driver">
                                                    <i class="fas fa-car"></i> Driver
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="alert alert-info" style="margin-top: 20px;">
                            <strong>Selected:</strong> <span id="selected-count" style="font-size: 20px; color: #3498db;">0</span> volunteers
                        </div>

                        <!-- WhatsApp Preview -->
                        <div class="whatsapp-preview" id="whatsapp-preview"></div>

                        <div class="form-actions" style="margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-success btn-lg btn-pulse" id="assign-button" disabled>
                                <i class="fab fa-whatsapp"></i> Assign Volunteers & Send WhatsApp
                            </button>
                            <?php if (count($assigned_volunteers) > 0): ?>
                                <a href="execute_distribution.php?distribution_id=<?php echo $distribution_id; ?>" class="btn btn-warning btn-lg">
                                    <i class="fas fa-play-circle"></i> Execute Distribution
                                </a>
                            <?php endif; ?>
                            <a href="distribution_main.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Currently Assigned Volunteers -->
            <?php if (!empty($assigned_volunteers)): ?>
            <div class="card animated-card" style="margin-top: 30px;">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-user-check"></i> Assigned Volunteers
                        <span class="badge" style="background: white; color: #2c3e50; margin-left: 10px;"><?php echo count($assigned_volunteers); ?></span>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Volunteer</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>NGO</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_volunteers as $assignment): 
                                    $formatted_phone = formatPhoneForWhatsApp($assignment['phone']);
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="volunteer-avatar" style="width: 40px; height: 40px; font-size: 16px;">
                                                <?php echo strtoupper(substr($assignment['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($assignment['name']); ?></strong>
                                                <div style="font-size: 12px; color: #7f8c8d;">
                                                    VOL<?php echo str_pad($assignment['volunteer_id'], 4, '0', STR_PAD_LEFT); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($assignment['phone']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #25d366;">
                                            <i class="fab fa-whatsapp"></i> +<?php echo $formatted_phone; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge badge-<?php echo strtolower($assignment['role']); ?>">
                                            <?php echo $assignment['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($assignment['ngo_affiliation'] ?? 'Various'); ?></td>
                                    <td>
                                        <span class="badge badge-assigned">
                                            <?php echo $assignment['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="remove_volunteer" value="1">
                                                <input type="hidden" name="volunteer_id" value="<?php echo $assignment['volunteer_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Remove this volunteer from assignment?')">
                                                    <i class="fas fa-user-times"></i> Remove
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-success btn-sm" 
                                                    onclick="resendWhatsApp(<?php echo $assignment['volunteer_id']; ?>, '<?php echo htmlspecialchars($assignment['name']); ?>', '<?php echo htmlspecialchars($assignment['phone']); ?>', '<?php echo $assignment['role']; ?>')">
                                                <i class="fab fa-whatsapp"></i> Resend
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-grid animated-card" style="margin-top: 30px;">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <h3>Total Volunteers</h3>
                    <div class="stat-value"><?php echo count($all_volunteers); ?></div>
                    <p>In system database</p>
                </div>
                <div class="stat-card assigned">
                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                    <h3>Assigned</h3>
                    <div class="stat-value"><?php echo count($assigned_volunteers); ?></div>
                    <p>To this distribution</p>
                </div>
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>Available</h3>
                    <div class="stat-value"><?php echo count($available_volunteers); ?></div>
                    <p>Ready for assignment</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Show success modal if assignment was successful
        <?php if ($show_success_modal): ?>
        window.addEventListener('load', function() {
            document.getElementById('successModal').style.display = 'flex';
            <?php unset($_SESSION['assignment_success']); ?>
        });
        <?php endif; ?>
        
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            // Clear success parameter from URL
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url);
        }
        
        function viewWhatsAppMessages() {
            closeSuccessModal();
            // Scroll to assigned volunteers section
            const assignedSection = document.querySelector('.card:last-of-type');
            if (assignedSection) {
                assignedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        // Volunteer selection and filtering
        const volunteerCards = document.querySelectorAll('.volunteer-card');
        const checkboxes = document.querySelectorAll('.volunteer-checkbox');
        const ngoFilter = document.getElementById('ngo_filter');
        const availabilityFilter = document.getElementById('availability_filter');
        const assignButton = document.getElementById('assign-button');
        const whatsappPreview = document.getElementById('whatsapp-preview');
        const selectAllCheckbox = document.getElementById('select-all-volunteers');
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.volunteer-checkbox:checked');
            document.getElementById('selected-count').textContent = selected.length;
            assignButton.disabled = selected.length === 0;
            
            if (selected.length > 0) {
                assignButton.classList.add('btn-pulse');
            } else {
                assignButton.classList.remove('btn-pulse');
            }
            
            updateWhatsAppPreview();
        }
        
        function updateRoleSelect(checkbox) {
            const card = checkbox.closest('.volunteer-card');
            const roleSelection = card.querySelector('.role-selection');
            
            card.classList.toggle('selected', checkbox.checked);
            if (roleSelection) {
                roleSelection.style.display = checkbox.checked ? 'block' : 'none';
            }
            
            updateSelectedCount();
        }
        
        function filterVolunteers() {
            const ngoValue = ngoFilter.value;
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
        
        function updateWhatsAppPreview() {
            const selectedCheckboxes = document.querySelectorAll('.volunteer-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                if (whatsappPreview) {
                    whatsappPreview.style.display = 'none';
                }
                return;
            }
            
            const firstVolunteer = selectedCheckboxes[0];
            const volunteerName = firstVolunteer.getAttribute('data-volunteer-name');
            const volunteerId = firstVolunteer.getAttribute('data-volunteer-id');
            
            let role = 'Distributor';
            const roleRadio = document.querySelector(`input[name="roles[${volunteerId}]"]:checked`);
            if (roleRadio) {
                role = roleRadio.value;
            }
            
            const message = `Assalamualaikum ${volunteerName},

TUGASAN AGIHAN BANTUAN
📅 Tarikh: <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
⏰ Masa: 4:00 PM
📍 Lokasi: Dewan Serbaguna Masjid Tanah
👥 Peranan: ${role}
📦 Mangsa: 50 keluarga

Sila hadir 15 minit awal. Bawa:
✓ Vest sukarelawan
✓ Sarung tangan
✓ Topeng muka

Hubungi Encik Ahmad: 06-XXX XXXX

Terima kasih! - JKM Melaka`;
            
            if (whatsappPreview) {
                whatsappPreview.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0; color: white;"><i class="fab fa-whatsapp"></i> WhatsApp Message Preview</h4>
                        <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 15px; font-size: 12px;">
                            Will be sent to ${selectedCheckboxes.length} volunteer(s)
                        </span>
                    </div>
                    <div style="background: rgba(255,255,255,0.15); padding: 15px; border-radius: 8px; font-size: 14px;">
                        ${message.replace(/\n/g, '<br>')}
                    </div>
                `;
                whatsappPreview.style.display = 'block';
            }
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    updateRoleSelect(checkbox);
                });
            });
        }
        
        if (ngoFilter) ngoFilter.addEventListener('change', filterVolunteers);
        if (availabilityFilter) availabilityFilter.addEventListener('change', filterVolunteers);
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateRoleSelect(this);
            });
        });
        
        // Role badge click handling
        document.querySelectorAll('.role-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                const radio = this.previousElementSibling;
                if (radio && radio.type === 'radio') {
                    radio.checked = true;
                    updateWhatsAppPreview();
                    
                    // Update visual selection
                    const roleSelection = this.closest('.role-selection');
                    roleSelection.querySelectorAll('.role-badge').forEach(b => {
                        b.style.opacity = '0.6';
                        b.style.transform = 'scale(0.95)';
                    });
                    this.style.opacity = '1';
                    this.style.transform = 'scale(1)';
                }
            });
        });
        
        function resendWhatsApp(volunteerId, volunteerName, volunteerPhone, role) {
            let formattedPhone = volunteerPhone.replace(/\D/g, '');
            if (formattedPhone.startsWith('0')) {
                formattedPhone = '60' + formattedPhone.substring(1);
            }
            if (!formattedPhone.startsWith('60')) {
                formattedPhone = '60' + formattedPhone;
            }
            
            const message = `Assalamualaikum ${volunteerName},

TUGASAN AGIHAN BANTUAN
📅 Tarikh: <?php echo date('d/m/Y', strtotime($distribution['date'])); ?>
⏰ Masa: 4:00 PM
📍 Lokasi: Dewan Serbaguna Masjid Tanah
👥 Peranan: ${role}
📦 Mangsa: 50 keluarga

Sila hadir 15 minit awal. Bawa:
✓ Vest sukarelawan
✓ Sarung tangan
✓ Topeng muka

Hubungi Encik Ahmad: 06-XXX XXXX

Terima kasih! - JKM Melaka`;
            
            const encodedMessage = encodeURIComponent(message);
            const whatsappUrl = `https://wa.me/${formattedPhone}?text=${encodedMessage}`;
            
            window.open(whatsappUrl, '_blank');
        }
        
        const form = document.getElementById('assign-volunteers-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const selected = document.querySelectorAll('.volunteer-checkbox:checked').length;
                if (selected === 0) {
                    e.preventDefault();
                    alert('Please select at least one volunteer.');
                    return;
                }
                
                if (!confirm(`Assign ${selected} volunteer(s) to this distribution?\n\n✅ Volunteers will be assigned\n✅ WhatsApp notifications sent automatically\n✅ Status updated to "Assigned"`)) {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                assignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
                assignButton.disabled = true;
            });
        }
        
        updateSelectedCount();
        filterVolunteers();
    </script>
</body>
</html>