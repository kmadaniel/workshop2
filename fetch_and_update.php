<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'db.php';

// Define the API endpoints
$apis = [
    'medical' => 'http://10.147.17.224:8000/medical_resource_api.php',
    'clothing' => 'http://10.147.17.224:8000/clothing_resource_api.php',
    'food' => 'http://10.147.17.224:8000/food_resource_api.php',
    'shelter' => 'http://10.147.17.224:8000/shelter_resource_api.php'
];

echo "<h2>Update with REAL Resource IDs from APIs</h2>";

// Step 1: Check if we have any needs without resource_id
try {
    $checkStmt = $conn->prepare("
        SELECT need_id, victim_id, disaster_id, temp_resource_name 
        FROM public.needs 
        WHERE resource_id IS NULL OR resource_id LIKE '______%' -- Likely hashes
        ORDER BY need_id
        LIMIT 20
    ");
    $checkStmt->execute();
    $needsToUpdate = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($needsToUpdate) . " records that might need updating<br>";
    
    if (count($needsToUpdate) == 0) {
        echo "No records found. Checking all records...<br>";
        
        // Check what resource_ids we have
        $sampleStmt = $conn->prepare("
            SELECT resource_id, COUNT(*) as count 
            FROM public.needs 
            GROUP BY resource_id 
            ORDER BY count DESC
            LIMIT 10
        ");
        $sampleStmt->execute();
        
        echo "<h3>Current Resource IDs in database:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Resource ID</th><th>Count</th><th>Type</th></tr>";
        while ($row = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
            $type = 'Unknown';
            if (strlen($row['resource_id']) > 20) {
                $type = 'Hash (probably auto-generated)';
            } elseif (is_numeric($row['resource_id'])) {
                $type = 'Numeric ID';
            }
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['resource_id']) . "</td>";
            echo "<td>" . $row['count'] . "</td>";
            echo "<td>" . $type . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit;
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Step 2: Fetch REAL IDs from APIs
echo "<h3>Fetching REAL IDs from APIs...</h3>";

$allRealIds = [];
$apiItems = [];

foreach ($apis as $type => $url) {
    echo "Fetching $type API... ";
    
    $context = stream_context_create([
        'http' => ['timeout' => 10]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $item) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $allRealIds[] = (int)$item['id'];
                    $apiItems[] = [
                        'id' => (int)$item['id'],
                        'name' => $item['name'] ?? 'Unknown',
                        'type' => $type,
                        'quantity' => $item['quantity'] ?? 0
                    ];
                    $count++;
                }
            }
            echo "✅ Found $count real numeric IDs<br>";
        } else {
            echo "❌ Invalid data<br>";
        }
    } else {
        echo "❌ Failed to fetch<br>";
    }
}

if (empty($allRealIds)) {
    die("No real IDs found from APIs!");
}

echo "<br>Total REAL IDs found: " . count($allRealIds) . "<br>";
echo "Sample IDs: " . implode(', ', array_slice($allRealIds, 0, 10)) . "...<br>";

// Show what we found
echo "<h3>Sample API Items:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Quantity</th></tr>";
foreach (array_slice($apiItems, 0, 10) as $item) {
    echo "<tr>";
    echo "<td>" . $item['id'] . "</td>";
    echo "<td>" . htmlspecialchars($item['name']) . "</td>";
    echo "<td>" . $item['type'] . "</td>";
    echo "<td>" . $item['quantity'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Step 3: Update database with REAL IDs
echo "<h3>Updating database with REAL IDs...</h3>";

try {
    // We need to match each need record with an API ID
    // Option 1: Assign IDs sequentially
    // Option 2: Match by some logic (e.g., resource type)
    
    echo "Updating " . count($needsToUpdate) . " records...<br><br>";
    
    $updatedCount = 0;
    
    // Clear any hashes first (optional)
    echo "Clearing any hash-based resource_ids...<br>";
    $clearStmt = $conn->prepare("
        UPDATE public.needs 
        SET resource_id = NULL 
        WHERE resource_id IS NOT NULL 
        AND LENGTH(resource_id) > 10
    ");
    $clearStmt->execute();
    echo "Cleared " . $clearStmt->rowCount() . " hash-based IDs<br><br>";
    
    // Now assign REAL IDs
    foreach ($needsToUpdate as $index => $need) {
        // Get a real ID (cycle through available IDs)
        $realIdIndex = $index % count($allRealIds);
        $realId = $allRealIds[$realIdIndex];
        
        // Get the corresponding item details
        $apiItem = $apiItems[$realIdIndex] ?? $apiItems[0];
        
        try {
            $updateStmt = $conn->prepare("
                UPDATE public.needs 
                SET resource_id = :resource_id,
                    temp_resource_name = :resource_name
                WHERE need_id = :need_id
            ");
            
            $resourceName = $apiItem['name'] . " (" . $apiItem['type'] . ")";
            
            $updateStmt->execute([
                'resource_id' => $realId,
                'resource_name' => $resourceName,
                'need_id' => $need['need_id']
            ]);
            
            if ($updateStmt->rowCount() > 0) {
                $updatedCount++;
                echo "✅ Updated need_id " . $need['need_id'] . " with REAL ID: " . $realId . " (" . $apiItem['name'] . ")<br>";
            }
            
        } catch (PDOException $e) {
            // Skip duplicate constraint errors
            if (strpos($e->getMessage(), 'unique_victim_disaster_resource') === false) {
                echo "❌ Error updating need_id " . $need['need_id'] . ": " . $e->getMessage() . "<br>";
            } else {
                echo "⚠️ Need_id " . $need['need_id'] . ": Duplicate (victim_id, disaster_id, resource_id) combination<br>";
            }
        }
    }
    
    echo "<h3>✅ Update Complete!</h3>";
    echo "Successfully updated $updatedCount records with REAL IDs from APIs<br>";
    
    // Show summary
    echo "<h3>📊 Summary of REAL IDs used:</h3>";
    $idCounts = array_count_values($allRealIds);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Resource ID</th><th>Frequency in APIs</th><th>Sample Name</th></tr>";
    
    $sampleCount = 0;
    foreach ($idCounts as $id => $count) {
        if ($sampleCount++ >= 15) break;
        
        // Find this item
        $itemName = 'Unknown';
        foreach ($apiItems as $item) {
            if ($item['id'] == $id) {
                $itemName = $item['name'] . " (" . $item['type'] . ")";
                break;
            }
        }
        
        echo "<tr>";
        echo "<td>$id</td>";
        echo "<td>$count</td>";
        echo "<td>" . htmlspecialchars($itemName) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>