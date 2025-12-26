<?php
include "db.php";

/* =======================
   HANDLE FEEDBACK
======================= */
$feedback_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    try {
        $stmt = $conn->prepare("INSERT INTO feedback (rating, comments) VALUES (?, ?)");
        $stmt->execute([$_POST['rating'], $_POST['comments']]);
        $feedback_message = "✅ Thank you for sharing your feedback!";
    } catch (PDOException $e) {
        $feedback_message = "❌ Unable to submit feedback.";
    }
}

/* =======================
   FETCH DISASTERS
======================= */
$disasters = $conn->query("
    SELECT d.disaster_id, d.disaster_name, d.district, d.severity,
           d.alert_message, d.status,
           COUNT(v.victim_id) AS total_victims
    FROM disaster d
    LEFT JOIN victim v ON d.disaster_id = v.disaster_id
    GROUP BY d.disaster_id, d.disaster_name, d.district, d.severity, d.alert_message, d.status
    ORDER BY d.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


/* =======================
   SHELTERS
======================= */
$shelters_by_district = [
    'Melaka Tengah' => [
        ['name'=>'Melaka Tengah Shelter 1','address'=>'123 Jalan Merdeka','phone'=>'012-3456789','email'=>'mt1@shelter.gov.my']
    ],
    'Alor Gajah' => [
        ['name'=>'Alor Gajah Shelter 1','address'=>'12 Jalan Melati','phone'=>'013-1112223','email'=>'ag1@shelter.gov.my']
    ],
    'Jasin' => [
        ['name'=>'Jasin Shelter 1','address'=>'56 Jalan Kenanga','phone'=>'014-5556667','email'=>'js1@shelter.gov.my']
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Melaka Disaster Assistance Portal</title>

<!-- Dashboard CSS -->
<link rel="stylesheet" href="header.css">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- GLOBAL FONT -->
<style>
* {
    font-family: system-ui, -apple-system, BlinkMacSystemFont,
                 "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
</style>
</head>

<body>

<!-- ================= HEADER ================= -->
<header class="system-header">
    <div class="header-container">
        <div class="logo-section">
            <i class="fas fa-shield-heart logo-icon"></i>
            <div class="logo-text">
                <h1>Melaka Disaster Assistance</h1>
                <small>Public Support & Emergency Information</small>
            </div>
        </div>
    </div>
</header>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">
    <ul class="nav-menu">
        <li class="nav-item">
            <a class="nav-link active">
                <i class="fas fa-house"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="victim_register.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                <span class="nav-text">Victim Registration</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="report_disaster.php" class="nav-link">
                <i class="fas fa-triangle-exclamation"></i>
                <span class="nav-text">Report Disaster</span>
            </a>
        </li>
    </ul>
</div>

<!-- ================= MAIN ================= -->
<div class="main-content">

<!-- ===== QUICK HELP ===== -->
<div class="top-action-header">
    <h2><i class="fas fa-hand-holding-heart"></i> How can we help you today?</h2>
    <div class="action-buttons-grid">
        <a href="victim_register.php" class="action-header-btn btn-create">
            <i class="fas fa-user-injured"></i>
            <div class="btn-text">
                Register as a Victim
                <small>Request help for yourself or others</small>
            </div>
        </a>
        <a href="report_disaster.php" class="action-header-btn btn-report">
            <i class="fas fa-bullhorn"></i>
            <div class="btn-text">
                Report a Disaster
                <small>Inform authorities immediately</small>
            </div>
        </a>
    </div>
</div>

<!-- ===== ACTIVE DISASTERS ===== -->
<div class="card">
<h3><i class="fas fa-triangle-exclamation"></i> Current Disaster Alerts</h3>

<?php if (!$disasters): ?>
<p class="muted">No active disasters reported. Stay safe 💙</p>
<?php else: ?>
<div class="disaster-grid">
<?php foreach ($disasters as $d): ?>
<div class="disaster-card">
    <div class="disaster-header">
        <strong><?= htmlspecialchars($d['disaster_name']) ?></strong>
        <span class="status-pill"><?= htmlspecialchars($d['status']) ?></span>
    </div>

    <div class="disaster-info">
        <span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($d['district']) ?></span>
        <span><i class="fas fa-bolt"></i> <?= htmlspecialchars($d['severity']) ?></span>
        <span><i class="fas fa-users"></i> <?= $d['total_victims'] ?> victims</span>
    </div>

    <p class="disaster-message">
        <?= htmlspecialchars($d['alert_message']) ?>
    </p>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>


<div class="card">
    <h3><i class="fas fa-comment-dots"></i> Your Feedback</h3>

    <div class="feedback-layout">

        <!-- LEFT -->
        <div class="feedback-left">

            <?php if ($feedback_message): ?>
                <div class="message <?= str_contains($feedback_message,'✅') ? 'success':'error' ?>">
                    <?= $feedback_message ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="feedback-form">

                <!-- Rating -->
                <div class="form-group">
                    <label class="feedback-label">Overall experience</label>
                    <div class="rating-row">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="rate<?= $i ?>" value="<?= $i ?>" required>
                            <label for="rate<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Comments -->
                <div class="form-group">
                    <label class="feedback-label">Comments</label>
                    <textarea
                        name="comments"
                        placeholder="Share your experience or suggestions"
                        required></textarea>
                </div>

                <button type="submit" name="feedback_submit" class="btn-purple">
                    <i class="fas fa-paper-plane"></i> Submit Feedback
                </button>

            </form>
        </div>

        <!-- RIGHT -->
        <div class="feedback-right">
            <h4>Why your feedback matters</h4>
            <ul class="feedback-info">
                <li><i class="fas fa-check-circle"></i> Improves emergency response</li>
                <li><i class="fas fa-check-circle"></i> Helps allocate aid fairly</li>
                <li><i class="fas fa-check-circle"></i> Identifies system issues</li>
                <li><i class="fas fa-lock"></i> Anonymous & confidential</li>
            </ul>
        </div>

    </div>
</div>




<!-- ===== SHELTERS ===== -->
<div class="card">
<h3><i class="fas fa-building"></i> Nearby Shelters</h3>

<div class="shelter-grid">
<?php foreach ($shelters_by_district as $district => $list): ?>
<div class="shelter-card">
<h4><?= htmlspecialchars($district) ?></h4>
<?php foreach ($list as $s): ?>
<p><strong><?= $s['name'] ?></strong></p>
<p>📍 <?= $s['address'] ?></p>
<p>📞 <?= $s['phone'] ?></p>
<p>✉ <?= $s['email'] ?></p>
<hr>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
</div>

</div>
</body>
</html>
