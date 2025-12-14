<?php
session_start();

// --- SECURITY CHECK ---
if(!isset($_SESSION['name']) || $_SESSION['role'] != "ngo"){
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'connection.php';

// --- HANDLE FORM SUBMISSION ---
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $created_by = $_POST['created_by'];
    
    // Handle image upload
    $image_url = NULL;
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/news/";
        if(!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        
        if(in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // Check if image file is actual image
            $check = getimagesize($_FILES['image']['tmp_name']);
            if($check !== false) {
                if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                } else {
                    $error_message = "Failed to upload image.";
                }
            } else {
                $error_message = "File is not a valid image.";
            }
        } else {
            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }
    
    // Insert into database (only if no error)
    if(!isset($error_message)) {
        $sql = "INSERT INTO [UserManagement].[dbo].[News] 
                (Title, Description, ImageURL, CreatedBy, CreatedAt) 
                VALUES (?, ?, ?, ?, GETDATE())";
        
        $params = array($title, $description, $image_url, $created_by);
        
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        
        if(sqlsrv_execute($stmt)) {
            $success_message = "News created successfully!";
            
            // Clear form values after successful submission
            unset($_POST);
            
            // Redirect to same page to show success message
            header("Location: create_news.php?success=1");
            exit();
        } else {
            $errors = sqlsrv_errors();
            $error_message = "Database error: " . $errors[0]['message'];
        }
        
        sqlsrv_free_stmt($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create News - NGO Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            background: #f5f5f5;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #1d3557;
            padding: 20px;
        }
        .sidebar h4 {
            color: white;
        }
        .sidebar a {
            display: block;
            padding: 10px;
            margin: 5px 0;
            color: #f1faee;
            text-decoration: none;
            border-radius: 5px;
        }
        .sidebar a:hover {
            background: #457b9d;
        }
        .content {
            flex-grow: 1;
            padding: 30px;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>NGO Panel</h4>
        <hr style="color:white;">

        <a href="ngo_dashboard.php">🏠 Dashboard</a>
        <a href="ngo_profile.php">👤 Profile</a>
        <a href="view_volunteers.php">👥 My Volunteers</a>
        <a href="create_news.php" style="background: #457b9d;">📝 Apply Story Activity</a>
        <a href="post_opportunity.php">📢 Post Opportunity</a>
        <a href="view_opportunities.php">📋 View Opportunities</a>
        <a href="logout.php" class="text-danger">🚪 Logout</a>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📝 Apply Story Activity</h2>
            <a href="news_list.php" class="btn btn-outline-primary">View All News</a>
        </div>
        
        <?php
        // Tampilkan pesan sukses
        if(isset($_GET['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i> News created successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
        
        // Tampilkan error message jika ada
        if(isset($error_message)) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> ' . htmlspecialchars($error_message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
        ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="title" class="form-label">Title *</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           placeholder="Enter news title" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description *</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="4" placeholder="Describe the fire incident or activity..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="image" class="form-label">Image (Optional)</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <small class="text-muted">Upload an image of the fire incident (JPG, PNG, GIF - Max 2MB)</small>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Created By</label>
                        <input type="text" class="form-control" value="<?php echo $_SESSION['name']; ?>" readonly>
                        <input type="hidden" name="created_by" value="<?php echo $_SESSION['name']; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="reset" class="btn btn-secondary me-md-2">Clear Form</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Submit News
                    </button>
                </div>
            </form>
        </div>

        <!-- Display existing news -->
        <div class="mt-5">
            <h4>Recent News</h4>
            <?php
            // Query to get news from database
            $sql = "SELECT NewsID, Title, Description, ImageURL, CreatedBy, CreatedAt 
                    FROM [UserManagement].[dbo].[News] 
                    WHERE CreatedBy = ? 
                    ORDER BY CreatedAt DESC";
            
            $stmt = sqlsrv_prepare($conn, $sql, array(&$_SESSION['name']));
            
            if($stmt && sqlsrv_execute($stmt)) {
                $has_news = false;
                
                echo '<div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Image</th>
                                    <th>Created By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>';
                
                while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $has_news = true;
                    echo "<tr>";
                    echo "<td>" . $row['NewsID'] . "</td>";
                    echo "<td><strong>" . htmlspecialchars($row['Title']) . "</strong></td>";
                    echo "<td>" . substr(htmlspecialchars($row['Description']), 0, 100) . "...</td>";
                    echo "<td>";
                    if($row['ImageURL'] != NULL && file_exists($row['ImageURL'])) {
                        echo '<img src="' . $row['ImageURL'] . '" width="50" height="50" class="rounded">';
                    } else {
                        echo '<span class="badge bg-secondary">No Image</span>';
                    }
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($row['CreatedBy']) . "</td>";
                    echo "<td>" . $row['CreatedAt']->format('Y-m-d H:i:s') . "</td>";
                    echo "</tr>";
                }
                
                echo '</tbody></table></div>';
                
                if(!$has_news) {
                    echo '<div class="alert alert-info">No news created yet. Create your first news above!</div>';
                }
                
                sqlsrv_free_stmt($stmt);
            } else {
                echo '<div class="alert alert-warning">Unable to fetch news data.</div>';
            }
            
            sqlsrv_close($conn);
            ?>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // File size validation
        document.getElementById('image').addEventListener('change', function(e) {
            var file = e.target.files[0];
            var maxSize = 2 * 1024 * 1024; // 2MB
            
            if(file && file.size > maxSize) {
                alert('File size must be less than 2MB');
                e.target.value = '';
            }
        });
    </script>
</body>
</html>