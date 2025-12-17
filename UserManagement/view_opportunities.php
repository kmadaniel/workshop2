<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}

// ================= DATABASE CONNECTION =================
$serverName = "localhost";
$connectionInfo = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if($conn === false){
    die(print_r(sqlsrv_errors(), true));
}

// Get NGO ID
$ngo_id = $_SESSION['user_id'] ?? 0;

// ================= FETCH OPPORTUNITIES WITH VOLUNTEER INFO =================
$sql = "
SELECT 
    o.*,
    -- Count berapa banyak volunteers dah apply
    (SELECT COUNT(*) FROM opportunity_volunteer ov 
     WHERE ov.opportunity_id = o.opportunity_id) as applied_count,
    -- List nama volunteers yang dah apply
    STUFF((
        SELECT ', ' + v.FullName
        FROM opportunity_volunteer ov
        JOIN Volunteer v ON ov.volunteer_id = v.VolunteerID
        WHERE ov.opportunity_id = o.opportunity_id
        FOR XML PATH(''), TYPE
    ).value('.', 'NVARCHAR(MAX)'),1,2,'') AS volunteers_list
FROM opportunity o
WHERE o.ngo_id = ? 
ORDER BY o.created_at DESC";

$params = array($ngo_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false){
    die(print_r(sqlsrv_errors(), true));
}

// Count stats
$total = 0;
$open = 0;
$rows = [];

