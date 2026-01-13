<?php
// ========================================
// Reports Dashboard - Session & Auth
// Integrated from distribution_main.php
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// TEMPORARILY DISABLED SESSION CHECK FOR TESTING
/*
// Check if user is authenticated via API bridge
$is_api_authenticated = false;

// Check multiple possible API authentication indicators
if (isset($_SESSION['admin_api_verified']) && $_SESSION['admin_api_verified'] === true) {
    $is_api_authenticated = true;
} elseif (isset($_SESSION['AdminID']) || isset($_SESSION['FullName'])) {
    // If we have API fields in session, treat as API authenticated
    $is_api_authenticated = true;
    $_SESSION['admin_api_verified'] = true;
}

// User Detection
$user_type = 'guest';
$current_user = null;

// 1. FIRST PRIORITY: Check for API-authenticated admin
if ($is_api_authenticated) {
    $user_type = 'admin';
    
    $admin_id = $_SESSION['AdminID'] ?? $_SESSION['user_id'] ?? 0;
    $admin_name = $_SESSION['FullName'] ?? $_SESSION['user_name'] ?? 'Admin User';
    $admin_email = $_SESSION['Email'] ?? $_SESSION['user_email'] ?? 'admin@disasterrelief.org';
    $admin_role = $_SESSION['Role'] ?? $_SESSION['user_role'] ?? 'Administrator';
    
    $current_user = [
        'id' => $admin_id,
        'name' => $admin_name,
        'role' => $admin_role,
        'avatar' => substr($admin_name, 0, 2),
        'email' => $admin_email,
        'api_verified' => true
    ];
}
// 2. SECOND PRIORITY: Check for local staff/admin
elseif (isset($_SESSION['user_id']) && !isset($_SESSION['volunteer_id'])) {
    $user_type = 'staff';
    $current_user = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Admin User',
        'role' => $_SESSION['user_role'] ?? 'System Administrator',
        'avatar' => substr($_SESSION['user_name'] ?? 'AU', 0, 2),
        'email' => $_SESSION['user_email'] ?? 'admin@disasterrelief.org',
        'api_verified' => false
    ];
}
// 3. ACCESS CONTROL: Redirect if Guest or Volunteer
else {
    header("Location: http://10.147.17.30:8000/login.php");
    exit;
}
*/

