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
    <title>View Opportunities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- ================= SIDEBAR (SAMA DASHBOARD - TAK TUKAR) ================= -->
<div class="sidebar" style="width: 250px; height: 100vh; background: #1d3557; padding: 20px; position: fixed;">
    <h4 style="color: white;">NGO Panel</h4>
    <hr style="color:white;">

    <a href="ngo_dashboard.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px;">🏠 Dashboard</a>
    <a href="ngo_profile.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px;">👤 Profile</a>
    <a href="ngo_view_volunteer.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px;">👥 My Volunteers</a>
    <a href="create_news.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px;">📝 Apply Story Activity</a>
    <a href="post_opportunity.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px;">📢 Post Opportunity</a>
    <a href="view_opportunities.php" style="display: block; padding: 10px; margin: 5px 0; color: #f1faee; text-decoration: none; border-radius: 5px; background: #457b9d;">📋 View Opportunities</a>

    <a href="logout.php" style="display: block; padding: 10px; margin: 5px 0; color: #dc3545; text-decoration: none; border-radius: 5px;">🚪 Logout</a>
</div>

<!-- ================= CONTENT ================= -->
<div class="content" style="flex-grow: 1; padding: 30px; margin-left: 250px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>My Opportunities</h2>
            <p class="text-muted">Manage your volunteer opportunities</p>
        </div>
        <a href="post_opportunity.php" class="btn btn-primary">
            + Post New
        </a>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-3">
                <h5>Total</h5>
                <h3><?php echo $total; ?></h3>
                <small>Opportunities</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <h5>Active</h5>
                <h3><?php echo $open; ?></h3>
                <small>Open Opportunities</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <h5>User</h5>
                <h3><?php echo $_SESSION['name']; ?></h3>
                <small>Logged in as NGO</small>
            </div>
        </div>
    </div>

    <!-- OPPORTUNITIES TABLE -->
    <div class="card">
        <div class="card-header">
            <h5>Opportunities List</h5>
        </div>
        <div class="card-body p-0">

            <?php if(count($rows) > 0): ?>
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
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
                            <small><?php echo substr(htmlspecialchars($row['description']),0,50); ?>...</small>
                        </td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td>
                            <?php
                            if($row['event_date'] instanceof DateTime){
                                echo $row['event_date']->format('d/m/Y');
                            } else {
                                echo date('d/m/Y', strtotime($row['event_date']));
                            }
                            ?>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo $available_slots; ?></strong> / <?php echo $total_slots; ?> available
                            </div>
                            <small class="text-muted">
                                <?php echo $applied_count; ?> assigned
                            </small>
                        </td>
                        <td>
                            <?php if($row['status'] === 'Open'): ?>
                                <span class="badge bg-success">Open</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?php echo $row['status']; ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
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
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
            
                        <td>
                            <a href="view_opportunity_details.php?id=<?php echo $row['opportunity_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="edit_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" class="btn btn-sm btn-outline-warning">Edit</a>
                            <a href="delete_opportunity.php?id=<?php echo $row['opportunity_id']; ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this opportunity?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
                <div class="p-4 text-center">
                    <h5>No opportunities found</h5>
                    <a href="post_opportunity.php" class="btn btn-primary mt-2">Post Opportunity</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

</body>
</html>

<?php
sqlsrv_close($conn);
?>