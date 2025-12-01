<?php
require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

// Get data for dropdowns
$victims = [];
$disasters = [];
$resources = [];

try {
    // Get all victims
    $victims_query = "SELECT victim_id, name, age, address FROM victim";
    $result = $db->query($victims_query);
    $victims = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    // Get all disasters
    $disasters_query = "SELECT disaster_id, Disaster_Name, Disaster_Type, Location FROM disaster";
    $result = $db->query($disasters_query);
    $disasters = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

    // Get all resources
    $resources_query = "SELECT resource_id, name, type, quantity_available, unit FROM resource";
    $result = $db->query($resources_query);
    $resources = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();

} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
}

// Handle form submission
if ($_POST) {
    $victim_id = $_POST['victim_id'];
    $disaster_id = $_POST['disaster_id'];
    $resource_id = $_POST['resource_id'];
    $quantity_sent = $_POST['quantity_sent'];
    $delivery_date = $_POST['delivery_date'];
    $priority = $_POST['priority'];
    $comments = $_POST['comments'] ?? '';
    
    try {
        // Start transaction
        $db->begin_transaction();

        // Check resource availability
        $check_resource_query = "SELECT quantity_available FROM resource WHERE resource_id = ?";
        $check_stmt = $db->prepare($check_resource_query);
        $check_stmt->bind_param("i", $resource_id);
        $check_stmt->execute();
        $resource_result = $check_stmt->get_result();
        $resource_data = $resource_result->fetch_assoc();
        $check_stmt->close();

        if (!$resource_data) {
            throw new Exception("Selected resource not found!");
        }

        if ($resource_data['quantity_available'] < $quantity_sent) {
            throw new Exception("Insufficient resource quantity! Available: " . $resource_data['quantity_available']);
        }

        // Create distribution
        $distribution_query = "
            INSERT INTO distribution (victim_id, disaster_id, resource_id, quantity_sent, date, status, comments) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ";
        $distribution_stmt = $db->prepare($distribution_query);
        $distribution_stmt->bind_param("iiiiss", 
            $victim_id,
            $disaster_id,
            $resource_id,
            $quantity_sent,
            $delivery_date,
            $comments
        );
        
        if (!$distribution_stmt->execute()) {
            throw new Exception("Error creating distribution: " . $distribution_stmt->error);
        }
        
        $distribution_id = $db->insert_id;
        $distribution_stmt->close();

        // Update resource quantity
        $update_resource_query = "UPDATE resource SET quantity_available = quantity_available - ? WHERE resource_id = ?";
        $update_stmt = $db->prepare($update_resource_query);
        $update_stmt->bind_param("ii", $quantity_sent, $resource_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error updating resource quantity: " . $update_stmt->error);
        }
        $update_stmt->close();

        // Create need record if it doesn't exist
        $check_need_query = "SELECT need_id FROM needs WHERE victim_id = ? AND resource_id = ? AND disaster_id = ?";
        $check_need_stmt = $db->prepare($check_need_query);
        $check_need_stmt->bind_param("iii", $victim_id, $resource_id, $disaster_id);
        $check_need_stmt->execute();
        $need_result = $check_need_stmt->get_result();
        $existing_need = $need_result->fetch_assoc();
        $check_need_stmt->close();

        if ($existing_need) {
            // Update existing need
            $update_need_query = "UPDATE needs SET distribution_id = ?, status = 'fulfilled' WHERE need_id = ?";
            $update_need_stmt = $db->prepare($update_need_query);
            $update_need_stmt->bind_param("ii", $distribution_id, $existing_need['need_id']);
            $update_need_stmt->execute();
            $update_need_stmt->close();
        } else {
            // Create new need record
            $need_query = "
                INSERT INTO needs (victim_id, resource_id, disaster_id, distribution_id, quantity_needed, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'fulfilled')
            ";
            $need_stmt = $db->prepare($need_query);
            $need_stmt->bind_param("iiiiis", 
                $victim_id,
                $resource_id,
                $disaster_id,
                $distribution_id,
                $quantity_sent,
                $priority
            );
            $need_stmt->execute();
            $need_stmt->close();
        }

        // Commit transaction
        $db->commit();
        
        $success = "Distribution created successfully! Distribution ID: #" . $distribution_id;
        
        // Reset form or redirect
        $_POST = array();

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Distribution</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create New Distribution</h1>
            <p>Create a new resource distribution for disaster victims</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <div style="margin-top: 10px;">
                    <a href="view_distribution.php?id=<?php echo $distribution_id; ?>" class="btn btn-primary btn-sm">View Distribution</a>
                    <a href="assign_volunteer.php?id=<?php echo $distribution_id; ?>" class="btn btn-success btn-sm">Assign Volunteers</a>
                    <a href="create_distribution.php" class="btn btn-secondary btn-sm">Create Another</a>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card-3d">
            <h2>Distribution Details</h2>
            <form method="POST" id="create-distribution-form">
                <div class="grid-3">
                    <!-- Victim Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Victim *</label>
                        <select class="form-control" name="victim_id" required id="victim_select">
                            <option value="">Choose a victim...</option>
                            <?php foreach ($victims as $victim): ?>
                            <option value="<?php echo $victim['victim_id']; ?>" 
                                    <?php echo ($_POST['victim_id'] ?? '') == $victim['victim_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($victim['name']); ?> 
                                (Age: <?php echo $victim['age']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Select the victim receiving assistance</small>
                    </div>
                    
                    <!-- Disaster Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Disaster *</label>
                        <select class="form-control" name="disaster_id" required id="disaster_select">
                            <option value="">Choose a disaster...</option>
                            <?php foreach ($disasters as $disaster): ?>
                            <option value="<?php echo $disaster['disaster_id']; ?>"
                                    <?php echo ($_POST['disaster_id'] ?? '') == $disaster['disaster_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($disaster['Disaster_Name']); ?> 
                                (<?php echo htmlspecialchars($disaster['Disaster_Type']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Select the related disaster event</small>
                    </div>
                    
                    <!-- Resource Selection -->
                    <div class="form-group">
                        <label class="form-label">Select Resource *</label>
                        <select class="form-control" name="resource_id" required id="resource_select">
                            <option value="">Choose a resource...</option>
                            <?php foreach ($resources as $resource): ?>
                            <option value="<?php echo $resource['resource_id']; ?>"
                                    data-available="<?php echo $resource['quantity_available']; ?>"
                                    data-unit="<?php echo htmlspecialchars($resource['unit']); ?>"
                                    <?php echo ($_POST['resource_id'] ?? '') == $resource['resource_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($resource['name']); ?> 
                                (<?php echo htmlspecialchars($resource['type']); ?>)
                                - Available: <?php echo $resource['quantity_available'] . ' ' . $resource['unit']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Select the resource to distribute</small>
                    </div>
                </div>

                <div class="grid-3">
                    <!-- Quantity -->
                    <div class="form-group">
                        <label class="form-label">Quantity to Send *</label>
                        <input type="number" class="form-control" name="quantity_sent" 
                               id="quantity_sent" min="1" required 
                               value="<?php echo $_POST['quantity_sent'] ?? ''; ?>"
                               placeholder="Enter quantity">
                        <small id="quantity_help" class="form-text">Available: <span id="available_quantity">0</span> <span id="quantity_unit">units</span></small>
                    </div>
                    
                    <!-- Delivery Date -->
                    <div class="form-group">
                        <label class="form-label">Delivery Date *</label>
                        <input type="date" class="form-control" name="delivery_date" required 
                               min="<?php echo date('Y-m-d'); ?>"
                               value="<?php echo $_POST['delivery_date'] ?? date('Y-m-d'); ?>">
                    </div>
                    
                    <!-- Priority -->
                    <div class="form-group">
                        <label class="form-label">Priority Level *</label>
                        <select class="form-control" name="priority" required>
                            <option value="">Select priority...</option>
                            <option value="Low" <?php echo ($_POST['priority'] ?? '') == 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo ($_POST['priority'] ?? '') == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo ($_POST['priority'] ?? '') == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Urgent" <?php echo ($_POST['priority'] ?? '') == 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Comments -->
                <div class="form-group">
                    <label class="form-label">Additional Comments</label>
                    <textarea class="form-control" name="comments" rows="3" 
                              placeholder="Any additional notes or instructions..."><?php echo $_POST['comments'] ?? ''; ?></textarea>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-success btn-lg">
                        <span style="margin-right: 8px;">✓</span>
                        Create Distribution
                    </button>
                    <button type="reset" class="btn btn-secondary">Clear Form</button>
                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </form>
        </div>

        <!-- Quick Stats -->
        <div class="grid-3">
            <div class="stat-card">
                <div class="stat-label">Available Victims</div>
                <div class="stat-number"><?php echo count($victims); ?></div>
                <div class="stat-desc">Registered in system</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Disasters</div>
                <div class="stat-number"><?php echo count($disasters); ?></div>
                <div class="stat-desc">Requiring assistance</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Available Resources</div>
                <div class="stat-number"><?php echo count($resources); ?></div>
                <div class="stat-desc">Ready for distribution</div>
            </div>
        </div>

        <!-- Resource Availability -->
        <div class="card-3d">
            <h2>Resource Availability</h2>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Resource Name</th>
                            <th>Type</th>
                            <th>Available Quantity</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($resource['name']); ?></td>
                            <td><?php echo htmlspecialchars($resource['type']); ?></td>
                            <td><?php echo $resource['quantity_available']; ?></td>
                            <td><?php echo htmlspecialchars($resource['unit']); ?></td>
                            <td>
                                <?php if ($resource['quantity_available'] > 50): ?>
                                    <span class="status-badge status-completed">Well Stocked</span>
                                <?php elseif ($resource['quantity_available'] > 10): ?>
                                    <span class="status-badge status-assigned">Adequate</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">Low Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resourceSelect = document.getElementById('resource_select');
            const quantitySent = document.getElementById('quantity_sent');
            const availableQuantity = document.getElementById('available_quantity');
            const quantityUnit = document.getElementById('quantity_unit');
            const quantityHelp = document.getElementById('quantity_help');

            function updateQuantityInfo() {
                const selectedOption = resourceSelect.options[resourceSelect.selectedIndex];
                if (selectedOption.value) {
                    const available = selectedOption.getAttribute('data-available');
                    const unit = selectedOption.getAttribute('data-unit');
                    
                    availableQuantity.textContent = available;
                    quantityUnit.textContent = unit;
                    
                    // Set max attribute for quantity input
                    quantitySent.max = available;
                    
                    // Update help text color based on availability
                    if (available < 10) {
                        quantityHelp.style.color = '#e74c3c';
                    } else if (available < 50) {
                        quantityHelp.style.color = '#f39c12';
                    } else {
                        quantityHelp.style.color = '#27ae60';
                    }
                } else {
                    availableQuantity.textContent = '0';
                    quantityUnit.textContent = 'units';
                    quantitySent.max = '';
                    quantityHelp.style.color = '';
                }
            }

            // Initial update
            updateQuantityInfo();

            // Update when resource selection changes
            resourceSelect.addEventListener('change', updateQuantityInfo);

            // Validate quantity before form submission
            document.getElementById('create-distribution-form').addEventListener('submit', function(e) {
                const selectedOption = resourceSelect.options[resourceSelect.selectedIndex];
                if (selectedOption.value) {
                    const available = parseInt(selectedOption.getAttribute('data-available'));
                    const requested = parseInt(quantitySent.value);
                    
                    if (requested > available) {
                        e.preventDefault();
                        alert('Error: Requested quantity (' + requested + ') exceeds available quantity (' + available + ')');
                        quantitySent.focus();
                    }
                }
            });

            // Auto-focus first field
            document.getElementById('victim_select').focus();
        });
    </script>
    <script src="js/script.js"></script>
</body>
</html>