<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>Setting Up Volunteers and Tables</h2>";

// Create volunteer table if not exists
$create_volunteer_table = "
    CREATE TABLE IF NOT EXISTS volunteer (
        volunteer_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        phone VARCHAR(20),
        role VARCHAR(50)
    )
";

if ($db->query($create_volunteer_table)) {
    echo "✅ Volunteer table created/verified<br>";
} else {
    echo "❌ Error creating volunteer table: " . $db->error . "<br>";
}

// Create distribution_volunteer table if not exists
$create_dv_table = "
    CREATE TABLE IF NOT EXISTS distribution_volunteer (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        volunteer_id BIGINT,
        distribution_id BIGINT,
        role VARCHAR(50),
        status VARCHAR(20) DEFAULT 'Assigned',
        assignment_notes TEXT,
        assigned_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";

if ($db->query($create_dv_table)) {
    echo "✅ Distribution_volunteer table created/verified<br>";
} else {
    echo "❌ Error creating distribution_volunteer table: " . $db->error . "<br>";
}

// Insert sample volunteers
$sample_volunteers = [
    ['Muhammad Farhan', '012-3456789', 'Driver'],
    ['Nurul Huda', '013-9876543', 'Coordinator'],
    ['Raj Kumar', '014-5566778', 'Helper'],
    ['Siti Aminah', '015-1122334', 'Medical Staff'],
    ['Ahmad Firdaus', '016-5566778', 'Logistics']
];

$inserted = 0;
foreach ($sample_volunteers as $volunteer) {
    $insert_query = "INSERT IGNORE INTO volunteer (name, phone, role) VALUES (?, ?, ?)";
    $stmt = $db->prepare($insert_query);
    $stmt->bind_param("sss", $volunteer[0], $volunteer[1], $volunteer[2]);
    
    if ($stmt->execute()) {
        if ($db->affected_rows > 0) {
            $inserted++;
        }
    }
    $stmt->close();
}

echo "✅ Inserted $inserted sample volunteers<br>";

echo "<h3>Setup Complete!</h3>";
echo "<p><a href='assign_volunteer.php?id=1' class='btn btn-success'>Test Assign Volunteer Page</a></p>";
echo "<p><a href='distribution_main.php' class='btn btn-primary'>Go to Dashboard</a></p>";
?>