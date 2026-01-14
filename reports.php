<?php
// ========================================
// Reports Dashboard - Session & Auth
// Integrated from distribution_main.php
// ========================================

// 1. ENABLE ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. START SESSION
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    session_set_cookie_params(86400);
    session_start();
}

// ========================================
// AUTHENTICATION LOGIC (Token Handover)
// ========================================

// A. Check for Auth Token from Bridge (report.php on 17.30)
if (isset($_GET['auth_token']) && !empty($_GET['auth_token'])) {
    // Decode the token
    $json_data = base64_decode(urldecode($_GET['auth_token']));
    $data = json_decode($json_data, true);

    if ($data && isset($data['user_id'])) {
        // VALID TOKEN: Create local session on this server (17.58)
        $_SESSION['user_id'] = $data['user_id'];
        $_SESSION['name']    = $data['name'];
        $_SESSION['role']    = $data['role'];
        $_SESSION['email']   = $data['email'];
        
        // Remove token from URL to keep it clean (Redirect to self)
        header("Location: reports.php");
        exit();
    }
}

// B. Standard Session Check (Local Session)
if (!isset($_SESSION['user_id'])) {
    // Not logged in locally, and no token provided.
    // Redirect BACK to the main login server (17.30)
    header("Location: http://10.147.17.30:8000/login.php?return_to=reports");
    exit();
}

// 3. PREPARE USER DATA FOR UI
$uid = $_SESSION['user_id'];
$uname = $_SESSION['name'] ?? 'User';
$urole = $_SESSION['role'] ?? 'User';
$uemail = $_SESSION['email'] ?? '';

// Determine Avatar Initials
$avatar_initials = strtoupper(substr($uname, 0, 2));

