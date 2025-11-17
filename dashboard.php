<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Dashboard - Disaster Relief</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card { transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .sidebar { min-height: 100vh; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <h5 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                        DISTRIBUTION MODULE
                    </h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="distribution.php">
                                <i class="fas fa-truck"></i>
                                Distributions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="victims.php">
                                <i class="fas fa-users"></i>
                                Victims
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="volunteers.php">
                                <i class="fas fa-hands-helping"></i>
                                Volunteers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">
                                <i class="fas fa-boxes"></i>
                                Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i>
                                Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Distribution Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDistributionModal">
                            <i class="fas fa-plus"></i> New Distribution
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">12</h4>
                                        <p class="card-text">Active Distributions</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-truck fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">156</h4>
                                        <p class="card-text">Victims Served Today</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">45</h4>
                                        <p class="card-text">Active Volunteers</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-hands-helping fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">78%</h4>
                                        <p class="card-text">Resources Utilized</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Distributions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Distributions</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Location</th>
                                                <th>Date</th>
                                                <th>Victims</th>
                                                <th>Volunteers</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>#D-001</td>
                                                <td>Central Valley Camp</td>
                                                <td>2024-01-26</td>
                                                <td>45</td>
                                                <td>8</td>
                                                <td><span class="badge bg-success">Completed</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <button class="btn btn-sm btn-outline-warning">Edit</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>#D-002</td>
                                                <td>Northern Shelter</td>
                                                <td>2024-01-27</td>
                                                <td>32</td>
                                                <td>6</td>
                                                <td><span class="badge bg-warning">In Progress</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <button class="btn btn-sm btn-outline-warning">Edit</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>#D-003</td>
                                                <td>Field Hospital A</td>
                                                <td>2024-01-28</td>
                                                <td>28</td>
                                                <td>5</td>
                                                <td><span class="badge bg-info">Planned</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary">View</button>
                                                    <button class="btn btn-sm btn-outline-warning">Edit</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- New Distribution Modal -->
    <div class="modal fade" id="newDistributionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Distribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newDistributionForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Distribution Name</label>
                                <input type="text" class="form-control" placeholder="Enter distribution name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" placeholder="Enter distribution location" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Disaster Area</label>
                            <select class="form-select" required>
                                <option value="">Select disaster area</option>
                                <option value="1">Central Valley Earthquake</option>
                                <option value="2">Northern Floods</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" placeholder="Enter distribution details"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Create Distribution</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>