<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';

// Distribution API URL
$distributionUrl = 'http://10.147.17.154:8000/distribution_module/distribution.php';

echo "<h2>Fetch Distribution IDs and Update Needs Table</h2>";

// Step 1: Fetch data from distribution API
echo "<h3>Step 1: Fetching distribution data...</h3>";

$context = stream_context_create([
    'http' => ['timeout' => 15]
]);

$response = @file_get_contents($distributionUrl, false, $context);

if ($response === false) {
    die("❌ Failed to fetch from distribution API: $distributionUrl<br>
         Make sure the server is running and accessible.");
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Raw response: " . htmlspecialchars(substr($response, 0, 500)) . "...<br>";
    die("❌ Invalid JSON response: " . json_last_error_msg());
}

if (!is_array($data)) {
    die("❌ Data is not an array. Response: " . print_r($data, true));
}

echo "✅ Successfully fetched distribution data<br>";
echo "Total distribution records: " . count($data) . "<br><br>";

// Step 2: Show what we got
echo "<h3>Step 2: Distribution Data Sample</h3>";

// Check the structure
if (isset($data[0])) {
    $firstItem = $data[0];
    echo "Data structure keys: " . implode(', ', array_keys($firstItem)) . "<br>";
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr>";
    foreach (array_keys($firstItem) as $key) {
        echo "<th>" . htmlspecialchars($key) . "</th>";
    }
    echo "</tr>";
    
    // Show first 5 rows
    for ($i = 0; $i < min(5, count($data)); $i++) {
        echo "<tr>";
        foreach ($data[$i] as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table><br>";
}

// Step 3: Find distribution_id field
$distributionIdField = null;
$possibleIdFields = ['distribution_id', 'id', 'ID', 'distributionid', 'distributionId'];

foreach ($possibleIdFields as $field) {
    if (isset($data[0][$field])) {
        $distributionIdField = $field;
        break;
    }
}

if (!$distributionIdField) {
    echo "<h4>Available fields in first item:</h4>";
    echo "<pre>" . print_r($data[0], true) . "</pre>";
    die("❌ Could not find distribution ID field. Please check the field name above.");
}

echo "✅ Found distribution ID field: '$distributionIdField'<br>";

// Step 4: Check needs table status
echo "<h3>Step 3: Checking needs table...</h3>";

try {
    // Count needs with NULL distribution_id
    $nullStmt = $conn->prepare("
        SELECT COUNT(*) as null_count 
        FROM public.needs 
        WHERE distribution_id IS NULL
    ");
    $nullStmt->execute();
    $nullCount = $nullStmt->fetch(PDO::FETCH_ASSOC)['null_count'];
    
    // Count total needs
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM public.needs");
    $totalStmt->execute();
    $totalNeeds = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Count needs with existing distribution_id
    $existingStmt = $conn->prepare("
        SELECT COUNT(*) as existing_count 
        FROM public.needs 
        WHERE distribution_id IS NOT NULL
    ");
    $existingStmt->execute();
    $existingCount = $existingStmt->fetch(PDO::FETCH_ASSOC)['existing_count'];
    
    echo "Total needs records: $totalNeeds<br>";
    echo "Needs with distribution_id: $existingCount<br>";
    echo "Needs with NULL distribution_id: $nullCount<br><br>";
    
    if ($nullCount == 0) {
        echo "ℹ️ All needs already have distribution_id assigned.<br>";
        echo "Do you want to reassign?<br>";
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Step 5: Update strategy
echo "<h3>Step 4: Update Strategy</h3>";

// Collect all distribution IDs
$distributionIds = [];
foreach ($data as $item) {
    if (isset($item[$distributionIdField])) {
        $distributionIds[] = $item[$distributionIdField];
    }
}

echo "Available distribution IDs: " . count($distributionIds) . "<br>";
echo "Sample IDs: " . implode(', ', array_slice($distributionIds, 0, 10)) . "...<br>";

if (count($distributionIds) == 0) {
    die("❌ No distribution IDs found in the API data.");
}

// Step 6: Update needs table
echo "<h3>Step 5: Updating needs table...</h3>";

// Check if we need to match by some criteria or just assign sequentially
echo "How do you want to match needs with distributions?<br>";
echo "1. Assign sequentially (need 1 → distribution 1, need 2 → distribution 2, etc.)<br>";
echo "2. Match by victim_id (if distribution has victim_id field)<br>";
echo "3. Match by disaster_id (if distribution has disaster_id field)<br>";
echo "4. Random assignment<br><br>";

// Let's check if distribution data has matching fields
$hasVictimId = isset($data[0]['victim_id']) || isset($data[0]['victimid']) || isset($data[0]['victimId']);
$hasDisasterId = isset($data[0]['disaster_id']) || isset($data[0]['disasterid']) || isset($data[0]['disasterId']);

echo "Distribution data has victim_id field: " . ($hasVictimId ? 'Yes' : 'No') . "<br>";
echo "Distribution data has disaster_id field: " . ($hasDisasterId ? 'Yes' : 'No') . "<br><br>";

// For now, let's do sequential assignment
echo "Using sequential assignment...<br>";

try {
    // Get all needs that need distribution_id
    $needsStmt = $conn->prepare("
        SELECT need_id, victim_id, disaster_id 
        FROM public.needs 
        WHERE distribution_id IS NULL
        ORDER BY need_id
    ");
    $needsStmt->execute();
    $needs = $needsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Needs to update: " . count($needs) . "<br>";
    
    if (count($needs) == 0) {
        echo "✅ No needs need updating.<br>";
        exit;
    }
    
    $updated = 0;
    $errors = 0;
    
    // Assign distribution IDs sequentially
    foreach ($needs as $index => $need) {
        $needId = $need['need_id'];
        
        // Cycle through distribution IDs
        $distIndex = $index % count($distributionIds);
        $distributionId = $distributionIds[$distIndex];
        
        try {
            $updateStmt = $conn->prepare("
                UPDATE public.needs 
                SET distribution_id = :distribution_id
                WHERE need_id = :need_id
            ");
            
            $updateStmt->execute([
                'distribution_id' => $distributionId,
                'need_id' => $needId
            ]);
            
            $updated++;
            
            // Show progress
            if ($updated <= 20) {
                echo "✅ Need $needId → Distribution $distributionId<br>";
            } elseif ($updated == 21) {
                echo "... (showing first 20 updates)<br>";
            }
            
        } catch (PDOException $e) {
            $errors++;
            echo "❌ Error updating need $needId: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h3>✅ Update Complete!</h3>";
    echo "Successfully updated: $updated needs<br>";
    echo "Errors: $errors<br>";
    
    // Final stats
    $finalStmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN distribution_id IS NULL THEN 1 ELSE 0 END) as null_count,
            COUNT(DISTINCT distribution_id) as unique_distributions
        FROM public.needs
    ");
    $finalStmt->execute();
    $finalStats = $finalStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Final Statistics:</h3>";
    echo "Total needs: " . $finalStats['total'] . "<br>";
    echo "Needs without distribution: " . $finalStats['null_count'] . "<br>";
    echo "Unique distribution IDs assigned: " . $finalStats['unique_distributions'] . "<br>";
    
    // Show sample of updated records
    $sampleStmt = $conn->prepare("
        SELECT need_id, victim_id, disaster_id, distribution_id 
        FROM public.needs 
        WHERE distribution_id IS NOT NULL
        ORDER BY need_id
        LIMIT 10
    ");
    $sampleStmt->execute();
    
    echo "<h3>Sample Updated Records:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Need ID</th><th>Victim ID</th><th>Disaster ID</th><th>Distribution ID</th></tr>";
    while ($row = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['need_id'] . "</td>";
        echo "<td>" . $row['victim_id'] . "</td>";
        echo "<td>" . $row['disaster_id'] . "</td>";
        echo "<td>" . $row['distribution_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>