while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)){
    $rows[] = $row;
    $total++;
    if($row['status'] === 'Open') $open++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Opportunities - VolunteerHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #34495e;
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: #ecf0f1;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar a:hover {
            background: #34495e;
            color: white;
        }
        
        .sidebar a.active {
            background: #3498db;
            color: white;
        }
        
        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-title {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 28px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1c6ea4 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline-primary {
            color: #3498db;
            border: 2px solid #3498db;
            background: transparent;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-primary:hover {
            background: #3498db;
            color: white;
        }
        
        .btn-outline-warning {
            color: #f39c12;
            border: 2px solid #f39c12;
            background: transparent;
        }
        
        .btn-outline-warning:hover {
            background: #f39c12;
            color: white;
        }
        
        .btn-outline-danger {
            color: #e74c3c;
            border: 2px solid #e74c3c;
            background: transparent;
        }
        
        .btn-outline-danger:hover {
            background: #e74c3c;
            color: white;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border-left: 5px solid #3498db;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card h5 {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .stat-card h3 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card small {
            color: #95a5a6;
            font-size: 13px;
        }
        
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            border-bottom: none;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: #f8f9fa;
        }
        
        .table th {
            border-bottom: 2px solid #dee2e6;
            color: #2c3e50;
            font-weight: 600;
            padding: 15px;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .table tbody tr {
            transition: all 0.2s;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%) !important;
        }
        
        .badge.bg-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%) !important;
        }
        
        .no-data {
            padding: 50px 20px;
            text-align: center;
        }
        
        .no-data h5 {
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .no-data p {
            color: #95a5a6;
            margin-bottom: 25px;
        }
        
        .volunteer-list {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stat-card {
                margin-bottom: 20px;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4>NGO Panel</h4>
        <hr style="color:white;">

        <a href="ngo_dashboard.php">🏠 Dashboard</a>
        <a href="ngo_profile.php">👤 Profile</a>
        <a href="ngo_view_volunteer.php">👥 My Volunteers</a>
        <a href="create_news.php">📝 Apply Story Activity</a> 
        <a href="post_opportunity.php">📢 Post Opportunity</a>
        <a href="view_opportunities.php" class="active">📋 View Opportunities</a>
        <a href="distribution.php">📦 Distribution</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="page-title">My Opportunities</h2>
                    <p class="subtitle">Manage your volunteer opportunities</p>
                </div>
                <a href="post_opportunity.php" class="btn-primary">
                    + Post New Opportunity
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h5>Total Opportunities</h5>
                        <h3><?php echo $total; ?></h3>
                        <small>All opportunities you've created</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" style="border-left-color: #2ecc71;">
                        <h5>Active Opportunities</h5>
                        <h3><?php echo $open; ?></h3>
                        <small>Currently open for volunteers</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" style="border-left-color: #9b59b6;">
                        <h5>Logged in as</h5>
                        <h3 style="font-size: 24px;"><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                        <small>NGO Account</small>
                    </div>
                </div>
            </div>

            <!-- Opportunities Table -->
            <div class="main-card">
                <div class="card-header">
                    <h5 class="mb-0">Opportunities List</h5>
                </div>
                
                <div class="table-responsive">
                    <?php if(count($rows) > 0): ?>
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title & Description</th>
                                <th>Location</th>
                                <th>Date</th>
                                <th>Slots</th>
                                <th>Status</th>
                                <th>Volunteers</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach($rows as $row): 
                            // Calculate available slots
                            $total_slots = $row['slots'];
                            $applied_count = $row['applied_count'];
                            $available_slots = $total_slots - $applied_count;
                            
                            // Get volunteer names
                            $volunteers_list = $row['volunteers_list'];
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small class="text-muted"><?php echo substr(htmlspecialchars($row['description']),0,50); ?>...</small>
                                </td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td>
                                    <?php
                                    if($row['event_date'] instanceof DateTime){
                                        echo $row['event_date']->format('d/m/Y');
                                    } else if($row['event_date']) {
                                        echo date('d/m/Y', strtotime($row['event_date']));
                                    } else {
                                        echo '<span class="text-muted">No date</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $available_slots; ?></strong> / <?php echo $total_slots; ?> available
                                    </div>
                                    <div class="progress" style="height: 6px; margin-top: 5px;">
                                        <div class="progress-bar <?php echo $available_slots > 0 ? 'bg-success' : 'bg-danger'; ?>" 
                                             style="width: <?php echo ($applied_count / $total_slots) * 100; ?>%">
                                        </div>
                                    </div>
                                    <small class="text-muted" style="font-size: 11px;">
                                        <?php echo $applied_count; ?> assigned
                                    </small>
                                </td>
                                <td>
                                    <?php if($row['status'] === 'Open'): ?>
                                        <span class="badge bg-success">Open</span>
                                    <?php elseif($row['status'] === 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                    <?php endif; ?>
                                </td>

                                <td class="volunteer-list">
                                    <?php if(!empty($volunteers_list)): ?>
                                        <?php 
                                        $volunteers_array = explode(', ', $volunteers_list);
                                        $first_volunteer = htmlspecialchars($volunteers_array[0]);
                                        ?>
                                        <div>
                                            <strong><?php echo count($volunteers_array); ?> volunteers</strong><br>
                                            <small><?php echo $first_volunteer; ?>
                                            <?php if(count($volunteers_array) > 1): ?>
                                                + <?php echo count($volunteers_array)-1; ?> more
                                            <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No volunteers yet</span>
                                    <?php endif; ?>
                                </td>
                    
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="view_opportunity_details.php?id=<?php echo $row['opportunity_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">View</a>
                                        <a href="edit_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                                           class="btn btn-outline-warning btn-sm">Edit</a>
                                        <a href="delete_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                                           class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this opportunity? This action cannot be undone.')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php else: ?>
                        <div class="no-data">
                            <h5>No opportunities found</h5>
                            <p>You haven't posted any volunteer opportunities yet.</p>
                            <a href="post_opportunity.php" class="btn-primary">Post Your First Opportunity</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Footer -->
            <div class="mt-4 text-center text-muted">
                <small>
                    Showing <?php echo count($rows); ?> opportunity(ies) • 
                    <?php echo $open; ?> open • 
                    <?php echo $total - $open; ?> closed/pending
                </small>
            </div>
        </div>
    </div>

    <script>
        // Add confirmation for all delete links
        document.querySelectorAll('a[href*="delete_opportunity"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if(!confirm('Are you sure you want to delete this opportunity? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // Add hover effect to table rows
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>

</body>
</html>

<?php
sqlsrv_close($conn);
?>