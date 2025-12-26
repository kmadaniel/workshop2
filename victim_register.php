<?php
include "db.php";
$message = "";

// Fetch active disasters
$disasters = $conn->query("
    SELECT disaster_id, disaster_name, district
    FROM disaster
    WHERE status IN ('Active', 'Under Control')
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $conn->beginTransaction();

        // Force boolean values for checkboxes
        $has_baby     = !empty($_POST['has_baby']) ? true : false;
        $has_elderly  = !empty($_POST['has_elderly']) ? true : false;
        $has_disabled = !empty($_POST['has_disabled']) ? true : false;

        // Generate a verification token
        $verification_token = bin2hex(random_bytes(16));

        // Insert victim
        $stmt = $conn->prepare("
            INSERT INTO victim
            (full_name, ic_number, email, phone, email_verified, verification_token, address,
             postal_code, city, district, country, family_members, has_baby, has_elderly,
             has_disabled, created_at, disaster_id, special_request)
            VALUES
            (:full_name, :ic, :email, :phone, false, :token, :address,
             :postal, :city, :district, 'Malaysia', :family, :baby, :elderly,
             :disabled, NOW(), :disaster, :special_request)
            RETURNING victim_id
        ");

        $stmt->bindValue(':full_name', $_POST['full_name']);
        $stmt->bindValue(':ic', $_POST['ic_number']);
        $stmt->bindValue(':email', $_POST['email']);
        $stmt->bindValue(':phone', $_POST['phone']);
        $stmt->bindValue(':token', $verification_token);
        $stmt->bindValue(':address', $_POST['address']);
        $stmt->bindValue(':postal', $_POST['postal_code']);
        $stmt->bindValue(':city', $_POST['city'] ?: 'Melaka');
        $stmt->bindValue(':district', $_POST['district']);
        $stmt->bindValue(':family', $_POST['family_members'], PDO::PARAM_INT);
        $stmt->bindValue(':baby', $has_baby, PDO::PARAM_BOOL);
        $stmt->bindValue(':elderly', $has_elderly, PDO::PARAM_BOOL);
        $stmt->bindValue(':disabled', $has_disabled, PDO::PARAM_BOOL);
        $stmt->bindValue(':disaster', $_POST['disaster_id'], PDO::PARAM_INT);
        $stmt->bindValue(':special_request', $_POST['special_needs'] ?? '', PDO::PARAM_STR);

        $stmt->execute();
        $victim_id = $stmt->fetchColumn();

        $conn->commit();

        // TODO: send email verification with $verification_token here
        $message = "✅ Registration successful! Please check your email to verify your account.";

    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "❌ Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Victim Registration - Melaka Disaster Assistance</title>

<!-- Dashboard CSS -->
<link rel="stylesheet" href="header.css">

<!-- Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- GLOBAL FONT -->
<style>
* { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
input, textarea, select { width:100%; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #ccc; box-sizing:border-box; }
label { font-weight:600; display:block; margin-bottom:6px; }
button { background:#007bff; color:white; padding:16px; border:none; border-radius:12px; width:100%; cursor:pointer; font-size:16px; }
button:hover { background:#0056b3; }
.message { padding:14px; margin-bottom:20px; border-radius:10px; }
.success { background:#e8f5e9; color:#2e7d32; }
.error { background:#fdd; color:#c62828; }
.checkbox-group label { font-weight:normal; }
.note { background:#fff3cd; border-left:5px solid #ffc107; padding:12px; border-radius:10px; font-size:14px; }
.section-title { margin-top:20px; font-size:18px; font-weight:600; border-left:5px solid #007bff; padding-left:10px; }
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
            <a href="index.php" class="nav-link">
                <i class="fas fa-house"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="victim_register.php" class="nav-link active">
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

<!-- ================= MAIN CONTENT ================= -->
<div class="main-content">

<div class="card">
<h2>🧾 Victim Registration Form</h2>

<?php if ($message): ?>
<div class="message <?= str_contains($message,'✅') ? 'success':'error' ?>">
<?= $message ?>
</div>
<?php endif; ?>

<form method="POST">

<div class="section-title">1️⃣ Disaster Information</div>
<label>Select Disaster</label>
<select name="disaster_id" required>
<option value="">-- Please select the affected disaster --</option>
<?php foreach ($disasters as $d): ?>
<option value="<?= $d['disaster_id'] ?>"><?= htmlspecialchars($d['disaster_name']) ?> (<?= htmlspecialchars($d['district']) ?>)</option>
<?php endforeach; ?>
</select>

<div class="section-title">2️⃣ Personal Information</div>
<input name="full_name" placeholder="Full Name (as per IC)" required>
<input name="ic_number" placeholder="IC Number" required>
<input name="email" type="email" placeholder="Email Address" required>
<input name="phone" placeholder="Phone Number">

<div class="section-title">3️⃣ Address Details</div>
<textarea name="address" placeholder="Full Address" required></textarea>
<input name="postal_code" placeholder="Postal Code">
<input name="city" placeholder="City" value="Melaka">
<input name="district" placeholder="District" required>

<div class="section-title">4️⃣ Household Information</div>
<label>Number of Family Members</label>
<input type="number" name="family_members" min="1" value="1">
<div class="checkbox-group">
<label><input type="checkbox" name="has_baby"> Household has baby (below 2 years)</label>
<label><input type="checkbox" name="has_elderly"> Household has elderly</label>
<label><input type="checkbox" name="has_disabled"> Household has disabled member</label>
</div>

<div class="section-title">5️⃣ Special Needs Request (Optional)</div>
<div class="note">
You may request specific items such as baby supplies or medicine. Approval depends on stock availability and assessment.
</div>
<input name="special_needs" placeholder="e.g. Pampers size M, baby formula, specific medicine">

<button type="submit">Submit Registration</button>
</form>
</div>

</div>
</body>
</html>
