<?php
include "db.php";

// Handle feedback submission
$feedback_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    $stmt = $conn->prepare("INSERT INTO feedback (rating, comments) VALUES (?, ?)");
    try {
        $stmt->execute([$_POST['rating'], $_POST['comments']]);
        $feedback_message = "✅ Thank you for your feedback!";
    } catch (PDOException $e) {
        $feedback_message = "❌ Failed to submit feedback: " . $e->getMessage();
    }
}

// Fetch disasters with total victims
$disasters = $conn->query("
    SELECT d.disaster_id, d.disaster_name, d.district, d.severity, d.alert_message, d.status,
           COUNT(vd.victim_id) AS total_victims
    FROM disaster d
    LEFT JOIN victim_disaster vd ON d.disaster_id = vd.disaster_id
    GROUP BY d.disaster_id
    ORDER BY d.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Static shelters info
$shelters_by_district = [
    'Melaka Tengah' => [
        ['name'=>'Melaka Tengah Shelter 1','address'=>'123 Jalan Merdeka','contact_number'=>'012-3456789','email'=>'mt1@shelter.gov.my'],
        ['name'=>'Melaka Tengah Shelter 2','address'=>'45 Jalan Raya','contact_number'=>'012-9876543','email'=>'mt2@shelter.gov.my'],
        ['name'=>'Melaka Tengah Shelter 3','address'=>'78 Jalan Bukit','contact_number'=>'012-1122334','email'=>'mt3@shelter.gov.my']
    ],
    'Alor Gajah' => [
        ['name'=>'Alor Gajah Shelter 1','address'=>'12 Jalan Melati','contact_number'=>'013-1112223','email'=>'ag1@shelter.gov.my'],
        ['name'=>'Alor Gajah Shelter 2','address'=>'34 Jalan Mawar','contact_number'=>'013-3334445','email'=>'ag2@shelter.gov.my']
    ],
    'Jasin' => [
        ['name'=>'Jasin Shelter 1','address'=>'56 Jalan Kenanga','contact_number'=>'014-5556667','email'=>'js1@shelter.gov.my'],
        ['name'=>'Jasin Shelter 2','address'=>'78 Jalan Orkid','contact_number'=>'014-7778889','email'=>'js2@shelter.gov.my']
    ]
];
?>

<!DOCTYPE html>
<html>
<head>
<title>Melaka Disaster Assistance Portal</title>
<style>
body { margin:0; font-family:'Segoe UI',sans-serif; background:#eef3f8; }
.header { background:linear-gradient(135deg,#007bff,#4facfe); color:white; padding:30px; text-align:center; }
.container { max-width:1000px; margin:30px auto; padding:0 20px; }
.card { background:white; border-radius:14px; padding:25px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:25px; }
.actions { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
.action-card { background:white; border-radius:12px; padding:25px; text-align:center; box-shadow:0 3px 8px rgba(0,0,0,0.1); transition:.2s; }
.action-card:hover { transform:translateY(-4px); }
.action-card span { font-size:36px; display:block; }
.alert { background:#f8f9fa; padding:12px; border-left:5px solid #dc3545; border-radius:6px; margin-bottom:10px; }
.feedback-form { background:#f1f8ff; padding:20px; border-radius:12px; margin-top:25px; }
.feedback-form input, .feedback-form textarea, .feedback-form select, .feedback-form button { width:100%; padding:12px; margin-bottom:10px; border-radius:8px; border:1px solid #ccc; }
.feedback-form button { background:#007bff; color:white; border:none; cursor:pointer; }
.feedback-form button:hover { background:#0056b3; }
.message { padding:12px; border-radius:8px; margin-bottom:15px; }
.success { background:#e8f5e9; color:#2e7d32; border-left:4px solid #28a745; }
.error { background:#fdd; color:#c62828; border-left:4px solid #d9534f; }
.shelter { background:#fff5e6; padding:12px; border-left:4px solid #ffa500; border-radius:6px; margin-bottom:10px; }
a { text-decoration:none; color:inherit; }
</style>
</head>
<body>

<div class="header">
    <h1>🤝 Melaka Disaster Assistance Portal</h1>
    <p>Supporting Melaka residents during emergencies</p>
</div>

<div class="container">

<!-- Active Disasters -->
<div class="card">
<h3>🚨 Active Disasters</h3>
<?php if (!$disasters): ?>
<p>No active disasters.</p>
<?php else: foreach ($disasters as $d): ?>
<div class="alert">
<strong><?= htmlspecialchars($d['disaster_name']) ?></strong> (<?= htmlspecialchars($d['status']) ?>)<br>
📍 <?= htmlspecialchars($d['district']) ?> | ⚠ <?= htmlspecialchars($d['severity']) ?><br>
Total Victims Registered: <?= $d['total_victims'] ?><br>
<?= htmlspecialchars($d['alert_message']) ?>
</div>
<?php endforeach; endif; ?>
</div>

<!-- Actions -->
<div class="card">
<h3>📌 Actions</h3>
<div class="actions">
<a href="victim_register.php" class="action-card">
<span>🧾</span>
<strong>Victim Registration</strong>
</a>
<a href="report_disaster.php" class="action-card">
<span>🚨</span>
<strong>Report Disaster</strong>
</a>
</div>
</div>

<!-- Feedback Form -->
<div class="card feedback-form">
<h3>💬 Give Your Feedback</h3>
<?php if ($feedback_message): ?>
<div class="message <?= strpos($feedback_message,'✅')!==false ? 'success':'error' ?>">
<?= $feedback_message ?>
</div>
<?php endif; ?>
<form method="POST">
<label>Rating (1-5)</label>
<select name="rating" required>
    <option value="">-- Select Rating --</option>
    <?php for ($i=1;$i<=5;$i++): ?>
    <option value="<?= $i ?>"><?= $i ?></option>
    <?php endfor; ?>
</select>
<label>Comments / Suggestions</label>
<textarea name="comments" placeholder="Your comments" required></textarea>
<button type="submit" name="feedback_submit">Submit Feedback</button>
</form>
</div>

<!-- Static Shelters -->
<div class="card">
<h3>🏠 Nearest Shelters / Distribution Centers</h3>
<?php foreach ($shelters_by_district as $district => $s_list): ?>
<h4><?= htmlspecialchars($district) ?></h4>
<?php foreach ($s_list as $s): ?>
<div class="shelter">
<strong><?= htmlspecialchars($s['name']) ?></strong><br>
📍 <?= htmlspecialchars($s['address']) ?><br>
📞 <?= htmlspecialchars($s['contact_number']) ?><br>
✉ <?= htmlspecialchars($s['email']) ?>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
</div>

</div>
</body>
</html>
