<?php
// Database connection untuk ambil news
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// Get latest 3 news
$latest_news = array();
if($conn !== false) {
    $sql = "SELECT TOP 3 NewsID, Title, Description, ImageURL, CreatedAt 
            FROM dbo.News 
            ORDER BY CreatedAt DESC";
    $stmt = sqlsrv_query($conn, $sql);
    
    if($stmt !== false) {
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $latest_news[] = $row;
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
    <title>Main Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* NAVBAR */
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

        /* HERO SECTION */
        .hero {
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding-top: 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.4);
        }

        .hero h2 {
            font-size: 2rem;
            margin-bottom: 30px;
            font-weight: 600;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.4);
        }

        .hero .report-btn {
            margin-top: 20px;
            padding: 15px 40px;
            background: white;
            color: #333;
            font-size: 1.2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .hero .report-btn:hover {
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* LATEST NEWS SECTION */
        .news-section {
            padding: 80px 60px;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .news-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
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

        .news-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .news-date i {
            margin-right: 8px;
            color: #007bff;
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

        .view-all-btn {
            display: block;
            width: 200px;
            margin: 50px auto 0;
            padding: 12px 30px;
            background: #007bff;
            color: white;
            text-align: center;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .view-all-btn:hover {
            background: #0056b3;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
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
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero h2 {
                font-size: 1.5rem;
            }
            
            .news-section {
                padding: 50px 20px;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="main_page.php">HOME</a>
            <a href="news.php">NEWS</a>
            <a href="#">MAP</a>
            <a href="#">CONTACT</a>
        </div>

        <div class="nav-right">
            <a href="login.php" class="btn-login">Sign in</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- HERO SECTION -->
    <div class="hero">
        <div class="hero-content">
            <h1>Welcome to</h1>
            <h2>Disaster Management System</h2>
            <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9;">
                Stay informed, stay prepared. Real-time disaster alerts and management.
            </p>
            <a href="report_incident.php" class="report-btn">
                <i class="fas fa-bullhorn me-2"></i>Report New Incident
            </a>
        </div>
    </div>

    <!-- LATEST NEWS SECTION -->
    <div class="news-section">
        <div class="section-title">
            <h2>Latest News & Updates</h2>
            <p>Stay updated with the latest disaster alerts, relief efforts, and community news</p>
        </div>

        <?php if(!empty($latest_news)): ?>
            <div class="news-grid">
                <?php foreach($latest_news as $news): 
                    $date = $news['CreatedAt'] instanceof DateTime 
                        ? $news['CreatedAt']->format('M d, Y') 
                        : date('M d, Y', strtotime($news['CreatedAt']));
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
                        <div class="news-date">
                            <i class="far fa-calendar-alt"></i> <?php echo $date; ?>
                        </div>
                        <h3 class="news-title"><?php echo htmlspecialchars($news['Title']); ?></h3>
                        <p class="news-desc">
                            <?php 
                            $desc = strip_tags($news['Description']);
                            echo htmlspecialchars(substr($desc, 0, 150)) . '...';
                            ?>
                        </p>
                        <a href="news_detail.php?id=<?php echo $news['NewsID']; ?>" class="read-more">
                            Read More <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <a href="news.php" class="view-all-btn">
                View All News <i class="fas fa-arrow-right ms-2"></i>
            </a>
            
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-newspaper fa-4x text-muted mb-4"></i>
                <h3>No News Available</h3>
                <p class="text-muted">Check back later for updates</p>
            </div>
        <?php endif; ?>
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
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if(target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

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