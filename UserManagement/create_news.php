<?php
session_start();
if(!isset($_SESSION['name']) || $_SESSION['role'] != "admin"){
    header("Location: login.php");
    exit();
}

// Initialize variables
$title = $description = "";
$error = "";
$success = "";
$image_error = "";

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

// Check if form is submitted
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $createdBy = $_SESSION['name'];
    
    // Validation
    if(empty($title) || empty($description)) {
        $error = "Title and Description are required!";
    } else {
        // Handle image upload
        $imageURL = NULL;
        
        if(isset($_FILES['news_image']) && $_FILES['news_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $_FILES['news_image']['type'];
            $file_size = $_FILES['news_image']['size'];
            $file_name = $_FILES['news_image']['name'];
            
            // Check file type
            if(!in_array($file_type, $allowed_types)) {
                $image_error = "Only JPG, JPEG, PNG & GIF files are allowed!";
            }
            // Check file size (max 5MB)
            elseif($file_size > 5 * 1024 * 1024) {
                $image_error = "File size must be less than 5MB!";
            }
            else {
                // Create uploads directory if not exists
                $upload_dir = "uploads/news/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = uniqid() . '_' . date('Ymd_His') . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if(move_uploaded_file($_FILES['news_image']['tmp_name'], $upload_path)) {
                    $imageURL = $upload_path;
                } else {
                    $image_error = "Failed to upload image!";
                }
            }
        }
        
        // Only proceed if no image error (or if no image was uploaded)
        if(empty($image_error)) {
            // Insert news
            $insertSql = "INSERT INTO dbo.News (Title, Description, ImageURL, CreatedAt, CreatedBy) 
                          VALUES (?, ?, ?, GETDATE(), ?)";
            $insertParams = array($title, $description, $imageURL, $createdBy);
            $insertStmt = sqlsrv_query($conn, $insertSql, $insertParams);
            
            if($insertStmt === false) {
                $error = "Failed to create news: " . print_r(sqlsrv_errors(), true);
            } else {
                $success = "News created successfully!";
                // Reset form
                $title = $description = "";
                
                // Redirect to view news page after 2 seconds
                header("refresh:2;url=view_news.php?add=success");
            }
            sqlsrv_free_stmt($insertStmt);
        }
    }
}

sqlsrv_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create News - Admin Panel</title>
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
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .required:after {
            content: " *";
            color: red;
        }
        .image-preview {
            max-width: 300px;
            max-height: 200px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            display: none;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 180px;
        }
        .char-count {
            font-size: 12px;
            color: #6c757d;
        }
        .btn-upload {
            position: relative;
            overflow: hidden;
        }
        .btn-upload input[type=file] {
            position: absolute;
            top: 0;
            right: 0;
            min-width: 100%;
            min-height: 100%;
            font-size: 100px;
            text-align: right;
            filter: alpha(opacity=0);
            opacity: 0;
            outline: none;
            background: white;
            cursor: inherit;
            display: block;
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
        <a href="create_news.php" class="active"><i class="fas fa-newspaper me-2"></i>Create News</a>
        <a href="view_news.php"><i class="fas fa-list me-2"></i>View News</a>
        <hr style="border-color: #495057; margin: 20px 0;">
        <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Create News Article</h2>
                <p class="text-muted">Publish news and announcements to the system</p>
            </div>
            <div>
                <span class="badge bg-info fs-6 p-2">Logged in as: <?php echo $_SESSION['name']; ?></span>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($image_error): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $image_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Create News Form Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>News Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Title -->
                    <div class="mb-4">
                        <label class="form-label required">News Title</label>
                        <input type="text" class="form-control form-control-lg" name="title" 
                               value="<?php echo htmlspecialchars($title); ?>" 
                               placeholder="Enter news title" required>
                        <div class="form-text">Make it catchy and descriptive</div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <label class="form-label required">News Description</label>
                        <textarea class="form-control" name="description" rows="6" 
                                  placeholder="Write the full news content here..." 
                                  required><?php echo htmlspecialchars($description); ?></textarea>
                        <div class="d-flex justify-content-between mt-2">
                            <div class="form-text">Write detailed information about the news</div>
                            <div class="char-count" id="charCount">0 characters</div>
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="form-label">News Image</label>
                        <div class="input-group">
                            <label class="btn btn-outline-primary btn-upload">
                                <i class="fas fa-cloud-upload-alt me-2"></i>Choose Image
                                <input type="file" name="news_image" id="news_image" accept="image/*" style="display: none;">
                            </label>
                            <input type="text" class="form-control" id="file-name" placeholder="No file chosen" readonly>
                            <button type="button" class="btn btn-secondary" onclick="clearImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Optional. Supported formats: JPG, PNG, GIF. Max size: 5MB
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="image-preview mt-3" id="imagePreview">
                            <img id="previewImage" src="#" alt="Image Preview">
                        </div>
                    </div>

                    <!-- Created By Info -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This news will be published under: <strong><?php echo $_SESSION['name']; ?></strong>
                        <br><small>Date: <?php echo date('Y-m-d H:i:s'); ?></small>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="view_news.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>View All News
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-warning me-2">
                                <i class="fas fa-redo me-2"></i>Clear Form
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-paper-plane me-2"></i>Publish News
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Guidelines Card -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i>News Writing Guidelines</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-check-circle text-success me-2"></i>Do's</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-check text-success me-2"></i>
                                Write clear and concise titles
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check text-success me-2"></i>
                                Include relevant images when available
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check text-success me-2"></i>
                                Use proper formatting and paragraphs
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-check text-success me-2"></i>
                                Fact-check information before publishing
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-times-circle text-danger me-2"></i>Don'ts</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-times text-danger me-2"></i>
                                Use misleading or clickbait titles
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-times text-danger me-2"></i>
                                Publish unverified information
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-times text-danger me-2"></i>
                                Upload copyrighted images without permission
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-times text-danger me-2"></i>
                                Include personal opinions as facts
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Character count for description
        const descriptionTextarea = document.querySelector('textarea[name="description"]');
        const charCount = document.getElementById('charCount');
        
        descriptionTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = `${length} characters`;
            
            if(length > 1000) {
                charCount.classList.add('text-danger');
            } else if(length > 500) {
                charCount.classList.add('text-warning');
            } else {
                charCount.classList.remove('text-danger', 'text-warning');
            }
        });
        
        // Initialize character count
        descriptionTextarea.dispatchEvent(new Event('input'));

        // Image preview and file name display
        const newsImageInput = document.getElementById('news_image');
        const fileNameInput = document.getElementById('file-name');
        const imagePreview = document.getElementById('imagePreview');
        const previewImage = document.getElementById('previewImage');
        
        newsImageInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if(file) {
                // Display file name
                fileNameInput.value = file.name;
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                clearImage();
            }
        });
        
        function clearImage() {
            newsImageInput.value = '';
            fileNameInput.value = '';
            imagePreview.style.display = 'none';
            previewImage.src = '#';
        }
        
        // Button click triggers file input
        document.querySelector('.btn-upload').addEventListener('click', function() {
            newsImageInput.click();
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            
            if(!title || !description) {
                e.preventDefault();
                alert('Please fill in all required fields!');
                return false;
            }
            
            if(description.length < 50) {
                if(!confirm('Description seems very short. Are you sure you want to publish?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>