$current_user = [
    'id' => $uid,
    'name' => $uname,
    'role' => ucfirst($urole), 
    'avatar' => $avatar_initials,
    'email' => $uemail
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
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
            max-height: 90vh; 
            display: flex;
            flex-direction: column;
        }

        /* PLAN MODE - Custom Style for displaying Plans */
        .confirm-modal.plan-mode {
            padding: 0; /* Remove default padding for full-bleed header */
            max-width: 700px; /* Wider for plans */
            overflow: hidden; /* Ensure rounded corners clip children */
        }
        
        .confirm-modal.plan-mode .confirm-message {
            padding: 24px;
            text-align: left;
            overflow-y: auto;
            flex: 1; /* Take remaining space */
            margin-bottom: 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .confirm-modal.plan-mode .confirm-actions {
            padding: 16px 24px;
            background: #f8fafc;
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
            flex-shrink: 0;
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
            flex-shrink: 0;
        }

        .confirm-message {
            color: var(--text-muted);
            margin-bottom: 28px;
            font-size: 0.95rem;
            line-height: 1.6;
            overflow-y: auto; 
            /* Improved scrolling behavior */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        /* --- PLAN VIEWER STYLES --- */
        .plan-viewer {
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.9rem;
            color: #334155;
        }

        .plan-header-banner {
            background: linear-gradient(135deg, var(--primary) 0%, #2563eb 100%);
            color: white;
            padding: 20px 24px;
            /* No negative margins needed in plan-mode */
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .plan-header-title {
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .plan-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .plan-row {
            display: flex;
            flex-direction: column;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }
        
        .plan-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .plan-value {
            font-weight: 500;
            color: #0f172a;
            font-size: 0.95rem;
        }

        .plan-section-title {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            margin: 24px 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .plan-family-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .plan-family-header {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .plan-sub-row {
            font-size: 0.9rem;
            margin-bottom: 6px;
            display: flex;
            gap: 8px;
            align-items: baseline;
        }

        .plan-tag {
            display: inline-flex;
            align-items: center;
            background: #fff7ed;
            color: #c2410c;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 0.8rem;
            border: 1px solid #fed7aa;
            margin-right: 6px;
            margin-top: 6px;
            font-weight: 600;
        }

        .confirm-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            flex-shrink: 0;
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
            <!-- Icon is now hidden/replaced in JS for plans, or shown for standard alerts -->
            <div class="confirm-icon-wrapper" id="confirm-icon-wrapper">
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
        
        // Updated Resource APIs
        const API_RESOURCES = {
            baby: 'http://10.147.17.224:8000/baby_api.php',
            basic_needs: 'http://10.147.17.224:8000/basic_needs_api.php',
            elderly: 'http://10.147.17.224:8000/elderly_api.php',
            medical: 'http://10.147.17.224:8000/medical_api.php',
            disabled: 'http://10.147.17.224:8000/disabled_api.php'
        };
        
        const data = { volunteers: [], resources: [], victims: [], distributions: [], disasters: [], reports: [] };
        let currentList = 'volunteers';
        let currentSort = { key: null, direction: 'asc' };
        let charts = { status: null, resource: null, trend: null };

        // --- UPDATE HEADER WITH USER INFO (Matches login.php session data) ---
        // Run immediately to update static header elements
        (function updateHeaderProfile() {
            const userNameEl = document.getElementById('user-name');
            const userRoleEl = document.getElementById('user-role');
            const userAvatarEl = document.getElementById('user-avatar');

            if (userNameEl) userNameEl.innerText = "<?php echo htmlspecialchars($current_user['name']); ?>";
            if (userRoleEl) userRoleEl.innerText = "<?php echo htmlspecialchars($current_user['role']); ?>";
            if (userAvatarEl) userAvatarEl.innerText = "<?php echo htmlspecialchars($current_user['avatar']); ?>";
        })();

        // --- Custom Confirmation Logic ---
        let confirmCallback = null;

        function showConfirmation(title, message, isDanger, onConfirm) {
            document.getElementById('confirm-title').innerText = title;
            document.getElementById('confirm-message').innerText = message;
            
            const modalContent = document.getElementById('confirm-modal-content');
            const icon = document.getElementById('confirm-icon');
            const iconWrapper = document.getElementById('confirm-icon-wrapper');
            const yesBtn = document.getElementById('confirm-yes-btn');
            const actionsDiv = document.getElementById('confirm-actions');
            
            // Standard Alert Reset
            iconWrapper.style.display = 'flex';
            document.getElementById('confirm-title').style.display = 'block';
            modalContent.classList.remove('danger', 'success', 'plan-mode'); // Remove plan-mode
            actionsDiv.classList.remove('single');
            document.querySelector('.btn-cancel').style.display = 'block';
            
            // Standard Styling Reset
            document.getElementById('confirm-message').style.maxHeight = '';
            
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
            const iconWrapper = document.getElementById('confirm-icon-wrapper');
            const yesBtn = document.getElementById('confirm-yes-btn');
            const actionsDiv = document.getElementById('confirm-actions');
            
            iconWrapper.style.display = 'flex';
            document.getElementById('confirm-title').style.display = 'block';
            modalContent.classList.remove('danger', 'plan-mode'); // Remove plan-mode
            
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
            
            confirmCallback = null; 
            const overlay = document.getElementById('confirm-modal-overlay');
            overlay.classList.add('active'); 
        }

        // --- NEW PLAN FORMATTER FUNCTION ---
        function formatPlan(text) {
            // Initialize container
            // Header is already part of the modal structure when in plan-mode, 
            // but we can prepend the banner inside the message area since we removed modal padding.
            let html = '<div class="plan-viewer">';
            
            html += `
            <div class="plan-header-banner">
                <div class="plan-header-title"><i class="fas fa-shipping-fast"></i> Distribution Plan</div>
                <div style="font-size:0.8rem; opacity:0.9;">System Generated</div>
            </div>`;
            
            // Safe Parsing
            const parts = text.split('SELECTED FAMILIES DETAILS:');
            const metaPart = parts[0];
            const rest = parts[1] || '';
            
            // 1. Parse Metadata
            html += '<div style="padding: 24px;"><div class="plan-meta-grid">';
            const metaLines = metaPart.split('\n');
            metaLines.forEach(line => {
                const cleanLine = line.trim();
                if(!cleanLine || cleanLine.includes('DISTRIBUTION PLAN') || cleanLine.includes('====')) return;
                
                const m = cleanLine.match(/^([a-zA-Z ]+): (.+)/);
                if(m) {
                     html += `<div class="plan-row">
                        <span class="plan-label">${m[1]}</span>
                        <span class="plan-value">${m[2]}</span>
                     </div>`;
                }
            });
            html += '</div>';
            
            // 2. Parse Families
            if(rest) {
                const familyParts = rest.split('AUTOMATIC BASIC NEEDS ALLOCATION:');
                const familyBlock = familyParts[0];
                const allocationBlock = familyParts[1] || '';

                html += '<div class="plan-section-title"><i class="fas fa-users"></i> Selected Families</div>';
                
                const famLines = familyBlock.split('\n');
                let inFamily = false;
                
                famLines.forEach(line => {
                    const cleanLine = line.trim();
                    if(!cleanLine || cleanLine.startsWith('---')) return;
                    
                    if(cleanLine.startsWith('- ')) {
                        if(inFamily) html += '</div>'; 
                        html += '<div class="plan-family-card">';
                        html += `<div class="plan-family-header"><i class="fas fa-user-circle"></i> ${cleanLine.substring(2)}</div>`;
                        inFamily = true;
                    } else if (inFamily) {
                        if(cleanLine.includes('👴') || cleanLine.includes('♿')) {
                            html += `<span class="plan-tag">${cleanLine}</span>`;
                        } else {
                             const kv = cleanLine.match(/([a-zA-Z ]+): (.+)/);
                             if(kv) {
                                 html += `<div class="plan-sub-row"><strong style="color:#64748b; font-size:0.8em; text-transform:uppercase; margin-right:4px;">${kv[1]}:</strong> <span>${kv[2]}</span></div>`;
                             } else {
                                 html += `<div class="plan-sub-row" style="margin-left:8px; color:#475569;">• ${cleanLine}</div>`;
                             }
                        }
                    }
                });
                if(inFamily) html += '</div>'; 
                
                // 3. Allocations
                if(allocationBlock && !allocationBlock.includes('Plan Created')) {
                     const allocText = allocationBlock.split('SPECIAL REQUEST')[0].replace(/-+/g,'').trim();
                     if(allocText) {
                         html += '<div class="plan-section-title"><i class="fas fa-box-open"></i> Allocations</div>';
                         html += `<div style="padding:16px; background:#f0fdf4; color:#166534; border-radius:8px; font-size:0.95rem; border:1px solid #bbf7d0;">${allocText}</div>`;
                     }
                }
            }
            html += '</div>'; // Close padding wrapper
            html += '</div>'; // Close viewer
            return html;
        }

        // Updated showPlan to use the formatter and CLEAN modal mode
        function showPlan(id) {
            const item = data.distributions.find(d => d.ID == id);
            
            if (item && item.Plan) {
                const msgEl = document.getElementById('confirm-message');
                const modalContent = document.getElementById('confirm-modal-content');
                const iconWrapper = document.getElementById('confirm-icon-wrapper');
                const titleEl = document.getElementById('confirm-title');
                
                // Hide Standard Elements
                iconWrapper.style.display = 'none';
                titleEl.style.display = 'none';
                
                // Format HTML
                msgEl.innerHTML = formatPlan(item.Plan);
                
                // RESET Styles from previous usage
                msgEl.style.whiteSpace = 'normal';
                msgEl.style.textAlign = 'left';
                msgEl.style.fontFamily = 'inherit';
                msgEl.style.fontSize = 'inherit';
                msgEl.style.background = 'transparent';
                msgEl.style.border = 'none';
                msgEl.style.padding = '0'; 
                msgEl.style.maxHeight = ''; // Remove fixed height, handled by flex in class

                // Setup Modal in PLAN MODE
                modalContent.className = 'confirm-modal plan-mode'; // Add class
                
                const yesBtn = document.getElementById('confirm-yes-btn');
                const actionsDiv = document.getElementById('confirm-actions');
                
                actionsDiv.classList.add('single');
                document.querySelector('.btn-cancel').style.display = 'none';
                yesBtn.innerText = "Close Plan";
                
                confirmCallback = null;
                const overlay = document.getElementById('confirm-modal-overlay');
                overlay.classList.add('active'); 

            } else {
                showAlert('No Plan', 'No distribution plan found for this ID.', false);
            }
        }

        function closeConfirmation() {
            const overlay = document.getElementById('confirm-modal-overlay');
            overlay.classList.remove('active');
            
            setTimeout(() => {
                 confirmCallback = null;
                 // Reset Modal State to Default
                 const msgEl = document.getElementById('confirm-message');
                 const modalContent = document.getElementById('confirm-modal-content');
                 const iconWrapper = document.getElementById('confirm-icon-wrapper');
                 const titleEl = document.getElementById('confirm-title');
                 
                 // Remove Plan Mode Class
                 modalContent.classList.remove('plan-mode');
                 
                 // Show standard elements
                 iconWrapper.style.display = 'flex';
                 titleEl.style.display = 'block';
                 
                 // Reset Content
                 msgEl.innerHTML = 'Are you sure you want to proceed?';
                 msgEl.removeAttribute('style');
                 
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
            }
        });

        // --- BACKUP & RESTORE LOGIC ---
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

        // --- CHART & DATA FUNCTIONS ---
        function updateStats() {
            const volEl = document.getElementById('stat-volunteers'); if(volEl) volEl.innerText = data.volunteers.length;
            const resEl = document.getElementById('stat-resources'); if(resEl) resEl.innerText = data.resources.reduce((sum, item) => sum + item.Qty, 0);
            const vicEl = document.getElementById('stat-victims'); if(vicEl) vicEl.innerText = data.victims.filter(v => v.Status !== 'Assisted').length;
            const distEl = document.getElementById('stat-distributions'); if(distEl) distEl.innerText = data.distributions.length;
            
            const repEl = document.getElementById('stat-reports');
            if (repEl) repEl.innerText = data.reports.length;
            
            runPredictions();
        }

        async function fetchResources() { 
             try {
                // Modified to handle the 5 new endpoints
                const promises = Object.entries(API_RESOURCES).map(async ([category, url]) => {
                    try {
                        const response = await fetch(url);
                        if (!response.ok) throw new Error('Err');
                        const items = await response.json();
                        // Assume standard array response
                        // Map fields: ID, Item Name, Category (key), Qty, Location, Expiry
                        return items.map(item => ({
                            ID: item.id || '-', 
                            Item: item.name || 'Unknown', 
                            Category: category.charAt(0).toUpperCase() + category.slice(1).replace('_', ' '), 
                            Qty: parseInt(item.quantity||0), 
                            Warehouse: item.location||'Unknown'
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
                
                // MAPPED NEW API STRUCTURE
                data.distributions = raw.map(i => ({
                    ID: i.distribution_id, 
                    Date: i.date, 
                    Location: i.location || 'Pending Assignment', // Handle null location
                    Status: i.status,
                    Plan: i.comments // Map comments to Plan
                }));
                
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

            // 2. Update Sidebar Highlight
            document.querySelectorAll('.sidebar .nav-item').forEach(item => {
                item.classList.remove('active');
            });
            const activeBtn = document.querySelector(`.sidebar .nav-item[data-section="${id}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            // 3. Update URL
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
                 
                 // Handle PLAN column with a Button
                 if(k === 'Plan') {
                     return `<td><button onclick="showPlan('${row.ID}')" class="btn-primary" style="padding: 5px 10px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;"><i class="fas fa-clipboard-list"></i> View Plan</button></td>`;
                 }
                 
                 // Handle STATUS column with badges
                 if(k==='Status') {
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
        
        // --- CHARTS (Enhanced Power BI-like Style) ---
        
        // Global Chart Defaults
        Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        Chart.defaults.color = '#64748b';

        function renderStatusChart() {
            if(charts.status) charts.status.destroy();
            const ctx = document.getElementById('statusChart').getContext('2d');
            const counts = data.distributions.reduce((acc, d) => { acc[d.Status] = (acc[d.Status]||0)+1; return acc; }, {});
            
            charts.status = new Chart(ctx, {
                type: 'doughnut',
                data: { 
                    labels: Object.keys(counts), 
                    datasets: [{ 
                        data: Object.values(counts), 
                        backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }] 
                },
                options: { 
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
        }

        function renderResourceChart() {
             if(charts.resource) charts.resource.destroy();
             const ctx = document.getElementById('resourceChart').getContext('2d');
             const top = data.resources.sort((a,b)=>b.Qty - a.Qty).slice(0,5);
             
             charts.resource = new Chart(ctx, {
                type: 'bar',
                data: { 
                    labels: top.map(i=>i.Item), 
                    datasets: [{ 
                        label:'Quantity', 
                        data: top.map(i=>i.Qty), 
                        backgroundColor: '#3b82f6',
                        borderRadius: 4,
                        barThickness: 25
                    }] 
                },
                options: { 
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f1f5f9', borderDash: [5, 5] },
                            ticks: { font: { size: 11 } }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            displayColors: false
                        }
                    }
                }
            });
        }

        function renderTrendChart() {
            if(charts.trend) charts.trend.destroy();
            const ctx = document.getElementById('trendChart').getContext('2d');
            
            // --- AGGREGATE REAL DATA ---
            const monthlyCounts = {};
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            
            // Process real distributions from API
            data.distributions.forEach(d => {
                if (!d.Date) return;
                // Assuming date format is YYYY-MM-DD
                const dateObj = new Date(d.Date);
                // Check valid date
                if (!isNaN(dateObj)) {
                    const monthIndex = dateObj.getMonth();
                    const monthName = months[monthIndex];
                    monthlyCounts[monthName] = (monthlyCounts[monthName] || 0) + 1;
                }
            });

            // Ensure we have labels for months that have data, or default if empty
            let labels = Object.keys(monthlyCounts);
            let datasetData = Object.values(monthlyCounts);

            // Sort Chronologically if data exists
            if (labels.length > 0) {
                // Combine into objects to sort
                const combined = labels.map((lbl, i) => ({ label: lbl, val: datasetData[i] }));
                combined.sort((a, b) => months.indexOf(a.label) - months.indexOf(b.label));
                
                labels = combined.map(i => i.label);
                datasetData = combined.map(i => i.val);
            } else {
                // Fallback for empty state
                labels = ['No Data'];
                datasetData = [0];
            }
            
            charts.trend = new Chart(ctx, {
                type: 'line',
                data: { 
                    labels: labels, 
                    datasets: [{ 
                        label:'Distributions', 
                        data: datasetData, 
                        borderColor: '#10b981', 
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2
                    }] 
                },
                options: { 
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f1f5f9' },
                            ticks: { precision: 0 } // Integers only
                        },
                        x: { 
                            grid: { display: false }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 10,
                            displayColors: false,
                            intersect: false,
                            mode: 'index'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>