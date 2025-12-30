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

// Query to get all news
$sql = "SELECT NewsID, Title, Description, ImageURL, CreatedAt, CreatedBy 
        FROM dbo.News 
        ORDER BY CreatedAt DESC";
$stmt = sqlsrv_query($conn, $sql);

if($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

// Fetch all news data
$news = array();
while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $news[] = $row;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View News - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .news-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .news-card:hover {
            transform: translateY(-5px);
        }
        .news-img {
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .news-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-desc {
            font-size: 0.9rem;
            color: #666;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .badge-new {
            background-color: #dc3545;
        }
        .badge-old {
            background-color: #6c757d;
        }
    </style>
</head>
<body>

    <!-- Sidebar with News Menu -->
    <div class="sidebar">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <hr style="border-color: #495057; margin: 15px 0;">
        <a href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a href="admin_profile.php"><i class="fas fa-user me-2"></i>Profile</a>
        <a href="add_admin.php"><i class="fas fa-plus-circle me-2"></i>Add Admin</a>
        <a href="view_admin.php"><i class="fas fa-users me-2"></i>View Admins</a>
        <a href="view_ngo.php"><i class="fas fa-building me-2"></i>View NGO Register</a>
        <a href="report.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <!-- News Management Links -->
        <a href="create_news.php"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php" class="active"><i class="fas fa-list me-2"></i>View News</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">News Management</h2>
                <p class="text-muted">View and manage all published news articles</p>
            </div>
            <div>
                <span class="badge bg-info fs-6 p-2">Logged in as: <?php echo $_SESSION['name']; ?></span>
                <a href="create_news.php" class="btn btn-primary ms-2">
                    <i class="fas fa-plus me-1"></i> Create News
                </a>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-newspaper fa-2x mb-2"></i>
                        <h5>Total News</h5>
                        <h3><?php echo count($news); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                        <h5>Today's News</h5>
                        <h3>
                            <?php
                            $today = date('Y-m-d');
                            $todayCount = 0;
                            foreach($news as $item) {
                                $newsDate = $item['CreatedAt'] instanceof DateTime 
                                    ? $item['CreatedAt']->format('Y-m-d') 
                                    : date('Y-m-d', strtotime($item['CreatedAt']));
                                if($newsDate == $today) {
                                    $todayCount++;
                                }
                            }
                            echo $todayCount;
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-image fa-2x mb-2"></i>
                        <h5>With Images</h5>
                        <h3>
                            <?php
                            $withImages = 0;
                            foreach($news as $item) {
                                if(!empty($item['ImageURL'])) {
                                    $withImages++;
                                }
                            }
                            echo $withImages;
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h5>Authors</h5>
                        <h3>
                            <?php
                            $authors = array();
                            foreach($news as $item) {
                                $authors[$item['CreatedBy']] = true;
                            }
                            echo count($authors);
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- News List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All News Articles</h5>
                <span class="badge bg-dark"><?php echo count($news); ?> articles</span>
            </div>
            <div class="card-body">
                <?php if(empty($news)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
                        <h4>No News Found</h4>
                        <p class="text-muted">Start by creating your first news article</p>
                        <a href="create_news.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create First News
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($news as $item): 
                            $isNew = false;
                            $newsDate = $item['CreatedAt'] instanceof DateTime 
                                ? $item['CreatedAt']->format('Y-m-d') 
                                : date('Y-m-d', strtotime($item['CreatedAt']));
                            if($newsDate == date('Y-m-d')) {
                                $isNew = true;
                            }
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="card news-card h-100">
                                <?php if(!empty($item['ImageURL'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['ImageURL']); ?>" 
                                         class="card-img-top news-img" 
                                         alt="<?php echo htmlspecialchars($item['Title']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top news-img bg-secondary d-flex align-items-center justify-content-center">
                                        <i class="fas fa-newspaper fa-3x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge <?php echo $isNew ? 'badge-new' : 'badge-old'; ?>">
                                            <?php echo $isNew ? 'NEW' : 'Posted'; ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php 
                                            if($item['CreatedAt'] instanceof DateTime) {
                                                echo $item['CreatedAt']->format('M d, Y');
                                            } else {
                                                echo date('M d, Y', strtotime($item['CreatedAt']));
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <h5 class="news-title"><?php echo htmlspecialchars($item['Title']); ?></h5>
                                    <p class="news-desc mt-2"><?php echo htmlspecialchars(substr($item['Description'], 0, 150)); ?>...</p>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($item['CreatedBy']); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between">
                                        <a href="edit_news.php?id=<?php echo $item['NewsID']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <a href="delete_news.php?id=<?php echo $item['NewsID']; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Delete this news article?')">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Check for add success message
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const addStatus = urlParams.get('add');
            
            if(addStatus === 'success') {
                // Create success alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
                alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; max-width: 300px;';
                alertDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Success!</strong> News created successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(alertDiv);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
                
                // Remove parameter from URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>