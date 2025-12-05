<?php include "../db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register New Victim</title>
<style>
    /* Reset some default styles */
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

    body {
        background: #f0f4f8;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        padding: 40px 0;
    }

    .form-container {
        background: #fff;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        width: 500px;
    }

    h2 {
        text-align: center;
        margin-bottom: 25px;
        color: #333;
    }

    form {
        display: flex;
        flex-direction: column;
    }

    label {
        margin-top: 15px;
        margin-bottom: 5px;
        font-weight: 600;
        color: #555;
    }

    input[type="text"],
    input[type="number"],
    input[type="email"],
    input[type="date"],
    textarea,
    select {
        padding: 10px 12px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: 14px;
        transition: all 0.2s;
        width: 100%;
    }

    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="email"]:focus,
    input[type="date"]:focus,
    textarea:focus,
    select:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 6px rgba(0,123,255,0.3);
    }

    textarea {
        resize: vertical;
        min-height: 60px;
    }

    .checkbox-group {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }

    .checkbox-group label {
        font-weight: normal;
        color: #555;
    }

    button {
        margin-top: 25px;
        padding: 12px;
        border: none;
        border-radius: 8px;
        background-color: #007bff;
        color: #fff;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.2s;
    }

    button:hover {
        background-color: #0056b3;
    }
</style>
</head>
<body>

<div class="form-container">
    <h2>Register New Victim</h2>
    <form action="insert_victim.php" method="POST">

        <label>Name:</label>
        <input type="text" name="name" required>

        <label>Age:</label>
        <input type="number" name="age" required>

        <label>Gender:</label>
        <select name="gender" required>
            <option value="">--Select Gender--</option>
            <option>Male</option>
            <option>Female</option>
        </select>

        <label>Phone:</label>
        <input type="text" name="phone">

        <label>Address:</label>
        <textarea name="address"></textarea>

        <label>Postal Code:</label>
        <input type="text" name="postal_code">

        <label>District:</label>
        <select name="district" required>
            <option value="">--Select District--</option>
            <option>Jasin</option>
            <option>Melaka Tengah</option>
            <option>Alor Gajah</option>
        </select>

        <label>City:</label>
        <input type="text" name="city">

        <label>Country:</label>
        <input type="text" name="country" value="Malaysia">

        <label>Email:</label>
        <input type="email" name="email">

        <label>Date of Birth:</label>
        <input type="date" name="date_of_birth">

        <label>Family Members:</label>
        <input type="number" name="family_members">

        <div class="checkbox-group">
            <label><input type="checkbox" name="has_baby" value="1"> Has Baby</label>
            <label><input type="checkbox" name="has_elderly" value="1"> Has Elderly</label>
            <label><input type="checkbox" name="has_disabled" value="1"> Has Disabled</label>
        </div>

        <button type="submit">Save Victim</button>
    </form>
</div>

</body>
</html>
