<?php
include "db.php";

// Check which page to show
$page = $_GET['page'] ?? 'dashboard';
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'victim_register') {
    // Get victim info
    $full_name = $_POST['full_name'];
    $ic_number = $_POST['ic_number'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $postal_code = $_POST['postal_code'];
    $city = $_POST['city'];
    $district = $_POST['district'];
    $family_members = $_POST['family_members'];
    $has_baby = !empty($_POST['has_baby']) ? 't' : 'f';
    $has_elderly = !empty($_POST['has_elderly']) ? 't' : 'f';
    $has_disabled = !empty($_POST['has_disabled']) ? 't' : 'f';


    try {
        // Start transaction
        $conn->beginTransaction();

        // Insert victim
        $stmt = $conn->prepare("INSERT INTO victim
            (full_name, ic_number, email, phone, address, postal_code, city, district, family_members, has_baby, has_elderly, has_disabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING victim_id");
        $stmt->execute([$full_name, $ic_number, $email, $phone, $address, $postal_code, $city, $district, $family_members, $has_baby, $has_elderly, $has_disabled]);
        $victim_id = $stmt->fetchColumn();

        // Insert victim request
        // For simplicity, pick the first active disaster
        $disaster = $conn->query("SELECT disaster_id FROM disaster WHERE status='Active' ORDER BY severity DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $disaster_id = $disaster ? $disaster['disaster_id'] : null;

        if ($disaster_id) {
            $items = array_filter($_POST['item_name']); // remove empty names
            $remarks = "Requested items: " . implode(", ", $items);

            $stmt2 = $conn->prepare("INSERT INTO victim_requests (victim_id, disaster_id, remarks) VALUES (?, ?, ?)");
            $stmt2->execute([$victim_id, $disaster_id, $remarks]);
        }

        $conn->commit();
        $message = "✅ Registration successful! Your request has been submitted.";
    } catch (Exception $e) {
        $conn->rollBack();
        $message = "❌ Registration failed: " . $e->getMessage();
    }
}

// Fetch active disasters for dashboard
$disasters = $conn->query(
    "SELECT * FROM disaster WHERE status = 'Active' ORDER BY severity DESC"
)->fetchAll(PDO::FETCH_ASSOC);
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
h1,h2,h3 { margin-top:0; color:#333; }
a { text-decoration:none; }
.submit-btn, .action-btn { display:block; background:#007bff; color:white; border:none; padding:15px; margin-bottom:10px; border-radius:10px; text-align:center; cursor:pointer; transition:0.2s ease; }
.submit-btn:hover, .action-btn:hover { background:#0056b3; }
.back-btn { display:inline-block; margin-bottom:20px; color:#007bff; font-weight:bold; }
.back-btn:hover { text-decoration:underline; }
form label { display:block; font-weight:600; margin-bottom:6px; color:#444; }
form input, form textarea { width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; margin-bottom:15px; font-size:15px; }
form textarea { resize: vertical; }
.checkbox-group label { font-weight:normal; display:block; margin-bottom:8px; }
.items { display:grid; grid-template-columns:2fr 1fr; gap:10px; margin-bottom:10px; }
.actions { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
.action-card { background:white; border-radius:12px; padding:25px; text-align:center; color:#333; box-shadow:0 3px 8px rgba(0,0,0,0.1); transition:0.2s ease; }
.action-card:hover { transform:translateY(-4px); box-shadow:0 8px 20px rgba(0,0,0,0.15); }
.action-card span { font-size:36px; display:block; margin-bottom:10px; }
.alerts p { background:#f8f9fa; padding:12px; border-left:5px solid #dc3545; border-radius:6px; margin-bottom:12px; }
.note { background:#f1f8ff; padding:15px; border-left:4px solid #007bff; border-radius:8px; margin-bottom:20px; font-size:14px; }
.message { padding:12px; margin-bottom:20px; border-radius:8px; font-weight:bold; }
.success { background:#e8f5e9; color:#28a745; border-left:4px solid #28a745; }
.error { background:#fdd; color:#d9534f; border-left:4px solid #d9534f; }
</style>
</head>
<body>

<div class="header">
    <h1>🤝 Melaka Disaster Assistance Portal</h1>
    <p>Supporting Melaka residents during emergency situations</p>
</div>

<div class="container">

<?php if ($page === 'victim_register'): ?>
    <a href="index.php" class="back-btn">← Back to Dashboard</a>

    <div class="card">
        <h2>🧾 Victim Registration</h2>
        <div class="note">
            📌 <b>Guidelines for registering:</b>
            <ul>
                <li>One registration per victim for each disaster.</li>
                <li>False or misleading information may be rejected.</li>
                <li>Aid priority is based on family size and vulnerability.</li>
                <li>Email verification is required before assistance.</li>
                <li>You can request items like pampers, medicine, or baby formula based on availability.</li>
            </ul>
        </div>

        <div class="note" style="border-left:4px solid #28a745; background:#e8f5e9;">
            💙 Stay safe, Melaka residents! Fill in accurate info to help us assist you better.
        </div>

        <?php if($message): ?>
            <div class="message <?= strpos($message,'success')!==false ? 'success':'error' ?>"><?= $message ?></div>
        <?php endif; ?>

        <form action="index.php?page=victim_register" method="POST">
            <h3>👤 Personal Info</h3>
            <label>Full Name</label>
            <input type="text" name="full_name" required>
            <label>IC Number</label>
            <input type="text" name="ic_number" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Phone</label>
            <input type="text" name="phone">

            <h3>🏠 Address</h3>
            <label>Address</label>
            <textarea name="address" required></textarea>
            <label>Postal Code</label>
            <input type="text" name="postal_code">
            <label>City</label>
            <input type="text" name="city">
            <label>District</label>
            <input type="text" name="district" required>

            <h3>👪 Family & Vulnerabilities</h3>
            <label>Number of Family Members</label>
            <input type="number" name="family_members" min="1" value="1">
            <div class="checkbox-group">
                <label><input type="checkbox" name="has_baby"> Baby / Infant</label>
                <label><input type="checkbox" name="has_elderly"> Elderly</label>
                <label><input type="checkbox" name="has_disabled"> Disabled Person</label>
            </div>

            <h3>📦 Assistance Requests</h3>
            <p>You can request multiple items. Quantity depends on availability.</p>
            <div class="items">
                <input type="text" name="item_name[]" placeholder="Item name">
                <input type="number" name="quantity[]" min="1" placeholder="Qty">
            </div>
            <div class="items">
                <input type="text" name="item_name[]" placeholder="Item name">
                <input type="number" name="quantity[]" min="1" placeholder="Qty">
            </div>

            <button type="submit" class="submit-btn">Submit Registration</button>
        </form>

        <a href="report_disaster.php" class="action-card">
              <span>🚨</span>
              <strong>Report a Disaster</strong>
             <p>Inform authorities about a new disaster</p>
</a>

    </div>

<?php else: ?>
    <!-- Dashboard -->
    <div class="card">
        <h3>💙 Welcome</h3>
        <p>This portal helps Melaka residents register for disaster assistance, report disasters, and receive updates.</p>
    </div>

    <!-- Disaster Alerts -->
    <div class="card alerts">
        <h3>🚨 Current Disaster Alerts</h3>
        <?php if (empty($disasters)): ?>
            <p>No active disasters reported at the moment.</p>
        <?php else: ?>
            <?php foreach ($disasters as $d): ?>
                <p>
                    <strong><?= htmlspecialchars($d['disaster_name']) ?></strong><br>
                    📍 <?= $d['district'] ?> | ⚠ Severity: <?= $d['severity'] ?><br>
                    <?= $d['alert_message'] ?>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Dashboard Actions -->
    <div class="card">
        <h3>📌 Actions</h3>
        <div class="actions">
            <a href="index.php?page=victim_register" class="action-card">
                <span>🧾</span>
                <strong>Register as Affected Victim</strong>
                <p>Request assistance for yourself and your family</p>
            </a>

            <a href="report_disaster.php" class="action-card">
                <span>🚨</span>
                <strong>Report a Disaster</strong>
                <p>Inform authorities about a new disaster</p>
            </a>

            <a href="feedback.php" class="action-card">
                <span>⭐</span>
                <strong>Give Feedback</strong>
                <p>Help us improve this system</p>
            </a>
        </div>
    </div>
<?php endif; ?>

</div>
</body>
</html>
