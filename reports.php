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

        /* Prediction Cards */
        .prediction-card {
            border-left: 4px solid var(--primary);
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer; /* Clickable */
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .prediction-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .prediction-icon {
            color: var(--primary);
            font-size: 1.2rem;
            margin-top: 2px;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #64748b;
            cursor: pointer;
        }
        .modal-header { border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-title { font-size: 1.2rem; font-weight: bold; color: #1e293b; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="logo">
            Relief<span>Net</span>
        </div>
        <div class="nav-links">
            <button class="nav-item active" onclick="showSection('dashboard')">
                <i class="fas fa-chart-pie"></i> Dashboard
            </button>
            <button class="nav-item" onclick="showSection('lists')">
                <i class="fas fa-users"></i> Database Lists
            </button>
            <button class="nav-item" onclick="showSection('backup')">
                <i class="fas fa-database"></i> Backup & Restore
            </button>
            
            <!-- BACK BUTTON ADDED HERE -->
            <div style="margin-top: 20px; border-top: 1px solid #334155; padding-top: 10px;">
                <a href="http://10.147.17.58:8000/admin_dashboard.php" class="nav-item" style="color: #94a3b8; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Admin
                </a>
            </div>

        </div>
        <div class="user-profile">
            <div class="avatar" id="user-avatar">AD</div>
            <div class="user-info">
                <h4 id="user-name">Admin User</h4>
                <p id="user-role">System Administrator</p>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <header class="header">
            <div class="page-title" id="page-title">Dashboard</div>
            <!-- ID added for JS manipulation -->
            <div class="status-badge" id="system-status">
                <div class="status-dot"></div> System Online
            </div>
        </header>

        <div class="scroll-area">
            
            <!-- SECTION: DASHBOARD -->
            <section id="dashboard" class="fade-in">
                <!-- Predictive Analytics Section -->
                                <div style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h4 style="margin: 0; color: #475569; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">AI Predictive Insights</h4>
                        <a href="predictive_insights.html" class="btn-primary" style="padding: 6px 12px; font-size: 12px; text-decoration: none;">
                            <i class="fas fa-chart-line"></i> View Full Report
                        </a>
                    </div>
                    <div class="grid-4" id="prediction-container">
                        <!-- Predictions injected via JS -->
                        <div class="prediction-card">
                            <i class="fas fa-spinner fa-spin prediction-icon"></i>
                            <div>
                                <h5 style="font-size: 14px; margin: 0; font-weight: 600;">Analyzing Data...</h5>
                                <p style="font-size: 12px; color: #64748b; margin: 4px 0 0;">Calculating risk factors.</p>
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
                            <h3 id="stat-victims">0</h3>
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
                        <h4 style="margin-bottom: 16px;">Distribution Status Breakdown</h4>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h4 style="margin-bottom: 16px;">Top Resource Availability</h4>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="resourceChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div class="card" style="margin-bottom: 24px;">
                    <h4 style="margin-bottom: 16px;">Distribution Activity (Monthly)</h4>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                    
                <div class="card bg-dark" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white;">
                    <h3 style="margin-bottom: 12px; color: white;">Quick Auditor Export</h3>
                    <p style="color: #94a3b8; margin-bottom: 24px; font-size: 14px;">Download comprehensive CSV reports for external auditing immediately.</p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button onclick="exportData('volunteers')" class="btn-primary" style="background: #4f46e5;"><i class="fas fa-download"></i> Volunteers</button>
                        <button onclick="exportData('resources')" class="btn-primary" style="background: #4f46e5;"><i class="fas fa-download"></i> Inventory</button>
                        <button onclick="exportData('distributions')" class="btn-primary" style="background: #4f46e5;"><i class="fas fa-download"></i> Logistics</button>
                        <button onclick="exportData('disasters')" class="btn-primary" style="background: #4f46e5;"><i class="fas fa-download"></i> Disasters</button>
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

            <!-- SECTION: BACKUP (DISABLED) -->
            <section id="backup" class="hidden fade-in">
                <div class="disabled-section">
                    <!-- Overlay for non-interactivity -->
                    <div class="disabled-overlay">
                        <span class="coming-soon-badge"><i class="fas fa-lock"></i> Feature Disabled</span>
                    </div>

                    <div class="alert-box">
                        <i class="fas fa-exclamation-circle" style="margin-top: 4px;"></i>
                        <div>
                            <strong>Critical Zone</strong>
                            <p style="font-size: 13px; margin-top: 4px;">Restoring a database will overwrite current data. Ensure you have a backup.</p>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="card">
                            <h4 style="margin-bottom: 16px;"><i class="fas fa-save" style="color: var(--primary);"></i> Create Backup</h4>
                            <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">Generates a SQL/CSV dump of all current modules.</p>
                            <button class="btn-primary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-database"></i> Generate New Backup
                            </button>
                        </div>
                        <div class="card">
                            <h4 style="margin-bottom: 16px;"><i class="fas fa-upload" style="color: var(--secondary);"></i> Restore File</h4>
                            <div style="border: 2px dashed var(--border); border-radius: 8px; padding: 30px; text-align: center; color: var(--text-muted); cursor: pointer;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; margin-bottom: 10px;"></i><br>
                                Drag .sql or .csv file here
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h4 style="margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">Backup History</h4>
                        <div id="backup-list">
                            <!-- Backups injected via JS -->
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <!-- DETAIL MODAL -->
    <div class="modal-overlay" id="detail-modal" onclick="if(event.target === this) closeDetailModal()">
        <div class="modal-content">
            <button class="close-btn" onclick="closeDetailModal()">&times;</button>
            <div class="modal-header">
                <div class="modal-title" id="modal-title">
                    <!-- Title injected here -->
                </div>
            </div>
            <div class="modal-body" id="modal-body" style="font-size: 14px; color: #475569; line-height: 1.6;">
                <!-- Content injected here -->
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button class="btn-primary" onclick="closeDetailModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // --- API Config ---
        const API_DISTRIBUTIONS = 'http://10.147.17.154:8000/distribution_module/distribution.php';
        const API_VOLUNTEERS = 'http://10.147.17.30:8000/api_volunteer.php';
        const API_SESSION = 'http://10.147.17.30:8000/api_session.php'; 
        const API_DISASTERS = 'http://10.147.17.116:8000/disaster.php';
        const API_VICTIMS = 'http://10.147.17.116:8000/victim.php';
        
        // New Resource APIs (Updated IPs)
        const API_RESOURCES = {
            medical: 'http://10.147.17.224:8000/medical_resource_api.php',
            clothing: 'http://10.147.17.224:8000/clothing_resource_api.php',
            food: 'http://10.147.17.224:8000/food_resource_api.php',
            shelter: 'http://10.147.17.224:8000/shelter_resource_api.php'
        };
        
        // --- Data State ---
        const data = {
            volunteers: [],
            resources: [], 
            victims: [],
            distributions: [],
            disasters: []
        };

        const backups = [
            { name: 'backup_weekly_01.sql', date: '2024-01-01', size: '2.4MB' },
            { name: 'backup_weekly_02.sql', date: '2024-01-08', size: '2.6MB' }
        ];

        let currentList = 'volunteers';
        let charts = { status: null, resource: null, trend: null };

        // --- Init ---
        document.addEventListener('DOMContentLoaded', () => {
            fetchDistributions(); 
            fetchVolunteers();
            fetchResources(); 
            fetchSession(); 
            fetchDisasters();
            fetchVictims();
            renderBackups();
        });

        // --- PREDICTIVE ANALYTICS LOGIC ---
        function runPredictions() {
            const container = document.getElementById('prediction-container');
            if (!container) return;

            let predictions = [];

            // 1. High Risk Zone (Based on Disaster History)
            const locationCounts = {};
            data.disasters.forEach(d => {
                const loc = d.Location || 'Unknown';
                locationCounts[loc] = (locationCounts[loc] || 0) + 1;
            });
            // Find max
            let riskZone = 'None';
            let maxCount = 0;
            for (const [loc, count] of Object.entries(locationCounts)) {
                if (count > maxCount) { maxCount = count; riskZone = loc; }
            }
            if (maxCount > 0) {
                predictions.push({
                    type: 'risk',
                    icon: 'fa-map-marker-alt',
                    color: '#ef4444', // Red
                    title: 'High Risk Zone',
                    desc: `${riskZone} has reported ${maxCount} incidents historically. Priority area for monitoring.`
                });
            }

            // 2. Resource Alert (Low Stock Logic)
            const totalResources = data.resources.reduce((sum, r) => sum + r.Qty, 0);
            if (totalResources < 500 && data.disasters.some(d => d.Status === 'Active')) {
                predictions.push({
                    type: 'resource_low',
                    icon: 'fa-box-open',
                    color: '#f59e0b', // Orange
                    title: 'Resource Alert',
                    desc: 'Stock levels low relative to active disasters. Consider restocking Food & Medical.'
                });
            } else {
                 predictions.push({
                    type: 'resource_ok',
                    icon: 'fa-check-circle',
                    color: '#10b981', // Green
                    title: 'Resource Status',
                    desc: 'Current resource levels are sufficient for active operations.'
                });
            }

            // 3. Seasonal Forecast (Simple Month Check)
            const currentMonth = new Date().getMonth() + 1; // 1-12
            let seasonMsg = "Normal weather conditions expected.";
            let seasonIcon = "fa-sun";
            let seasonColor = "#3b82f6"; // Blue
            let seasonType = 'season_ok';

            // Example Logic: Monsoon season usually end of year
            if (currentMonth >= 10 || currentMonth <= 2) {
                seasonMsg = "Monsoon season approaching. Expect higher flood risk.";
                seasonIcon = "fa-cloud-showers-heavy";
                seasonColor = "#6366f1"; // Indigo
                seasonType = 'season_risk';
            }

            predictions.push({
                type: seasonType,
                icon: seasonIcon,
                color: seasonColor,
                title: 'Seasonal Forecast',
                desc: seasonMsg
            });

            // 4. Vulnerability Check
            const vulnerableCount = data.victims.filter(v => v.Vulnerabilities && v.Vulnerabilities !== 'None').length;
            if (vulnerableCount > 5) {
                 predictions.push({
                    type: 'vulnerability',
                    icon: 'fa-user-nurse',
                    color: '#ec4899', // Pink
                    title: 'Vulnerability Alert',
                    desc: `${vulnerableCount} victims identified as high-risk (Elderly/Infants). Specialized care required.`
                });
            }

            // Render
            container.innerHTML = predictions.map(p => `
                <div class="prediction-card" style="border-left-color: ${p.color};" onclick="openDetailModal('${p.type}')">
                    <i class="fas ${p.icon} prediction-icon" style="color: ${p.color};"></i>
                    <div>
                        <h5 style="font-size: 14px; margin: 0; font-weight: 600; color: #1e293b;">${p.title}</h5>
                        <p style="font-size: 12px; color: #64748b; margin: 4px 0 0; line-height: 1.4;">${p.desc}</p>
                        <span style="font-size: 11px; color: ${p.color}; font-weight: 600; margin-top: 6px; display: inline-block;">Click for details &rarr;</span>
                    </div>
                </div>
            `).join('');
        }

        // --- MODAL LOGIC ---
        function openDetailModal(type) {
            const modal = document.getElementById('detail-modal');
            const titleEl = document.getElementById('modal-title');
            const bodyEl = document.getElementById('modal-body');
            
            let title = '';
            let content = '';
            let icon = '';
            let color = '';

            if (type === 'risk') {
                title = 'High Risk Analysis';
                icon = 'fa-map-marker-alt';
                color = '#ef4444';
                // Analyze locations
                const locCounts = {};
                data.disasters.forEach(d => locCounts[d.Location] = (locCounts[d.Location]||0)+1);
                let sorted = Object.entries(locCounts).sort((a,b) => b[1] - a[1]);
                
                content = `<p><strong>Historical analysis indicates high frequency of disasters in the following zones:</strong></p>
                           <ul style="list-style: none; padding: 0;">
                           ${sorted.map(([loc, count]) => `<li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span style="font-weight: bold;">${loc}:</span> ${count} recorded incidents
                           </li>`).join('')}
                           </ul>
                           <p style="margin-top: 15px; background: #fef2f2; padding: 10px; border-radius: 6px; border-left: 3px solid #ef4444;">
                               <strong>Recommendation:</strong> Pre-position relief supplies in the top-ranked district 
                               before the start of the typical disaster season.
                           </p>`;
            } 
            else if (type === 'resource_low' || type === 'resource_ok') {
                title = 'Resource Inventory Health';
                icon = 'fa-box-open';
                color = type === 'resource_low' ? '#f59e0b' : '#10b981';
                
                const categories = {};
                data.resources.forEach(r => categories[r.Category] = (categories[r.Category]||0) + r.Qty);
                
                content = `<p><strong>Current Stock Levels by Category:</strong></p>
                           <ul style="list-style: none; padding: 0;">
                           ${Object.entries(categories).map(([cat, qty]) => `<li style="padding: 8px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between;">
                                <span>${cat}</span> <span style="font-weight: bold;">${qty} units</span>
                           </li>`).join('')}
                           </ul>
                           <p style="margin-top: 15px; font-style: italic;">
                               Analysis compares current stock against active disaster requirements. 
                               ${type === 'resource_low' ? 'Urgent restocking recommended for essential categories.' : 'Stock levels are currently healthy.'}
                           </p>`;
            }
            else if (type === 'season_risk' || type === 'season_ok') {
                title = 'Seasonal Weather Forecast';
                icon = 'fa-cloud-sun';
                color = '#3b82f6';
                content = `<p><strong>Based on historical data for this month:</strong></p>
                           <p>We are currently in a <strong>${type === 'season_risk' ? 'High Risk' : 'Low Risk'}</strong> seasonal window.</p>
                           <p>Historical data indicates that ${type === 'season_risk' ? '70%' : 'only 10%'} of annual disasters occur during this quarter.</p>
                           <p style="margin-top: 15px; background: #eff6ff; padding: 10px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                               <strong>Action:</strong> ${type === 'season_risk' ? 'Activate volunteer standby protocols and check generator fuel levels.' : 'Conduct routine maintenance on equipment and update volunteer rosters.'}
                           </p>`;
            }
            else if (type === 'vulnerability') {
                title = 'Vulnerability Assessment';
                icon = 'fa-user-nurse';
                color = '#ec4899';
                
                const vulns = data.victims.filter(v => v.Vulnerabilities && v.Vulnerabilities !== 'None');
                
                content = `<p><strong>Identified Vulnerable Groups:</strong></p>
                           <p>Total High-Risk Individuals: <strong>${vulns.length}</strong></p>
                           <div style="max-height: 200px; overflow-y: auto; background: #fdf2f8; padding: 10px; border-radius: 6px;">
                               ${vulns.map(v => `<div style="font-size: 12px; margin-bottom: 6px;">
                                    <strong>${v.Name}</strong> (${v.Address}): <span style="color: #db2777;">${v.Vulnerabilities}</span>
                               </div>`).join('')}
                           </div>
                           <p style="margin-top: 15px;"><strong>Action:</strong> Ensure medical kits include pediatric and geriatric supplies for these specific locations.</p>`;
            }

            titleEl.innerHTML = `<i class="fas ${icon}" style="color: ${color};"></i> ${title}`;
            bodyEl.innerHTML = content;
            modal.style.display = 'flex';
        }

        function closeDetailModal() {
            document.getElementById('detail-modal').style.display = 'none';
        }

        // --- API Fetching Logic: Session ---
        async function fetchSession() {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 2000); 

                const response = await fetch(API_SESSION, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (!response.ok) throw new Error('Session fetch failed');
                const sessionData = await response.json();

                if(sessionData.user_name) {
                    const userNameEl = document.getElementById('user-name');
                    if (userNameEl) userNameEl.innerText = sessionData.user_name;
                    
                    const userAvatarEl = document.getElementById('user-avatar');
                    if (userAvatarEl) userAvatarEl.innerText = sessionData.user_initials || sessionData.user_name.substring(0,2).toUpperCase();
                }
                if(sessionData.user_role) {
                    const userRoleEl = document.getElementById('user-role');
                    if (userRoleEl) userRoleEl.innerText = sessionData.user_role;
                }

            } catch (error) {
                console.warn("Session API Failed (Using Default):", error);
            }
        }

        // --- API Fetching Logic: Resources (Consolidated) ---
        async function fetchResources() {
            try {
                const promises = Object.entries(API_RESOURCES).map(async ([category, url]) => {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3000);
                    
                    try {
                        const response = await fetch(url, { signal: controller.signal });
                        clearTimeout(timeoutId);
                        if (!response.ok) throw new Error(`${category} API error`);
                        
                        const items = await response.json();
                        
                        return items.map((item, index) => ({
                            ID: item.id,
                            Item: item.name, 
                            Category: category.charAt(0).toUpperCase() + category.slice(1), 
                            Qty: parseInt(item.quantity || 0), 
                            Warehouse: item.location || 'Unknown', 
                            Supplier: item.supplier || '-',
                            'Expiry Date': item.expiry_date || '-' 
                        }));
                    } catch (err) {
                        console.warn(`Failed to fetch ${category}:`, err);
                        return []; 
                    }
                });

                const results = await Promise.all(promises);
                data.resources = results.flat();

                if (data.resources.length === 0) throw new Error("All resource APIs failed or returned empty");

                updateStats();
                renderResourceChart(); // Update Chart
                if(currentList === 'resources') renderTable('resources');

            } catch (error) {
                console.warn("Resource APIs Failed (Using Offline Data):", error);
                
                data.resources = Array.from({length: 12}, (_, i) => ({
                    ID: `OFFLINE-RES-${2000+i}`, 
                    Item: ['Rice','Antibiotics','T-Shirt','Tent'][i%4], 
                    Category: ['Food','Medical','Clothing','Shelter'][i%4], 
                    Qty: (i+1)*50, 
                    Warehouse: 'Central',
                    Supplier: 'Mock Supplier',
                    'Expiry Date': '2026-12-31'
                }));
                
                updateStats();
                renderResourceChart(); // Update Chart
                if(currentList === 'resources') renderTable('resources');
            }
        }

        // --- API Fetching Logic: Distributions ---
        async function fetchDistributions() {
            const statusText = document.getElementById('system-status');
            
            try {
                if (statusText) statusText.innerHTML = '<div class="status-dot" style="background-color: #f59e0b;"></div> Connecting...';
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); 

                const response = await fetch(API_DISTRIBUTIONS, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (!response.ok) throw new Error('Network response was not ok');
                
                const rawData = await response.json();
                
                data.distributions = rawData.map((item, index) => {
                    let displayLocation = item.location;
                    if (!displayLocation && item.comments) {
                        const locMatch = item.comments.match(/Location: (.*?)(\n|$)/);
                        if (locMatch && locMatch[1]) displayLocation = locMatch[1].trim();
                    }
                    
                    return {
                        ID: item.distribution_id,
                        'Disaster ID': item.disaster_id, // Added
                        Date: item.date,
                        Location: displayLocation || 'Unknown Zone', 
                        Status: item.status,
                        Coordinator: item.coordinator_name || 'N/A', // Added
                        'Contact': item.coordinator_contact || '-', // Added
                        'Vols Needed': item.volunteers_needed || '0', // Added
                        'Qty Sent': item.quantity_sent || '0', // Added
                        Items: item.comments ? "See details" : "General Aid" // Kept for now
                    };
                });

                if (statusText) {
                    statusText.innerHTML = '<div class="status-dot" style="background-color: #22c55e;"></div> System Online';
                    statusText.style.backgroundColor = '#dcfce7';
                    statusText.style.color = '#166534';
                }

                updateStats();
                renderStatusChart(); // Update Chart
                renderTrendChart();  // Update Chart
                if(currentList === 'distributions') renderTable('distributions');

            } catch (error) {
                console.warn("Distribution API Failed:", error);
                
                if (statusText) {
                    statusText.innerHTML = '<div class="status-dot" style="background-color: #ef4444;"></div> Offline Mode';
                    statusText.style.backgroundColor = '#fee2e2';
                    statusText.style.color = '#991b1b';
                }

                data.distributions = [
                    { ID: 'DIS-1001', Date: '2024-01-15', Location: 'North Zone', Status: 'Completed', 'Coordinator': 'Ahmad', 'Contact': '0123456789', 'Vols Needed': '4', 'Qty Sent': '10', Items: 'Rice, Water' },
                    { ID: 'DIS-1002', Date: '2024-01-16', Location: 'East District', Status: 'In Transit', 'Coordinator': 'Siti', 'Contact': '0198765432', 'Vols Needed': '2', 'Qty Sent': '5', Items: 'Tents' }
                ];
                
                updateStats();
                renderStatusChart(); // Update Chart
                renderTrendChart();  // Update Chart
                if(currentList === 'distributions') renderTable('distributions');
            }
        }

        // --- API Fetching Logic: Volunteers ---
        async function fetchVolunteers() {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); 

                const response = await fetch(API_VOLUNTEERS, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (!response.ok) throw new Error('Network response was not ok');
                const rawData = await response.json();

                data.volunteers = rawData.map((item, index) => ({
                    ID: item.VolunteerID, 
                    Name: item.FullName,
                    Role: item.SkillCategory || 'General Support',
                    Status: item.Status,
                    Phone: item.Phone,
                    Address: item.Address 
                }));

                updateStats();
                if(currentList === 'volunteers') renderTable('volunteers');

            } catch (error) {
                console.warn("Volunteer API Failed:", error);
                data.volunteers = Array.from({length: 15}, (_, i) => ({
                    ID: `VOL-${1000+i}`, Name: `Volunteer ${i+1}`, Role: ['Medical','Rescue','Logistics'][i%3], Status: ['Active','Inactive'][i%2], Email: 'email@example.com', Phone: '0123456789', Address: 'Address', 'Assigned NGO': '1'
                }));
                updateStats();
                if(currentList === 'volunteers') renderTable('volunteers');
            }
        }

        // --- API Fetching Logic: Disasters ---
        async function fetchDisasters() {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); 

                const response = await fetch(API_DISASTERS, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (!response.ok) throw new Error('Network response was not ok');
                const rawData = await response.json();

                data.disasters = rawData.map((item, index) => ({
                    ID: item.disaster_id || `DST-${100+index}`,
                    Name: item.name || item.disaster_name || 'Unknown Disaster', // Corrected mapping
                    Type: item.type || item.disaster_name || 'General',
                    Date: item.date || item.start_date || '-',
                    Location: item.location || item.district || 'Unknown',
                    Status: item.status || 'Active',
                    Severity: item.severity || 'Moderate',
                    Description: item.description || ''
                }));

                updateStats();
                if(currentList === 'disasters') renderTable('disasters');

            } catch (error) {
                console.warn("Disaster API Failed:", error);
                data.disasters = [
                    { ID: 'DST-101', Name: 'Flood 2024', Type: 'Flood', Date: '2024-01-10', Location: 'District A', Status: 'Active', Severity: 'High' },
                    { ID: 'DST-102', Name: 'Landslide B', Type: 'Landslide', Date: '2024-02-05', Location: 'Hillside', Status: 'Closed', Severity: 'Medium' }
                ];
                updateStats();
                if(currentList === 'disasters') renderTable('disasters');
            }
        }

        // --- API Fetching Logic: Victims ---
        async function fetchVictims() {
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 3000); 

                const response = await fetch(API_VICTIMS, { signal: controller.signal });
                clearTimeout(timeoutId);

                if (!response.ok) throw new Error('Network response was not ok');
                const rawData = await response.json();

                data.victims = rawData.map((item) => ({
                    ID: item.victim_id,
                    Name: item.full_name,
                    'IC No': item.ic_number,
                    Contact: item.phone,
                    Email: item.email,
                    Address: `${item.address}, ${item.postal_code} ${item.city}`,
                    'Family Size': item.family_members,
                    'Vulnerabilities': [
                        item.has_baby === 't' ? 'Baby' : null,
                        item.has_elderly === 't' ? 'Elderly' : null,
                        item.has_disabled === 't' ? 'Disabled' : null
                    ].filter(Boolean).join(', ') || 'None',
                    Needs: item.special_request || 'None',
                    'Disaster ID': item.disaster_id,
                    Status: item.email_verified === 't' ? 'Verified' : 'Pending'
                }));

                updateStats();
                if(currentList === 'victims') renderTable('victims');

            } catch (error) {
                console.warn("Victim API Failed:", error);
                data.victims = Array.from({length: 20}, (_, i) => ({
                    ID: `VIC-${3000+i}`, Name: `Family ${String.fromCharCode(65+i)}`, 'IC No': '990101-01-1234', Contact: '012-3456789', Address: `District ${i%5 + 1}`, 'Family Size': 4, Needs: ['Food','Shelter'][i%2], Status: ['Pending','Assisted'][i%2]
                }));
                updateStats();
                if(currentList === 'victims') renderTable('victims');
            }
        }

        // --- Navigation Logic ---
        function showSection(sectionId) {
            ['dashboard', 'lists', 'backup'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.classList.add('hidden');
            });
            const target = document.getElementById(sectionId);
            if (target) target.classList.remove('hidden');
            
            const titleEl = document.getElementById('page-title');
            if (titleEl) titleEl.innerText = sectionId.replace('-', ' ');

            document.querySelectorAll('.nav-item').forEach(btn => {
                btn.classList.remove('active');
                if(btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(sectionId)) {
                    btn.classList.add('active');
                }
            });
        }
        
        function navigateToList(type) {
            showSection('lists');
            switchList(type);
        }

        // --- Dashboard Logic ---
        function updateStats() {
            const volEl = document.getElementById('stat-volunteers');
            if (volEl) volEl.innerText = data.volunteers.length;
            
            const resEl = document.getElementById('stat-resources');
            if (resEl) resEl.innerText = data.resources.reduce((sum, item) => sum + item.Qty, 0);
            
            const vicEl = document.getElementById('stat-victims');
            if (vicEl) vicEl.innerText = data.victims.filter(v => v.Status !== 'Assisted').length;
            
            const distEl = document.getElementById('stat-distributions');
            if (distEl) distEl.innerText = data.distributions.length;
            
            // New for active disasters card if present
            const disasterEl = document.getElementById('stat-disasters');
            if (disasterEl) disasterEl.innerText = data.disasters.filter(d => d.Status === 'Active' || d.Status === 'Ongoing').length;

            // Run predictions after stats are updated
            runPredictions();
        }

        // --- Charts Render Logic (Replicating analytics.php style) ---
        function renderStatusChart() {
            const canvas = document.getElementById('statusChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            const counts = {};
            data.distributions.forEach(d => {
                const s = d.Status || 'Unknown';
                counts[s] = (counts[s] || 0) + 1;
            });
            
            const labels = Object.keys(counts);
            const values = Object.values(counts);
            
            // Colors from analytics.php (Yellow, Green, Teal, Red)
            const bgColors = ['#ffc107', '#28a745', '#17a2b8', '#dc3545', '#6c757d', '#007bff'];

            if (charts.status) charts.status.destroy();

            charts.status = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: bgColors.slice(0, labels.length),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        function renderResourceChart() {
            const canvas = document.getElementById('resourceChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            const topResources = data.resources.slice(0, 10);
            const labels = topResources.map(r => r.Item);
            const values = topResources.map(r => r.Qty);

            if (charts.resource) charts.resource.destroy();

            charts.resource = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Quantity Available',
                        data: values,
                        backgroundColor: '#007bff' // Blue from analytics.php
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        function renderTrendChart() {
            const canvas = document.getElementById('trendChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            const trendCounts = {};
            data.distributions.forEach(d => {
                const dateStr = d.Date || '';
                const month = dateStr.length >= 7 ? dateStr.substring(0, 7) : 'Unknown'; 
                trendCounts[month] = (trendCounts[month] || 0) + 1;
            });
            
            const labels = Object.keys(trendCounts).sort();
            const values = labels.map(m => trendCounts[m]);

            if (charts.trend) charts.trend.destroy();

            charts.trend = new Chart(ctx, {
                // CHANGED TO BAR as requested
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Distributions',
                        data: values,
                        backgroundColor: '#6610f2', // Purple from analytics.php
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // --- Lists Logic ---
        function switchList(type) {
            currentList = type;
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                if(btn.innerText.toLowerCase() === type) btn.classList.add('active');
            });

            const container = document.querySelector('.table-container');
            if (container) {
                container.classList.remove('slide-in');
                void container.offsetWidth; 
                container.classList.add('slide-in');
            }

            renderTable(type);
        }

        function renderTable(type) {
            const tableHead = document.querySelector('#data-table thead');
            const tableBody = document.querySelector('#data-table tbody');
            const dataset = data[type];

            if (!tableHead || !tableBody) return;

            if (!dataset || dataset.length === 0) {
                tableHead.innerHTML = '';
                tableBody.innerHTML = '<tr><td style="text-align:center; padding: 20px;">No data available</td></tr>';
                return;
            }

            const headers = Object.keys(dataset[0]);
            tableHead.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;

            tableBody.innerHTML = dataset.map(row => `
                <tr>
                    ${headers.map(header => {
                        const val = row[header];
                        if (header === 'Status') {
                            let badgeClass = val === 'Active' || val === 'Completed' || val === 'Assisted' || val === 'Delivered' || val === 'Verified' ? 'badge-active' :
                                             val === 'Pending' || val === 'In Transit' || val === 'Planning' || val === 'Volunteer Needed' || val === 'In Progress' ? 'badge-pending' : 'badge-critical';
                            return `<td><span class="badge ${badgeClass}">${val}</span></td>`;
                        }
                        return `<td>${val}</td>`;
                    }).join('')}
                </tr>
            `).join('');
        }

        // --- Export Logic ---
        function exportCurrentList() {
            exportData(currentList);
        }

        function exportData(type) {
            const dataset = data[type];
            if (!dataset || dataset.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = Object.keys(dataset[0]);
            const csvRows = [
                headers.join(','),
                ...dataset.map(row => headers.map(fieldName => JSON.stringify(row[fieldName])).join(','))
            ];

            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${type}_report_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // --- Backup Logic ---
        function renderBackups() {
            const container = document.getElementById('backup-list');
            if (container) {
                container.innerHTML = backups.map(b => `
                    <div class="backup-item">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-file-archive" style="color: #cbd5e1; font-size: 20px;"></i>
                            <div>
                                <div style="font-weight: 500; font-size: 14px;">${b.name}</div>
                                <div style="font-size: 12px; color: var(--text-muted);">${b.date} • ${b.size}</div>
                            </div>
                        </div>
                        <button class="btn-restore" disabled style="opacity:0.5; cursor:not-allowed;">Restore</button>
                    </div>
                `).join('');
            }
        }

        function createNewBackup() {
            // Disabled
        }
    </script>
</body>
</html>