// Mock User for Testing
$current_user = [
    'id' => 1,
    'name' => 'Test Admin',
    'role' => 'Administrator',
    'avatar' => 'TA',
    'email' => 'admin@test.com'
];
$is_api_authenticated = true; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReliefNet - Resource Management System</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js for Graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- External Style Sheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* DISABLED STATE STYLES */
        .disabled-section {
            opacity: 0.5;
            pointer-events: none; /* Prevents clicks */
            filter: grayscale(100%);
            position: relative;
        }
        
        .disabled-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: not-allowed;
        }

        .coming-soon-badge {
            background: #cbd5e1;
            color: #475569;
            padding: 8px 16px;
            border-radius: 99px;
            font-weight: bold;
            font-size: 14px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        /* Sortable Header Styles */
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        th.sortable:hover {
            background-color: #e2e8f0;
        }
        th.sortable i {
            margin-left: 5px;
            font-size: 0.8em;
            color: #94a3b8;
        }

        /* Backup UI Styles */
        .backup-controls {
            display: flex;
            gap: 20px;
            margin-bottom: 24px;
            align-items: stretch; /* Match height */
        }
        .backup-option-card {
            flex: 1;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .backup-option-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        .schedule-btn {
            padding: 10px 15px;
            margin: 5px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        .schedule-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .manual-backup-btn {
            background-color: #10b981; /* Green */
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
        }
        .manual-backup-btn:hover {
            background-color: #059669;
        }
        .next-run-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }
        .next-run-text strong {
            color: var(--primary);
        }

        /* CUSTOM CONFIRMATION MODAL STYLES */
        .confirm-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); /* Semi-transparent backdrop */
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 3000; /* High z-index to sit on top */
            backdrop-filter: blur(3px); /* Blur effect */
            transition: opacity 0.2s ease-in-out;
        }

        .confirm-modal {
            background: white;
            border-radius: 16px;
            padding: 32px;
            width: 90%;
            max-width: 420px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .confirm-modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .confirm-modal-overlay.active .confirm-modal {
            transform: scale(1);
            opacity: 1;
        }

        .confirm-icon-wrapper {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: #eff6ff; /* Light blue bg */
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 28px;
        }

        .confirm-modal.danger .confirm-icon-wrapper {
            background-color: #fef2f2; /* Light red bg */
            color: #ef4444;
        }
        
        .confirm-modal.success .confirm-icon-wrapper {
            background-color: #ecfdf5; /* Light green bg */
            color: #10b981;
        }

        .confirm-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .confirm-message {
            color: var(--text-muted);
            margin-bottom: 28px;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .confirm-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Single button layout for alerts */
        .confirm-actions.single {
            grid-template-columns: 1fr;
        }

        .btn-modal {
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.95rem;
            border: none;
        }

        .btn-cancel {
            background-color: white;
            color: var(--text-muted);
            border: 1px solid #e2e8f0;
        }
        .btn-cancel:hover {
            background-color: #f8fafc;
            color: var(--text-main);
            border-color: #cbd5e1;
        }

        .btn-confirm {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }
        .btn-confirm:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .confirm-modal.danger .btn-confirm {
            background-color: #ef4444;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        }
        .confirm-modal.danger .btn-confirm:hover {
            background-color: #dc2626;
        }
        
        .confirm-modal.success .btn-confirm {
            background-color: #10b981;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }
        .confirm-modal.success .btn-confirm:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>

    <!-- HEADER MODULE (Now Full Width) -->
    <?php include 'header.php'; ?>

    <!-- LAYOUT WRAPPER -->
    <div class="layout-wrapper">
        
        <!-- SIDEBAR MODULE -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            
            <div class="scroll-area">
                
                <!-- SECTION: DASHBOARD -->
                <section id="dashboard" class="fade-in">
                    
                    <!-- INFO BUTTON REMOVED -->

                    <!-- Predictive Analytics Section -->
                    <div class="mb-4">
                        <div class="section-header">
                            <h4 class="section-title">AI Predictive Insights</h4>
                            <a href="predictive_insights.php" class="btn-primary btn-sm">
                                <i class="fas fa-chart-line"></i> View Full Report
                            </a>
                        </div>
                        <div class="grid-4" id="prediction-container">
                            <!-- Predictions injected via JS -->
                            <div class="prediction-card" onclick="window.location.href='predictive_insights.php'" style="cursor: pointer;">
                                <i class="fas fa-spinner fa-spin prediction-icon"></i>
                                <div class="prediction-content">
                                    <h5>Analyzing Data...</h5>
                                    <p>Calculating risk factors.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid-4">
                        <div class="card stat-card" onclick="navigateToList('volunteers')">
                            <div class="icon-box bg-blue"><i class="fas fa-users"></i></div>
                            <div class="stat-info">
                                <p>Total Volunteers</p>
                                <h3 id="stat-volunteers">Loading...</h3>
                            </div>
                        </div>
                        <div class="card stat-card" onclick="navigateToList('resources')">
                            <div class="icon-box bg-green"><i class="fas fa-box-open"></i></div>
                            <div class="stat-info">
                                <p>Resources Tracked</p>
                                <h3 id="stat-resources">Loading...</h3>
                            </div>
                        </div>
                        <div class="card stat-card" onclick="navigateToList('victims')">
                            <div class="icon-box bg-red"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="stat-info">
                                <p>Active Victims</p>
                                <h3 id="stat-victims">Loading...</h3>
                            </div>
                        </div>
                        <div class="card stat-card" onclick="navigateToList('distributions')">
                            <div class="icon-box bg-purple"><i class="fas fa-truck"></i></div>
                            <div class="stat-info">
                                <p>Distributions</p>
                                <h3 id="stat-distributions">Loading...</h3>
                            </div>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="card">
                            <h4 class="mb-4">Distribution Status Breakdown</h4>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="card">
                            <h4 class="mb-4">Top Resource Availability</h4>
                            <div style="position: relative; height: 300px; width: 100%;">
                                <canvas id="resourceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="card mb-4">
                        <h4 class="mb-4">Distribution Activity (Monthly)</h4>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                        
                    <!-- Quick Auditor Export -->
                    <div class="card card-dark">
                        <h3 style="margin-bottom: 12px; color: white;">Quick Auditor Export</h3>
                        <p class="text-light mb-4" style="font-size: 14px;">Download comprehensive CSV reports for external auditing immediately.</p>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <button onclick="exportData('volunteers')" class="btn-primary" style="background: #3b82f6;"><i class="fas fa-download"></i> Volunteers</button>
                            <button onclick="exportData('resources')" class="btn-primary" style="background: #3b82f6;"><i class="fas fa-download"></i> Inventory</button>
                            <button onclick="exportData('distributions')" class="btn-primary" style="background: #3b82f6;"><i class="fas fa-download"></i> Logistics</button>
                            <button onclick="exportData('disasters')" class="btn-primary" style="background: #3b82f6;"><i class="fas fa-download"></i> Disasters</button>
                        </div>
                    </div>
                </section>

                <!-- SECTION: LISTS -->
                <section id="lists" class="hidden fade-in">
                    <div class="controls">
                        <div class="tab-group">
                            <button class="tab-btn active" onclick="switchList('volunteers')">Volunteers</button>
                            <button class="tab-btn" onclick="switchList('resources')">Resources</button>
                            <button class="tab-btn" onclick="switchList('victims')">Victims</button>
                            <button class="tab-btn" onclick="switchList('distributions')">Distributions</button>
                            <button class="tab-btn" onclick="switchList('disasters')">Disasters</button>
                            <button class="tab-btn" onclick="switchList('reports')">Generated Reports</button>
                        </div>
                        <button class="btn-primary" onclick="exportCurrentList()">
                            <i class="fas fa-file-csv"></i> Export Current View
                        </button>
                    </div>

                    <div class="table-container">
                        <table id="data-table">
                            <thead>
                                <!-- Headers injected via JS -->
                            </thead>
                            <tbody>
                                <!-- Rows injected via JS -->
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- SECTION: BACKUP (ENABLED) -->
                <section id="backup" class="hidden fade-in">
                    
                    <div class="alert-box">
                        <i class="fas fa-exclamation-circle" style="margin-top: 4px;"></i>
                        <div>
                            <strong>System Backup & Restore</strong>
                            <p style="font-size: 13px; margin-top: 4px;">Create backups to protect data or restore from a previous point. Restoring will overwrite current data.</p>
                        </div>
                    </div>

                    <!-- Backup Controls -->
                    <div class="backup-controls">
                        <!-- Auto Schedule Card -->
                        <div class="backup-option-card">
                            <h4 class="mb-4"><i class="fas fa-clock" style="color: var(--primary);"></i> Auto Backup Schedule</h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">
                                Set how frequently the system should automatically back up data.
                            </p>
                            <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 10px;">
                                <button class="schedule-btn active" onclick="setSchedule('Daily', this)">Daily</button>
                                <button class="schedule-btn" onclick="setSchedule('Weekly', this)">Weekly</button>
                                <button class="schedule-btn" onclick="setSchedule('Monthly', this)">Monthly</button>
                            </div>
                            <div class="next-run-text" id="next-run-display">
                                Next scheduled run: <strong>Calculating...</strong>
                            </div>
                        </div>

                        <!-- Manual Backup Card -->
                        <div class="backup-option-card">
                            <h4 class="mb-4"><i class="fas fa-save" style="color: #10b981;"></i> Manual Backup</h4>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">
                                Immediately create a full system backup of the 'Reports' database.
                            </p>
                            <button class="manual-backup-btn" onclick="triggerManualBackup()">
                                <i class="fas fa-database"></i> Backup Now
                            </button>
                        </div>
                    </div>

                    <!-- Restore List -->
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                            <h4 style="margin: 0;">Available Backups</h4>
                            <button onclick="fetchBackups()" class="btn-primary" style="padding: 4px 12px; font-size: 12px;"><i class="fas fa-sync"></i> Refresh List</button>
                        </div>
                        <div id="backup-list" class="restore-list">
                            <div style="text-align:center; padding: 20px; color: #64748b;">Loading backups...</div>
                        </div>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- CUSTOM CONFIRMATION MODAL -->
    <div class="confirm-modal-overlay" id="confirm-modal-overlay">
        <div class="confirm-modal" id="confirm-modal-content">
            <div class="confirm-icon-wrapper">
                <i class="fas fa-question" id="confirm-icon"></i>
            </div>
            <div class="confirm-title" id="confirm-title">Confirm Action</div>
            <div class="confirm-message" id="confirm-message">Are you sure you want to proceed?</div>
            <div class="confirm-actions" id="confirm-actions">
                <button class="btn-modal btn-cancel" onclick="closeConfirmation()">Cancel</button>
                <button class="btn-modal btn-confirm" id="confirm-yes-btn">Confirm</button>
            </div>
        </div>
    </div>

    <!-- JS Logic -->
    <script>
        const API_DISTRIBUTIONS = 'http://10.147.17.154:8000/distribution_module/distribution.php';
        const API_VOLUNTEERS = 'http://10.147.17.30:8000/api_volunteer.php';
        const API_SESSION = 'http://10.147.17.30:8000/api_session.php'; 
        const API_DISASTERS = 'http://10.147.17.116:8000/disaster.php';
        const API_VICTIMS = 'http://10.147.17.116:8000/victim.php';
        const API_REPORTS = 'api_reports.php';
        const API_BACKUP_SYSTEM = 'api_backup_system.php';
        
        const API_RESOURCES = {
            medical: 'http://10.147.17.224:8000/medical_resource_api.php',
            clothing: 'http://10.147.17.224:8000/clothing_resource_api.php',
            food: 'http://10.147.17.224:8000/food_resource_api.php',
            shelter: 'http://10.147.17.224:8000/shelter_resource_api.php'
        };
        
        const data = { volunteers: [], resources: [], victims: [], distributions: [], disasters: [], reports: [] };
        let currentList = 'volunteers';
        let currentSort = { key: null, direction: 'asc' };
        let charts = { status: null, resource: null, trend: null };

        // --- Custom Confirmation Logic ---
        let confirmCallback = null;

        function showConfirmation(title, message, isDanger, onConfirm) {
            document.getElementById('confirm-title').innerText = title;
            document.getElementById('confirm-message').innerText = message;
            
            const modalContent = document.getElementById('confirm-modal-content');
            const icon = document.getElementById('confirm-icon');
            const yesBtn = document.getElementById('confirm-yes-btn');
            const actionsDiv = document.getElementById('confirm-actions');
            
            // Reset classes
            modalContent.classList.remove('danger', 'success');
            actionsDiv.classList.remove('single');
            document.querySelector('.btn-cancel').style.display = 'block';
            
            if (isDanger) {
                modalContent.classList.add('danger');
                icon.className = 'fas fa-exclamation-triangle';
                yesBtn.innerText = "Yes, Proceed";
            } else {
                icon.className = 'fas fa-question';
                yesBtn.innerText = "Confirm";
            }
            
            confirmCallback = onConfirm;
            const overlay = document.getElementById('confirm-modal-overlay');
            overlay.classList.add('active'); 
        }

        function showAlert(title, message, isSuccess = true) {
            document.getElementById('confirm-title').innerText = title;
            document.getElementById('confirm-message').innerText = message;
            
            const modalContent = document.getElementById('confirm-modal-content');
            const icon = document.getElementById('confirm-icon');
            const yesBtn = document.getElementById('confirm-yes-btn');
            const actionsDiv = document.getElementById('confirm-actions');
            
            modalContent.classList.remove('danger');
            
            if (isSuccess) {
                modalContent.classList.add('success');
                icon.className = 'fas fa-check';
            } else {
                modalContent.classList.add('danger');
                icon.className = 'fas fa-times';
            }
            
            // Single button mode
            actionsDiv.classList.add('single');
            document.querySelector('.btn-cancel').style.display = 'none';
            yesBtn.innerText = "OK";
            
            confirmCallback = null; // No callback for simple alert
            const overlay = document.getElementById('confirm-modal-overlay');
            overlay.classList.add('active'); 
        }

        function closeConfirmation() {
            const overlay = document.getElementById('confirm-modal-overlay');
            overlay.classList.remove('active');
            setTimeout(() => {
                 // Reset state after animation
                 confirmCallback = null;
            }, 200);
        }

        document.getElementById('confirm-yes-btn').addEventListener('click', () => {
            if (confirmCallback) confirmCallback();
            closeConfirmation();
        });

        // --- INIT ---
        document.addEventListener('DOMContentLoaded', () => {
            fetchDistributions(); fetchVolunteers(); fetchResources(); 
            fetchDisasters(); fetchVictims(); fetchReports(); fetchBackups(); getSchedule();

            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view');
            if (view) {
                if(view === 'backup') showSection('backup');
                else { showSection('lists'); switchList(view); }
                
                // FIXED: Don't clear history immediately so sidebar PHP knows what to highlight on refresh
                // window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // --- BACKUP & RESTORE LOGIC (UPDATED WITH CUSTOM MODAL) ---
        async function fetchBackups() {
            const container = document.getElementById('backup-list');
            try {
                const res = await fetch(`${API_BACKUP_SYSTEM}?action=list`);
                const backups = await res.json();
                
                if (backups.length === 0) {
                    container.innerHTML = '<div style="text-align:center; padding: 20px;">No backups found.</div>';
                    return;
                }

                container.innerHTML = backups.map(b => `
                    <div class="backup-item">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-file-archive" style="color: #3b82f6; font-size: 20px;"></i>
                            <div>
                                <div style="font-weight: 500; font-size: 14px;">${b.name}</div>
                                <div style="font-size: 12px; color: var(--text-muted);">${b.date} • ${b.size}</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-restore" onclick="restoreBackup('${b.name}')">Restore</button>
                            <button class="btn-restore" style="border-color: #ef4444; color: #ef4444;" onclick="deleteBackup('${b.name}')"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                `).join('');
            } catch (err) {
                container.innerHTML = '<div style="color: red; text-align:center;">Failed to load backups.</div>';
                console.error(err);
            }
        }

        function triggerManualBackup() {
            showConfirmation(
                "Initiate Backup",
                "Are you sure you want to create a manual system backup now? This might take a few moments.",
                false,
                async () => {
                    try {
                        const res = await fetch(`${API_BACKUP_SYSTEM}?action=backup&type=Manual`);
                        const result = await res.json();
                        
                        if (result.status === 'success') {
                            showAlert("Success", "Backup created successfully: " + result.file, true);
                            fetchBackups();
                            logReportToDB('System Backup (Manual)'); 
                            getSchedule(); 
                        } else {
                            showAlert("Error", "Backup Failed: " + result.message, false);
                        }
                    } catch (err) {
                        showAlert("Error", "Error connecting to backup system.", false);
                    }
                }
            );
        }
        
        function deleteBackup(filename) {
            showConfirmation(
                "Delete Backup",
                `Are you sure you want to permanently delete "${filename}"? This cannot be undone.`,
                true,
                async () => {
                    try {
                        const res = await fetch(`${API_BACKUP_SYSTEM}?action=delete`, {
                             method: 'POST', body: JSON.stringify({ filename: filename })
                        });
                        const result = await res.json();
                        
                        if (result.status === 'success') {
                            fetchBackups();
                            // Optional: No alert needed for delete success to keep it snappy, or use showAlert
                        } else {
                            showAlert("Error", "Delete Failed: " + result.message, false);
                        }
                    } catch (err) {
                        showAlert("Error", "Error connecting to backup system.", false);
                    }
                }
            );
        }

        async function getSchedule() {
            try {
                const res = await fetch(`${API_BACKUP_SYSTEM}?action=get_schedule`);
                const result = await res.json();
                
                document.querySelectorAll('.schedule-btn').forEach(btn => {
                    if (btn.innerText === result.schedule) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });

                const nextRunEl = document.getElementById('next-run-display');
                if (nextRunEl) {
                    nextRunEl.innerHTML = `Next scheduled run: <strong>${result.next_run}</strong>`;
                }
            } catch (err) { console.error('Failed to get schedule'); }
        }

        function setSchedule(type, btnElement) {
            showConfirmation(
                "Change Schedule",
                `Are you sure you want to change the automated backup frequency to ${type}?`,
                false,
                async () => {
                    try {
                        const res = await fetch(`${API_BACKUP_SYSTEM}?action=set_schedule`, {
                            method: 'POST',
                            body: JSON.stringify({ schedule: type })
                        });
                        const result = await res.json();
                        
                        if (result.status === 'success') {
                            document.querySelectorAll('.schedule-btn').forEach(b => b.classList.remove('active'));
                            btnElement.classList.add('active');
                            getSchedule(); 
                            showAlert("Success", `Schedule updated to ${type}`, true);
                        }
                    } catch (err) {
                        showAlert("Error", "Failed to set schedule.", false);
                    }
                }
            );
        }

        function restoreBackup(filename) {
            showConfirmation(
                "Restore Database",
                `WARNING: This will overwrite the current 'Reports' database with ${filename}. This action cannot be undone. Are you sure?`,
                true, // isDanger = true
                async () => {
                    try {
                        const res = await fetch(`${API_BACKUP_SYSTEM}?action=restore`, {
                            method: 'POST',
                            body: JSON.stringify({ filename: filename })
                        });
                        const result = await res.json();
                        
                        if (result.status === 'success') {
                            showAlert("Success", "Restore Successful! The page will reload.", true);
                            logReportToDB('System Restore (' + filename + ')'); 
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showAlert("Error", "Restore Failed: " + result.message, false);
                        }
                    } catch (err) {
                        showAlert("Error", "Error during restore process.", false);
                    }
                }
            );
        }

        // ... [Rest of your existing functions: updateStats, fetch*, render*, export*, sortData etc.] ...
        
        function updateStats() {
            const volEl = document.getElementById('stat-volunteers'); if(volEl) volEl.innerText = data.volunteers.length;
            const resEl = document.getElementById('stat-resources'); if(resEl) resEl.innerText = data.resources.reduce((sum, item) => sum + item.Qty, 0);
            const vicEl = document.getElementById('stat-victims'); if(vicEl) vicEl.innerText = data.victims.filter(v => v.Status !== 'Assisted').length;
            const distEl = document.getElementById('stat-distributions'); if(distEl) distEl.innerText = data.distributions.length;
            
            const repEl = document.getElementById('stat-reports');
            if (repEl) repEl.innerText = data.reports.length;
            
            runPredictions();
        }

        // --- Include all previous fetch functions here ---
        async function fetchSession() { /* Client-side fetch kept if needed, but PHP handles main auth */ }
        async function fetchResources() { 
             try {
                const promises = Object.entries(API_RESOURCES).map(async ([category, url]) => {
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('Err');
                        const items = await response.json();
                        return items.map(item => ({
                            ID: item.id, Item: item.name, Category: category, Qty: parseInt(item.quantity||0), Warehouse: item.location||'Unknown'
                        }));
                    } catch { return []; }
                });
                const results = await Promise.all(promises);
                data.resources = results.flat();
                updateStats(); renderResourceChart(); if(currentList === 'resources') renderTable('resources');
            } catch(e) { console.warn(e); }
        }
        async function fetchDistributions() {
             try {
                const res = await fetch(API_DISTRIBUTIONS);
                const raw = await res.json();
                data.distributions = raw.map(i => ({ID: i.distribution_id, Date: i.date, Location: i.location, Status: i.status}));
                updateStats(); renderStatusChart(); renderTrendChart(); if(currentList==='distributions') renderTable('distributions');
             } catch {}
        }
        async function fetchVolunteers() {
             try {
                const res = await fetch(API_VOLUNTEERS);
                const raw = await res.json();
                data.volunteers = raw.map(i => ({ID: i.VolunteerID, Name: i.FullName, Role: i.SkillCategory, Status: i.Status}));
                updateStats(); if(currentList==='volunteers') renderTable('volunteers');
             } catch {}
        }
        async function fetchDisasters() {
            try {
                const res = await fetch(API_DISASTERS);
                data.disasters = await res.json();
                if(currentList==='disasters') renderTable('disasters');
            } catch {}
        }
        async function fetchVictims() {
            try {
                const res = await fetch(API_VICTIMS);
                const raw = await res.json();
                data.victims = raw.map(i => ({ID: i.victim_id, Name: i.full_name, Status: i.email_verified==='t'?'Verified':'Pending'}));
                updateStats(); if(currentList==='victims') renderTable('victims');
            } catch {}
        }
        async function fetchReports() {
            try {
                const res = await fetch(API_REPORTS);
                data.reports = await res.json();
                if(currentList==='reports') renderTable('reports');
            } catch {}
        }

        // --- Navigation & UI Logic ---
        function showSection(id) {
            // 1. Hide/Show Sections
            ['dashboard','lists','backup'].forEach(s => {
                const el = document.getElementById(s);
                if(el) el.classList.add('hidden');
            });
            const target = document.getElementById(id);
            if(target) target.classList.remove('hidden');

            // 2. Update Sidebar Highlight (FIXED)
            // Remove 'active' from all sidebar items
            document.querySelectorAll('.sidebar .nav-item').forEach(item => {
                item.classList.remove('active');
            });

            // Find the button/link that corresponds to this section and make it active
            const activeBtn = document.querySelector(`.sidebar .nav-item[data-section="${id}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }

            // 3. Update URL without reloading (Optional)
            // This ensures if you refresh, you stay on the same tab because PHP looks at $_GET['view']
            const url = new URL(window.location);
            url.searchParams.set('view', id);
            window.history.pushState({}, '', url);
        }

        function navigateToList(type) { showSection('lists'); switchList(type); }
        function switchList(type) {
            currentList = type;
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active');
                if(b.innerText.toLowerCase().includes(type.replace('reports','generated'))) b.classList.add('active');
                else if(b.innerText.toLowerCase() === type) b.classList.add('active');
            });
            renderTable(type);
        }

        function renderTable(type) {
            const head = document.querySelector('#data-table thead');
            const body = document.querySelector('#data-table tbody');
            const dataset = data[type];
            if(!dataset || dataset.length === 0) { head.innerHTML=''; body.innerHTML='<tr><td style="padding:20px;text-align:center">No data</td></tr>'; return; }
            
            const keys = Object.keys(dataset[0]);
            head.innerHTML = `<tr>${keys.map(k => `<th class="sortable" onclick="sortData('${k}')">${k} <i class="fas fa-sort"></i></th>`).join('')}</tr>`;
            body.innerHTML = dataset.map(row => `<tr>${keys.map(k => {
                 let val = row[k];
                 if(k==='Status') {
                     // FIXED: Added case-insensitive active check
                     let badgeClass = val === 'Active' || val === 'active' || val === 'Completed' || val === 'Assisted' || val === 'Delivered' || val === 'Verified' ? 'badge-active' :
                                      val === 'Pending' || val === 'In Transit' || val === 'Planning' || val === 'Volunteer Needed' || val === 'In Progress' ? 'badge-pending' : 'badge-critical';
                     return `<td><span class="badge ${badgeClass}">${val}</span></td>`;
                 }
                 return `<td>${val}</td>`;
            }).join('')}</tr>`).join('');
        }

        function sortData(key) {
            if (currentSort.key === key) currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            else { currentSort.key = key; currentSort.direction = 'asc'; }
            
            data[currentList].sort((a,b) => {
                let va = a[key], vb = b[key];
                if(typeof va === 'string') va = va.toLowerCase();
                if(typeof vb === 'string') vb = vb.toLowerCase();
                if (va < vb) return currentSort.direction === 'asc' ? -1 : 1;
                if (va > vb) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            renderTable(currentList);
        }
        
        function exportCurrentList() { exportData(currentList); }
        function exportData(type) {
            // ... existing export logic ...
             const headers = Object.keys(data[type][0]);
             const rows = [headers.join(','), ...data[type].map(r => headers.map(h => JSON.stringify(r[h])).join(','))];
             const blob = new Blob([rows.join('\n')], {type:'text/csv'});
             const url = URL.createObjectURL(blob);
             const a = document.createElement('a'); a.href=url; a.download=type+'.csv'; a.click();
             logReportToDB(type);
        }
        
        async function logReportToDB(type) {
             await fetch(API_REPORTS, {
                 method:'POST', 
                 body: JSON.stringify({
                     report_type: type+' Export', 
                     generated_by: '<?php echo $current_user["name"]; ?>', 
                     description: 'Exported ' + type
                 })
             });
             if(currentList === 'reports') fetchReports();
        }

        function runPredictions() { 
             document.getElementById('prediction-container').innerHTML = `
                <div class="prediction-card" onclick="window.location.href='predictive_insights.php'" style="cursor: pointer; border-left-color: #3b82f6;">
                    <i class="fas fa-chart-line prediction-icon" style="color: #3b82f6;"></i>
                    <div class="prediction-content"><h5>System Status</h5><p>Click for detailed AI analysis.</p></div>
                </div>`;
        }
        
        function renderStatusChart() { /* ... chart logic ... */ }
        function renderResourceChart() { /* ... chart logic ... */ }
        function renderTrendChart() { /* ... chart logic ... */ }
    </script>
</body>
</html>