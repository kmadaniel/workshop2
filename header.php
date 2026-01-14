<header class="system-header">
    <!-- Dynamic Branding Logic -->
    <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $pageTitle = 'Administrator'; // Default Title
        $pageSubtitle = 'User Management System'; // Default Subtitle

        if ($currentPage == 'predictive_insights.php') {
            $pageTitle = 'Predictive Analytics';
            $pageSubtitle = 'AI-driven forecasts & logistics';
        }
        
        // Get user details from session if available
        $sessionEmail = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'guest@example.com';
        $sessionName = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Guest User';
        
        // Force role to be Administrator for display if session says 'admin'
        $rawRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'Guest';
        $displayRole = (strtolower($rawRole) === 'admin') ? 'Administrator' : ucfirst($rawRole);

        $sessionInitial = isset($_SESSION['name']) ? strtoupper(substr($_SESSION['name'], 0, 1)) : 'G';
    ?>

    <div class="header-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="logo-text">
                <h1><?php echo $pageTitle; ?></h1>
                <small><?php echo $pageSubtitle; ?></small>
            </div>
        </div>

        <!-- Header Controls -->
        <div class="header-controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>

            <!-- Profile Dropdown Container -->
            <div style="position: relative;">
                <div class="user-profile" id="userProfileBtn" onclick="toggleProfileDropdown()">
                    <div class="user-avatar">
                        <?php echo $sessionInitial; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $sessionName; ?></div>
                        <div class="user-role"><?php echo $displayRole; ?></div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: #bbdefb;"></i>
                </div>

                <!-- Dropdown Content -->
                <div id="profileDropdown" style="display: none; position: absolute; top: 110%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; width: 220px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2); z-index: 1001; overflow: hidden; animation: fadeIn 0.2s ease-out;">
                    <div style="padding: 20px; border-bottom: 1px solid #f1f5f9; text-align: center; background: #f8fafc;">
                        <div class="user-avatar" style="width: 54px; height: 54px; font-size: 1.4rem; margin: 0 auto 12px auto; background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;">
                            <?php echo $sessionInitial; ?>
                        </div>
                        <h4 style="margin: 0; font-size: 1rem; color: #1e293b; font-weight: 600;"><?php echo $sessionName; ?></h4>
                        <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: #64748b;"><?php echo $sessionEmail; ?></p>
                    </div>
                    <div style="padding: 8px;">
                        <a href="http://10.147.17.30:8000/admin_profile.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #475569; border-radius: 8px; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.color='#0f172a'" onmouseout="this.style.background='transparent'; this.style.color='#475569'">
                            <i class="fas fa-user-circle" style="color: #3b82f6; width: 20px; text-align: center;"></i> Manage Profile
                        </a>
                        <a href="http://10.147.17.30:8000/main_page.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: #ef4444; border-radius: 8px; font-size: 0.9rem; transition: all 0.2s; margin-top: 4px;" onmouseover="this.style.background='#fef2f2'; this.style.color='#dc2626'" onmouseout="this.style.background='transparent'; this.style.color='#ef4444'">
                            <i class="fas fa-sign-out-alt" style="width: 20px; text-align: center;"></i> Log Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* System Header */
        .system-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 0 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 70px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            font-size: 28px;
            color: #4fc3f7;
        }

        .logo-text h1 {
            font-size: 22px;
            margin: 0;
            font-weight: 600;
            color: white;
        }

        .logo-text small {
            font-size: 12px;
            opacity: 0.8;
            color: #bbdefb;
            display: block;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 2px rgba(79, 195, 247, 0.3);
        }
        
        .search-box input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #bbdefb;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 5px 15px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.08);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4fc3f7 0%, #0288d1 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 16px;
        }

        .user-info {
            line-height: 1.3;
            text-align: left;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            color: white;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.8;
            color: #bbdefb;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 10px;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .mobile-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .mobile-toggle {
                display: block;
            }
            
            .search-box {
                width: 200px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                height: auto;
                padding: 15px 0;
            }
            
            .logo-text h1 {
                font-size: 18px;
            }
            
            .search-box {
                width: 100%;
                order: 3;
                margin-top: 15px;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const isHidden = dropdown.style.display === 'none';
            dropdown.style.display = isHidden ? 'block' : 'none';
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const button = document.getElementById('userProfileBtn');
            // Ensure elements exist before checking containment
            if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Mobile Toggle Handler (Placeholder for sidebar integration)
        const mobileToggle = document.getElementById('mobileToggle');
        if(mobileToggle) {
            mobileToggle.addEventListener('click', function() {
                const sidebar = document.querySelector('.sidebar');
                if(sidebar) sidebar.classList.toggle('active');
            });
        }
    </script>
</header>