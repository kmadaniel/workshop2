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
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-left a {
            margin: 0 20px;
            text-decoration: none;
            color: #333;
            font-size: 16px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .nav-left a:hover {
            color: #007bff;
        }

        .nav-right a {
            margin-left: 20px;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-login {
            color: #333;
            border: 2px solid #007bff;
        }

        .btn-login:hover {
            background: #007bff;
            color: white;
        }

        .btn-register {
            background: #007bff;
            color: white;
        }

        .btn-register:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        /* HERO SECTION */
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
            position: relative;
        }

        .hero-slide::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
        }

        /* Melaka flood images - using placeholder images for demo */
        .slide-1 {
            background-image: url('https://3.bp.blogspot.com/-0vMqWZs5IOM/V0WxfZ6tpuI/AAAAAAAAAGE/4VNMQ-gQi2Q8_doXrQ9SF1QYnEgxLZ4lgCLcB/s1600/doa-anti-banjir.jpg');
        }

      .slide-2 {
            background-image: url('https://www.kosmo.com.my/wp-content/uploads/2023/02/GEMPA1.jpg');
        }



    

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.7);
        }

        .hero h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            font-weight: 600;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.7);
            color: #ffcc00;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.9;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
            max-width: 700px;
            line-height: 1.6;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .hero .report-btn {
            padding: 15px 40px;
            background: #ff6b6b;
            color: white;
            font-size: 1.2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border: none;
            cursor: pointer;
        }

        .hero .report-btn:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .hero .info-btn {
            padding: 15px 40px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1.2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border: 2px solid white;
        }

        .hero .info-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* FEATURES SECTION */
        .features-section {
            padding: 100px 60px;
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .section-title p {
            color: #666;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2rem;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        /* QUICK STATS SECTION */
        .stats-section {
            padding: 80px 60px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #ffcc00;
        }

        .stat-item p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* FOOTER */
        .footer {
            background: #343a40;
            color: white;
            padding: 40px 60px;
            text-align: center;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: white;
        }

        .copyright {
            color: #999;
            font-size: 0.9rem;
            margin-top: 20px;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .hero h2 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 15px 20px;
            }
            
            .nav-left a {
                margin: 0 10px;
                font-size: 14px;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero h2 {
                font-size: 1.5rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .features-section, .stats-section {
                padding: 60px 20px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .hero-buttons a {
                width: 100%;
                max-width: 300px;
                text-align: center;
            }
            
            .footer-links {
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                flex-direction: column;
                padding: 15px;
            }
            
            .nav-left {
                margin-bottom: 15px;
            }
            
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .hero h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="main_page.php">HOME</a>
            <a href="news.php">NEWS</a>
            <a href="#">MAP</a>
            <a href="#">RESOURCES</a>
            <a href="#">CONTACT</a>
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
                <a href="report_incident.php" class="report-btn">
                    <i class="fas fa-bullhorn me-2"></i>Report Emergency
                </a>
               
            </div>
        </div>
    </div>

    <!-- FEATURES SECTION -->
    <div class="features-section">
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
            <h2>Melaka Disaster Response</h2>
            <p>Current statistics and response data for Negeri Melaka</p>
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
        <div class="footer-links">
            <a href="main_page.php">Home</a>
            <a href="news.php">News</a>
            <a href="#">About System</a>
            <a href="#">Emergency Contacts</a>
            <a href="#">Resources</a>
            <a href="#">Privacy Policy</a>
        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> Disaster Relief Resource Management System (Negeri Melaka).<br>
            Developed in collaboration with Melaka State Disaster Management Committee.
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
                speed: 1000,
                slidesToShow: 1,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 5000,
                fade: true,
                cssEase: 'linear',
                arrows: false,
                pauseOnHover: false
            });

            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                    navbar.style.padding = '15px 60px';
                } else {
                    navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                    navbar.style.padding = '20px 60px';
                }
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if(target) {
                        target.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>