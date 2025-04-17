<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Check if an action is requested
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$logId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new maintenance log
        if (isset($_POST['add_maintenance'])) {
            $vehicleId = intval($_POST['vehicle_id']);
            $personnelId = intval($_POST['personnel_id']);
            $maintenanceType = sanitizeInput($_POST['maintenance_type']);
            $description = sanitizeInput($_POST['description']);
            $startDate = sanitizeInput($_POST['start_date']);
            $endDate = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
            $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : null;
            $status = sanitizeInput($_POST['status']);
            
            $stmt = $db->prepare("INSERT INTO maintenance_logs (vehicle_id, personnel_id, maintenance_type, description, start_date, end_date, cost, status) VALUES (:vehicle_id, :personnel_id, :maintenance_type, :description, :start_date, :end_date, :cost, :status)");
            
            $stmt->bindParam(':vehicle_id', $vehicleId);
            $stmt->bindParam(':personnel_id', $personnelId);
            $stmt->bindParam(':maintenance_type', $maintenanceType);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                // If maintenance is completed, update vehicle's last_maintenance date
                if ($status === 'completed') {
                    $updateVehicle = $db->prepare("UPDATE vehicles SET last_maintenance = :maintenance_date, next_maintenance = DATE_ADD(:maintenance_date, INTERVAL 6 MONTH) WHERE vehicle_id = :vehicle_id");
                    $updateVehicle->bindParam(':maintenance_date', $endDate ? $endDate : $startDate);
                    $updateVehicle->bindParam(':vehicle_id', $vehicleId);
                    $updateVehicle->execute();
                }
                
                // Record the transaction
                $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, personnel_id, transaction_date, notes) VALUES ('maintenance', 'vehicle', :item_id, :personnel_id, NOW(), :notes)");
                $transactionStmt->bindParam(':item_id', $vehicleId);
                $transactionStmt->bindParam(':personnel_id', $personnelId);
                $transactionStmt->bindParam(':notes', $description);
                $transactionStmt->execute();
                
                setFlashMessage('success', 'Maintenance log added successfully.');
            } else {
                setFlashMessage('danger', 'Failed to add maintenance log.');
            }
        }
        
        // Update maintenance log
        if (isset($_POST['update_maintenance'])) {
            $id = intval($_POST['log_id']);
            $vehicleId = intval($_POST['vehicle_id']);
            $personnelId = intval($_POST['personnel_id']);
            $maintenanceType = sanitizeInput($_POST['maintenance_type']);
            $description = sanitizeInput($_POST['description']);
            $startDate = sanitizeInput($_POST['start_date']);
            $endDate = !empty($_POST['end_date']) ? sanitizeInput($_POST['end_date']) : null;
            $cost = !empty($_POST['cost']) ? floatval($_POST['cost']) : null;
            $status = sanitizeInput($_POST['status']);
            
            $stmt = $db->prepare("UPDATE maintenance_logs SET vehicle_id = :vehicle_id, personnel_id = :personnel_id, maintenance_type = :maintenance_type, description = :description, start_date = :start_date, end_date = :end_date, cost = :cost, status = :status WHERE log_id = :log_id");
            
            $stmt->bindParam(':log_id', $id);
            $stmt->bindParam(':vehicle_id', $vehicleId);
            $stmt->bindParam(':personnel_id', $personnelId);
            $stmt->bindParam(':maintenance_type', $maintenanceType);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->bindParam(':cost', $cost);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                // If maintenance is completed, update vehicle's last_maintenance date
                if ($status === 'completed') {
                    $updateVehicle = $db->prepare("UPDATE vehicles SET last_maintenance = :maintenance_date, next_maintenance = DATE_ADD(:maintenance_date, INTERVAL 6 MONTH) WHERE vehicle_id = :vehicle_id");
                    $updateVehicle->bindParam(':maintenance_date', $endDate ? $endDate : $startDate);
                    $updateVehicle->bindParam(':vehicle_id', $vehicleId);
                    $updateVehicle->execute();
                }
                
                setFlashMessage('success', 'Maintenance log updated successfully.');
            } else {
                setFlashMessage('danger', 'Failed to update maintenance log.');
            }
        }
        
        // Delete maintenance log
        if (isset($_POST['delete_maintenance'])) {
            $id = intval($_POST['log_id']);
            
            $stmt = $db->prepare("DELETE FROM maintenance_logs WHERE log_id = :log_id");
            $stmt->bindParam(':log_id', $id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Maintenance log deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete maintenance log.');
            }
        }
        
        // Redirect to list after form processing - BEFORE ANY OUTPUT
        header("Location: " . SITE_URL . "maintenance/");
        exit;
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get maintenance log data for editing
$editData = null;
if ($action === 'edit' && $logId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM maintenance_logs WHERE log_id = :log_id");
        $stmt->bindParam(':log_id', $logId);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editData) {
            setFlashMessage('danger', 'Maintenance log not found.');
            header("Location: " . SITE_URL . "maintenance/");
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all maintenance logs for listing
$maintenanceLogs = [];
try {
    $query = "SELECT ml.*, 
                    v.vehicle_code, v.name AS vehicle_name, v.model AS vehicle_model,
                    CONCAT(p.rank, ' ', p.first_name, ' ', p.last_name) AS personnel_name
              FROM maintenance_logs ml
              LEFT JOIN vehicles v ON ml.vehicle_id = v.vehicle_id
              LEFT JOIN personnel p ON ml.personnel_id = p.personnel_id
              ORDER BY ml.start_date DESC";
    $stmt = $db->query($query);
    $maintenanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Get all vehicles for dropdown
$vehicles = [];
try {
    $stmt = $db->query("SELECT vehicle_id, vehicle_code, name, model FROM vehicles ORDER BY vehicle_code");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Get all personnel for dropdown
$personnel = [];
try {
    $stmt = $db->query("SELECT personnel_id, service_number, rank, first_name, last_name FROM personnel WHERE specialization LIKE '%Maintenance%' OR position LIKE '%Mechanic%' ORDER BY rank, last_name");
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Include header AFTER all potential redirects
require_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Maintenance Management</h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Maintenance Log
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>maintenance/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($action === 'list'): ?>
                <!-- Maintenance Logs Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Maintenance Records</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="maintenanceTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Vehicle</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Technician</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($maintenanceLogs) > 0): ?>
                                        <?php foreach ($maintenanceLogs as $log): ?>
                                            <tr>
                                                <td>
                                                    <?php echo $log['vehicle_code']; ?><br>
                                                    <small><?php echo $log['vehicle_name'] . ' ' . $log['vehicle_model']; ?></small>
                                                </td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $log['maintenance_type'])); ?></td>
                                                <td><?php echo nl2br($log['description']); ?></td>
                                                <td><?php echo $log['personnel_name']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($log['start_date'])); ?></td>
                                                <td><?php echo $log['end_date'] ? date('M d, Y', strtotime($log['end_date'])) : '-'; ?></td>
                                                <td><?php echo $log['cost'] ? '$' . number_format($log['cost'], 2) : '-'; ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($log['status']) {
                                                        case 'scheduled':
                                                            $statusClass = 'info';
                                                            break;
                                                        case 'in_progress':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'completed':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $log['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $log['log_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $log['log_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $log['log_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete this maintenance record for <strong><?php echo $log['vehicle_code'] . ' - ' . $log['vehicle_name']; ?></strong>?
                                                                    <br><br>
                                                                    This action cannot be undone.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="log_id" value="<?php echo $log['log_id']; ?>">
                                                                        <button type="submit" name="delete_maintenance" class="btn btn-danger">Delete</button>
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
                                            <td colspan="9" class="text-center">No maintenance logs found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Maintenance Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'add' ? 'Add New Maintenance Log' : 'Edit Maintenance Log'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="log_id" value="<?php echo $editData['log_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vehicle_id" class="form-label">Vehicle</label>
                                    <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <option value="<?php echo $vehicle['vehicle_id']; ?>" <?php echo ($action === 'edit' && $editData['vehicle_id'] == $vehicle['vehicle_id']) ? 'selected' : ''; ?>>
                                                <?php echo $vehicle['vehicle_code'] . ' - ' . $vehicle['name'] . ' ' . $vehicle['model']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="personnel_id" class="form-label">Technician</label>
                                    <select class="form-select" id="personnel_id" name="personnel_id" required>
                                        <option value="">Select Technician</option>
                                        <?php foreach ($personnel as $person): ?>
                                            <option value="<?php echo $person['personnel_id']; ?>" <?php echo ($action === 'edit' && $editData['personnel_id'] == $person['personnel_id']) ? 'selected' : ''; ?>>
                                                <?php echo $person['service_number'] . ' - ' . $person['rank'] . ' ' . $person['first_name'] . ' ' . $person['last_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="maintenance_type" class="form-label">Maintenance Type</label>
                                    <select class="form-select" id="maintenance_type" name="maintenance_type" required>
                                        <option value="">Select Type</option>
                                        <option value="routine" <?php echo ($action === 'edit' && $editData['maintenance_type'] === 'routine') ? 'selected' : ''; ?>>Routine</option>
                                        <option value="repair" <?php echo ($action === 'edit' && $editData['maintenance_type'] === 'repair') ? 'selected' : ''; ?>>Repair</option>
                                        <option value="inspection" <?php echo ($action === 'edit' && $editData['maintenance_type'] === 'inspection') ? 'selected' : ''; ?>>Inspection</option>
                                        <option value="upgrade" <?php echo ($action === 'edit' && $editData['maintenance_type'] === 'upgrade') ? 'selected' : ''; ?>>Upgrade</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="scheduled" <?php echo ($action === 'edit' && $editData['status'] === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="in_progress" <?php echo ($action === 'edit' && $editData['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo ($action === 'edit' && $editData['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($action === 'edit' && $editData['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                           value="<?php echo $action === 'edit' ? date('Y-m-d', strtotime($editData['start_date'])) : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo ($action === 'edit' && $editData['end_date']) ? date('Y-m-d', strtotime($editData['end_date'])) : ''; ?>">
                                    <small class="text-muted">Leave blank if maintenance is not completed yet</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cost" class="form-label">Cost ($)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="cost" name="cost" 
                                           value="<?php echo $action === 'edit' ? $editData['cost'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo $action === 'edit' ? $editData['description'] : ''; ?></textarea>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($action === 'add'): ?>
                                    <button type="submit" name="add_maintenance" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Maintenance Log
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="update_maintenance" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Maintenance Log
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>maintenance/" class="btn btn-secondary">
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

<?php
require_once '../includes/footer.php';
?>

<!-- Add jQuery if it's not already included in footer -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with proper column definitions
    $('#maintenanceTable').DataTable({
        responsive: true,
        order: [[4, 'desc']],
        columnDefs: [
            { targets: [2], width: '20%' }, // Description column
            { targets: [8], orderable: false, width: '10%' } // Actions column
        ],
        columns: [
            null, // Vehicle
            null, // Type
            null, // Description
            null, // Technician
            null, // Start Date
            null, // End Date
            null, // Cost
            null, // Status
            { orderable: false } // Actions
        ]
    });
    
    // Show/hide end date based on status
    const statusSelect = document.getElementById('status');
    const endDateField = document.getElementById('end_date');
    
    if (statusSelect && endDateField) {
        statusSelect.addEventListener('change', function() {
            if (this.value === 'completed') {
                endDateField.setAttribute('required', 'required');
                endDateField.closest('.mb-3').querySelector('small').textContent = 'Required for completed maintenance';
            } else {
                endDateField.removeAttribute('required');
                endDateField.closest('.mb-3').querySelector('small').textContent = 'Leave blank if maintenance is not completed yet';
            }
        });
        
        // Trigger on page load if editing
        if (statusSelect.value === 'completed') {
            endDateField.setAttribute('required', 'required');
            endDateField.closest('.mb-3').querySelector('small').textContent = 'Required for completed maintenance';
        }
    }
});
</script> 