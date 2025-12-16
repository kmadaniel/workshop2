<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// SECURITY CHECK
if (!isset($_SESSION['name']) || $_SESSION['role'] !== "ngo") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

// CURRENT NGO ID
$current_ngo_id = $_SESSION['user_id'];

// FETCH VOLUNTEERS
$sql = "
SELECT 
    VolunteerID,
    FullName,
    Email,
    Phone,
    SkillCategory,
    Status
FROM Volunteer
WHERE AssignedNGO = ?
ORDER BY VolunteerID DESC
";

$params = array($current_ngo_id);
$result = sqlsrv_query($conn, $sql, $params);

// COUNT VOLUNTEERS
$count_sql = "
SELECT COUNT(*) AS total 
FROM Volunteer 
WHERE AssignedNGO = ?
";
$count_result = sqlsrv_query($conn, $count_sql, $params);
$count_row = sqlsrv_fetch_array($count_result, SQLSRV_FETCH_ASSOC);
$total_volunteers = $count_row['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Volunteers</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <style>
        body { display:flex; background:#f5f5f5; min-height:100vh; }
        .sidebar {
            width:250px; background:#1d3557; padding:20px;
            position:fixed; height:100vh;
        }
        .sidebar h4, .sidebar a { color:white; }
        .sidebar a {
            display:block; padding:10px; margin:5px 0;
            text-decoration:none; border-radius:5px;
        }
        .sidebar a:hover { background:#457b9d; }
        .content {
            margin-left:250px; padding:30px; width:100%;
        }
        .table-container {
            background:white; padding:20px;
            border-radius:8px;
            box-shadow:0 2px 10px rgba(0,0,0,.05);
        }
        .stats-card {
            background:linear-gradient(135deg,#457b9d,#1d3557);
            color:white; padding:15px; border-radius:8px;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h4>NGO Panel</h4>
    <hr>
    <a href="ngo_dashboard.php">🏠 Dashboard</a>
    <a href="ngo_profile.php">👤 Profile</a>
    <a href="view_volunteers.php" style="background:#457b9d;">👥 My Volunteers</a>
    <a href="post_opportunity.php">📢 Post Opportunity</a>
    <a href="view_opportunities.php">📋 View Opportunities</a>
    <a href="logout.php" class="text-danger">🚪 Logout</a>
</div>

<!-- CONTENT -->
<div class="content">
    <h2>My Volunteers</h2>
    <p class="text-muted">List of volunteers assigned to your NGO</p>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <h5>Total Volunteers</h5>
                <h3><?= $total_volunteers ?></h3>
            </div>
        </div>
    </div>

    <div class="table-container">
        <?php if ($result): ?>
        <table id="volunteerTable" class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Skills</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['FullName']) ?></td>
                    <td><?= htmlspecialchars($row['Email']) ?></td>
                    <td><?= htmlspecialchars($row['Phone']) ?></td>
                    <td>
                        <?php
                        $skills = explode(',', $row['SkillCategory']);
                        foreach ($skills as $skill) {
                            echo '<span class="badge bg-info me-1">'.htmlspecialchars(trim($skill)).'</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="badge <?= $row['Status']=='Active'?'bg-success':'bg-warning' ?>">
                            <?= htmlspecialchars($row['Status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary viewBtn"
                            data-name="<?= htmlspecialchars($row['FullName']) ?>"
                            data-email="<?= htmlspecialchars($row['Email']) ?>"
                            data-phone="<?= htmlspecialchars($row['Phone']) ?>"
                            data-skills="<?= htmlspecialchars($row['SkillCategory']) ?>"
                            data-status="<?= htmlspecialchars($row['Status']) ?>">
                            View
                        </button>
                        <a href="mailto:<?= htmlspecialchars($row['Email']) ?>" class="btn btn-sm btn-outline-success">Email</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p class="text-muted text-center">No volunteers found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Volunteer Details</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><b>Name:</b> <span id="m_name"></span></p>
        <p><b>Email:</b> <span id="m_email"></span></p>
        <p><b>Phone:</b> <span id="m_phone"></span></p>
        <p><b>Skills:</b> <span id="m_skills"></span></p>
        <p><b>Status:</b> <span id="m_status"></span></p>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function(){
    $('#volunteerTable').DataTable();

    $('.viewBtn').click(function(){
        $('#m_name').text($(this).data('name'));
        $('#m_email').text($(this).data('email'));
        $('#m_phone').text($(this).data('phone'));
        $('#m_status').text($(this).data('status'));

        let skills = $(this).data('skills').split(',');
        let html = '';
        skills.forEach(s => {
            html += '<span class="badge bg-info me-1">'+s.trim()+'</span>';
        });
        $('#m_skills').html(html);

        new bootstrap.Modal('#viewModal').show();
    });
});
</script>

</body>
</html>

<?php sqlsrv_close($conn); ?>
