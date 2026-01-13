<aside class="sidebar">
    
    <div class="nav-links">
        <?php 
        $currentPage = basename($_SERVER['PHP_SELF']); 
        $currentView = $_GET['view'] ?? 'dashboard'; // Default to dashboard if no view param
        ?>

        <!-- Dashboard Link -->
        <!-- FIX: Added data-section="dashboard" so JS can find it -->
        <a href="reports.php" 
           class="nav-item <?php echo ($currentPage == 'reports.php' && $currentView == 'dashboard') ? 'active' : ''; ?>" 
           data-section="dashboard"
           onclick="<?php echo ($currentPage == 'reports.php') ? "showSection('dashboard'); return false;" : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>

        <!-- Database Lists Link -->
        <?php if ($currentPage == 'reports.php'): ?>
            <button class="nav-item <?php echo ($currentView == 'lists') ? 'active' : ''; ?>" 
                    onclick="showSection('lists')"
                    data-section="lists"> <!-- This allows JS to find this button -->
                <i class="fas fa-users"></i> Database Lists
            </button>
        <?php else: ?>
            <a href="reports.php?view=lists" class="nav-item">
                <i class="fas fa-users"></i> Database Lists
            </a>
        <?php endif; ?>

        <!-- Backup Link -->
        <?php if ($currentPage == 'reports.php'): ?>
            <button class="nav-item <?php echo ($currentView == 'backup') ? 'active' : ''; ?>" 
                    onclick="showSection('backup')"
                    data-section="backup"> <!-- This allows JS to find this button -->
                <i class="fas fa-database"></i> Backup & Restore
            </button>
        <?php else: ?>
            <a href="reports.php?view=backup" class="nav-item">
                <i class="fas fa-database"></i> Backup & Restore
            </a>
        <?php endif; ?>

        <!-- AI Insights Link -->
        <a href="predictive_insights.php" class="nav-item <?php echo ($currentPage == 'predictive_insights.php') ? 'active' : ''; ?>">
            <i class="fas fa-brain"></i> AI Insights
        </a>
        
        <div class="sidebar-footer">
            <a href="http://10.147.17.58:8000/admin_dashboard.php" class="nav-item" style="color: #94a3b8; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>
    </div>
    
    <!-- Logout Area -->
    <div class="sidebar-status-area">
        <a href="http://10.147.17.30:8000/main_page.php" class="nav-item" style="color: white; text-decoration: none; justify-content: flex-start; width: 100%; display: flex; align-items: center; padding: 12px 16px; border-radius: 8px; transition: background-color 0.2s;">
            <i class="fas fa-sign-out-alt" style="width: 20px; text-align: center;"></i> 
            <span style="font-weight: 500;">Log Out</span>
        </a>
    </div>
</aside>