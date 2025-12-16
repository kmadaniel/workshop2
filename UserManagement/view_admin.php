<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Database connection
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if($conn === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Query to get all admins
$sql = "SELECT AdminID, FullName, Email, PasswordHash, Phone, Role, CreatedAt FROM dbo.Admin ORDER BY CreatedAt DESC";
$stmt = sqlsrv_query($conn, $sql);

if($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fetch all admin data
$admins = array();
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $admins[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Admins - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #343a40;
            padding: 20px;
            position: fixed;
        }
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 8px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: #495057;
            color: white;
        }
        .sidebar a.active {
            background: #0d6efd;
            color: white;
        }
        .sidebar h4 {
            color: white;
            padding-bottom: 15px;
            border-bottom: 2px solid #495057;
            margin-bottom: 20px;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: #343a40;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        .badge-superadmin {
            background-color: #dc3545;
        }
        .badge-admin {
            background-color: #0d6efd;
        }
        .action-buttons .btn {
            padding: 5px 10px;
            margin: 0 3px;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        .password-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .avatar {
            width: 35px;
            height: 35px;
            background: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        .swal2-popup {
            border-radius: 15px !important;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="view_admin.php" class="active"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="admin_manage_ngo.php"><i class="fas fa-building me-2"></i>View NGO Register</a>
        <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php"><i class="fas fa-list me-2"></i>View News</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Admin Management</h2>
                <p class="text-muted">View and manage all system administrators</p>
            </div>
            <div>
                <span class="badge bg-info fs-6 p-2">Logged in as: <?php echo $_SESSION['name']; ?></span>
            </div>
        </div>

        <!-- Warning Alert -->
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Security Note:</strong> Some passwords appear in plaintext. Please ensure all passwords are properly hashed.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Admin List Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Admin List</h5>
                <a href="add_admin.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus me-1"></i> Add New Admin
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Password Hash</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($admins)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-user-slash fa-2x mb-3"></i><br>
                                        No admin users found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($admins as $admin): ?>
                                <tr>
                                    <td><strong>#<?php echo $admin['AdminID']; ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar">
                                                <?php echo strtoupper(substr($admin['FullName'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($admin['FullName']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                                    <td class="password-cell" title="<?php echo htmlspecialchars($admin['PasswordHash']); ?>">
                                        <code class="bg-light p-1 rounded">
                                            <?php 
                                            $password = $admin['PasswordHash'];
                                            echo strlen($password) > 20 ? substr($password, 0, 20).'...' : $password;
                                            ?>
                                        </code>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['Phone']); ?></td>
                                    <td>
                                        <?php 
                                        $roleClass = ($admin['Role'] == 'SuperAdmin') ? 'badge-superadmin' : 'badge-admin';
                                        ?>
                                        <span class="badge <?php echo $roleClass; ?> p-2">
                                            <?php echo htmlspecialchars($admin['Role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if($admin['CreatedAt'] instanceof DateTime) {
                                            echo $admin['CreatedAt']->format('Y-m-d H:i:s');
                                        } else {
                                            echo date('Y-m-d H:i:s', strtotime($admin['CreatedAt']));
                                        }
                                        ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="edit_admin.php?id=<?php echo $admin['AdminID']; ?>" 
                                           class="btn btn-warning btn-sm" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-danger btn-sm delete-btn" 
                                                data-id="<?php echo $admin['AdminID']; ?>"
                                                data-name="<?php echo htmlspecialchars($admin['FullName']); ?>"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Auto-dismiss alert after 5 seconds
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);

        // Delete confirmation with SweetAlert
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const adminId = this.getAttribute('data-id');
                const adminName = this.getAttribute('data-name');
                
                Swal.fire({
                    title: 'Delete Admin?',
                    html: `<div style="text-align: center;">
                              <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                              <p>Are you sure you want to delete admin <strong>"${adminName}"</strong>?</p>
                              <div class="alert alert-warning mt-2 mb-0">
                                  <i class="fas fa-exclamation-circle me-2"></i>
                                  This action cannot be undone.
                              </div>
                           </div>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: '<i class="fas fa-trash me-2"></i>Delete',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                    width: '450px',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-4',
                        confirmButton: 'btn-lg',
                        cancelButton: 'btn-lg'
                    },
                    buttonsStyling: false,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Deleting...',
                            text: 'Please wait while we delete the admin',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Redirect to delete script
                        window.location.href = `delete_admin.php?id=${adminId}`;
                    }
                });
            });
        });

        // Check for delete success/failure message in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const deleteStatus = urlParams.get('delete');
            
            if(deleteStatus === 'success') {
                Swal.fire({
                    title: 'Success!',
                    text: 'Admin has been deleted successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6',
                    timer: 3000
                });
                
                // Remove parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if(deleteStatus === 'error') {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete admin. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
                
                // Remove parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>