<?php include "../db.php"; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Disaster</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef3f7;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 50%;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { font-weight: bold; }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin: 8px 0 18px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        button {
            width: 100%;
            padding: 12px;
            border: none;
            background: #005dc9;
            color: white;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover { background: #003f8a; }
    </style>
</head>
<body>

<div class="container">
<h2>Register New Disaster</h2>

<form action="insert_disaster.php" method="POST">

    <label>Disaster Name:</label>
    <input type="text" name="disaster_name" required>

    <label>Description:</label>
    <textarea name="description" rows="4" required></textarea>

    <label>District:</label>
    <select name="district" required>
        <option value="Jasin">Jasin</option>
        <option value="Melaka Tengah">Melaka Tengah</option>
        <option value="Alor Gajah">Alor Gajah</option>
    </select>

    <label>Severity (1–10):</label>
    <input type="number" name="severity" min="1" max="10" required>

    <label>Alert Message:</label>
    <input type="text" name="alert_message">

    <label>Start Date:</label>
    <input type="date" name="start_date">

    <label>End Date:</label>
    <input type="date" name="end_date">

    <label>Affected People:</label>
    <input type="number" name="affected_people" min="0">

    <button type="submit">Save Disaster</button>
</form>

</div>

</body>
</html>
