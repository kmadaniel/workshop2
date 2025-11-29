<!DOCTYPE html>
<html>
<head>
    <title>Victim Disaster System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Optional: Homepage-specific styles */
        body {
            background: linear-gradient(to right, #74ebd5, #ACB6E5);
            color: #fff;
        }
        .container {
            width: 500px;
            margin: 100px auto;
            background-color: rgba(255,255,255,0.95);
            color: #333;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            text-align: center;
        }
        h1 {
            margin-bottom: 30px;
        }
        .btn {
            display: block;
            width: 80%;
            margin: 15px auto;
            padding: 15px;
            font-size: 18px;
            border-radius: 10px;
            text-decoration: none;
            color: #fff;
            background-color: #007bff;
            transition: 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to Victim Disaster System</h1>
        <p>Access services for victims affected by disasters in Melaka.</p>
        <a href="register.php" class="btn">Register as Victim</a>
        <a href="login.php" class="btn">Login</a>
        <a href="view_disaster.php" class="btn">View Affected Areas (Melaka)</a>
    </div>
</body>
</html>
