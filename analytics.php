<?php
// analytics.php
require_once 'report_config.php';

$database = new Database();
$db = $database->getConnection();

// 1. Fetch Distribution Status Counts
// Note: Adapting queries to be compatible with standard SQL (Postgres/MySQL)
$queryStatus = "SELECT status, COUNT(*) as count FROM distribution GROUP BY status";
$stmt = $db->prepare($queryStatus);
$stmt->execute();
$statusData = $stmt->fetchAll();

// Prepare data for Chart.js
$statusLabels = [];
$statusCounts = [];
foreach ($statusData as $row) {
    $statusLabels[] = ucfirst($row['status']); // Capitalize first letter
    $statusCounts[] = $row['count'];
}

// 2. Fetch Resource Availability
$queryResources = "SELECT name, quantity_available FROM resource LIMIT 10";
$stmt = $db->prepare($queryResources);
$stmt->execute();
$resourceData = $stmt->fetchAll();

$resourceLabels = [];
$resourceQuantities = [];
foreach ($resourceData as $row) {
    $resourceLabels[] = $row['name'];
    $resourceQuantities[] = $row['quantity_available'];
}

// 3. Fetch Distributions per Month (Trend Analysis)
// PostgreSQL uses TO_CHAR, MySQL uses DATE_FORMAT. 
// We will use a generic approach or specific Postgres syntax here.
$queryTrend = "SELECT TO_CHAR(date, 'YYYY-MM') as month, COUNT(*) as count 
               FROM distribution 
               GROUP BY TO_CHAR(date, 'YYYY-MM') 
               ORDER BY month ASC LIMIT 6";
try {
    $stmt = $db->prepare($queryTrend);
    $stmt->execute();
    $trendData = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback if table is empty or syntax error
    $trendData = []; 
}

$trendLabels = [];
$trendCounts = [];
foreach ($trendData as $row) {
    $trendLabels[] = $row['month'];
    $trendCounts[] = $row['count'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Disaster Relief</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .card { margin-bottom: 20px; border: none; shadow: 0 0 10px rgba(0,0,0,0.1); }
        .card-header { background-color: #fff; border-bottom: 1px solid #eee; font-weight: bold; }
        .sidebar { min-height: 100vh; background-color: #343a40; color: white; padding-top: 20px; }
        .sidebar a { color: #cfd2d6; text-decoration: none; display: block; padding: 10px 20px; }
        .sidebar a:hover { background-color: #495057; color: white; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <h4 class="text-center">Relief System</h4>
            <hr>
            <a href="reports.php">📑 Reports</a>
            <a href="analytics.php" style="background-color: #495057; color: white;">📊 Analytics</a>
            <a href="#">🏠 Main Dashboard</a>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <h2 class="mb-4">Data Analytics & Insights</h2>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Total Distributions</h5>
                            <p class="card-text display-4"><?php echo array_sum($statusCounts); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Resources Tracked</h5>
                            <p class="card-text display-4"><?php echo count($resourceLabels); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending Requests</h5>
                            <!-- Logic to find pending count -->
                            <?php 
                                $pendingIndex = array_search('Pending', $statusLabels);
                                $pendingCount = ($pendingIndex !== false) ? $statusCounts[$pendingIndex] : 0;
                            ?>
                            <p class="card-text display-4"><?php echo $pendingCount; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Status Chart -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Distribution Status Breakdown</div>
                        <div class="card-body">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Resource Chart -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">Top Resource Availability</div>
                        <div class="card-body">
                            <canvas id="resourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Timeline Chart -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">Distribution Activity (Monthly)</div>
                        <div class="card-body">
                            <canvas id="trendChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // 1. Status Chart (Doughnut)
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($statusLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($statusCounts); ?>,
                backgroundColor: ['#ffc107', '#28a745', '#17a2b8', '#dc3545'],
            }]
        }
    });

    // 2. Resource Chart (Bar)
    const ctxResource = document.getElementById('resourceChart').getContext('2d');
    new Chart(ctxResource, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($resourceLabels); ?>,
            datasets: [{
                label: 'Quantity Available',
                data: <?php echo json_encode($resourceQuantities); ?>,
                backgroundColor: '#007bff'
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 3. Trend Chart (Line)
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Distributions',
                data: <?php echo json_encode($trendCounts); ?>,
                borderColor: '#6610f2',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true, suggestedMax: 10 }
            }
        }
    });
</script>

</body>
</html>