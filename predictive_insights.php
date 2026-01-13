<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Predictive Insights - ReliefNet</title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Shared Styles -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* INLINE DETAIL VIEW STYLES */
        .inline-detail-container {
            display: none; /* Hidden by default */
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin: 0 32px 32px 32px; /* Margins to match grid padding */
            padding: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 15px;
        }

        .detail-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-header-actions {
            display: flex;
            gap: 10px;
        }

        .detail-content {
            /* Styles for the inner content */
        }

        .detail-section {
            margin-bottom: 24px;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .detail-table th, .detail-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .detail-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
            cursor: pointer; /* Indicate sortable */
        }
        
        .close-inline-btn {
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 4px;
            transition: color 0.2s;
        }
        .close-inline-btn:hover {
            color: #ef4444;
        }

        .btn-export {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-export:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>

    <!-- HEADER MODULE (Full Width) -->
    <?php include 'header.php'; ?>

    <!-- LAYOUT WRAPPER -->
    <div class="layout-wrapper">
        
        <!-- SIDEBAR MODULE -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            
            <div class="insight-header">
                <h1 style="font-size: 24px; margin-bottom: 8px;">Predictive Analytics Report</h1>
                <p style="color: #bfdbfe; font-size: 14px;">AI-driven forecasts based on historical disaster patterns and current resource levels.</p>
            </div>

            <div class="scroll-area">
                
                <!-- Top Summary Row -->
                <div class="insight-grid">
                    <!-- RISK ANALYSIS -->
                    <div class="insight-card" style="border-top: 4px solid #ef4444; cursor: pointer;" onclick="openFullDetail('risk')">
                        <div class="insight-title"><i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> High Risk Zones</div>
                        <div class="insight-metric" id="risk-metric">Loading...</div>
                        <p style="color: #64748b; font-size: 0.9rem;">Districts with the highest probability of recurring disasters.</p>
                        <div class="chart-container">
                            <canvas id="riskChart"></canvas>
                        </div>
                        <div class="recommendation-box" style="border-color: #ef4444; background: #fef2f2;">
                            <strong>Action:</strong> Pre-position heavy machinery and evacuation teams in these zones immediately.
                        </div>
                        <div style="text-align: center; margin-top: 15px; color: #ef4444; font-size: 0.85rem; font-weight: 600;">
                            Click to view detailed risk map &rarr;
                        </div>
                    </div>

                    <!-- RESOURCE HEALTH -->
                    <div class="insight-card" style="border-top: 4px solid #f59e0b; cursor: pointer;" onclick="openFullDetail('stock')">
                        <div class="insight-title"><i class="fas fa-boxes" style="color: #f59e0b;"></i> Stock Depletion Forecast</div>
                        <div class="insight-metric" id="stock-metric">Loading...</div>
                        <p style="color: #64748b; font-size: 0.9rem;">Estimated days until critical supplies run out at current usage rates.</p>
                        <div class="chart-container">
                            <canvas id="stockChart"></canvas>
                        </div>
                        <div class="recommendation-box" style="border-color: #f59e0b; background: #fffbeb;">
                            <strong>Action:</strong> Initiate procurement orders for Food and Medical supplies within 48 hours.
                        </div>
                        <div style="text-align: center; margin-top: 15px; color: #f59e0b; font-size: 0.85rem; font-weight: 600;">
                            Click to view inventory breakdown &rarr;
                        </div>
                    </div>

                    <!-- POST-DISASTER IMPACT (With Normal Person Logic) -->
                    <div class="insight-card" style="border-top: 4px solid #8b5cf6; cursor: pointer;" onclick="openFullDetail('impact')">
                        <div class="insight-title" style="justify-content: space-between;">
                            <span style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-history" style="color: #8b5cf6;"></i> Post-Disaster Impact
                            </span>
                        </div>
                        <div class="insight-metric" id="impact-metric">Loading...</div>
                        <p style="color: #64748b; font-size: 0.9rem;">Demographics affected in ended disasters.</p>
                        <div class="chart-container">
                            <canvas id="impactChart"></canvas>
                        </div>
                        <div class="recommendation-box" style="border-color: #8b5cf6; background: #f5f3ff;">
                            <strong>Action:</strong> Prepare specialized care packages and general aid based on demographic breakdown.
                        </div>
                        <div style="text-align: center; margin-top: 15px; color: #8b5cf6; font-size: 0.85rem; font-weight: 600;">
                            Click to view full impact report &rarr;
                        </div>
                    </div>

                    <!-- VULNERABILITY INDEX -->
                    <div class="insight-card" style="border-top: 4px solid #ec4899; cursor: pointer;" onclick="openFullDetail('vulnerability')">
                        <div class="insight-title"><i class="fas fa-users" style="color: #ec4899;"></i> Vulnerability Index</div>
                        <div class="insight-metric" id="vuln-metric">Loading...</div>
                        <p style="color: #64748b; font-size: 0.9rem;">Count of registered victims requiring specialized assistance (All Active).</p>
                        <div class="chart-container">
                            <canvas id="vulnChart"></canvas>
                        </div>
                        <div class="recommendation-box" style="border-color: #ec4899; background: #fdf2f8;">
                            <strong>Action:</strong> Allocate 30% more medical volunteers to high-vulnerability districts.
                        </div>
                        <div style="text-align: center; margin-top: 15px; color: #ec4899; font-size: 0.85rem; font-weight: 600;">
                            Click to view vulnerable population list &rarr;
                        </div>
                    </div>
                </div>

                <!-- INLINE DETAIL CONTAINER (Replaces Modal) -->
                <div id="inline-detail-container" class="inline-detail-container">
                    <div class="detail-header">
                        <h2 id="detail-view-title">Detailed Insight</h2>
                        <div class="detail-header-actions">
                            <button onclick="exportCurrentViewData()" class="btn-export">
                                <i class="fas fa-file-csv"></i> Export Data
                            </button>
                            <button class="close-inline-btn" onclick="closeDetailView()" title="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div id="detail-view-body" class="detail-content">
                        <!-- Dynamic Content Loaded Here -->
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // API Configuration
        const API_DISASTERS = 'http://10.147.17.116:8000/disaster.php';
        const API_VICTIMS = 'http://10.147.17.116:8000/victim.php';
        const API_REPORTS = 'api_reports.php'; 
        const API_RESOURCES = {
            medical: 'http://10.147.17.224:8000/medical_resource_api.php',
            food: 'http://10.147.17.224:8000/food_resource_api.php'
        };

        // Data Store
        const dataStore = {
            disasters: [],
            victims: [],
            resources: [],
            impactData: [] // Store for export
        };

        // Sorting State
        let currentSort = { key: null, direction: 'asc' };
        let currentViewType = ''; // 'risk', 'stock', 'impact', 'vulnerability', 'victimlist'
        let currentViewData = []; // Holds the data currently displayed in the detail view

        document.addEventListener('DOMContentLoaded', () => {
            loadData();
        });

        async function loadData() {
            try {
                // Fetch Disasters
                const disRes = await fetch(API_DISASTERS);
                if(disRes.ok) dataStore.disasters = await disRes.json();
                
                // Fetch Victims
                const vicRes = await fetch(API_VICTIMS);
                if(vicRes.ok) dataStore.victims = await vicRes.json();

                // Fetch Resources (Subset)
                const medRes = await fetch(API_RESOURCES.medical);
                const foodRes = await fetch(API_RESOURCES.food);
                let meds = [], foods = [];
                if(medRes.ok) meds = await medRes.json();
                if(foodRes.ok) foods = await foodRes.json();
                dataStore.resources = [...meds, ...foods];

                // If fetch fails (Offline Mode), load mock data for demo
                if(dataStore.disasters.length === 0) throw new Error("Offline");

                renderInsights();

            } catch (err) {
                console.warn("Using Mock Data for Insights:", err);
                // Mock Data for Visualization
                dataStore.disasters = [
                    {disaster_id: '1', name: 'Flood A', status: 'Active', location: 'Masjid Tanah', severity: 'High'}, 
                    {disaster_id: '2', name: 'Landslide B', status: 'Ended', location: 'Alor Gajah', severity: 'Medium'}, 
                    {disaster_id: '3', name: 'Storm C', status: 'Ended', location: 'Jasin', severity: 'High'}
                ];
                dataStore.victims = Array(100).fill(0).map((_,i) => ({
                    disaster_id: (i % 3 + 1).toString(),
                    full_name: `Victim ${i+1}`,
                    address: `Address ${i+1}, Zone ${i%3}`,
                    has_elderly: i%10===0?'t':'f', 
                    has_baby: i%15===0?'t':'f', 
                    has_disabled: i%20===0?'t':'f'
                }));
                dataStore.resources = [
                    {name: 'Rice 10kg', quantity: '50', location: 'Central'}, 
                    {name: 'Antibiotics', quantity: '20', location: 'North'}, 
                    {name: 'Canned Food', quantity: '100', location: 'Central'}
                ];
                
                renderInsights();
            }
        }

        // --- Helper Function: Standardize Location Name ---
        function standardizeLocation(loc) {
            if (!loc) return 'Unknown';
            return loc.toLowerCase().split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        }

        function renderInsights() {
            // 1. Risk Chart (Location Frequency)
            const locCounts = {};
            dataStore.disasters.forEach(d => {
                let rawLoc = d.location || d.Location || d.district || d.District || 'Unknown';
                if (rawLoc !== 'Unknown') {
                    const loc = standardizeLocation(rawLoc);
                    locCounts[loc] = (locCounts[loc] || 0) + 1;
                }
            });
            
            let topLoc = "None";
            if (Object.keys(locCounts).length > 0) {
                topLoc = Object.keys(locCounts).reduce((a, b) => locCounts[a] > locCounts[b] ? a : b);
            }
            document.getElementById('risk-metric').innerText = topLoc;

            new Chart(document.getElementById('riskChart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(locCounts),
                    datasets: [{
                        label: 'Incident Count',
                        data: Object.values(locCounts),
                        backgroundColor: '#ef4444'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });

            // 2. Stock Chart
            const daysLeft = 14; 
            document.getElementById('stock-metric').innerText = daysLeft + " Days";
            
            new Chart(document.getElementById('stockChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Remaining', 'Depleted'],
                    datasets: [{
                        data: [30, 70],
                        backgroundColor: ['#f59e0b', '#e2e8f0']
                    }]
                },
                options: { cutout: '70%', responsive: true, maintainAspectRatio: false }
            });

            // --- 3. POST-DISASTER IMPACT TREND ---
            const endedDisasters = dataStore.disasters.filter(d => {
                const status = d.status || d.Status || '';
                return (status.toLowerCase() === 'ended' || status.toLowerCase() === 'closed' || status.toLowerCase() === 'completed');
            });

            let impElderly = 0, impBaby = 0, impDisabled = 0, impNormal = 0;
            dataStore.impactData = []; 

            endedDisasters.forEach(disaster => {
                const victimsInDisaster = dataStore.victims.filter(v => v.disaster_id == disaster.disaster_id);
                let dElderly = 0, dBaby = 0, dDisabled = 0, dNormal = 0;
                
                victimsInDisaster.forEach(v => {
                    let isVulnerable = false;
                    // Check logic for 't' or true
                    if(v.has_elderly === 't' || v.has_elderly === true) { impElderly++; dElderly++; isVulnerable = true; }
                    if(v.has_baby === 't' || v.has_baby === true) { impBaby++; dBaby++; isVulnerable = true; }
                    if(v.has_disabled === 't' || v.has_disabled === true) { impDisabled++; dDisabled++; isVulnerable = true; }
                    
                    if(!isVulnerable) { 
                        impNormal++; 
                        dNormal++; 
                    }
                });

                let rawLoc = disaster.location || disaster.Location || disaster.district || disaster.District || 'Unknown';
                // Handle various name properties from potential API differences
                let rawName = disaster.name || disaster.Name || disaster.disaster_name || disaster.DisasterName || 'Unknown Disaster';

                dataStore.impactData.push({
                    'Disaster Name': rawName,
                    'Location': standardizeLocation(rawLoc),
                    'Date Ended': disaster.end_date || disaster.date || 'N/A', 
                    'Total Victims': victimsInDisaster.length,
                    'Elderly': dElderly,
                    'Infants': dBaby,
                    'Disabled': dDisabled,
                    'Normal': dNormal
                });
            });

            const totalEndedVictims = dataStore.victims.filter(v => endedDisasters.some(d => d.disaster_id == v.disaster_id)).length;
            document.getElementById('impact-metric').innerText = totalEndedVictims + " Victims";

            new Chart(document.getElementById('impactChart'), {
                type: 'bar',
                data: {
                    labels: ['Elderly', 'Infants', 'Disabled', 'Normal'],
                    datasets: [{
                        label: 'Total Affected Count',
                        data: [impElderly, impBaby, impDisabled, impNormal],
                        backgroundColor: ['#a78bfa', '#c4b5fd', '#ddd6fe', '#60a5fa'], // Violet shades + Blue for Normal
                        borderColor: ['#8b5cf6', '#8b5cf6', '#8b5cf6', '#2563eb'],
                        borderWidth: 1
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } } 
                }
            });

            // 4. Vulnerability Chart (Pie) - GLOBAL
            let elderly = 0, baby = 0, disabled = 0;
            dataStore.victims.forEach(v => {
                if(v.has_elderly === 't' || v.has_elderly === true) elderly++;
                if(v.has_baby === 't' || v.has_baby === true) baby++;
                if(v.has_disabled === 't' || v.has_disabled === true) disabled++;
            });
            document.getElementById('vuln-metric').innerText = (elderly + baby + disabled);

            new Chart(document.getElementById('vulnChart'), {
                type: 'pie',
                data: {
                    labels: ['Elderly', 'Infants', 'Disabled'],
                    datasets: [{
                        data: [elderly, baby, disabled],
                        backgroundColor: ['#ec4899', '#f472b6', '#fbcfe8']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
            });
        }

        // --- SORT FUNCTION ---
        function sortData(key) {
            // Toggle direction if clicking same key
            if (currentSort.key === key) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.key = key;
                currentSort.direction = 'asc';
            }

            // Sort currentViewData
            currentViewData.sort((a, b) => {
                let valA = a[key];
                let valB = b[key];

                // Handle numbers vs strings
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });

            // Re-render based on currentViewType
            if (currentViewType === 'risk') renderRiskTable();
            else if (currentViewType === 'stock') renderStockTable();
            else if (currentViewType === 'impact') renderImpactTable();
            else if (currentViewType === 'vulnerability') renderVulnerabilityTable();
            else if (currentViewType === 'victimlist') renderVictimListTable();
        }

        // --- RENDER HELPERS ---
        function renderRiskTable() {
            const tbody = document.querySelector('#detail-table-body');
            if(!tbody) return;
            tbody.innerHTML = currentViewData.length > 0 ? currentViewData.map(item => {
                let level = item.count > 5 ? '<span style="color:#ef4444; font-weight:bold;">Critical</span>' : 
                            item.count > 2 ? '<span style="color:#f59e0b; font-weight:bold;">High</span>' : 
                            '<span style="color:#10b981;">Moderate</span>';
                return `<tr><td>${item.loc}</td><td>${item.count} incidents</td><td>${level}</td></tr>`;
            }).join('') : '<tr><td colspan="3">No location data available.</td></tr>';
        }

        function renderStockTable() {
            const tbody = document.querySelector('#detail-table-body');
            if(!tbody) return;
            tbody.innerHTML = currentViewData.map(r => {
                // Use correct property names based on dataStore structure
                let name = r.name || r.Item || 'Unknown Item';
                let q = parseInt(r.quantity || r.Qty || 0);
                let loc = r.location || r.Warehouse || 'Unknown Location';
                
                let status = q < 20 ? '<span class="badge badge-critical">Low</span>' : '<span class="badge badge-active">Healthy</span>';
                return `<tr><td>${name}</td><td>${q} units</td><td>${loc}</td><td>${status}</td></tr>`;
            }).join('');
        }

        function renderImpactTable() {
            const tbody = document.querySelector('#detail-table-body');
            if(!tbody) return;
            tbody.innerHTML = currentViewData.map(d => `
                <tr>
                    <td>${d['Disaster Name']}</td>
                    <td>${d['Location']}</td>
                    <td><strong>${d['Total Victims']}</strong></td>
                    <td style="color:#a78bfa; font-weight:bold;">${d['Elderly']}</td>
                    <td style="color:#c4b5fd; font-weight:bold;">${d['Infants']}</td>
                    <td style="color:#ddd6fe; font-weight:bold;">${d['Disabled']}</td>
                    <td style="color:#60a5fa; font-weight:bold;">${d['Normal']}</td>
                    <td>
                        <button class="btn-primary btn-sm" onclick="showVictimList('${d['Disaster Name']}')" style="background-color: #4b5563; font-size: 10px; padding: 4px 8px;">
                            View Names
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function renderVulnerabilityTable() {
            const tbody = document.querySelector('#detail-table-body');
            if(!tbody) return;
            tbody.innerHTML = currentViewData.map(v => {
                let needs = [];
                if(v.has_elderly === 't') needs.push("Elderly Care");
                if(v.has_baby === 't') needs.push("Infant Care");
                if(v.has_disabled === 't') needs.push("Disability Support");
                return `<tr><td>${v.full_name || v.Name}</td><td>${v.address || v.Address}</td><td><span style="color:#ec4899; font-weight:bold;">${needs.join(', ')}</span></td></tr>`;
            }).join('');
        }

        function renderVictimListTable() {
            const tbody = document.querySelector('#detail-table-body');
            if(!tbody) return;
            tbody.innerHTML = currentViewData.map(v => {
                let categories = [];
                if(v.has_elderly === 't' || v.has_elderly === true) categories.push('Elderly');
                if(v.has_baby === 't' || v.has_baby === true) categories.push('Infant');
                if(v.has_disabled === 't' || v.has_disabled === true) categories.push('Disabled');
                if(categories.length === 0) categories.push('Normal');
                
                return `<tr>
                    <td><strong>${v.full_name || v.Name || 'Unknown Name'}</strong></td>
                    <td>${categories.join(', ')}</td>
                    <td>${v.address || v.Address || v.phone || v.Contact || '-'}</td>
                </tr>`;
            }).join('');
        }


        // --- INLINE DETAIL LOGIC (Replaces Modal) ---
        function openFullDetail(type) {
            currentViewType = type; 
            const container = document.getElementById('inline-detail-container');
            const titleEl = document.getElementById('detail-view-title');
            const bodyEl = document.getElementById('detail-view-body');
            
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            let content = '';
            
            if (type === 'risk') {
                titleEl.innerHTML = '<i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> High Risk Analysis';
                
                const locCounts = {};
                dataStore.disasters.forEach(d => {
                    let rawLoc = d.location || d.Location || d.district || d.District || 'Unknown';
                    if (rawLoc !== 'Unknown') {
                        const loc = standardizeLocation(rawLoc);
                        locCounts[loc] = (locCounts[loc] || 0) + 1;
                    }
                });
                
                // Prepare data for sorting
                currentViewData = Object.entries(locCounts).map(([loc, count]) => ({ loc, count }));
                // Default sort by count desc
                currentViewData.sort((a,b) => b.count - a.count);

                let recommendationLoc = currentViewData[0]?.loc || 'the most active district';

                content = `
                    <div class="detail-section">
                        <h3><i class="fas fa-exclamation-circle"></i> Historical Disaster Frequency</h3>
                        <p>This report aggregates all historical disaster data to identify high-frequency zones.</p>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th onclick="sortData('loc')" style="cursor:pointer;">Location / District <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('count')" style="cursor:pointer;">Incident Count <i class="fas fa-sort"></i></th>
                                    <th>Risk Level</th>
                                </tr>
                            </thead>
                            <tbody id="detail-table-body">
                                <!-- Data injected via render helper -->
                            </tbody>
                        </table>
                    </div>
                    <div class="detail-section">
                        <h3>Strategy Recommendation</h3>
                        <p>Focus resource allocation on the top 3 critical zones. Establish permanent supply depots in <strong>${recommendationLoc}</strong>.</p>
                    </div>
                `;
                
                bodyEl.innerHTML = content;
                renderRiskTable();
            } 
            else if (type === 'stock') {
                titleEl.innerHTML = '<i class="fas fa-boxes" style="color: #f59e0b;"></i> Inventory Health Report';
                
                currentViewData = [...dataStore.resources]; 

                content = `
                    <div class="detail-section">
                        <h3><i class="fas fa-cubes"></i> Current Stock Levels</h3>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th onclick="sortData('name')" style="cursor:pointer;">Resource Name <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('quantity')" style="cursor:pointer;">Quantity <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('location')" style="cursor:pointer;">Location <i class="fas fa-sort"></i></th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="detail-table-body">
                                <!-- Rendered via helper -->
                            </tbody>
                        </table>
                    </div>
                `;
                bodyEl.innerHTML = content;
                renderStockTable();
            }
            else if (type === 'impact') {
                titleEl.innerHTML = '<i class="fas fa-history" style="color: #8b5cf6;"></i> Post-Disaster Impact Report';
                
                currentViewData = [...dataStore.impactData]; 

                content = `
                    <div class="detail-section" style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <h3><i class="fas fa-chart-bar"></i> Impact by Disaster</h3>
                            <p>Analysis of vulnerable populations in recently ended events.</p>
                        </div>
                    </div>
                    <div class="detail-section">
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th onclick="sortData('Disaster Name')" style="cursor:pointer;">Disaster Name <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Location')" style="cursor:pointer;">Location <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Total Victims')" style="cursor:pointer;">Total Victims <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Elderly')" style="cursor:pointer;">Elderly <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Infants')" style="cursor:pointer;">Infants <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Disabled')" style="cursor:pointer;">Disabled <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('Normal')" style="cursor:pointer;">Normal <i class="fas fa-sort"></i></th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="detail-table-body">
                                <!-- Rendered via helper -->
                            </tbody>
                        </table>
                    </div>
                `;
                bodyEl.innerHTML = content;
                renderImpactTable();
            }
            else if (type === 'vulnerability') {
                titleEl.innerHTML = '<i class="fas fa-user-nurse" style="color: #ec4899;"></i> Vulnerability Index';
                
                currentViewData = dataStore.victims.filter(v => 
                    v.has_elderly === 't' || v.has_baby === 't' || v.has_disabled === 't'
                );
                
                content = `
                    <div class="detail-section">
                        <h3><i class="fas fa-notes-medical"></i> High Priority Individuals</h3>
                        <p>Total: <strong>${currentViewData.length}</strong> identified vulnerable individuals.</p>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th onclick="sortData('full_name')" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                                    <th onclick="sortData('address')" style="cursor:pointer;">Address <i class="fas fa-sort"></i></th>
                                    <th>Specific Needs</th>
                                </tr>
                            </thead>
                            <tbody id="detail-table-body">
                                <!-- Rendered via helper -->
                            </tbody>
                        </table>
                    </div>
                `;
                bodyEl.innerHTML = content;
                renderVulnerabilityTable();
            }
        }

        function closeDetailView() {
            document.getElementById('inline-detail-container').style.display = 'none';
        }

        // --- DRILL DOWN: SHOW VICTIM NAMES ---
        function showVictimList(disasterName) {
            currentViewType = 'victimlist'; 
            
            const bodyEl = document.getElementById('detail-view-body');
            const titleEl = document.getElementById('detail-view-title');
            
            const targetDisasters = dataStore.disasters.filter(d => (d.name || d.Name || d.disaster_name || d.DisasterName) === disasterName);
            const targetIds = targetDisasters.map(d => d.disaster_id);
            
            currentViewData = dataStore.victims.filter(v => targetIds.includes(v.disaster_id)); 

            titleEl.innerHTML = `<i class="fas fa-user-injured"></i> Victims in: ${disasterName}`;
            
            let html = `
                <div class="detail-section">
                    <button class="btn-primary" onclick="openFullDetail('impact')" style="background-color: #64748b; margin-bottom: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Report
                    </button>
                    
                    <h3>Victim List (${currentViewData.length})</h3>
                    <table class="detail-table">
                        <thead>
                            <tr>
                                <th onclick="sortData('full_name')" style="cursor:pointer;">Name <i class="fas fa-sort"></i></th>
                                <th>Status/Category</th>
                                <th onclick="sortData('address')" style="cursor:pointer;">Contact/Address <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody id="detail-table-body">
                            <!-- Rendered via helper -->
                        </tbody>
                    </table>
                </div>
            `;
            
            bodyEl.innerHTML = html;
            renderVictimListTable(); 
        }

        // --- EXPORT FUNCTION ---
        function exportCurrentViewData() {
            // Exports whatever is currently in currentViewData
            // This makes it generic for ANY open detail view
            if (!currentViewData || currentViewData.length === 0) {
                alert("No data available to export.");
                return;
            }

            const headers = Object.keys(currentViewData[0]);
            const csvRows = [
                headers.join(','),
                ...currentViewData.map(row => headers.map(fieldName => JSON.stringify(row[fieldName])).join(','))
            ];

            const csvContent = "data:text/csv;charset=utf-8," + csvRows.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            
            let filename = currentViewType ? `${currentViewType}_trend_export.csv` : 'trend_export.csv';
            link.setAttribute("download", filename);
            document.body.appendChild(link);

            link.click();
            document.body.removeChild(link);
            
            // Log based on current view
            logReportToDB(currentViewType || 'General Trend');
        }

        async function logReportToDB(type) {
            try {
                const payload = {
                    report_type: type + ' Export',
                    generated_by: 'Admin User',
                    description: `User exported ${type} trend analysis.`
                };
                await fetch(API_REPORTS, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            } catch (error) {
                console.error("Failed to log report:", error);
            }
        }
    </script>
</body>
</html>