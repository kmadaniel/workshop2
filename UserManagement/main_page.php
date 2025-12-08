<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        /* NAVBAR */
        .navbar {
            width: 100%;
            padding: 20px 60px;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            border-bottom: 2px solid #eee;
            z-index: 10;
        }

        .nav-left a {
            margin: 0 20px;
            text-decoration: none;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }

        .nav-right a {
            margin-left: 20px;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-login {
            color: #333;
            border: 2px solid #007bff;
        }

        .btn-register {
            background: #007bff;
            color: white;
        }

        /* HERO SECTION */
        .hero {
            height: 100vh;
            background: #7b3ff3; /* Purple background */
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding-top: 80px;
        }

        .hero h1 {
            font-size: 50px;
            font-weight: bold;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.4);
        }

        .hero h2 {
            font-size: 32px;
            margin-top: 10px;
            font-weight: 600;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.4);
        }

        .hero .report-btn {
            margin-top: 40px;
            padding: 15px 40px;
            background: white;
            color: #333;
            font-size: 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .hero .report-btn:hover {
            background: #f0f0f0;
        }

    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="">HOME</a>
            <a href="news.php">NEWS</a>
            <a href="#">MAP</a>
            <a href="#">CONTACT</a>
        </div>

        <div class="nav-right">
            <a href="login.php" class="btn-login">Sign in</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- HERO SECTION -->
    <div class="hero">
        <h1>Welcome to</h1>
        <h2>Disaster Management System</h2>

        <a href="report_incident.php" class="report-btn">Report New Incident</a>
    </div>

</body>
</html>
