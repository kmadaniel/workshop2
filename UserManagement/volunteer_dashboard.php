<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];

// ============================
// DISTRIBUTION SYSTEM URL - CORRECTED
// ============================
$distribution_url = "http://10.147.17.154:8000/distribution_module/login_callback.php?volunteer_id=" . $user_id;

// ============================
// FETCH VOLUNTEER & ASSIGNED NGO INFO
// ============================
$sql = "SELECT 
            v.FullName,
            v.SkillCategory,
            v.AssignedNGO,
            n.NGOName,
            n.RegistrationNo
        FROM Volunteer v
        LEFT JOIN NGO n ON v.AssignedNGO = n.NGOID
        WHERE v.VolunteerID = ?";
        
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}

$volunteer = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// If no volunteer data found, use defaults
if (!$volunteer) {
    $volunteer = [
        'FullName' => $_SESSION['name'] ?? 'Guest', 
        'SkillCategory' => 'Not Specified', 
        'NGOName' => 'No NGO Assigned',
        'AssignedNGO' => null
    ];
}

// ============================
// FETCH NEWS/STORIES
// ============================
$news = [];
$ngoName = $volunteer['NGOName'] ?? '';

// Check jika table News wujud
$checkNewsTable = "SELECT COUNT(*) as TableExists 
                   FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = 'dbo' 
                   AND TABLE_NAME = 'News'";
$checkStmt = sqlsrv_query($conn, $checkNewsTable);

if ($checkStmt) {
    $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    if ($row['TableExists'] > 0 && !empty($ngoName) && $ngoName != 'No NGO Assigned') {
        // Fetch news dari table News
        $newsSql = "SELECT TOP 4 
                        NewsID,
                        Title,
                        Description,
                        ImageURL,
                        CreatedBy,
                        CreatedAt,
                        FORMAT(CreatedAt, 'dd MMM yyyy HH:mm') as FormattedDate
                    FROM News 
                    WHERE CreatedBy = ? OR CreatedBy LIKE ?
                    ORDER BY CreatedAt DESC";
        
        $ngoNameParam = $ngoName;
        $ngoNameLikeParam = '%' . $ngoName . '%';
        $newsParams = array($ngoNameParam, $ngoNameLikeParam);
        $newsStmt = sqlsrv_query($conn, $newsSql, $newsParams);
        
        if ($newsStmt !== false) {
            while ($story = sqlsrv_fetch_array($newsStmt, SQLSRV_FETCH_ASSOC)) {
                $news[] = $story;
            }
        }
    }
}

