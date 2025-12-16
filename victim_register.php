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

        // ✅ FORCE BOOLEAN VALUES
        $has_baby     = isset($_POST['has_baby']) ? true : false;
        $has_elderly  = isset($_POST['has_elderly']) ? true : false;
        $has_disabled = isset($_POST['has_disabled']) ? true : false;

        // Insert victim
        $stmt = $conn->prepare("
            INSERT INTO victim
            (full_name, ic_number, email, phone, address, postal_code, city, district,
             family_members, has_baby, has_elderly, has_disabled)
            VALUES
            (:full_name, :ic, :email, :phone, :address, :postal, :city, :district,
             :family, :baby, :elderly, :disabled)
            RETURNING victim_id
        ");

        $stmt->bindValue(':full_name', $_POST['full_name']);
        $stmt->bindValue(':ic', $_POST['ic_number']);
        $stmt->bindValue(':email', $_POST['email']);
        $stmt->bindValue(':phone', $_POST['phone']);
        $stmt->bindValue(':address', $_POST['address']);
        $stmt->bindValue(':postal', $_POST['postal_code']);
        $stmt->bindValue(':city', $_POST['city']);
        $stmt->bindValue(':district', $_POST['district']);
        $stmt->bindValue(':family', $_POST['family_members'], PDO::PARAM_INT);
        $stmt->bindValue(':baby', $has_baby, PDO::PARAM_BOOL);
        $stmt->bindValue(':elderly', $has_elderly, PDO::PARAM_BOOL);
        $stmt->bindValue(':disabled', $has_disabled, PDO::PARAM_BOOL);

        $stmt->execute();
        $victim_id = $stmt->fetchColumn();

        // Link victim to disaster
        $link = $conn->prepare("
            INSERT INTO victim_disaster (victim_id, disaster_id)
            VALUES (:victim, :disaster)
        ");
        $link->execute([
            ':victim'   => $victim_id,
            ':disaster' => $_POST['disaster_id']
        ]);

        $conn->commit();
        $message = "✅ Registration successful. Your information has been submitted for verification.";

    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "❌ Registration failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Victim Registration</title>
<style>
body {
    font-family:'Segoe UI',sans-serif;
    background:#eef3f8;
}
.container {
    max-width:800px;
    margin:30px auto;
}
.card {
    background:white;
    padding:30px;
    border-radius:16px;
    box-shadow:0 6px 16px rgba(0,0,0,.08);
}
h2 { margin-top:0; }
.section-title {
    margin-top:30px;
    font-size:18px;
    font-weight:600;
    border-left:5px solid #007bff;
    padding-left:10px;
}
input, textarea, select {
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border-radius:8px;
    border:1px solid #ccc;
}
label { font-weight:600; display:block; margin-bottom:6px; }
button {
    background:#007bff;
    color:white;
    padding:16px;
    border:none;
    border-radius:12px;
    width:100%;
    cursor:pointer;
    font-size:16px;
}
button:hover { background:#0056b3; }
.message {
    padding:14px;
    margin-bottom:20px;
    border-radius:10px;
}
.success { background:#e8f5e9; color:#2e7d32; }
.error { background:#fdd; color:#c62828; }

.guidelines {
    background:#f1f8ff;
    border-left:5px solid #007bff;
    padding:15px;
    border-radius:10px;
    margin-bottom:25px;
}
.note {
    background:#fff3cd;
    border-left:5px solid #ffc107;
    padding:12px;
    border-radius:10px;
    font-size:14px;
}
.checkbox-group label {
    font-weight:normal;
}
.back {
    margin-bottom:15px;
    display:inline-block;
}
</style>
</head>

<body>
<div class="container">

<a href="index.php" class="back">← Back to Dashboard</a>

<div class="card">

<h2>🧾 Victim Registration Form</h2>

<div class="guidelines">
<strong>Please read before registering:</strong>
<ul>
<li>Each victim can register <strong>once per disaster</strong>.</li>
<li>False or misleading information may cause rejection.</li>
<li>Aid priority is based on <strong>family size and vulnerability</strong>.</li>
<li>Email verification may be required before assistance.</li>
<li>Special requests depend on <strong>available stock</strong>.</li>
</ul>
</div>

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
<option value="<?= $d['disaster_id'] ?>">
<?= $d['disaster_name'] ?> (<?= $d['district'] ?>)
</option>
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
<input name="city" placeholder="City">
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
You may request specific items such as baby supplies or medicine.
Approval depends on stock availability and assessment.
</div>

<input name="special_needs" placeholder="e.g. Pampers size M, baby formula, specific medicine">

<button type="submit">Submit Registration</button>

</form>

</div>
</div>
</body>
</html>
