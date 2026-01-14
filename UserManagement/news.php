<?php
// Database connection untuk ambil SEMUA news
$serverName = "localhost";
$connectionOptions = array(
    "Database" => "UserManagement",
    "Uid" => "yanadb",
    "PWD" => "yana123"
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

// Get all news termasuk CreatedBy
$all_news = array();
if($conn !== false) {
    $sql = "SELECT NewsID, Title, Description, ImageURL, CreatedAt, CreatedBy 
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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
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

        /* HERO BANNER */
        .news-hero {
            height: 250px;
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
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .news-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
        }

        /* MAIN LAYOUT - SIDEBAR & CONTENT */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            gap: 40px;
            flex: 1;
        }

        /* LEFT SIDEBAR - FILTER & SEARCH */
        .sidebar-left {
            width: 300px;
            flex-shrink: 0;
            position: sticky;
            top: 120px;
            height: fit-content;
        }

        .search-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .search-box h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-input {
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .filter-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .filter-box h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 20px;
        }

        .category-list {
            list-style: none;
        }

        .category-list li {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-list li:hover {
            background: #f8f9fa;
        }

        .category-list li.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .category-count {
            background: #e9ecef;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .category-list li.active .category-count {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .recent-news {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-top: 25px;
        }

        .recent-news h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 20px;
        }

        .recent-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item h4 {
            font-size: 0.95rem;
            color: #333;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .recent-item p {
            font-size: 0.85rem;
            color: #666;
        }

        /* RIGHT CONTENT - NEWS ARTICLES */
        .content-right {
            flex: 1;
            min-width: 0;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h2 {
            font-size: 1.8rem;
            color: #333;
            font-weight: 600;
        }

        .articles-count {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* NEWS GRID */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .news-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .news-img-container {
            height: 200px;
            width: 100%;
            overflow: hidden;
        }

        .news-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .news-card:hover .news-img {
            transform: scale(1.05);
        }

        .no-image {
            height: 200px;
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .no-image i {
            font-size: 2rem;
            opacity: 0.8;
        }

        .news-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .news-category {
            display: inline-block;
            background: #f0f5ff;
            color: #667eea;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 12px;
            align-self: flex-start;
        }

        .news-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .news-desc {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 0.95rem;
            flex: 1;
        }

        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .news-author-date {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .news-author {
            font-size: 0.9rem;
            color: #667eea;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .news-author i {
            font-size: 0.8rem;
        }

        .news-date {
            font-size: 0.85rem;
            color: #888;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .read-more {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #667eea;
        }

        .read-more:hover {
            background: #667eea;
            color: white;
            transform: translateX(5px);
        }

        /* MODAL STYLING */
        .news-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border-radius: 15px;
            overflow: hidden;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal-open .modal-content {
            transform: scale(1);
        }

        .modal-img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .modal-body {
            padding: 40px;
            overflow-y: auto;
            max-height: calc(90vh - 300px);
        }

        .modal-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .modal-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .modal-author, .modal-date, .modal-category {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 0.95rem;
        }

        .modal-description {
            color: #555;
            line-height: 1.8;
            font-size: 1.05rem;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 2001;
        }

        .close-modal:hover {
            background: #667eea;
            color: white;
            transform: rotate(90deg);
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 50px 0;
        }

        .page-link {
            padding: 10px 18px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s;
            min-width: 45px;
            text-align: center;
        }

        .page-link:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .page-link.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* FOOTER */
        .footer {
            background: #343a40;
            color: white;
            padding: 40px 60px;
            text-align: center;
            margin-top: 80px;
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
        @media (max-width: 1100px) {
            .main-container {
                flex-direction: column;
            }
            
            .sidebar-left {
                width: 100%;
                position: static;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 25px;
            }
            
            .search-box, .filter-box, .recent-news {
                margin: 0;
            }
        }

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
                height: 200px;
                padding-top: 100px;
            }
            
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .content-header {
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
            <a href="victim.php">VICTIM</a>
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

    <!-- MAIN CONTENT WITH SIDEBAR -->
    <div class="main-container">
        <!-- LEFT SIDEBAR -->
        <div class="sidebar-left">
            <!-- SEARCH BOX -->
            <div class="search-box">
                <h3><i class="fas fa-search"></i> Search</h3>
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search news articles...">
                </div>
            </div>

            <!-- CATEGORY FILTER -->
            <div class="filter-box">
                <h3><i class="fas fa-filter"></i> Categories</h3>
                <ul class="category-list">
                    <li class="active" data-category="all">
                        All News <span class="category-count"><?php echo count($all_news); ?></span>
                    </li>
                    <li data-category="general">
                        General <span class="category-count"><?php 
                            echo count(array_filter($all_news, function($news) {
                                $title = strtolower($news['Title']);
                                return !(strpos($title, 'banjir') !== false || strpos($title, 'flood') !== false || 
                                        strpos($title, 'kebakaran') !== false || strpos($title, 'fire') !== false ||
                                        strpos($title, 'gempa') !== false || strpos($title, 'earthquake') !== false ||
                                        strpos($title, 'darah') !== false || strpos($title, 'blood') !== false ||
                                        strpos($title, 'health') !== false || strpos($title, 'kesihatan') !== false);
                            }));
                        ?></span>
                    </li>
                    <li data-category="health">
                        Health <span class="category-count"><?php 
                            echo count(array_filter($all_news, function($news) {
                                $title = strtolower($news['Title']);
                                return strpos($title, 'health') !== false || strpos($title, 'kesihatan') !== false || 
                                       strpos($title, 'darah') !== false || strpos($title, 'blood') !== false;
                            }));
                        ?></span>
                    </li>
                    <li data-category="flood">
                        Flood <span class="category-count"><?php 
                            echo count(array_filter($all_news, function($news) {
                                $title = strtolower($news['Title']);
                                return strpos($title, 'banjir') !== false || strpos($title, 'flood') !== false;
                            }));
                        ?></span>
                    </li>
                    <li data-category="fire">
                        Fire <span class="category-count"><?php 
                            echo count(array_filter($all_news, function($news) {
                                $title = strtolower($news['Title']);
                                return strpos($title, 'kebakaran') !== false || strpos($title, 'fire') !== false;
                            }));
                        ?></span>
                    </li>
                    <li data-category="earthquake">
                        Earthquake <span class="category-count"><?php 
                            echo count(array_filter($all_news, function($news) {
                                $title = strtolower($news['Title']);
                                return strpos($title, 'gempa') !== false || strpos($title, 'earthquake') !== false;
                            }));
                        ?></span>
                    </li>
                </ul>
            </div>

            <!-- RECENT NEWS -->
            <div class="recent-news">
                <h3><i class="fas fa-clock"></i> Recent News</h3>
                <?php 
                $recent_news = array_slice($all_news, 0, 3);
                foreach($recent_news as $recent): 
                    $date = $recent['CreatedAt'] instanceof DateTime 
                        ? $recent['CreatedAt']->format('M d') 
                        : date('M d', strtotime($recent['CreatedAt']));
                ?>
                <div class="recent-item">
                    <h4><?php echo htmlspecialchars(substr($recent['Title'], 0, 50)); ?>...</h4>
                    <p><i class="far fa-calendar-alt"></i> <?php echo $date; ?> • By <?php echo htmlspecialchars($recent['CreatedBy'] ?? 'Admin'); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT CONTENT -->
        <div class="content-right">
            <div class="content-header">
                <h2>All News Articles</h2>
                <span class="articles-count"><?php echo count($all_news); ?> Articles</span>
            </div>

            <?php if(!empty($all_news)): ?>
                <div class="news-grid">
                    <?php foreach($all_news as $news): 
                        $date = $news['CreatedAt'] instanceof DateTime 
                            ? $news['CreatedAt']->format('M d, Y') 
                            : date('M d, Y', strtotime($news['CreatedAt']));
                        
                        // Determine category
                        $category = "General";
                        $title_lower = strtolower($news['Title']);
                        if(strpos($title_lower, 'banjir') !== false || strpos($title_lower, 'flood') !== false) {
                            $category = "Flood";
                        } elseif(strpos($title_lower, 'kebakaran') !== false || strpos($title_lower, 'fire') !== false) {
                            $category = "Fire";
                        } elseif(strpos($title_lower, 'gempa') !== false || strpos($title_lower, 'earthquake') !== false) {
                            $category = "Earthquake";
                        } elseif(strpos($title_lower, 'darah') !== false || strpos($title_lower, 'blood') !== false || 
                                strpos($title_lower, 'health') !== false || strpos($title_lower, 'kesihatan') !== false) {
                            $category = "Health";
                        }
                        
                        $short_desc = strip_tags($news['Description']);
                        if(strlen($short_desc) > 120) {
                            $short_desc = substr($short_desc, 0, 120) . '...';
                        }
                    ?>
                    <div class="news-card" data-category="<?php echo strtolower($category); ?>" 
                         data-title="<?php echo htmlspecialchars(strtolower($news['Title'])); ?>">
                        <div class="news-img-container">
                            <?php if(!empty($news['ImageURL'])): ?>
                                <img src="<?php echo htmlspecialchars($news['ImageURL']); ?>" 
                                     alt="<?php echo htmlspecialchars($news['Title']); ?>" 
                                     class="news-img">
                            <?php else: ?>
                                <div class="no-image">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="news-content">
                            <span class="news-category"><?php echo $category; ?></span>
                            <h3 class="news-title"><?php echo htmlspecialchars($news['Title']); ?></h3>
                            <p class="news-desc"><?php echo htmlspecialchars($short_desc); ?></p>
                            
                            <div class="news-meta">
                                <div class="news-author-date">
                                    <div class="news-author">
                                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($news['CreatedBy'] ?? 'Admin'); ?>
                                    </div>
                                    <div class="news-date">
                                        <i class="far fa-calendar-alt"></i> <?php echo $date; ?>
                                    </div>
                                </div>
                                <a href="#" class="read-more read-more-btn" 
                                   data-id="<?php echo $news['NewsID']; ?>"
                                   data-title="<?php echo htmlspecialchars($news['Title']); ?>"
                                   data-desc="<?php echo htmlspecialchars($news['Description']); ?>"
                                   data-img="<?php echo htmlspecialchars($news['ImageURL']); ?>"
                                   data-date="<?php echo $date; ?>"
                                   data-category="<?php echo $category; ?>"
                                   data-author="<?php echo htmlspecialchars($news['CreatedBy'] ?? 'Admin'); ?>">
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

            <!-- Pagination -->
            <div class="pagination">
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link">2</a>
                <a href="#" class="page-link">3</a>
                <a href="#" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <!-- NEWS MODAL -->
    <div class="news-modal" id="newsModal">
        <div class="modal-content">
            <div class="close-modal" id="closeModal">
                <i class="fas fa-times"></i>
            </div>
            <div id="modalImageContainer">
                <img src="" alt="" class="modal-img" id="modalImage">
            </div>
            <div class="modal-body">
                <span class="news-category" id="modalCategory">General</span>
                <h1 class="modal-title" id="modalTitle"></h1>
                
                <div class="modal-meta">
                    <div class="modal-author">
                        <i class="fas fa-user-circle"></i> <span id="modalAuthor"></span>
                    </div>
                    <div class="modal-date">
                        <i class="far fa-calendar-alt"></i> <span id="modalDate"></span>
                    </div>
                    <div class="modal-category">
                        <i class="fas fa-tag"></i> <span id="modalCategoryText"></span>
                    </div>
                </div>
                
                <div class="modal-description" id="modalDescription"></div>
            </div>
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

        // Read More Modal Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('newsModal');
            const closeModal = document.getElementById('closeModal');
            const readMoreButtons = document.querySelectorAll('.read-more-btn');
            const searchInput = document.getElementById('searchInput');
            const categoryItems = document.querySelectorAll('.category-list li');
            const newsCards = document.querySelectorAll('.news-card');
            
            // Open modal when Read More is clicked
            readMoreButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const newsId = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const description = this.getAttribute('data-desc');
                    const image = this.getAttribute('data-img');
                    const date = this.getAttribute('data-date');
                    const category = this.getAttribute('data-category');
                    const author = this.getAttribute('data-author');
                    
                    // Set modal content
                    document.getElementById('modalTitle').textContent = title;
                    document.getElementById('modalDescription').innerHTML = description.replace(/\n/g, '<br>');
                    document.getElementById('modalDate').textContent = date;
                    document.getElementById('modalCategory').textContent = category;
                    document.getElementById('modalCategoryText').textContent = category;
                    document.getElementById('modalAuthor').textContent = author;
                    
                    const modalImage = document.getElementById('modalImage');
                    const modalImageContainer = document.getElementById('modalImageContainer');
                    
                    if(image && image.trim() !== '') {
                        modalImage.src = image;
                        modalImage.alt = title;
                        modalImageContainer.style.display = 'block';
                    } else {
                        modalImageContainer.style.display = 'none';
                    }
                    
                    // Show modal with animation
                    modal.style.display = 'flex';
                    setTimeout(() => {
                        modal.style.opacity = '1';
                        modal.classList.add('modal-open');
                    }, 10);
                    
                    // Prevent body scrolling
                    document.body.style.overflow = 'hidden';
                });
            });
            
            // Close modal
            function closeNewsModal() {
                modal.style.opacity = '0';
                modal.classList.remove('modal-open');
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 300);
            }
            
            closeModal.addEventListener('click', closeNewsModal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if(e.target === modal) {
                    closeNewsModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if(e.key === 'Escape' && modal.style.display === 'flex') {
                    closeNewsModal();
                }
            });
            
            // Search functionality
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                newsCards.forEach(card => {
                    const title = card.getAttribute('data-title');
                    const category = card.getAttribute('data-category');
                    
                    if(searchTerm === '' || 
                       title.includes(searchTerm) || 
                       category.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
            
            // Category filter functionality
            categoryItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    categoryItems.forEach(i => i.classList.remove('active'));
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    const selectedCategory = this.getAttribute('data-category');
                    
                    // Filter news cards
                    newsCards.forEach(card => {
                        const cardCategory = card.getAttribute('data-category');
                        
                        if(selectedCategory === 'all' || cardCategory === selectedCategory) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Pagination functionality
            const pageLinks = document.querySelectorAll('.page-link');
            pageLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    pageLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Here you would normally load new page content via AJAX
                    // For now, just scroll to top
                    window.scrollTo({ top: 500, behavior: 'smooth' });
                });
            });
        });
    </script>
</body>
</html>