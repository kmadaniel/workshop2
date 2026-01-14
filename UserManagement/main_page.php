<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disaster Relief Resource Management System (Negeri Melaka)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include Slick Slider CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            overflow-x: hidden;
        }

        /* NAVBAR IMPROVED */
        .navbar {
            width: 100%;
            padding: 15px 60px;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            padding: 10px 60px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }

        .nav-left a {
            margin: 0 25px;
            text-decoration: none;
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
            position: relative;
            transition: color 0.3s;
            padding: 5px 0;
        }

        .nav-left a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
            transition: width 0.3s ease;
        }

        .nav-left a:hover::after,
        .nav-left a.active::after {
            width: 100%;
        }

        .nav-left a:hover {
            color: #007bff;
        }

        .nav-right a {
            margin-left: 20px;
            padding: 12px 28px;
            text-decoration: none;
            border-radius: 30px;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .btn-login {
            color: #007bff;
            border: 2px solid #007bff;
            background: transparent;
        }

        .btn-login:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }

        .btn-register {
            background: linear-gradient(135deg, #007bff 0%, #00b4ff 100%);
            color: white;
            border: none;
        }

        .btn-register:hover {
            background: linear-gradient(135deg, #0056b3 0%, #0088cc 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 123, 255, 0.4);
        }

        /* HERO SECTION WITH SLIDER */
        .hero {
            height: 100vh;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding-top: 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .hero-slide {
            height: 100vh;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }

        .hero-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(30, 60, 114, 0.85) 0%, 
                rgba(42, 82, 152, 0.8) 50%,
                rgba(0, 123, 255, 0.7) 100%);
        }

        /* Melaka disaster images */
        .slide-1 {
            background-image: url('https://3.bp.blogspot.com/-0vMqWZs5IOM/V0WxfZ6tpuI/AAAAAAAAAGE/4VNMQ-gQi2Q8_doXrQ9SF1QYnEgxLZ4lgCLcB/s1600/doa-anti-banjir.jpg');
        }

        .slide-2 {
            background-image: url('https://www.kosmo.com.my/wp-content/uploads/2023/02/GEMPA1.jpg');
        }

        .slide-3 {
            background-image: url('https://images.unsplash.com/photo-1589652717521-10c0d092dea9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
        }

        .slide-4 {
            background-image: url('https://images.unsplash.com/photo-1506197603052-3cc9c3a201bd?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
        }

        .slide-5 {
            background-image: url('https://images.unsplash.com/photo-1515168833906-d2a3b82b5d63?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
        }

        .hero-content {
            position: relative;
            z-index: 3;
            max-width: 1000px;
            padding: 0 30px;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 15px;
          
            line-height: 1.1;
            animation: fadeInUp 1s ease;
        }

        .hero h2 {
            font-size: 2.5rem;
            margin-bottom: 25px;
            font-weight: 600;
            color: #ffcc00;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease 0.3s both;
        }

        .hero p {
            font-size: 1.4rem;
            margin-bottom: 40px;
            opacity: 0.95;
            max-width: 800px;
            line-height: 1.8;
            margin-left: auto;
            margin-right: auto;
            font-weight: 300;
            animation: fadeInUp 1s ease 0.6s both;
        }

        .hero-buttons {
            display: flex;
            gap: 25px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
            animation: fadeInUp 1s ease 0.9s both;
        }

        .report-btn {
            padding: 18px 50px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            font-size: 1.3rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .report-btn:hover {
            background: linear-gradient(135deg, #ff5252 0%, #ff3838 100%);
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(255, 107, 107, 0.4);
        }

        .info-btn {
            padding: 18px 50px;
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 1.3rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            backdrop-filter: blur(5px);
        }

        .info-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        /* SLICK SLIDER CUSTOMIZATION */
        .slick-dots {
            bottom: 30px !important;
        }

        .slick-dots li button:before {
            font-size: 12px;
            color: white;
            opacity: 0.5;
        }

        .slick-dots li.slick-active button:before {
            color: #ffcc00;
            opacity: 1;
        }

        .slick-dots li button:hover:before {
            color: #ffcc00;
        }

        /* FEATURES SECTION ENHANCED */
        .features-section {
            padding: 120px 60px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            position: relative;
            overflow: hidden;
        }

        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
        }

        .section-title {
            text-align: center;
            margin-bottom: 80px;
            position: relative;
        }

        .section-title h2 {
            font-size: 3rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 700;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
            border-radius: 2px;
        }

        .section-title p {
            color: #666;
            font-size: 1.2rem;
            max-width: 700px;
            margin: 30px auto 0;
            line-height: 1.8;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
            margin-top: 40px;
        }

        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 50px 35px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
        }

        .feature-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 2.5rem;
            transition: all 0.4s ease;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .feature-card:hover .feature-icon {
            transform: rotateY(180deg) scale(1.1);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .feature-card h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .feature-card p {
            color: #666;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        /* STATS SECTION ENHANCED */
        .stats-section {
            padding: 100px 60px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,100 L1000,0 L1000,100 Z" fill="rgba(255,255,255,0.05)"/></svg>');
            background-size: cover;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 40px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .stat-item {
            padding: 40px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .stat-item h3 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 15px;
            color: #ffcc00;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .stat-item p {
            font-size: 1.3rem;
            opacity: 0.95;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* FOOTER ENHANCED */
        .footer {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 60px 60px 30px;
            position: relative;
            overflow: hidden;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 50px;
            margin-bottom: 40px;
        }

        .footer-section h4 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            color: #ffcc00;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-section h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
            border-radius: 2px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 15px;
        }

        .footer-links a {
            color: #b0b0b0;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-links a:hover {
            color: white;
            transform: translateX(10px);
        }

        .footer-links a i {
            width: 20px;
            text-align: center;
            color: #007bff;
        }

        .contact-info {
            list-style: none;
            padding: 0;
        }

        .contact-info li {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            color: #b0b0b0;
        }

        .contact-info i {
            color: #007bff;
            font-size: 1.2rem;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #888;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .social-icons {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-icons a:hover {
            background: #007bff;
            transform: translateY(-5px) rotate(10deg);
        }

        /* ANIMATIONS */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* SCROLL PROGRESS BAR */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #00b4ff);
            z-index: 1001;
            transition: width 0.1s ease;
        }

        /* RESPONSIVE IMPROVEMENTS */
        @media (max-width: 1200px) {
            .hero h1 {
                font-size: 3.5rem;
            }
            
            .hero h2 {
                font-size: 2.2rem;
            }
            
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .navbar {
                padding: 15px 30px;
            }
            
            .navbar.scrolled {
                padding: 10px 30px;
            }
            
            .nav-left a {
                margin: 0 15px;
                font-size: 15px;
            }
            
            .hero h1 {
                font-size: 3rem;
            }
            
            .hero h2 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1.2rem;
            }
            
            .features-section, .stats-section {
                padding: 80px 40px;
            }
            
            .section-title h2 {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 12px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .navbar.scrolled {
                padding: 8px 20px;
            }
            
            .nav-left {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }
            
            .nav-left a {
                margin: 0;
                font-size: 14px;
            }
            
            .nav-right {
                display: flex;
                gap: 10px;
            }
            
            .nav-right a {
                padding: 10px 20px;
                font-size: 13px;
                margin-left: 0;
            }
            
            .hero {
                padding-top: 120px;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .report-btn, .info-btn {
                padding: 15px 30px;
                font-size: 1.1rem;
                width: 100%;
                max-width: 300px;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .features-section, .stats-section, .footer {
                padding: 60px 20px;
            }
            
            .section-title h2 {
                font-size: 2.2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            
            .stat-item h3 {
                font-size: 3rem;
            }
        }

        @media (max-width: 576px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero h2 {
                font-size: 1.5rem;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
            .feature-card {
                padding: 30px 20px;
            }
            
            .feature-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>
<body>

    <!-- Scroll Progress Bar -->
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- NAVBAR -->
    <div class="navbar" id="navbar">
        <div class="nav-left">
            <a href="main_page.php" class="active">HOME</a>
            <a href="news.php">NEWS</a>
            <a href="victim.php">VICTIM</a>
        </div>

        <div class="nav-right">
            <a href="login.php" class="btn-login">Sign in</a>
            <a href="register.php" class="btn-register">Register</a>
        </div>
    </div>

    <!-- HERO SECTION WITH SLIDER -->
    <div class="hero">
        <!-- Image Slider -->
        <div class="hero-slider" id="heroSlider">
            <div class="hero-slide slide-1">
                <!-- Background image set via CSS -->
            </div>
            <div class="hero-slide slide-2">
                <!-- Background image set via CSS -->
            </div>
            <div class="hero-slide slide-3">
                <!-- Background image set via CSS -->
            </div>
            <div class="hero-slide slide-4">
                <!-- Background image set via CSS -->
            </div>
            <div class="hero-slide slide-5">
                <!-- Background image set via CSS -->
            </div>
        </div>

        <div class="hero-content">
            <h1>Disaster Relief Resource</h1>
            <h2>Management System (Negeri Melaka)</h2>
            <p>
                A centralized platform for coordinating disaster response, resource allocation, 
                and emergency management in Melaka. Stay informed, stay prepared with real-time 
                disaster alerts and comprehensive resource management.
            </p>
            
            <div class="hero-buttons">
                <a href="report_emergency.php" class="report-btn floating">
                    <i class="fas fa-bullhorn me-2"></i>Report Emergency
                </a>
                <a href="#features" class="info-btn">
                    <i class="fas fa-info-circle me-2"></i>Learn More
                </a>
            </div>
        </div>
    </div>

    <!-- FEATURES SECTION -->
    <div class="features-section" id="features">
        <div class="section-title">
            <h2>System Features</h2>
            <p>Comprehensive tools and features designed specifically for disaster management in Melaka</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>Real-Time Mapping</h3>
                <p>Interactive maps showing disaster-affected areas, resource distribution points, and evacuation centers across Melaka.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3>Emergency Alerts</h3>
                <p>Instant notifications and warnings for floods, storms, and other emergencies in your area.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3>Resource Management</h3>
                <p>Track and allocate emergency supplies, equipment, and personnel efficiently during disaster response.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Volunteer Coordination</h3>
                <p>Register and coordinate volunteers for disaster relief operations throughout Melaka.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Analytics & Reporting</h3>
                <p>Comprehensive data analysis and reporting tools for disaster preparedness and response assessment.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-phone-alt"></i>
                </div>
                <h3>Emergency Hotlines</h3>
                <p>Direct access to emergency services, hospitals, and disaster management authorities in Melaka.</p>
            </div>
        </div>
    </div>

    <!-- QUICK STATS SECTION -->
    <div class="stats-section">
        <div class="section-title">
            <h2 style="color: white;">Melaka Disaster Response</h2>
            <p style="color: rgba(255, 255, 255, 0.9);">Current statistics and response data for Negeri Melaka</p>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <h3>24/7</h3>
                <p>Emergency Monitoring</p>
            </div>
            <div class="stat-item">
                <h3>15+</h3>
                <p>Evacuation Centers</p>
            </div>
            <div class="stat-item">
                <h3>500+</h3>
                <p>Trained Volunteers</p>
            </div>
            <div class="stat-item">
                <h3>10+</h3>
                <p>Response Agencies</p>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="main_page.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="news.php"><i class="fas fa-newspaper"></i> News</a></li>
                    <li><a href="#"><i class="fas fa-map"></i> Emergency Map</a></li>
                    <li><a href="#"><i class="fas fa-file-alt"></i> Reports</a></li>
                    <li><a href="#"><i class="fas fa-question-circle"></i> Help & Support</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Emergency Contacts</h4>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <strong>Emergency Hotline</strong><br>
                            <span>999 / 112</span>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-hospital"></i>
                        <div>
                            <strong>Hospital Melaka</strong><br>
                            <span>06-289 2344</span>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-fire-extinguisher"></i>
                        <div>
                            <strong>Fire & Rescue</strong><br>
                            <span>06-288 4444</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="footer-section">
                <h4>Connect With Us</h4>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
                <p style="margin-top: 20px; color: #b0b0b0; font-size: 0.95rem;">
                    <i class="fas fa-envelope me-2"></i> contact@melakadisaster.gov.my
                </p>
            </div>
        </div>

        <div class="copyright">
            &copy; <?php echo date('Y'); ?> Disaster Relief Resource Management System (Negeri Melaka).<br>
            Developed in collaboration with Melaka State Disaster Management Committee.<br>
            <small style="opacity: 0.7; font-size: 0.85rem;">Always be prepared. Safety first.</small>
        </div>
    </div>

    <!-- jQuery and Slick Slider JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize Slick Slider
        $(document).ready(function(){
            $('#heroSlider').slick({
                dots: true,
                infinite: true,
                speed: 1500,
                slidesToShow: 1,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 5000,
                fade: true,
                cssEase: 'cubic-bezier(0.7, 0, 0.3, 1)',
                arrows: false,
                pauseOnHover: false,
                pauseOnFocus: false,
                responsive: [
                    {
                        breakpoint: 768,
                        settings: {
                            dots: true,
                            arrows: false,
                            autoplaySpeed: 4000
                        }
                    }
                ]
            });

            // Scroll Progress Bar
            window.addEventListener('scroll', function() {
                const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
                const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
                const scrolled = (winScroll / height) * 100;
                document.getElementById('scrollProgress').style.width = scrolled + '%';
            });

            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                const navbar = document.getElementById('navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if(targetId === '#') return;
                    
                    const target = document.querySelector(targetId);
                    if(target) {
                        window.scrollTo({
                            top: target.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Add animation to feature cards on scroll
            function animateOnScroll() {
                const featureCards = document.querySelectorAll('.feature-card');
                const windowHeight = window.innerHeight;
                
                featureCards.forEach(card => {
                    const cardPosition = card.getBoundingClientRect().top;
                    if(cardPosition < windowHeight - 100) {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }
                });
            }

            // Initialize feature card animations
            document.querySelectorAll('.feature-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            });

            // Add scroll event listener for animations
            window.addEventListener('scroll', animateOnScroll);
            window.addEventListener('load', animateOnScroll);

            // Floating animation for report button
            setInterval(() => {
                const reportBtn = document.querySelector('.report-btn');
                reportBtn.classList.toggle('floating');
            }, 3000);

            // Fade in animation for hero content
            document.addEventListener('DOMContentLoaded', function() {
                const heroContent = document.querySelector('.hero-content');
                heroContent.style.opacity = '0';
                heroContent.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    heroContent.style.transition = 'opacity 1s ease, transform 1s ease';
                    heroContent.style.opacity = '1';
                    heroContent.style.transform = 'translateY(0)';
                }, 500);
            });

            // Stats counter animation
            function animateStats() {
                const statsSection = document.querySelector('.stats-section');
                const statsPosition = statsSection.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if(statsPosition < windowHeight - 100) {
                    const statNumbers = document.querySelectorAll('.stat-item h3');
                    statNumbers.forEach(stat => {
                        const target = parseInt(stat.textContent);
                        let current = 0;
                        const increment = Math.ceil(target / 30);
                        const timer = setInterval(() => {
                            current += increment;
                            if(current >= target) {
                                stat.textContent = target + (stat.textContent.includes('+') ? '+' : '');
                                clearInterval(timer);
                            } else {
                                stat.textContent = current + (stat.textContent.includes('+') ? '+' : '');
                            }
                        }, 50);
                    });
                    window.removeEventListener('scroll', animateStats);
                }
            }

            window.addEventListener('scroll', animateStats);

            // Slider auto-play with pause on hover
            const slider = $('#heroSlider');
            slider.on('mouseenter', function() {
                slider.slick('slickPause');
            }).on('mouseleave', function() {
                slider.slick('slickPlay');
            });

            // Add slide number indicator
            slider.on('init reInit afterChange', function(event, slick, currentSlide) {
                const i = (currentSlide ? currentSlide : 0) + 1;
                console.log(`Slide ${i} of ${slick.slideCount}`);
            });
        });
    </script>
</body>
</html>