// Jika masih no news, guna sample news dari NGO
if (empty($news) && !empty($ngoName) && $ngoName != 'No NGO Assigned') {
    $news = [
        [
            'NewsID' => 1,
            'Title' => 'Welcome to ' . $ngoName . '!',
            'Description' => 'Thank you for joining our volunteer team. Your dedication helps us make a real difference in the community.',
            'ImageURL' => '',
            'CreatedBy' => $ngoName,
            'FormattedDate' => date('d M Y H:i')
        ],
        [
            'NewsID' => 2,
            'Title' => 'Upcoming Community Events',
            'Description' => 'Check out our latest opportunities including food helper and beach cleanup activities.',
            'ImageURL' => '',
            'CreatedBy' => $ngoName,
            'FormattedDate' => date('d M Y H:i', strtotime('-1 day'))
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Dashboard - <?= htmlspecialchars($volunteer['NGOName']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }
        
        /* ========== SIDEBAR STYLING ========== */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            padding: 20px;
            min-height: 100vh;
            position: fixed;
        }
        
        .sidebar h4 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }
        
        .sidebar a {
            display: block;
            padding: 12px 15px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }
        
        .sidebar a.active {
            background: rgba(255,255,255,0.3);
            font-weight: 500;
        }
        
        /* Distribution Link Styling */
        .distribution-link {
            background: rgba(255, 126, 95, 0.2);
            border-left: 3px solid #ff7e5f;
        }
        
        .distribution-link:hover {
            background: rgba(255, 126, 95, 0.3);
        }
        
        /* ========== MAIN CONTENT ========== */
        .content {
            flex: 1;
            padding: 40px;
            margin-left: 250px;
            overflow-y: auto;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        
        .card-title {
            color: #27ae60;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            font-size: 1.2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(39, 174, 96, 0.3);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* News Card Styling */
        .news-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            background: white;
            transition: all 0.3s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .news-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .news-image {
            height: 160px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .news-card:hover .news-image img {
            transform: scale(1.05);
        }
        
        .news-content {
            padding: 15px;
        }
        
        .news-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .news-description {
            color: #546e7a;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #78909c;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        
        .news-author, .news-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* No Image Placeholder */
        .no-image {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .no-image i {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .skill-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .btn-news {
            background: #3498db;
            color: white;
            border: none;
            padding: 6px 15px;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        
        .btn-news:hover {
            background: #2980b9;
        }
        
        .ngo-name {
            background: rgba(255,255,255,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-left: 10px;
        }
        
        /* Distribution Notification */
        .distribution-notice {
            background: linear-gradient(135deg, #ff7e5f 0%, #feb47b 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .distribution-notice i {
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
            
            .news-image {
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <h4>Volunteer Panel</h4>
        <a href="volunteer_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="volunteer_profile.php">👤 Profile</a>
        
        <!-- UPDATED: Points to login_callback.php (same as main login) -->
        <a href="<?php echo $distribution_url; ?>" 
           target="_blank"
           class="distribution-link">
           🚚 My Tasks (Distribution System)
        </a>
        
        
        <a href="main_page.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <div class="content">
        <!-- Distribution System Notice -->
        <div class="distribution-notice">
            <i class="fas fa-external-link-alt"></i>
            <div>
                <strong>Distribution System Access:</strong> 
                Click on <strong>🚚 My Tasks (Distribution System)</strong> in the sidebar to access 
                distribution management tasks at the external distribution system.
            </div>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <h3>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Volunteer') ?>! 👋</h3>
            <p>You are viewing the dashboard for 
                <strong class="ngo-name"><?= htmlspecialchars($volunteer['NGOName']) ?></strong>
            </p>
            <div class="skill-badge">
                <i class="fas fa-stethoscope"></i> Skill: <?= htmlspecialchars($volunteer['SkillCategory']) ?>
            </div>
        </div>

        <!-- Statistics Cards (DIPENDEK: Hanya 2 cards) -->
        <div class="dashboard-card">
            <div class="row">
                <!-- Card 1: Primary Skill -->
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-number">
                            <?= htmlspecialchars($volunteer['SkillCategory']) ?>
                        </div>
                        <div class="stat-label">Primary Skill</div>
                    </div>
                </div>
                
                <!-- Card 2: News Stories -->
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($news) ?></div>
                        <div class="stat-label">News Stories</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEWS FROM YOUR NGO SECTION -->
        <div class="dashboard-card">
            <h5 class="card-title"><i class="fas fa-newspaper"></i> Latest News from <?= htmlspecialchars($volunteer['NGOName']) ?></h5>
            <?php if (empty($news)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📰</div>
                    <h5>No News Available</h5>
                    <p class="text-muted"><?= htmlspecialchars($volunteer['NGOName']) ?> hasn't posted any news stories yet.</p>
                    <p><small>Check back later for updates on their activities.</small></p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($news as $story): 
                        $short_description = strlen($story['Description']) > 120 
                            ? substr($story['Description'], 0, 120) . '...' 
                            : $story['Description'];
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="news-card">
                            <div class="news-image">
                                <?php if (!empty($story['ImageURL']) && file_exists($story['ImageURL'])): ?>
                                    <img src="<?= htmlspecialchars($story['ImageURL']) ?>" alt="<?= htmlspecialchars($story['Title']) ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="news-content">
                                <h6 class="news-title"><?= htmlspecialchars($story['Title']) ?></h6>
                                <p class="news-description"><?= htmlspecialchars($short_description) ?></p>
                                <div class="news-meta">
                                    <div class="news-author">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?= htmlspecialchars($story['CreatedBy']) ?></span>
                                    </div>
                                    <div class="news-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?= htmlspecialchars($story['FormattedDate'] ?? date('d M Y H:i')) ?></span>
                                    </div>
                                </div>
                                <button class="btn-news" onclick="viewNews(<?= $story['NewsID'] ?>)">
                                    <i class="fas fa-eye"></i> View Full Story
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewNews(newsId) {
            window.location.href = `news_details.php?id=${newsId}`;
        }
        
        // Auto-refresh dashboard every 60 seconds
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Show confirmation when clicking distribution link
        document.addEventListener('DOMContentLoaded', function() {
            const distributionLink = document.querySelector('.distribution-link');
            if (distributionLink) {
                distributionLink.addEventListener('click', function(e) {
                    const confirmMsg = "You are being redirected to the Distribution System.\n\n" +
                                     "After verification, you will be redirected to:\n" +
                                     "http://10.147.17.154:8000/distribution_module/volunteer_distribution.php\n\n" +
                                     "Continue?";
                    
                    if (!confirm(confirmMsg)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>