<?php
require_once 'config.php';

// Use the global connection directly
global $conn;
$db = $conn;

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
        distribution_volunteer_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        volunteer_id BIGINT,
        distribution_id BIGINT,
        role VARCHAR(50),
        status VARCHAR(20) DEFAULT 'assigned',
        assignment_notes TEXT,
        assigned_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (volunteer_id) REFERENCES volunteer(volunteer_id),
        FOREIGN KEY (distribution_id) REFERENCES distribution(distribution_id)
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
    $check_query = "SELECT volunteer_id FROM volunteer WHERE name = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bind_param("s", $volunteer[0]);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0) {
        $insert_query = "INSERT INTO volunteer (name, phone, role) VALUES (?, ?, ?)";
        $stmt = $db->prepare($insert_query);
        $stmt->bind_param("sss", $volunteer[0], $volunteer[1], $volunteer[2]);
        
        if ($stmt->execute()) {
            $inserted++;
            echo "✅ Inserted: {$volunteer[0]}<br>";
        }
        $stmt->close();
    } else {
        echo "ℹ️ Volunteer already exists: {$volunteer[0]}<br>";
    }
    $check_stmt->close();
}

echo "<br>✅ Setup complete! Inserted $inserted new volunteers<br>";

// Show all volunteers
$result = $db->query("SELECT * FROM volunteer ORDER BY volunteer_id");
echo "<h3>Current Volunteers in Database:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Name</th><th>Phone</th><th>Role</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['volunteer_id']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['phone']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>✅ Setup Complete!</h3>";
echo "<p><a href='distribution_main.php' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>Go to Distribution Dashboard</a></p>";
?>