<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "volunteer") {
    header("Location: login.php");
    exit();
}

require_once "connection.php";

$user_id = $_SESSION['user_id'];

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
$volunteer = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Debug: Check what's in $volunteer
error_log("Volunteer Data: " . print_r($volunteer, true));

// ============================
// FETCH OPPORTUNITIES FROM ASSIGNED NGO
// ============================
$opportunities = [];
if ($volunteer && isset($volunteer['AssignedNGO']) && $volunteer['AssignedNGO']) {
    $oppSql = "SELECT 
                    OpportunityID,
                    Title,
                    Description,
                    Location,
                    StartDate,
                    EndDate,
                    RequiredVolunteers,
                    Status
                FROM Opportunities 
                WHERE NGOID = ? AND Status = 'active'
                ORDER BY StartDate ASC";
    
    $oppParams = array($volunteer['AssignedNGO']);
    $oppStmt = sqlsrv_query($conn, $oppSql, $oppParams);
    
    if ($oppStmt) {
        while ($opp = sqlsrv_fetch_array($oppStmt, SQLSRV_FETCH_ASSOC)) {
            $opportunities[] = $opp;
        }
    } else {
        error_log("Opportunities query error: " . print_r(sqlsrv_errors(), true));
    }
} else {
    error_log("No AssignedNGO found for volunteer ID: $user_id");
}

// ============================
// FETCH VOLUNTEER'S ASSIGNMENTS
// ============================
$assignments = [];
$assignSql = "SELECT 
                a.AssignmentID,
                a.Status as AssignmentStatus,
                a.AssignedDate,
                o.Title,
                o.Location,
                o.StartDate,
                o.EndDate
            FROM Assignments a
            JOIN Opportunities o ON a.OpportunityID = o.OpportunityID
            WHERE a.VolunteerID = ?
            ORDER BY a.AssignedDate DESC";
            
$assignParams = array($user_id);
$assignStmt = sqlsrv_query($conn, $assignSql, $assignParams);

if ($assignStmt) {
    while ($assign = sqlsrv_fetch_array($assignStmt, SQLSRV_FETCH_ASSOC)) {
        $assignments[] = $assign;
    }
} else {
    error_log("Assignments query error: " . print_r(sqlsrv_errors(), true));
}

// ============================
// FETCH NEWS/STORIES FROM ASSIGNED NGO
// ============================
$news = [];
if ($volunteer && isset($volunteer['AssignedNGO']) && $volunteer['AssignedNGO']) {
    // Get NGO Name
    $ngoName = $volunteer['NGOName'] ?? '';
    
    // Fetch recent news/stories created by this NGO
    $newsSql = "SELECT TOP 4 
                    NewsID,
                    Title,
                    Description,
                    ImageURL,
                    CreatedBy,
                    FORMAT(CreatedAt, 'dd MMM yyyy HH:mm') as FormattedDate
                FROM [UserManagement].[dbo].[News] 
                WHERE CreatedBy = ? 
                ORDER BY CreatedAt DESC";
    
    $newsParams = array($ngoName);
    $newsStmt = sqlsrv_query($conn, $newsSql, $newsParams);
    
    if ($newsStmt) {
        while ($story = sqlsrv_fetch_array($newsStmt, SQLSRV_FETCH_ASSOC)) {
            $news[] = $story;
        }
    } else {
        error_log("News query error: " . print_r(sqlsrv_errors(), true));
    }
}

