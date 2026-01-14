<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <ul class="nav-menu">
            <?php 
            $currentPage = basename($_SERVER['PHP_SELF']); 
            // Default view to dashboard if not set
            $currentView = $_GET['view'] ?? 'dashboard'; 
            
            // Helper function to check active state
            function isActive($page, $currentPage, $currentView, $view = null) {
                // If pages match
                if ($page === $currentPage) {
                    // If no specific view required (e.g. predictive_insights), it's active
                    if ($view === null) return 'active';
                    // If view matches, it's active
                    if ($view === $currentView) return 'active';
                }
                return '';
            }
            ?>

            <li class="nav-label">REPORTS MODULE</li>

            <!-- Dashboard Link -->
            <li class="nav-item">
                <a href="reports.php" 
                   class="nav-link <?php echo isActive('reports.php', $currentPage, $currentView, 'dashboard'); ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <!-- Database Lists Link -->
            <li class="nav-item">
                <a href="reports.php?view=lists" 
                   class="nav-link <?php echo isActive('reports.php', $currentPage, $currentView, 'lists'); ?>">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Database Lists</span>
                </a>
            </li>

            <!-- Backup Link -->
            <li class="nav-item">
                <a href="reports.php?view=backup" 
                   class="nav-link <?php echo isActive('reports.php', $currentPage, $currentView, 'backup'); ?>">
                    <i class="fas fa-database"></i>
                    <span class="nav-text">Backup & Restore</span>
                </a>
            </li>

            <li class="nav-divider"></li>
            <li class="nav-label">ANALYTICS</li>

            <!-- AI Insights Link -->
            <li class="nav-item">
                <a href="predictive_insights.php" 
                   class="nav-link <?php echo isActive('predictive_insights.php', $currentPage, $currentView); ?>">
                    <i class="fas fa-brain"></i>
                    <span class="nav-text">AI Insights</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Sidebar Footer (Back to Admin) -->
    <div class="sidebar-footer">
        <a href="http://10.147.17.30:8000/admin_dashboard.php">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Admin</span>
        </a>
    </div>
</aside>

<style>
    /* CRITICAL LAYOUT FIX: 
       Ensure main content respects sidebar width.
       This style targets the container typically found in reports.php/predictive_insights.php
    */
    .main-content {
        margin-left: 250px !important; /* Force margin equal to sidebar width */
        padding: 30px;
        transition: margin-left 0.3s ease;
        min-height: calc(100vh - 70px);
        width: calc(100% - 250px); /* Ensure it doesn't overflow horizontally */
        box-sizing: border-box;
    }

    /* Mobile Responsive adjustment */
    @media (max-width: 1024px) {
        .main-content {
            margin-left: 0 !important;
            width: 100%;
        }
    }

    /* Sidebar Layout */
    .sidebar {
        position: fixed;
        left: 0;
        top: 70px; /* Matches header height */
        width: 250px;
        height: calc(100vh - 70px);
        background: linear-gradient(180deg, #1a237e 0%, #283593 100%);
        color: white;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        z-index: 1000; /* Ensure high z-index to stay on top */
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    /* Scrollable Content Area */
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 20px 0;
    }

    /* Custom Scrollbar */
    .sidebar-content::-webkit-scrollbar { width: 5px; }
    .sidebar-content::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
    .sidebar-content::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 3px; }
    .sidebar-content::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.4); }

    /* Navigation List */
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    /* Navigation Items - Fix for Uniform Width & Spacing */
    .nav-item {
        margin: 4px 0; /* Remove horizontal margin to allow full width */
        padding: 0 12px; /* Apply padding to container instead */
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px; /* Increased padding for better touch target/visuals */
        color: #bbdefb;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s ease-in-out;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        width: 100%; /* Ensure full width relative to parent .nav-item */
        box-sizing: border-box; /* Include padding in width */
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(4px); /* Subtle hover effect */
    }

    /* Active State Styling */
    .nav-link.active {
        background: rgba(79, 195, 247, 0.15); /* Semi-transparent active bg */
        color: #ffffff;
        border-left: 4px solid #4fc3f7; /* Accent border on left */
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1); /* Subtle depth */
    }

    .nav-link i {
        width: 24px; /* Fixed width for icons alignment */
        text-align: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .nav-text {
        font-size: 0.95rem;
        flex: 1;
    }

    /* Divider & Labels */
    .nav-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 16px 20px;
    }

    .nav-label {
        padding: 0 24px 8px 24px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #90caf9;
        font-weight: 600;
        opacity: 0.8;
    }

    /* Footer */
    .sidebar-footer {
        padding: 16px;
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #bbdefb;
        text-decoration: none;
        padding: 10px;
        border-radius: 6px;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .sidebar-footer a:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
</style>