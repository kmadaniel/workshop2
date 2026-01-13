<aside class="sidebar">
    <!-- Logo removed from sidebar as requested -->
    
    <div class="nav-links">
        <?php 
        $currentPage = basename($_SERVER['PHP_SELF']); 
        ?>

        <!-- Dashboard Link -->
        <a href="reports.php" class="nav-item <?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>" onclick="<?php echo ($currentPage == 'reports.php') ? "showSection('dashboard')" : ''; ?>">
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>

        <!-- Lists Link (Active/Clickable based on page) -->
        <?php if ($currentPage == 'reports.php'): ?>
            <button class="nav-item" onclick="showSection('lists')">
                <i class="fas fa-users"></i> Database Lists
            </button>
            <button class="nav-item" onclick="showSection('backup')">
                <i class="fas fa-database"></i> Backup & Restore
            </button>
        <?php else: ?>
            <!-- Redirects with View Parameter -->
            <a href="reports.php?view=lists" class="nav-item">
                <i class="fas fa-users"></i> Database Lists
            </a>
            <a href="reports.php?view=backup" class="nav-item">
                <i class="fas fa-database"></i> Backup & Restore
            </a>
        <?php endif; ?>

        <!-- AI Insights Link (Always visible) -->
        <a href="predictive_insights.php" class="nav-item <?php echo ($currentPage == 'predictive_insights.php') ? 'active' : ''; ?>">
            <i class="fas fa-brain"></i> AI Insights
        </a>
        
        <div class="sidebar-footer">
            <!-- UPDATED IP TO MATCH SNIPPET: .58 -->
            <a href="http://10.147.17.58:8000/admin_dashboard.php" class="nav-item" style="color: #94a3b8; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>
    </div>
    
    <!-- Logout Area (New) -->
    <div class="sidebar-status-area">
        <a href="http://10.147.17.30:8000/main_page.php" class="nav-item" style="color: white; text-decoration: none; justify-content: flex-start; width: 100%; display: flex; align-items: center; padding: 12px 16px; border-radius: 8px; transition: background-color 0.2s;">
            <i class="fas fa-sign-out-alt" style="width: 20px; text-align: center;"></i> 
            <span style="font-weight: 500;">Log Out</span>
        </a>
    </div>
</aside>