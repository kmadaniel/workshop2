<?php
// debug_api.php - Check raw API responses

$DISASTER_API_URL = 'http://10.147.17.116:8000/disaster.php';
$VICTIM_API_URL = 'http://10.147.17.116:8000/needs.php';

function fetchRawData($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    return $response;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>API Debug Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .api-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow: auto; }
        .url { background: #3498db; color: white; padding: 5px 10px; border-radius: 4px; margin-bottom: 10px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <h1>API Debug Tool</h1>
        
        <div class="api-section">
            <h2>Disaster API</h2>
            <div class="url">URL: <?php echo htmlspecialchars($DISASTER_API_URL); ?></div>
            <button onclick="fetchData('disaster')">Fetch Data</button>
            <div id="disaster-result" style="margin-top: 20px;"></div>
        </div>
        
        <div class="api-section">
            <h2>Victim API</h2>
            <div class="url">URL: <?php echo htmlspecialchars($VICTIM_API_URL); ?></div>
            <button onclick="fetchData('victim')">Fetch Data</button>
            <div id="victim-result" style="margin-top: 20px;"></div>
        </div>
    </div>
    
    <script>
        function fetchData(type) {
            const resultDiv = document.getElementById(type + '-result');
            resultDiv.innerHTML = '<div style="padding: 20px; text-align: center;">Fetching data...</div>';
            
            const url = type === 'disaster' ? 'fetch_api.php?type=disaster' : 'fetch_api.php?type=victim';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <h3>Response (${data.data.length} items):</h3>
                            <pre>${JSON.stringify(data.data, null, 2)}</pre>
                            <h3>First item details:</h3>
                            <pre>${data.data[0] ? JSON.stringify(data.data[0], null, 2) : 'No data'}</pre>
                        `;
                    } else {
                        resultDiv.innerHTML = `<div style="color: red; padding: 10px; background: #ffebee; border-radius: 5px;">
                            <strong>Error:</strong> ${data.error}
                        </div>`;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `<div style="color: red; padding: 10px; background: #ffebee; border-radius: 5px;">
                        <strong>Fetch Error:</strong> ${error}
                    </div>`;
                });
        }
    </script>
</body>
</html>