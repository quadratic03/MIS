<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Check if an action is requested
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$personnelId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new personnel
        if (isset($_POST['add_personnel'])) {
            $serviceNumber = sanitizeInput($_POST['service_number']);
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $rank = sanitizeInput($_POST['rank']);
            $unit = sanitizeInput($_POST['unit']);
            $position = sanitizeInput($_POST['position']);
            $specialization = sanitizeInput($_POST['specialization']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $status = sanitizeInput($_POST['status']);
            
            // Check if service number already exists
            $checkStmt = $db->prepare("SELECT personnel_id FROM personnel WHERE service_number = :service_number");
            $checkStmt->bindParam(':service_number', $serviceNumber);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Service number already exists. Please use a unique service number.');
            } else {
                $stmt = $db->prepare("INSERT INTO personnel (service_number, first_name, last_name, rank, unit, position, specialization, email, phone, status) VALUES (:service_number, :first_name, :last_name, :rank, :unit, :position, :specialization, :email, :phone, :status)");
                
                $stmt->bindParam(':service_number', $serviceNumber);
                $stmt->bindParam(':first_name', $firstName);
                $stmt->bindParam(':last_name', $lastName);
                $stmt->bindParam(':rank', $rank);
                $stmt->bindParam(':unit', $unit);
                $stmt->bindParam(':position', $position);
                $stmt->bindParam(':specialization', $specialization);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':status', $status);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Personnel added successfully.');
                } else {
                    setFlashMessage('danger', 'Failed to add personnel.');
                }
            }
        }
        
        // Update personnel
        if (isset($_POST['update_personnel'])) {
            $id = intval($_POST['personnel_id']);
            $serviceNumber = sanitizeInput($_POST['service_number']);
            $firstName = sanitizeInput($_POST['first_name']);
            $lastName = sanitizeInput($_POST['last_name']);
            $rank = sanitizeInput($_POST['rank']);
            $unit = sanitizeInput($_POST['unit']);
            $position = sanitizeInput($_POST['position']);
            $specialization = sanitizeInput($_POST['specialization']);
            $email = sanitizeInput($_POST['email']);
            $phone = sanitizeInput($_POST['phone']);
            $status = sanitizeInput($_POST['status']);
            
            // Check if service number already exists for other personnel
            $checkStmt = $db->prepare("SELECT personnel_id FROM personnel WHERE service_number = :service_number AND personnel_id != :personnel_id");
            $checkStmt->bindParam(':service_number', $serviceNumber);
            $checkStmt->bindParam(':personnel_id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Service number already exists. Please use a unique service number.');
            } else {
                $stmt = $db->prepare("UPDATE personnel SET service_number = :service_number, first_name = :first_name, last_name = :last_name, rank = :rank, unit = :unit, position = :position, specialization = :specialization, email = :email, phone = :phone, status = :status WHERE personnel_id = :personnel_id");
                
                $stmt->bindParam(':personnel_id', $id);
                $stmt->bindParam(':service_number', $serviceNumber);
                $stmt->bindParam(':first_name', $firstName);
                $stmt->bindParam(':last_name', $lastName);
                $stmt->bindParam(':rank', $rank);
                $stmt->bindParam(':unit', $unit);
                $stmt->bindParam(':position', $position);
                $stmt->bindParam(':specialization', $specialization);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':status', $status);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Personnel updated successfully.');
                } else {
                    setFlashMessage('danger', 'Failed to update personnel.');
                }
            }
        }
        
        // Delete personnel
        if (isset($_POST['delete_personnel'])) {
            $id = intval($_POST['personnel_id']);
            
            $stmt = $db->prepare("DELETE FROM personnel WHERE personnel_id = :personnel_id");
            $stmt->bindParam(':personnel_id', $id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Personnel deleted successfully.');
            } else {
                setFlashMessage('danger', 'Failed to delete personnel.');
            }
        }
        
        // Redirect to list after form processing - MOVED HERE BEFORE ANY OUTPUT
        header("Location: " . SITE_URL . "personnel/");
        exit;
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get personnel data for editing
$editData = null;
if ($action === 'edit' && $personnelId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM personnel WHERE personnel_id = :personnel_id");
        $stmt->bindParam(':personnel_id', $personnelId);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editData) {
            setFlashMessage('danger', 'Personnel not found.');
            header("Location: " . SITE_URL . "personnel/");
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all personnel for listing
$personnel = [];
try {
    $stmt = $db->query("SELECT * FROM personnel ORDER BY rank, last_name");
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
                <h1 class="h3 mb-0 text-gray-800">Personnel Management</h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Personnel
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>personnel/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($action === 'list'): ?>
                <!-- Personnel List Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Personnel Database</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="personnelTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Service #</th>
                                        <th>Name</th>
                                        <th>Rank</th>
                                        <th>Unit</th>
                                        <th>Position</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($personnel) > 0): ?>
                                        <?php foreach ($personnel as $person): ?>
                                            <tr>
                                                <td><?php echo $person['service_number']; ?></td>
                                                <td><?php echo $person['last_name'] . ', ' . $person['first_name']; ?></td>
                                                <td><?php echo $person['rank']; ?></td>
                                                <td><?php echo $person['unit']; ?></td>
                                                <td><?php echo isset($person['position']) ? $person['position'] : 'N/A'; ?></td>
                                                <td><?php echo $person['specialization']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($person['status']) {
                                                        case 'active':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'reserve':
                                                            $statusClass = 'primary';
                                                            break;
                                                        case 'deployed':
                                                            $statusClass = 'warning';
                                                            break;
                                                        case 'leave':
                                                            $statusClass = 'info';
                                                            break;
                                                        case 'inactive':
                                                            $statusClass = 'secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($person['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $person['personnel_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $person['personnel_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $person['personnel_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete <strong><?php echo $person['rank'] . ' ' . $person['first_name'] . ' ' . $person['last_name']; ?></strong> from the system?
                                                                    <br><br>
                                                                    This action cannot be undone.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="personnel_id" value="<?php echo $person['personnel_id']; ?>">
                                                                        <button type="submit" name="delete_personnel" class="btn btn-danger">Delete</button>
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
                                            <td colspan="8" class="text-center">No personnel found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Personnel Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'add' ? 'Add New Personnel' : 'Edit Personnel'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="personnel_id" value="<?php echo $editData['personnel_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="service_number" class="form-label">Service Number</label>
                                    <input type="text" class="form-control" id="service_number" name="service_number" required 
                                           value="<?php echo $action === 'edit' ? $editData['service_number'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="rank" class="form-label">Rank</label>
                                    <input type="text" class="form-control" id="rank" name="rank" required 
                                           value="<?php echo $action === 'edit' ? $editData['rank'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required 
                                           value="<?php echo $action === 'edit' && isset($editData['first_name']) ? $editData['first_name'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required 
                                           value="<?php echo $action === 'edit' && isset($editData['last_name']) ? $editData['last_name'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="unit" class="form-label">Unit</label>
                                    <input type="text" class="form-control" id="unit" name="unit" required 
                                           value="<?php echo $action === 'edit' && isset($editData['unit']) ? $editData['unit'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="position" class="form-label">Position</label>
                                    <input type="text" class="form-control" id="position" name="position" required 
                                           value="<?php echo $action === 'edit' && isset($editData['position']) ? $editData['position'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" class="form-control" id="specialization" name="specialization" 
                                           value="<?php echo $action === 'edit' && isset($editData['specialization']) ? $editData['specialization'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="active" <?php echo ($action === 'edit' && isset($editData['status']) && $editData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="reserve" <?php echo ($action === 'edit' && isset($editData['status']) && $editData['status'] === 'reserve') ? 'selected' : ''; ?>>Reserve</option>
                                        <option value="deployed" <?php echo ($action === 'edit' && isset($editData['status']) && $editData['status'] === 'deployed') ? 'selected' : ''; ?>>Deployed</option>
                                        <option value="leave" <?php echo ($action === 'edit' && isset($editData['status']) && $editData['status'] === 'leave') ? 'selected' : ''; ?>>Leave</option>
                                        <option value="inactive" <?php echo ($action === 'edit' && isset($editData['status']) && $editData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo $action === 'edit' && isset($editData['email']) ? $editData['email'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo $action === 'edit' && isset($editData['phone']) ? $editData['phone'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($action === 'add'): ?>
                                    <button type="submit" name="add_personnel" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Personnel
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="update_personnel" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Personnel
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>personnel/" class="btn btn-secondary">
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
    const table = new DataTable('#personnelTable', {
        responsive: true,
        order: [[2, 'asc'], [1, 'asc']]
    });
});
</script>

<?php
require_once '../includes/footer.php';
?> 