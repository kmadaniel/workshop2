<?php
// Database connection
require_once __DIR__ . '/../distribution_module/config.php'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT * FROM distribution LIMIT 100");
    $distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($distributions);
    $success = true;
} catch(PDOException $e) {
    $success = false;
    $errorMessage = $e->getMessage();
    $distributions = [];
    $total = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Data</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-in-transit {
            background: #cce5ff;
            color: #004085;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-assigned {
            background: #e2e3e5;
            color: #383d41;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Distribution Data</h1>
        <p class="subtitle">Data retrieved directly from database</p>

        <?php if (!$success): ?>
            <div class="error">
                <strong>❌ Database Error:</strong><br>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php elseif ($total > 0): ?>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-label">Total Distributions</div>
                    <div class="stat-number"><?php echo $total; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Records Shown</div>
                    <div class="stat-number"><?php echo $total; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Database Status</div>
                    <div class="stat-number">✓</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Victim ID</th>
                        <th>Disaster ID</th>
                        <th>Resource ID</th>
                        <th>Quantity</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distributions as $dist): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($dist['distribution_id']); ?></strong></td>
                        <td><?php echo htmlspecialchars($dist['victim_id']); ?></td>
                        <td><?php echo htmlspecialchars($dist['disaster_id']); ?></td>
                        <td><?php echo htmlspecialchars($dist['resource_id']); ?></td>
                        <td><?php echo htmlspecialchars($dist['quantity_sent']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($dist['date'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($dist['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($dist['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($dist['comments'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>
            <div class="error">
                <strong>ℹ️ No Data:</strong><br>
                No distribution records found in the database.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>