<?php
include "db.php";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disaster_name = trim($_POST['disaster_name']);
    $description = trim($_POST['description']);
    $district = trim($_POST['district']);
    $severity = trim($_POST['severity']); // Low, Medium, High
    $alert_message = trim($_POST['alert_message']);
    $status = 'Active';

    try {
        $stmt = $conn->prepare("
            INSERT INTO disaster (disaster_name, description, district, severity, alert_message, status)
            VALUES (:name, :desc, :district, :severity, :alert, :status)
        ");
        $stmt->execute([
            ':name' => $disaster_name,
            ':desc' => $description,
            ':district' => $district,
            ':severity' => $severity,
            ':alert' => $alert_message,
            ':status' => $status
        ]);
        $message = "✅ Disaster reported successfully!";
    } catch (PDOException $e) {
        $message = "❌ Failed to report disaster: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report Disaster - Melaka Disaster Assistance</title>
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

/* PURPLE HERO SECTION */
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

/* BACK BUTTON in Header */
.back-button {
    position: absolute;
    top: 30px;
    left: 30px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    transition: all 0.3s;
    z-index: 3;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateX(-5px);
    color: white;
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

/* MESSAGES */
.message {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
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
    
    .back-button {
        top: 20px;
        left: 20px;
        padding: 8px 15px;
        font-size: 14px;
    }
    
    .main-content {
        padding: 0 15px;
        margin: 0 auto 30px;
    }
    
    .form-card {
        padding: 20px;
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
    
    .back-button {
        top: 15px;
        left: 15px;
        padding: 6px 12px;
        font-size: 13px;
    }
}
</style>
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

    <!-- PURPLE HERO SECTION -->
    <div class="dashboard-hero">
        <!-- Back button in top-left corner -->
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="dashboard-hero-content">
            <h1>Report Disaster</h1>
            <p>Immediately report any disaster or emergency situation in Melaka to alert authorities</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Main Form Card -->
        <div class="form-card">
            
            <h2><i class="fas fa-triangle-exclamation"></i> Disaster Report Form</h2>

            <?php if ($message): ?>
            <div class="message <?= str_contains($message,'✅') ? 'success':'error' ?>">
                <i class="fas fa-<?= str_contains($message,'✅') ? 'check-circle':'exclamation-triangle' ?>"></i>
                <?= $message ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                
                <!-- Section 1: Disaster Information -->
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> 1. Disaster Information
                </div>
                
                <div class="form-group">
                    <label class="form-label">Disaster Name</label>
                    <input type="text" name="disaster_name" class="form-control" placeholder="e.g. Flood, Landslide, Fire, Storm" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Detailed Description</label>
                    <textarea name="description" class="form-control" placeholder="Provide detailed description of the disaster situation" rows="4" required></textarea>
                </div>

                <!-- Section 2: Location Details -->
                <div class="section-title">
                    <i class="fas fa-map-marker-alt"></i> 2. Location Details
                </div>
                
                <div class="form-group">
                    <label class="form-label">District</label>
                    <input type="text" name="district" class="form-control" placeholder="e.g. Melaka Tengah, Alor Gajah, Jasin" required>
                </div>

                <!-- Section 3: Severity Level -->
                <div class="section-title">
                    <i class="fas fa-bolt"></i> 3. Severity Level
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Severity Level</label>
                    <select name="severity" class="form-control" required>
                        <option value="">-- Select Severity Level --</option>
                        <option value="Low">Low - Minor impact, manageable locally</option>
                        <option value="Medium">Medium - Significant impact, requires external help</option>
                        <option value="High">High - Major disaster, requires emergency response</option>
                    </select>
                </div>

                <!-- Section 4: Alert Message -->
                <div class="section-title">
                    <i class="fas fa-bullhorn"></i> 4. Alert Message
                </div>
                
                <div class="note">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Important:</strong> This message will be shown to the public on the dashboard. Provide clear instructions and warnings.
                </div>
                
                <div class="form-group">
                    <label class="form-label">Public Alert Message</label>
                    <textarea name="alert_message" class="form-control" placeholder="e.g. Avoid riverbanks, Evacuate immediately, Stay indoors, Road closures in area" rows="3" required></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Disaster Report
                </button>

            </form>
            
        </div>
    </div>

</body>
</html>