<?php
session_start();
// In real application, you would get distribution ID from URL parameter
$distribution_id = isset($_GET['id']) ? $_GET['id'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribution Details - Disaster Relief</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <!-- Same sidebar content -->
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Distribution Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Central Valley Relief</h1>
                        <p class="text-muted">Distribution #D-001 | Central Valley Camp</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary">Print</button>
                            <button class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                        <button class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i> Edit Distribution
                        </button>
                    </div>
                </div>

                <!-- Distribution Stats -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">45</h5>
                                <p class="card-text text-muted">Victims</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">8</h5>
                                <p class="card-text text-muted">Volunteers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">156</h5>
                                <p class="card-text text-muted">Items Distributed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">100%</h5>
                                <p class="card-text text-muted">Completion</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">3.5h</h5>
                                <p class="card-text text-muted">Duration</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Completed</h5>
                                <p class="card-text">Status</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs" id="distributionTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="needs-tab" data-bs-toggle="tab" data-bs-target="#needs" type="button">
                            <i class="fas fa-box-open"></i> Victim Needs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="volunteers-tab" data-bs-toggle="tab" data-bs-target="#volunteers" type="button">
                            <i class="fas fa-users"></i> Assigned Volunteers
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button">
                            <i class="fas fa-boxes"></i> Resource Allocation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="progress-tab" data-bs-toggle="tab" data-bs-target="#progress" type="button">
                            <i class="fas fa-chart-line"></i> Progress Tracking
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-3 border border-top-0" id="distributionTabsContent">
                    <!-- Victim Needs Tab -->
                    <div class="tab-pane fade show active" id="needs" role="tabpanel">
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Victim Needs Management</h5>
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Needs
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Victim ID</th>
                                        <th>Name</th>
                                        <th>Item Needed</th>
                                        <th>Quantity</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#V-101</td>
                                        <td>Maria Garcia</td>
                                        <td>Water Bottles</td>
                                        <td>12</td>
                                        <td><span class="badge bg-danger">High</span></td>
                                        <td><span class="badge bg-success">Fulfilled</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-warning">Edit</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>#V-102</td>
                                        <td>John Chen</td>
                                        <td>First Aid Kit</td>
                                        <td>1</td>
                                        <td><span class="badge bg-danger">Critical</span></td>
                                        <td><span class="badge bg-success">Fulfilled</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-warning">Edit</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Volunteers Tab -->
                    <div class="tab-pane fade" id="volunteers" role="tabpanel">
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Assigned Volunteers</h5>
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-user-plus"></i> Assign Volunteer
                            </button>
                        </div>
                        <div class="row">
                            <?php for($i = 1; $i <= 8; $i++): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-user-circle fa-3x text-primary"></i>
                                        </div>
                                        <h6 class="card-title">Volunteer <?php echo $i; ?></h6>
                                        <p class="card-text text-muted small">
                                            <?php echo $i == 1 ? 'Coordinator' : 'Field Worker'; ?>
                                        </p>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Progress Tracking Tab -->
                    <div class="tab-pane fade" id="progress" role="tabpanel">
                        <h5>Distribution Progress</h5>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h6>Timeline</h6>
                                        <div class="progress mb-3" style="height: 25px;">
                                            <div class="progress-bar bg-success" style="width: 100%">Completed</div>
                                        </div>
                                        <div class="timeline">
                                            <div class="d-flex mb-3">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0">Distribution Planned</h6>
                                                    <small class="text-muted">2024-01-25 10:00 AM</small>
                                                </div>
                                            </div>
                                            <div class="d-flex mb-3">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0">Resources Prepared</h6>
                                                    <small class="text-muted">2024-01-25 02:30 PM</small>
                                                </div>
                                            </div>
                                            <div class="d-flex mb-3">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0">Distribution Started</h6>
                                                    <small class="text-muted">2024-01-26 08:00 AM</small>
                                                </div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-0">Distribution Completed</h6>
                                                    <small class="text-muted">2024-01-26 12:30 PM</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>