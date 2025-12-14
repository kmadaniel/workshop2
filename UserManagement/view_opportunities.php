<?php
session_start();

// First, check if session exists
if(empty($_SESSION['name']) || empty($_SESSION['role'])) {
    die("Please <a href='login.php'>login first</a>");
}

// Check if role is NGO
if($_SESSION['role'] !== "ngo") {
    die("Access denied. NGO role required.");
}

// Connect to SQL Server
$serverName = "localhost";
$connectionInfo = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123",
    "CharacterSet" => "UTF-8"
);

$conn = sqlsrv_connect($serverName, $connectionInfo);

if($conn === false) {
    die("Database connection failed: " . print_r(sqlsrv_errors(), true));
}

// Get NGO ID
$ngo_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Fetch opportunities
$sql = "SELECT * FROM opportunity WHERE ngo_id = ? ORDER BY created_at DESC";
$params = array($ngo_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if($stmt === false) {
    die("Query failed: " . print_r(sqlsrv_errors(), true));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Opportunities - NGO Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: #1d3557;
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #457b9d;
        }
        .sidebar a.active {
            background: #457b9d;
            font-weight: bold;
        }
        .content {
            padding: 30px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 sidebar">
            <h4 class="text-center mb-4">NGO Panel</h4>
            <a href="ngo_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
            <a href="ngo_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
            <a href="view_volunteers.php"><i class="fas fa-users me-2"></i>Volunteers</a>
            <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>News</a>
            <a href="post_opportunity.php"><i class="fas fa-plus me-2"></i>Post Opportunity</a>
            <a href="view_opportunities.php" class="active"><i class="fas fa-list me-2"></i>My Opportunities</a>
            <hr class="bg-white">
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9 col-lg-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-list-alt text-primary me-2"></i>My Opportunities</h2>
                    <p class="text-muted">Manage your volunteer opportunities</p>
                </div>
                <a href="post_opportunity.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Post New
                </a>
            </div>
            
            <!-- Statistics -->
            <?php
            // Count rows for statistics
            $total = 0;
            $open = 0;
            
            while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $total++;
                if($row['status'] == 'Open') $open++;
            }
            
            // Reset pointer
            sqlsrv_free_stmt($stmt);
            $stmt = sqlsrv_query($conn, $sql, $params);
            ?>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-bullhorn me-2"></i>Total</h5>
                            <h3 class="mb-0"><?php echo $total; ?></h3>
                            <p class="mb-0">Opportunities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Active</h5>
                            <h3 class="mb-0"><?php echo $open; ?></h3>
                            <p class="mb-0">Open Opportunities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-user me-2"></i>User</h5>
                            <h3 class="mb-0"><?php echo htmlspecialchars($_SESSION['name']); ?></h3>
                            <p class="mb-0">Logged in as NGO</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Opportunities Table -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Opportunities List</h5>
                </div>
                <div class="card-body p-0">
                    <?php if(sqlsrv_has_rows($stmt)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Slots</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo substr(htmlspecialchars($row['description']), 0, 50); ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            <?php echo htmlspecialchars($row['location']); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if($row['event_date'] instanceof DateTime) {
                                                echo $row['event_date']->format('d/m/Y');
                                            } else {
                                                echo date('d/m/Y', strtotime($row['event_date']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $row['slots']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($row['status'] == 'Open'): ?>
                                                <span class="badge bg-success">Open</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view_opportunity_details.php?id=<?php echo $row['opportunity_id']; ?>" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                                                   class="btn btn-outline-danger" title="Delete"
                                                   onclick="return confirm('Delete this opportunity?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h4>No Opportunities Yet</h4>
                            <p class="text-muted mb-4">You haven't posted any volunteer opportunities.</p>
                            <a href="post_opportunity.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Post Your First Opportunity
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Info Box -->
            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle me-2"></i>Quick Tips</h5>
                <ul class="mb-0">
                    <li>Click <strong>View</strong> to see full details of an opportunity</li>
                    <li>Click <strong>Edit</strong> to modify an existing opportunity</li>
                    <li>Click <strong>Delete</strong> to remove an opportunity (cannot be undone)</li>
                    <li>Use <strong>Post New</strong> button to create new opportunities</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Simple confirmation for delete
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.btn-outline-danger');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if(!confirm('Are you sure you want to delete this opportunity?')) {
                    e.preventDefault();
                }
            });
        });
    });
</script>

</body>
</html>

<?php 
sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>