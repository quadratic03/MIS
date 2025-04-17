<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Check if an action is requested
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$vehicleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new vehicle
        if (isset($_POST['add_vehicle'])) {
            $vehicleCode = sanitizeInput($_POST['vehicle_code']);
            $category = sanitizeInput($_POST['category']);
            $name = sanitizeInput($_POST['name']);
            $model = sanitizeInput($_POST['model']);
            $manufacturer = sanitizeInput($_POST['manufacturer']);
            $yearManufactured = intval($_POST['year_manufactured']);
            $status = sanitizeInput($_POST['status']);
            $currentLocation = sanitizeInput($_POST['current_location']);
            $fuelCapacity = floatval($_POST['fuel_capacity']);
            $mileage = intval($_POST['mileage']);
            $lastMaintenance = $_POST['last_maintenance'] ? $_POST['last_maintenance'] : null;
            $nextMaintenance = $_POST['next_maintenance'] ? $_POST['next_maintenance'] : null;
            
            // Check if vehicle code already exists
            $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_code = :vehicle_code");
            $checkStmt->bindParam(':vehicle_code', $vehicleCode);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Vehicle code already exists. Please use a unique code.');
            } else {
                $stmt = $db->prepare("INSERT INTO vehicles (vehicle_code, category, name, model, manufacturer, year_manufactured, status, current_location, fuel_capacity, mileage, last_maintenance, next_maintenance) VALUES (:vehicle_code, :category, :name, :model, :manufacturer, :year_manufactured, :status, :current_location, :fuel_capacity, :mileage, :last_maintenance, :next_maintenance)");
                
                $stmt->bindParam(':vehicle_code', $vehicleCode);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':model', $model);
                $stmt->bindParam(':manufacturer', $manufacturer);
                $stmt->bindParam(':year_manufactured', $yearManufactured);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':current_location', $currentLocation);
                $stmt->bindParam(':fuel_capacity', $fuelCapacity);
                $stmt->bindParam(':mileage', $mileage);
                $stmt->bindParam(':last_maintenance', $lastMaintenance);
                $stmt->bindParam(':next_maintenance', $nextMaintenance);
                
                if ($stmt->execute()) {
                    // Log transaction
                    $vehicleId = $db->lastInsertId();
                    $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, to_location, transaction_date, notes) VALUES ('acquisition', 'vehicle', :item_id, :to_location, NOW(), :notes)");
                    $transactionStmt->bindParam(':item_id', $vehicleId);
                    $transactionStmt->bindParam(':to_location', $currentLocation);
                    $notes = "Added new vehicle: $name ($model)";
                    $transactionStmt->bindParam(':notes', $notes);
                    $transactionStmt->execute();
                    
                    setFlashMessage('success', 'Vehicle added successfully.');
                } else {
                    setFlashMessage('danger', 'Failed to add vehicle.');
                }
            }
        }
        
        // Update vehicle
        if (isset($_POST['update_vehicle'])) {
            $id = intval($_POST['vehicle_id']);
            $vehicleCode = sanitizeInput($_POST['vehicle_code']);
            $category = sanitizeInput($_POST['category']);
            $name = sanitizeInput($_POST['name']);
            $model = sanitizeInput($_POST['model']);
            $manufacturer = sanitizeInput($_POST['manufacturer']);
            $yearManufactured = intval($_POST['year_manufactured']);
            $status = sanitizeInput($_POST['status']);
            $currentLocation = sanitizeInput($_POST['current_location']);
            $fuelCapacity = floatval($_POST['fuel_capacity']);
            $mileage = intval($_POST['mileage']);
            $lastMaintenance = $_POST['last_maintenance'] ? $_POST['last_maintenance'] : null;
            $nextMaintenance = $_POST['next_maintenance'] ? $_POST['next_maintenance'] : null;
            
            // Check if vehicle code already exists for other vehicles
            $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_code = :vehicle_code AND vehicle_id != :vehicle_id");
            $checkStmt->bindParam(':vehicle_code', $vehicleCode);
            $checkStmt->bindParam(':vehicle_id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Vehicle code already exists. Please use a unique code.');
            } else {
                $stmt = $db->prepare("UPDATE vehicles SET vehicle_code = :vehicle_code, category = :category, name = :name, model = :model, manufacturer = :manufacturer, year_manufactured = :year_manufactured, status = :status, current_location = :current_location, fuel_capacity = :fuel_capacity, mileage = :mileage, last_maintenance = :last_maintenance, next_maintenance = :next_maintenance WHERE vehicle_id = :vehicle_id");
                
                $stmt->bindParam(':vehicle_id', $id);
                $stmt->bindParam(':vehicle_code', $vehicleCode);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':model', $model);
                $stmt->bindParam(':manufacturer', $manufacturer);
                $stmt->bindParam(':year_manufactured', $yearManufactured);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':current_location', $currentLocation);
                $stmt->bindParam(':fuel_capacity', $fuelCapacity);
                $stmt->bindParam(':mileage', $mileage);
                $stmt->bindParam(':last_maintenance', $lastMaintenance);
                $stmt->bindParam(':next_maintenance', $nextMaintenance);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Vehicle updated successfully.');
                    // Log transaction
                    $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, to_location, transaction_date, notes) VALUES ('transfer', 'vehicle', :item_id, :to_location, NOW(), :notes)");
                    $transactionStmt->bindParam(':item_id', $id);
                    $transactionStmt->bindParam(':to_location', $currentLocation);
                    $notes = "Updated vehicle information: $name ($model)";
                    $transactionStmt->bindParam(':notes', $notes);
                    $transactionStmt->execute();
                } else {
                    setFlashMessage('danger', 'Failed to update vehicle.');
                }
            }
        }
        
        // Delete vehicle
        if (isset($_POST['delete_vehicle'])) {
            $id = intval($_POST['vehicle_id']);
            
            // Check for maintenance logs or other dependencies
            $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM maintenance_logs WHERE vehicle_id = :vehicle_id");
            $checkStmt->bindParam(':vehicle_id', $id);
            $checkStmt->execute();
            $dependencies = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dependencies['count'] > 0) {
                setFlashMessage('danger', 'Cannot delete this vehicle. It has maintenance records associated with it.');
            } else {
                // Get vehicle info for transaction log
                $infoStmt = $db->prepare("SELECT name, model, current_location FROM vehicles WHERE vehicle_id = :vehicle_id");
                $infoStmt->bindParam(':vehicle_id', $id);
                $infoStmt->execute();
                $vehicleInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("DELETE FROM vehicles WHERE vehicle_id = :vehicle_id");
                $stmt->bindParam(':vehicle_id', $id);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Vehicle deleted successfully.');
                    // Log transaction
                    $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, from_location, transaction_date, notes) VALUES ('decommission', 'vehicle', :item_id, :from_location, NOW(), :notes)");
                    $transactionStmt->bindParam(':item_id', $id);
                    $transactionStmt->bindParam(':from_location', $vehicleInfo['current_location']);
                    $notes = "Deleted vehicle: {$vehicleInfo['name']} ({$vehicleInfo['model']})";
                    $transactionStmt->bindParam(':notes', $notes);
                    $transactionStmt->execute();
                } else {
                    setFlashMessage('danger', 'Failed to delete vehicle.');
                }
            }
        }
        
        // Redirect to list after form processing - MOVED HERE BEFORE ANY OUTPUT
        header("Location: " . SITE_URL . "vehicles/");
        exit;
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get vehicle data for editing
$editData = null;
if ($action === 'edit' && $vehicleId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE vehicle_id = :vehicle_id");
        $stmt->bindParam(':vehicle_id', $vehicleId);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editData) {
            setFlashMessage('danger', 'Vehicle not found.');
            header("Location: " . SITE_URL . "vehicles/");
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all vehicles for listing
$vehicles = [];
try {
    $stmt = $db->query("SELECT * FROM vehicles ORDER BY vehicle_code");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Include header AFTER all potential redirects
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Vehicle Management</h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Vehicle
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>vehicles/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($action === 'list'): ?>
                <!-- Vehicle List Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Vehicle Inventory</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="vehiclesTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Model</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Last Maintained</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($vehicles) > 0): ?>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td><?php echo $vehicle['vehicle_code']; ?></td>
                                                <td><?php echo $vehicle['name']; ?></td>
                                                <td><?php echo $vehicle['model']; ?></td>
                                                <td>
                                                    <?php
                                                    $categoryClass = '';
                                                    switch ($vehicle['category']) {
                                                        case 'combat':
                                                            $categoryClass = 'danger';
                                                            break;
                                                        case 'transport':
                                                            $categoryClass = 'primary';
                                                            break;
                                                        case 'support':
                                                            $categoryClass = 'success';
                                                            break;
                                                        case 'specialized':
                                                            $categoryClass = 'warning';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $categoryClass; ?>">
                                                        <?php echo ucfirst($vehicle['category']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($vehicle['status']) {
                                                        case 'operational':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'maintenance':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'repair':
                                                            $statusClass = 'danger';
                                                            break;
                                                        case 'decommissioned':
                                                            $statusClass = 'secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($vehicle['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $vehicle['current_location']; ?></td>
                                                <td>
                                                    <?php echo $vehicle['last_maintenance'] ? date('M d, Y', strtotime($vehicle['last_maintenance'])) : 'Not set'; ?>
                                                </td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $vehicle['vehicle_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $vehicle['vehicle_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete the vehicle <strong><?php echo $vehicle['name']; ?> (<?php echo $vehicle['model']; ?>)</strong>?
                                                                    <br>
                                                                    This action cannot be undone.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="vehicle_id" value="<?php echo $vehicle['vehicle_id']; ?>">
                                                                        <button type="submit" name="delete_vehicle" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No vehicles found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Vehicle Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'add' ? 'Add New Vehicle' : 'Edit Vehicle'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="vehicle_id" value="<?php echo $editData['vehicle_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_code" class="form-label">Vehicle Code</label>
                                    <input type="text" class="form-control" id="vehicle_code" name="vehicle_code" required 
                                           value="<?php echo $action === 'edit' ? $editData['vehicle_code'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="combat" <?php echo ($action === 'edit' && $editData['category'] === 'combat') ? 'selected' : ''; ?>>Combat</option>
                                        <option value="transport" <?php echo ($action === 'edit' && $editData['category'] === 'transport') ? 'selected' : ''; ?>>Transport</option>
                                        <option value="support" <?php echo ($action === 'edit' && $editData['category'] === 'support') ? 'selected' : ''; ?>>Support</option>
                                        <option value="specialized" <?php echo ($action === 'edit' && $editData['category'] === 'specialized') ? 'selected' : ''; ?>>Specialized</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Vehicle Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo $action === 'edit' ? $editData['name'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model" required 
                                           value="<?php echo $action === 'edit' ? $editData['model'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="manufacturer" class="form-label">Manufacturer</label>
                                    <input type="text" class="form-control" id="manufacturer" name="manufacturer" required 
                                           value="<?php echo $action === 'edit' ? $editData['manufacturer'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="year_manufactured" class="form-label">Year Manufactured</label>
                                    <input type="number" class="form-control" id="year_manufactured" name="year_manufactured" required 
                                           value="<?php echo $action === 'edit' ? $editData['year_manufactured'] : date('Y'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="operational" <?php echo ($action === 'edit' && $editData['status'] === 'operational') ? 'selected' : ''; ?>>Operational</option>
                                        <option value="maintenance" <?php echo ($action === 'edit' && $editData['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="repair" <?php echo ($action === 'edit' && $editData['status'] === 'repair') ? 'selected' : ''; ?>>Repair</option>
                                        <option value="decommissioned" <?php echo ($action === 'edit' && $editData['status'] === 'decommissioned') ? 'selected' : ''; ?>>Decommissioned</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="current_location" class="form-label">Current Location</label>
                                    <input type="text" class="form-control" id="current_location" name="current_location" required 
                                           value="<?php echo $action === 'edit' ? $editData['current_location'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fuel_capacity" class="form-label">Fuel Capacity (L)</label>
                                    <input type="number" step="0.01" class="form-control" id="fuel_capacity" name="fuel_capacity" 
                                           value="<?php echo $action === 'edit' ? $editData['fuel_capacity'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="mileage" class="form-label">Mileage (km)</label>
                                    <input type="number" class="form-control" id="mileage" name="mileage" 
                                           value="<?php echo $action === 'edit' ? $editData['mileage'] : '0'; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="last_maintenance" class="form-label">Last Maintenance Date</label>
                                    <input type="date" class="form-control" id="last_maintenance" name="last_maintenance" 
                                           value="<?php echo $action === 'edit' && $editData['last_maintenance'] ? date('Y-m-d', strtotime($editData['last_maintenance'])) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="next_maintenance" class="form-label">Next Maintenance Date</label>
                                    <input type="date" class="form-control" id="next_maintenance" name="next_maintenance" 
                                           value="<?php echo $action === 'edit' && $editData['next_maintenance'] ? date('Y-m-d', strtotime($editData['next_maintenance'])) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($action === 'add'): ?>
                                    <button type="submit" name="add_vehicle" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Vehicle
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="update_vehicle" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Vehicle
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>vehicles/" class="btn btn-secondary">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const table = new DataTable('#vehiclesTable', {
        responsive: true,
        order: [[0, 'asc']]
    });
});
</script>

<?php
require_once '../includes/footer.php';
?> 