// Debug log
error_log("Opportunities count: " . count($opportunities));
error_log("Assignments count: " . count($assignments));
error_log("News count: " . count($news));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Volunteer Dashboard - <?= htmlspecialchars($volunteer['NGOName'] ?? 'No NGO') ?></title>
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
        
        .opportunity-card {
            border-left: 4px solid #27ae60;
            padding: 20px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .opportunity-card:hover {
            background: #e8f5e9;
            transform: translateX(5px);
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
            background: #f5f5f5;
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
            -webkit-line-clamp: 3;
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
            background: linear-gradient(135deg, #f5f5f5, #e0e0e0);
        }
        
        .no-image i {
            font-size: 3rem;
            color: #bdbdbd;
        }
        
        .assignment-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .badge-pending { background: #ffd700; color: #333; }
        .badge-confirmed { background: #27ae60; color: white; }
        .badge-completed { background: #3498db; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .volunteer-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .skill-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
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
        
        .btn-view {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(39, 174, 96, 0.3);
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
        <a href="volunteer_assigned.php">🔍 View Opportunities</a>
        <a href="my_tasks.php">📋 My Tasks</a>
        <a href="volunteer_reports.php">📊 My Reports</a>
        <a href="logout.php" style="background: rgba(231, 76, 60, 0.2);">🚪 Logout</a>
    </div>

    <div class="content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h3>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>! 👋</h3>
            <p>You are viewing the dashboard for <strong><?= htmlspecialchars($volunteer['NGOName'] ?? 'No NGO Assigned') ?></strong></p>
            <div class="skill-badge">
                Skill: <?= htmlspecialchars($volunteer['SkillCategory'] ?? 'Not specified') ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="dashboard-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($opportunities) ?></div>
                        <div class="stat-label">Available Opportunities</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($assignments) ?></div>
                        <div class="stat-label">My Assignments</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number">
                            <?= htmlspecialchars($volunteer['SkillCategory'] ?? 'N/A') ?>
                        </div>
                        <div class="stat-label">Primary Skill</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?= count($news) ?></div>
                        <div class="stat-label">News Stories</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEWS FROM YOUR NGO SECTION -->
        <div class="dashboard-card">
            <h5 class="card-title"><i class="fas fa-newspaper"></i> Latest News from <?= htmlspecialchars($volunteer['NGOName'] ?? 'your NGO') ?></h5>
            <?php if (empty($news)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📰</div>
                    <h5>No News Available</h5>
                    <p class="text-muted"><?= htmlspecialchars($volunteer['NGOName'] ?? 'Your NGO') ?> hasn't posted any news stories yet.</p>
                    <p><small>Check back later for updates on their activities.</small></p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($news as $story): 
                        $short_description = strlen($story['Description']) > 120 
                            ? substr($story['Description'], 0, 120) . '...' 
                            : $story['Description'];
                    ?>
                    <div class="col-md-6">
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
                                        <span><?= htmlspecialchars($story['FormattedDate']) ?></span>
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

        <!-- Available Opportunities -->
        <div class="dashboard-card">
            <h5 class="card-title"><i class="fas fa-tasks"></i> Available Opportunities from <?= htmlspecialchars($volunteer['NGOName'] ?? 'your NGO') ?></h5>
            <?php if (empty($opportunities)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h5>No Opportunities Available</h5>
                    <p class="text-muted">There are currently no opportunities posted by <?= htmlspecialchars($volunteer['NGOName'] ?? 'your NGO') ?>.</p>
                    <p><small>Check back later or contact your NGO coordinator.</small></p>
                </div>
            <?php else: ?>
                <?php foreach ($opportunities as $opp): 
                    // Format dates properly
                    $startDate = $opp['StartDate'] instanceof DateTime ? $opp['StartDate']->format('M d, Y') : date('M d, Y', strtotime($opp['StartDate']));
                    $endDate = $opp['EndDate'] instanceof DateTime ? $opp['EndDate']->format('M d, Y') : date('M d, Y', strtotime($opp['EndDate']));
                ?>
                    <div class="opportunity-card">
                        <h6><?= htmlspecialchars($opp['Title']) ?></h6>
                        <p class="text-muted"><?= htmlspecialchars(substr($opp['Description'], 0, 150)) ?>...</p>
                        <div class="mt-3">
                            <small class="text-muted d-block mb-2">
                                📍 <strong>Location:</strong> <?= htmlspecialchars($opp['Location']) ?>
                            </small>
                            <small class="text-muted d-block mb-2">
                                📅 <strong>Date:</strong> <?= $startDate ?> to <?= $endDate ?>
                            </small>
                            <small class="text-muted d-block">
                                👥 <strong>Volunteers Needed:</strong> <?= htmlspecialchars($opp['RequiredVolunteers']) ?>
                            </small>
                        </div>
                        <div class="mt-3">
                            <button class="btn-view" onclick="viewOpportunity(<?= $opp['OpportunityID'] ?>)">
                                View Details & Apply
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- My Assignments -->
        <div class="dashboard-card">
            <h5 class="card-title"><i class="fas fa-clipboard-list"></i> My Current Assignments</h5>
            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <h5>No Assignments Yet</h5>
                    <p class="text-muted">You haven't been assigned to any opportunities yet.</p>
                    <p><small>Browse available opportunities and apply to get started!</small></p>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assign): 
                    // Format dates properly
                    $startDate = $assign['StartDate'] instanceof DateTime ? $assign['StartDate']->format('M d, Y') : date('M d, Y', strtotime($assign['StartDate']));
                    $assignedDate = $assign['AssignedDate'] instanceof DateTime ? $assign['AssignedDate']->format('M d, Y') : date('M d, Y', strtotime($assign['AssignedDate']));
                ?>
                    <div class="opportunity-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-2"><?= htmlspecialchars($assign['Title']) ?></h6>
                            <span class="assignment-badge badge-<?= strtolower($assign['AssignmentStatus']) ?>">
                                <?= htmlspecialchars($assign['AssignmentStatus']) ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted d-block mb-1">
                                📍 <strong>Location:</strong> <?= htmlspecialchars($assign['Location']) ?>
                            </small>
                            <small class="text-muted d-block mb-1">
                                📅 <strong>Event Date:</strong> <?= $startDate ?>
                            </small>
                            <small class="text-muted d-block">
                                ✅ <strong>Assigned On:</strong> <?= $assignedDate ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewOpportunity(opportunityId) {
            window.location.href = `opportunity_details.php?id=${opportunityId}`;
        }
        
        function viewNews(newsId) {
            window.location.href = `news_details.php?id=${newsId}`;
        }
    </script>
</body>
</html>