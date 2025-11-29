<?php
include 'db.php';

// Get all disasters with location in Melaka
$locations = $conn->query("
    SELECT d.disaster_name, l.city, l.district, l.severity_level, l.location_id
    FROM disaster d
    JOIN location l ON l.location_id = d.disaster_id
    WHERE l.city='Melaka'
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Affected Areas - Melaka</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 500px; width: 80%; margin: auto; margin-top: 50px; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Affected Areas in Melaka</h2>
    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Initialize map (centered on Melaka)
        var map = L.map('map').setView([2.1896, 102.2501], 11); // coordinates for Melaka

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data © <a href="https://openstreetmap.org">OpenStreetMap</a>',
        }).addTo(map);

        // Add markers from PHP
        var locations = <?php echo json_encode($locations); ?>;
        locations.forEach(function(loc){
            // In real project, you would store latitude/longitude for each location
            // For now, we will randomly place them around Melaka as example
            var lat = 2.18 + Math.random()*0.05; 
            var lng = 102.25 + Math.random()*0.05; 
            L.marker([lat,lng]).addTo(map)
                .bindPopup("<b>" + loc.disaster_name + "</b><br>District: " + loc.district + "<br>Severity: " + loc.severity_level);
        });
    </script>
</body>
</html>
