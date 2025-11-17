<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Distributions - Disaster Relief</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (same as dashboard) -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <!-- Same sidebar content as dashboard -->
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Distributions</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-outline-primary">Export</button>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDistributionModal">
                            <i class="fas fa-plus"></i> New Distribution
                        </button>
                    </div>
                </div>

                <!-- Distribution List -->
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-0">All Distributions</h5>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search distributions...">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Distribution ID</th>
                                        <th>Name</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Victims</th>
                                        <th>Volunteers</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#D-001</td>
                                        <td>Central Valley Relief</td>
                                        <td>Central Valley Camp</td>
                                        <td>2024-01-26</td>
                                        <td>45</td>
                                        <td>8</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-success" style="width: 100%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info" title="Assign Volunteers">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <button class="btn btn-outline-success" title="Manage Needs">
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#D-002</td>
                                        <td>Northern Emergency Aid</td>
                                        <td>Northern Shelter</td>
                                        <td>2024-01-27</td>
                                        <td>32</td>
                                        <td>6</td>
                                        <td><span class="badge bg-warning">In Progress</span></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning" style="width: 65%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <button class="btn btn-outline-success">
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#D-003</td>
                                        <td>Medical Supply Distribution</td>
                                        <td>Field Hospital A</td>
                                        <td>2024-01-28</td>
                                        <td>28</td>
                                        <td>5</td>
                                        <td><span class="badge bg-info">Planned</span></td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: 30%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-info">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <button class="btn btn-outline-success">
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modals and scripts same as dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>