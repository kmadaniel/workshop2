<?php
// Database connection untuk ambil SEMUA news
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// Get all news
$all_news = array();
if($conn !== false) {
    $sql = "SELECT NewsID, Title, Description, ImageURL, CreatedAt 
            FROM dbo.News 
            ORDER BY CreatedAt DESC";
    $stmt = sqlsrv_query($conn, $sql);
    
    if($stmt !== false) {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $all_news[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - Disaster Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f8f9fa;
        }

        /* NAVBAR (Sama seperti main_page) */
        .navbar {
            width: 100%;
            padding: 20px 60px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            border-bottom: 2px solid #eee;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-left a {
            margin: 0 20px;
            text-decoration: none;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .nav-left a:hover {
            color: #007bff;
        }

        .nav-right a {
            margin-left: 20px;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-login {
            color: #333;
            border: 2px solid #007bff;
        }

        .btn-login:hover {
            background: #007bff;
            color: white;
        }

        .btn-register {
            background: #007bff;
            color: white;
        }

        .btn-register:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        /* HERO BANNER */
        .news-hero {
            height: 300px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding-top: 80px;
            margin-bottom: 50px;
        }

        .news-hero h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .news-hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
        }

        /* NEWS CONTENT */
        .news-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .news-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .news-header h2 {
            font-size: 2rem;
            color: #333;
        }

        .news-count {
            background: #007bff;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .news-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .news-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .news-img {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }

        .no-image {
            height: 200px;
            background: linear-gradient(45deg, #6a11cb 0%, #2575fc 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .news-content {
            padding: 25px;
        }

        .news-category {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .news-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .news-desc {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
           
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .news-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .news-date {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
        }

        .news-date i {
            margin-right: 8px;
            color: #007bff;
        }

        .read-more {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
        }

        .read-more:hover {
            color: #0056b3;
            transform: translateX(5px);
        }

        .read-more i {
            margin-left: 8px;
            transition: transform 0.3s;
        }

        .read-more:hover i {
            transform: translateX(5px);
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 50px 0;
        }

        .page-link {
            padding: 10px 15px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            font-weight: 600;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .page-link.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        /* FOOTER */
        .footer {
            background: #343a40;
            color: white;
            padding: 40px 60px;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .copyright {
            color: #999;
            font-size: 0.9rem;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            
            .nav-left a {
                margin: 0 10px;
                font-size: 14px;
            }
            
            .news-hero h1 {
                font-size: 2rem;
            }
            
            .news-hero {
                height: 250px;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .news-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="main_page.php">HOME</a>
            <a href="news.php" style="color: #007bff;">NEWS</a>
            <a href="#">MAP</a>
            <a href="#">CONTACT</a>
        </div>

        <div class="nav-right">
            <a href="login.php" class="btn-login">Sign in</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- HERO BANNER -->
    <div class="news-hero">
        <h1>Latest News & Updates</h1>
        <p>Stay informed with real-time disaster alerts, relief efforts, and community news</p>
    </div>

    <!-- NEWS CONTENT -->
    <div class="news-container">
        <div class="news-header">
            <h2>All News Articles</h2>
            <span class="news-count"><?php echo count($all_news); ?> Articles</span>
        </div>

        <?php if(!empty($all_news)): ?>
            <div class="news-grid">
                <?php foreach($all_news as $news): 
                    $date = $news['CreatedAt'] instanceof DateTime 
                        ? $news['CreatedAt']->format('M d, Y') 
                        : date('M d, Y', strtotime($news['CreatedAt']));
                    
                    // Determine category from title (simple example)
                    $category = "General";
                    $title_lower = strtolower($news['Title']);
                    if(strpos($title_lower, 'banjir') !== false || strpos($title_lower, 'flood') !== false) {
                        $category = "Flood";
                    } elseif(strpos($title_lower, 'kebakaran') !== false || strpos($title_lower, 'fire') !== false) {
                        $category = "Fire";
                    } elseif(strpos($title_lower, 'gempa') !== false || strpos($title_lower, 'earthquake') !== false) {
                        $category = "Earthquake";
                    }
                ?>
                <div class="news-card">
                    <?php if(!empty($news['ImageURL'])): ?>
                        <img src="<?php echo htmlspecialchars($news['ImageURL']); ?>" 
                             alt="<?php echo htmlspecialchars($news['Title']); ?>" 
                             class="news-img">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-newspaper fa-3x"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="news-content">
                        <span class="news-category"><?php echo $category; ?></span>
                        <h3 class="news-title"><?php echo htmlspecialchars($news['Title']); ?></h3>
                        <p class="news-desc">
                            <?php 
                            $desc = strip_tags($news['Description']);
                            echo htmlspecialchars(substr($desc, 0, 150)) . '...';
                            ?>
                        </p>
                        
                        <div class="news-footer">
                            <div class="news-date">
                                <i class="far fa-calendar-alt"></i> <?php echo $date; ?>
                            </div>
                            <a href="news_detail.php?id=<?php echo $news['NewsID']; ?>" class="read-more">
                                Read More <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-newspaper fa-4x text-muted mb-4"></i>
                <h3>No News Articles Found</h3>
                <p class="text-muted">Check back later for the latest updates</p>
            </div>
        <?php endif; ?>

        <!-- Simple Pagination -->
        <div class="pagination">
            <a href="#" class="page-link active">1</a>
            <a href="#" class="page-link">2</a>
            <a href="#" class="page-link">3</a>
            <a href="#" class="page-link">Next</a>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-links">
            <a href="main_page.php">Home</a>
            <a href="news.php">News</a>
            <a href="#">About</a>
            <a href="#">Contact</a>
            <a href="#">Privacy Policy</a>
        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> Disaster Management System. All rights reserved.
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                navbar.style.padding = '15px 60px';
            } else {
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                navbar.style.padding = '20px 60px';
            }
        });
    </script>
</body>
</html>