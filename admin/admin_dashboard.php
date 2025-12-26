<?php
include "../db.php";
$message = "";

// -------------------------
// Handle Disaster Addition / Update / Victim Update
// -------------------------
if (isset($_POST['add_disaster'])) {

    // 1️⃣ Insert into disaster_reports (admin report)
    $stmt = $conn->prepare("
        INSERT INTO disaster_reports 
        (reported_by_email, disaster_type, description, district, severity, status, reported_at)
        VALUES 
        (:reported_by_email, :disaster_type, :description, :district, :severity, :status, NOW())
    ");
    $stmt->execute([
        ':reported_by_email' => 'admin',
        ':disaster_type' => $_POST['disaster_type'],
        ':description' => $_POST['description'],
        ':district' => $_POST['district'],
        ':severity' => $_POST['severity'],
        ':status' => $_POST['status']
    ]);

    // 2️⃣ ALSO insert into disaster table (used by victim & admin management)
    $stmt2 = $conn->prepare("
        INSERT INTO disaster (disaster_name, district, severity, status)
        VALUES (:name, :district, :severity, :status)
    ");
    $stmt2->execute([
        ':name' => $_POST['disaster_type'],
        ':district' => $_POST['district'],
        ':severity' => $_POST['severity'],
        ':status' => $_POST['status']
    ]);

    $message = "✅ Disaster added and activated successfully!";
}



    if (isset($_POST['update_disaster'])) {
        $stmt = $conn->prepare("UPDATE disaster SET status = :status WHERE disaster_id = :id");
        $stmt->execute([
            ':status' => $_POST['status'],
            ':id' => $_POST['disaster_id']
        ]);
        $message = "✅ Disaster status updated!";
    }

    if (isset($_POST['update_victim'])) {
        $stmt = $conn->prepare("
            UPDATE needs
            SET status = :approval_status,
                distribution_id = :distribution_status
            WHERE victim_id = :victim_id
              AND disaster_id = :disaster_id
        ");
        $stmt->execute([
            ':approval_status' => $_POST['approval_status'],
            ':distribution_status' => $_POST['distribution_status'],
            ':victim_id' => $_POST['victim_id'],
            ':disaster_id' => $_POST['disaster_id']
        ]);
        $message = "✅ Victim status updated!";
    }


// Fetch disasters
$disasters = $conn->query("SELECT * FROM disaster ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch victims if a disaster is selected
$selected_disaster_id = $_GET['disaster_id'] ?? null;
$victims = [];
if ($selected_disaster_id) {
    $stmt = $conn->prepare("
        SELECT v.victim_id, v.full_name, v.phone,
               n.status AS approval_status,
               n.distribution_id AS distribution_status
        FROM victim v
        JOIN needs n ON v.victim_id = n.victim_id
        WHERE n.disaster_id = :id
    ");
    $stmt->execute([':id' => $selected_disaster_id]);
    $victims = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f4f6f9; }

/* Header */
.system-header { text-align:center; padding:15px 0; }
.logo-icon { color:#6c63ff; margin-right:10px; }

/* Container */
.container { display:flex; min-height:100vh; }

/* Sidebar */
.sidebar {
    width:220px;
    background:#fff;
    border-right:1px solid #ddd;
    padding:20px;
}
.nav-menu { list-style:none; padding:0; margin:0; }
.nav-item { margin-bottom:10px; }
.nav-link {
    display:flex; align-items:center;
    text-decoration:none; color:#333;
    padding:8px 10px; border-radius:6px;
}
.nav-link i { margin-right:10px; }
.nav-link.active, .nav-link:hover { background:#f0f0ff; }

/* Main content */
.main-content { flex:1; padding:30px; }

/* Messages */
.message { padding:12px 20px; border-radius:8px; margin-bottom:20px; text-align:center; }
.message.success { background:#d4edda; color:#155724; }
.message.error { background:#f8d7da; color:#721c24; }

/* Cards */
.admin-card {
    background:white; border-radius:12px;
    padding:20px; margin-bottom:30px;
    border:1px solid #ccc; box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
.admin-card h3 { margin-top:0; margin-bottom:15px; }

/* Forms */
.admin-form input, .admin-form select, .admin-form button {
    width:100%; padding:8px 10px; margin-bottom:10px; border-radius:6px; border:1px solid #ccc;
    box-sizing:border-box;
}
.admin-form button {
    background:#6c63ff; color:white; border:none; cursor:pointer; transition:0.3s;
}
.admin-form button:hover { background:#574fd6; }

/* Table */
.table-container { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
table th, table td { border:1px solid #ccc; padding:10px; text-align:center; }
table th { background:#6c63ff; color:white; }
table tr:nth-child(even) { background:#f9f9f9; }
.table-select { width:120px; padding:5px; border-radius:6px; border:1px solid #ccc; }
.table-btn { padding:6px 12px; border:none; border-radius:6px; background:#6c63ff; color:white; cursor:pointer; }
.table-btn:hover { background:#574fd6; }
</style>
</head>
<body>

<header class="system-header">
    <i class="fas fa-shield-heart logo-icon" style="font-size:36px;"></i>
    <h1>Melaka Disaster Assistance</h1>
    <small>Admin Dashboard</small>
</header>

<div class="container">

    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="nav-menu">
            <li class="nav-item">
                <a class="nav-link active"><i class="fas fa-house"></i> Dashboard</a>
            </li>
            <!-- Removed Victim Registration & Report Disaster -->
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <?php if($message): ?>
        <div class="message <?= strpos($message,'✅')!==false ? 'success':'error' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>

       

        <!-- ADD NEW DISASTER -->
<div class="admin-card">
    <h3><i class="fas fa-plus-circle"></i> Add New Disaster</h3>
    <form method="POST" class="admin-form">
        <input type="text" name="disaster_type" placeholder="Disaster Type" required>

        <small style="color:#555;">Max 200 characters</small>
        <textarea name="description" placeholder="Description"
            rows="4" maxlength="200"
            style="border:1px solid #ccc; border-radius:6px; padding:8px; width:100%; box-sizing:border-box;"
            required></textarea>

        <input type="text" name="district" placeholder="District" required>

        <select name="severity" required>
            <option value="">-- Select Severity --</option>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
        </select>

        <select name="status" required>
            <option value="Active">Active</option>
            <option value="Under Control">Under Control</option>
            <option value="Ended">Ended</option>
        </select>

        <button name="add_disaster">Add Disaster</button>
    </form>
</div>

<!-- MANAGE EXISTING DISASTERS -->
<div class="admin-card">
    <h3><i class="fas fa-tasks"></i> Manage Existing Disasters</h3>

    <?php if (empty($disasters)): ?>
        <p style="color:#666;">No disasters found.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <tr>
                    <th>Disaster</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($disasters as $d): ?>
                <tr>
                    <form method="POST">
                        <!-- Disaster Name -->
                        <td style="font-weight:bold;">
                            <?= htmlspecialchars($d['disaster_name']) ?>
                        </td>

                        <!-- Status Dropdown -->
                        <td>
                            <select name="status" class="table-select">
                                <option value="Active" <?= $d['status']=='Active'?'selected':'' ?>>Active</option>
                                <option value="Under Control" <?= $d['status']=='Under Control'?'selected':'' ?>>Under Control</option>
                                <option value="Ended" <?= $d['status']=='Ended'?'selected':'' ?>>Ended</option>
                            </select>
                        </td>

                        <!-- Actions -->
                        <td>
                            <input type="hidden" name="disaster_id" value="<?= $d['disaster_id'] ?>">

                            <button name="update_disaster" class="table-btn">
                                Update
                            </button>

                            <a href="?disaster_id=<?= $d['disaster_id'] ?>"
                               class="table-btn"
                               style="text-decoration:none;">
                                View Victims
                            </a>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>




        <!-- Victim Management -->
        <?php if($selected_disaster_id && $victims): ?>
        <div class="admin-card">
            <h3><i class="fas fa-users"></i> Victims for Selected Disaster</h3>
            <div class="table-container">
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Approval Status</th>
                        <th>Distribution Status</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach($victims as $v): ?>
                    <tr>
                        <form method="POST">
                            <td><?= htmlspecialchars($v['full_name']) ?></td>
                            <td><?= $v['phone'] ?></td>
                            <td>
                                <select name="approval_status" class="table-select">
                                    <option value="Pending" <?= $v['approval_status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Approved" <?= $v['approval_status']=='Approved'?'selected':'' ?>>Approved</option>
                                    <option value="Rejected" <?= $v['approval_status']=='Rejected'?'selected':'' ?>>Rejected</option>
                                </select>
                            </td>
                            <td>
                                <select name="distribution_status" class="table-select">
                                    <option value="1" <?= $v['distribution_status']==1?'selected':'' ?>>Pending</option>
                                    <option value="2" <?= $v['distribution_status']==2?'selected':'' ?>>Completed</option>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" name="victim_id" value="<?= $v['victim_id'] ?>">
                                <input type="hidden" name="disaster_id" value="<?= $selected_disaster_id ?>">
                                <button name="update_victim" class="table-btn">Update</button>
                            </td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
