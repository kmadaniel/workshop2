<?php
// ================= ERROR REPORTING =================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ================= DATABASE CONNECTION =================
include "db.php";
$message = "";

// Test connection
if (!$conn) {
    die("❌ Database connection failed! Check your db.php file.");
}

// Fetch active disasters
try {
    $disasters = $conn->query("
        SELECT disaster_id, disaster_name, district
        FROM disaster
        WHERE status IN ('Active', 'Under Control')
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("❌ Error fetching disasters: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Force boolean values for checkboxes
        $has_baby = !empty($_POST['has_baby']);
        $has_elderly = !empty($_POST['has_elderly']);
        $has_disabled = !empty($_POST['has_disabled']);

        // Generate a reference number
        $reference_number = 'VICT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // ================= INSERT INTO VICTIM TABLE =================
        $stmt = $conn->prepare("
            INSERT INTO victim 
            (full_name, ic_number, email, phone, email_verified, verification_token, address,
             postal_code, city, district, country, family_members, has_baby, has_elderly,
             has_disabled, created_at, disaster_id, special_request)
            VALUES 
            (:full_name, :ic, :email, :phone, false, :token, :address,
             :postal, :city, :district, 'Malaysia', :family, :baby, :elderly,
             :disabled, NOW(), :disaster, :special_request)
            RETURNING victim_id
        ");

        $stmt->bindValue(':full_name', $_POST['full_name']);
        $stmt->bindValue(':ic', $_POST['ic_number']);
        $stmt->bindValue(':email', $_POST['email']);
        $stmt->bindValue(':phone', $_POST['phone']);
        $stmt->bindValue(':token', $reference_number);
        $stmt->bindValue(':address', $_POST['address']);
        $stmt->bindValue(':postal', $_POST['postal_code']);
        $stmt->bindValue(':city', $_POST['city'] ?: 'Melaka');
        $stmt->bindValue(':district', $_POST['district']);
        $stmt->bindValue(':family', $_POST['family_members'], PDO::PARAM_INT);
        $stmt->bindValue(':baby', $has_baby, PDO::PARAM_BOOL);
        $stmt->bindValue(':elderly', $has_elderly, PDO::PARAM_BOOL);
        $stmt->bindValue(':disabled', $has_disabled, PDO::PARAM_BOOL);
        $stmt->bindValue(':disaster', $_POST['disaster_id'], PDO::PARAM_INT);
        $stmt->bindValue(':special_request', $_POST['special_needs'] ?? '', PDO::PARAM_STR);

        $stmt->execute();
        $victim_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $victim_id = $victim_row['victim_id'] ?? null;

        if (!$victim_id) {
            throw new Exception("Failed to get victim ID");
        }

        // ================= INSERT INTO NEEDS TABLE =================
        $needs_inserted = false;
        if (!empty($_POST['special_needs'])) {
            try {
                // First, let's check what columns the needs table has
                $check_stmt = $conn->query("
                    SELECT column_name 
                    FROM information_schema.columns 
                    WHERE table_name = 'needs' 
                    AND table_schema = 'public'
                    ORDER BY ordinal_position
                ");
                $columns = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($columns)) {
                    throw new Exception("Needs table not found or has no columns");
                }
                
                // Debug: Show columns found
                error_log("Needs table columns: " . implode(", ", $columns));
                
                // Prepare INSERT based on available columns
                $needs_sql = "INSERT INTO needs (";
                $values_sql = "VALUES (";
                $params = [];
                
                // Always include these if they exist
                if (in_array('victim_id', $columns)) {
                    $needs_sql .= "victim_id, ";
                    $values_sql .= ":victim_id, ";
                    $params[':victim_id'] = $victim_id;
                }
                
                if (in_array('disaster_id', $columns)) {
                    $needs_sql .= "disaster_id, ";
                    $values_sql .= ":disaster_id, ";
                    $params[':disaster_id'] = $_POST['disaster_id'];
                }
                
                // Add special needs text to appropriate column
                $needs_text = $_POST['special_needs'];
                if (in_array('item_name', $columns)) {
                    $needs_sql .= "item_name, ";
                    $values_sql .= ":needs_text, ";
                    $params[':needs_text'] = $needs_text;
                } elseif (in_array('request_details', $columns)) {
                    $needs_sql .= "request_details, ";
                    $values_sql .= ":needs_text, ";
                    $params[':needs_text'] = $needs_text;
                } elseif (in_array('special_needs', $columns)) {
                    $needs_sql .= "special_needs, ";
                    $values_sql .= ":needs_text, ";
                    $params[':needs_text'] = $needs_text;
                } elseif (in_array('description', $columns)) {
                    $needs_sql .= "description, ";
                    $values_sql .= ":needs_text, ";
                    $params[':needs_text'] = $needs_text;
                }
                
                // Add status if column exists
                if (in_array('status', $columns)) {
                    $needs_sql .= "status, ";
                    $values_sql .= "'Pending', ";
                }
                
                // Add created_at if column exists
                if (in_array('created_at', $columns)) {
                    $needs_sql .= "created_at";
                    $values_sql .= "NOW()";
                } else {
                    // Remove trailing comma and space
                    $needs_sql = rtrim($needs_sql, ", ");
                    $values_sql = rtrim($values_sql, ", ");
                }
                
                $needs_sql .= ") " . $values_sql . ")";
                
                // Debug: Show the SQL
                error_log("Needs INSERT SQL: " . $needs_sql);
                
                // Execute the insert
                $needs_stmt = $conn->prepare($needs_sql);
                foreach ($params as $key => $value) {
                    $needs_stmt->bindValue($key, $value);
                }
                $needs_stmt->execute();
                $needs_inserted = true;
                
            } catch (Exception $e) {
                // Log error but don't stop
                error_log("Needs table error (will continue): " . $e->getMessage());
            }
        }

        $conn->commit();

        // ================= GET DISASTER NAME =================
        $disaster_name = "Unknown Disaster";
        foreach ($disasters as $d) {
            if ($d['disaster_id'] == $_POST['disaster_id']) {
                $disaster_name = $d['disaster_name'];
                break;
            }
        }

        // ================= CREATE WHATSAPP LINK =================
        $clean_phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = substr($clean_phone, 1);
        }
        
        $whatsapp_message = "📋 *Melaka Disaster Assistance - Registration Confirmation*

✅ Registration Successful!

*Reference Number:* $reference_number
*Victim ID:* $victim_id
*Name:* " . $_POST['full_name'] . "
*Disaster:* $disaster_name
*Date:* " . date('d/m/Y H:i') . "

*Special Needs Request:*
" . (!empty($_POST['special_needs']) ? $_POST['special_needs'] : 'No special needs requested') . "

*Important Information:*
1. Keep this Reference Number for all future communications
2. You will receive updates about your special needs request
3. For emergencies, contact disaster hotline: 1-300-88-2010
4. Save this message for future reference

Thank you for registering with Melaka Disaster Assistance. Stay safe!";
        
        $whatsapp_link = "https://wa.me/6" . $clean_phone . "?text=" . urlencode($whatsapp_message);

        // ================= DISPLAY SUCCESS MESSAGE =================
        $db_status = "✅ Data saved to: <strong>victim table</strong>";
        if ($needs_inserted) {
            $db_status .= " and <strong>needs table</strong>";
        } elseif (!empty($_POST['special_needs'])) {
            $db_status .= " (needs table: skipped - table issue)";
        }
        
        $message = "
        <div class='success-message'>
            <div class='success-header'>
                <i class='fas fa-check-circle'></i>
                <h3>Registration Successful!</h3>
            </div>
            
            <div class='alert-box'>
                <i class='fas fa-database'></i>
                <div>
                    <p>$db_status</p>
                    <p>Reference Number: <strong class='ref-highlight'>$reference_number</strong></p>
                </div>
            </div>
            
            <div class='details-container'>
                <div class='details-card'>
                    <h4><i class='fas fa-id-card'></i> Registration Details</h4>
                    <div class='details-grid'>
                        <div class='detail-item'>
                            <span class='detail-label'>Reference No:</span>
                            <span class='detail-value highlight'>$reference_number</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Victim ID:</span>
                            <span class='detail-value'>$victim_id</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Full Name:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['full_name']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>IC Number:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['ic_number']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Phone:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['phone']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Email:</span>
                            <span class='detail-value'>" . htmlspecialchars($_POST['email']) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Disaster:</span>
                            <span class='detail-value'>" . htmlspecialchars($disaster_name) . "</span>
                        </div>
                        <div class='detail-item'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>" . date('d/m/Y H:i') . "</span>
                        </div>
                    </div>
                </div>
                
                " . (!empty($_POST['special_needs']) ? "
                <div class='special-needs-card'>
                    <h4><i class='fas fa-hand-holding-heart'></i> Special Needs Request</h4>
                    <div class='needs-box'>
                        <p><strong>Your Request:</strong></p>
                        <div class='needs-text'>" . nl2br(htmlspecialchars($_POST['special_needs'])) . "</div>
                        <p><strong>Status:</strong> <span class='status-badge'>Pending Review</span></p>
                        <p><strong>Database Status:</strong> " . ($needs_inserted ? "✅ Saved to needs table" : "⚠️ Not saved to needs table") . "</p>
                    </div>
                </div>
                " : "") . "
                
                <div class='whatsapp-section'>
                    <div class='section-header'>
                        <i class='fab fa-whatsapp'></i>
                        <h4>Save to WhatsApp</h4>
                    </div>
                    <p>Click below to save your registration details to WhatsApp:</p>
                    
                    <a href='$whatsapp_link' target='_blank' class='whatsapp-button'>
                        <i class='fab fa-whatsapp'></i> Save to WhatsApp
                    </a>
                    
                    <div class='instructions'>
                        <p><strong>How to save:</strong></p>
                        <ol>
                            <li>Click the WhatsApp button above</li>
                            <li>WhatsApp will open with your details</li>
                            <li>Send the message to yourself or save it</li>
                            <li>Keep the message for future reference</li>
                        </ol>
                    </div>
                </div>
                
                <div class='action-buttons'>
                    <button onclick='window.print()' class='action-btn print-btn'>
                        <i class='fas fa-print'></i> Print This Page
                    </button>
                    <button onclick='copyReference()' class='action-btn copy-btn'>
                        <i class='fas fa-copy'></i> Copy Reference Number
                    </button>
                </div>
                
                <div class='emergency-info'>
                    <h4><i class='fas fa-phone-alt'></i> Emergency Contact</h4>
                    <div class='emergency-card'>
                        <i class='fas fa-phone-volume'></i>
                        <div>
                            <h5>Disaster Hotline</h5>
                            <p class='emergency-number'>1-300-88-2010</p>
                            <p>24/7 Emergency Assistance</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        ";

    } catch (Exception $e) {
        $conn->rollBack();
        $message = "<div class='error-message'><h3><i class='fas fa-exclamation-triangle'></i> Registration Failed</h3><p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p><p>Please check your database connection and table structure.</p></div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Victim Registration - Melaka Disaster Assistance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: 100vh;
}

/* NAVBAR - Same as dashboard */
.navbar {
    width: 100%;
    padding: 20px 60px;
    background: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    border-bottom: 2px solid #eee;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.nav-left a {
    margin: 0 20px;
    text-decoration: none;
    color: #333;
    font-size: 16px;
    font-weight: 600;
    transition: color 0.3s;
    padding: 8px 0;
    position: relative;
}

.nav-left a:hover {
    color: #007bff;
}

.nav-left a.active {
    color: #007bff;
}

.nav-left a.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: #007bff;
    border-radius: 2px;
}

.nav-right a {
    margin-left: 20px;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-login {
    color: #333;
    border: 2px solid #007bff;
}

.btn-login:hover {
    background: #007bff;
    color: white;
}

.btn-register {
    background: #007bff;
    color: white;
}

.btn-register:hover {
    background: #0056b3;
    transform: translateY(-2px);
}

/* PURPLE HERO SECTION - Same as dashboard */
.dashboard-hero {
    height: 50vh;
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding-top: 80px;
    position: relative;
    overflow: hidden;
    margin-bottom: 40px;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') center/cover;
    opacity: 0.15;
}

.dashboard-hero-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    padding: 0 20px;
}

.dashboard-hero h1 {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 15px;
    text-shadow: 2px 2px 8px rgba(0,0,0,0.5);
}

.dashboard-hero p {
    font-size: 1.2rem;
    margin-bottom: 30px;
    opacity: 0.95;
    text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
    max-width: 700px;
    line-height: 1.6;
    margin: 0 auto 30px;
}

/* Quick Actions */
.quick-actions {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

.action-btn {
    padding: 15px 35px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    font-size: 1.1rem;
    border-radius: 50px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    color: white;
}

.action-btn.primary {
    background: #ff6b6b;
    border-color: #ff6b6b;
}

.action-btn.primary:hover {
    background: #ff5252;
}

/* MAIN CONTENT */
.main-content {
    max-width: 800px;
    margin: 0 auto 60px;
    padding: 0 20px;
}

/* FORM CARD */
.form-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.form-card h2 {
    color: #2c3e50;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* FORM STYLES */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

select.form-control {
    appearance: none;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 15px center;
    background-size: 16px;
}

/* CHECKBOX GROUP */
.checkbox-group {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.checkbox-item input[type="checkbox"] {
    width: auto;
}

/* INFO NOTES */
.note {
    background: #fff3cd;
    border-left: 5px solid #ffc107;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.note i {
    color: #ffc107;
    margin-right: 8px;
}

/* SECTION TITLES */
.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #007bff;
    margin: 25px 0 15px;
    padding-left: 15px;
    border-left: 4px solid #007bff;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* SUBMIT BUTTON */
.submit-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

/* SUCCESS MESSAGE STYLES */
.success-message {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    border: 2px solid #4caf50;
}

.success-header {
    background: linear-gradient(135deg, #4caf50, #2e7d32);
    color: white;
    padding: 25px;
    text-align: center;
}

.success-header i {
    font-size: 48px;
    margin-bottom: 15px;
}

.success-header h3 {
    margin: 0;
    font-size: 24px;
}

.alert-box {
    background: #e8f5e9;
    border: 1px solid #4caf50;
    border-radius: 10px;
    padding: 15px;
    margin: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.alert-box i {
    color: #2e7d32;
    font-size: 24px;
}

.ref-highlight {
    background: #ffeb3b;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
}

.details-container {
    padding: 20px;
}

.details-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.details-card h4 {
    color: #495057;
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #007bff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    padding: 10px;
    background: white;
    border-radius: 8px;
    border: 1px solid #eee;
}

.detail-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 16px;
    color: #212529;
    font-weight: 500;
}

.highlight {
    color: #007bff;
    font-weight: bold;
    font-size: 18px;
}

/* SPECIAL NEEDS CARD */
.special-needs-card {
    background: #e8f4f8;
    border: 1px solid #17a2b8;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.special-needs-card h4 {
    color: #0c5460;
    margin-top: 0;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.needs-box {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #17a2b8;
}

.needs-text {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 10px 0;
    border: 1px solid #dee2e6;
}

.status-badge {
    background: #ff9800;
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 14px;
}

/* WHATSAPP SECTION */
.whatsapp-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.section-header i {
    font-size: 24px;
    color: #25D366;
}

.section-header h4 {
    margin: 0;
    color: #495057;
}

.whatsapp-button {
    display: block;
    background: #25D366;
    color: white;
    text-align: center;
    padding: 18px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 18px;
    font-weight: bold;
    margin: 20px 0;
    transition: all 0.3s;
}

.whatsapp-button:hover {
    background: #128C7E;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
}

.whatsapp-button i {
    margin-right: 10px;
    font-size: 24px;
}

.instructions {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #25D366;
}

.instructions ol {
    margin: 10px 0 0 20px;
}

.instructions li {
    margin-bottom: 8px;
}

/* ACTION BUTTONS */
.action-buttons {
    display: flex;
    gap: 15px;
    margin: 25px 0;
}

.action-btn {
    flex: 1;
    padding: 15px;
    font-size: 16px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.print-btn {
    background: #28a745;
    color: white;
}

.print-btn:hover {
    background: #218838;
    transform: translateY(-2px);
}

.copy-btn {
    background: #6c757d;
    color: white;
}

.copy-btn:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* EMERGENCY INFO */
.emergency-info {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 10px;
    padding: 20px;
}

.emergency-info h4 {
    color: #856404;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.emergency-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
}

.emergency-card i {
    font-size: 36px;
    color: #dc3545;
}

.emergency-number {
    font-size: 24px;
    font-weight: bold;
    color: #dc3545;
    margin: 5px 0;
}

/* ERROR MESSAGE */
.error-message {
    background: #ffebee;
    border: 1px solid #f44336;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
}

.error-message h3 {
    color: #c62828;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .navbar {
        padding: 15px 20px;
    }
    
    .nav-left a {
        margin: 0 10px;
        font-size: 14px;
    }
    
    .dashboard-hero {
        height: 40vh;
        margin-bottom: 30px;
    }
    
    .dashboard-hero h1 {
        font-size: 2rem;
    }
    
    .dashboard-hero p {
        font-size: 1rem;
    }
    
    .main-content {
        padding: 0 15px;
        margin: 0 auto 30px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .quick-actions {
        flex-direction: column;
        align-items: center;
    }
    
    .action-btn {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .navbar {
        flex-direction: column;
        padding: 15px;
    }
    
    .nav-left {
        margin-bottom: 15px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
    }
    
    .nav-left a {
        margin: 0 5px;
    }
    
    .dashboard-hero h1 {
        font-size: 1.8rem;
    }
}
</style>
<script>
function copyReference() {
    const refElement = document.querySelector('.ref-highlight');
    if (refElement) {
        const text = refElement.textContent;
        navigator.clipboard.writeText(text).then(function() {
            const originalText = refElement.textContent;
            refElement.textContent = 'Copied!';
            refElement.style.background = '#4caf50';
            refElement.style.color = 'white';
            
            setTimeout(function() {
                refElement.textContent = originalText;
                refElement.style.background = '#ffeb3b';
                refElement.style.color = 'black';
            }, 2000);
            
            alert('Reference number copied to clipboard!');
        });
    }
}
</script>
</head>
<body>

    <!-- NAVBAR - Same as dashboard -->
    <div class="navbar">
        <div class="nav-left">
            <a href="http://10.147.17.30:8000/main_page.php">HOME</a>
            <a href="http://10.147.17.30:8000/news.php">NEWS</a>
            <a href="index.php">VICTIM</a>
        </div>

        <div class="nav-right">
            <a href="http://10.147.17.30:8000/login.php" class="btn-login">Sign in</a>
            <a href="http://10.147.17.30:8000/register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- PURPLE HERO SECTION - Same as dashboard -->
    <div class="dashboard-hero">
        <div class="dashboard-hero-content">
            <h1>Victim Registration Form</h1>
            <p>Register as a victim for disaster assistance and emergency support in Melaka</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Main Form Card -->
        <div class="form-card">
            
            <?php if ($message): ?>
            <div class="message <?= str_contains($message,'Registration Successful') ? 'success':'error' ?>">
                <?= $message ?>
            </div>
            <?php endif; ?>

            <?php if (empty($message) || str_contains($message, 'Registration Failed')): ?>
            <h2><i class="fas fa-user-plus"></i> Registration Details</h2>
            
            <form method="POST">
                
                <!-- Section 1: Disaster Information -->
                <div class="section-title">
                    <i class="fas fa-triangle-exclamation"></i> 1. Disaster Information
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Disaster</label>
                    <select name="disaster_id" class="form-control" required>
                        <option value="">-- Please select the affected disaster --</option>
                        <?php foreach ($disasters as $d): ?>
                        <option value="<?= $d['disaster_id'] ?>">
                            <?= htmlspecialchars($d['disaster_name']) ?> (<?= htmlspecialchars($d['district']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section 2: Personal Information -->
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> 2. Personal Information
                </div>
                
                <div class="form-group">
                    <input type="text" name="full_name" class="form-control" placeholder="Full Name (as per IC)" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="ic_number" class="form-control" placeholder="IC Number (e.g., 901231-01-1234)" required>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                </div>
                
                <div class="form-group">
                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number (e.g., 012-3456789)" required>
                </div>
                
                <div class="note">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> Make sure all information is accurate. Your phone number will be used for WhatsApp confirmation.
                </div>

                <!-- Section 3: Address Details -->
                <div class="section-title">
                    <i class="fas fa-home"></i> 3. Address Details
                </div>
                
                <div class="form-group">
                    <textarea name="address" class="form-control" placeholder="Full Address (House no, Street, Area)" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <input type="text" name="postal_code" class="form-control" placeholder="Postal Code" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="city" class="form-control" placeholder="City" value="Melaka">
                </div>
                
                <div class="form-group">
                    <input type="text" name="district" class="form-control" placeholder="District" required>
                </div>

                <!-- Section 4: Household Information -->
                <div class="section-title">
                    <i class="fas fa-users"></i> 4. Household Information
                </div>
                
                <div class="form-group">
                    <label class="form-label">Number of Family Members (including yourself)</label>
                    <input type="number" name="family_members" class="form-control" min="1" value="1" required>
                </div>
                
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_baby" id="has_baby">
                        <label for="has_baby">Household has baby (below 2 years)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_elderly" id="has_elderly">
                        <label for="has_elderly">Household has elderly (above 65 years)</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" name="has_disabled" id="has_disabled">
                        <label for="has_disabled">Household has disabled member</label>
                    </div>
                </div>

                <!-- Section 5: Special Needs Request -->
                <div class="section-title">
                    <i class="fas fa-hand-holding-heart"></i> 5. Special Needs Request (Optional)
                </div>
                
                <div class="note">
                    <i class="fas fa-lightbulb"></i> 
                    <strong>Important:</strong> You can view the list of special needs on the victim homepage, but special needs assistance is provided only while stock is available.
                </div>
                
                <div class="form-group">
                    <textarea name="special_needs" class="form-control" placeholder="e.g. Pampers size M (2 packs), baby formula (Enfamil), specific medicine (Insulin), wheelchair" rows="3"></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Registration
                </button>

            </form>
            <?php endif; ?>
            
        </div>
    </div>

</body>
</html>