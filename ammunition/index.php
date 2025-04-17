<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Check if an action is requested
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$ammoId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new ammunition
        if (isset($_POST['add_ammo'])) {
            $ammoCode = sanitizeInput($_POST['ammo_code']);
            $name = sanitizeInput($_POST['name']);
            $category = sanitizeInput($_POST['category']);
            $caliber = sanitizeInput($_POST['caliber']);
            $quantity = intval($_POST['quantity']);
            $manufactureDate = $_POST['manufacture_date'] ? $_POST['manufacture_date'] : null;
            $expirationDate = $_POST['expiration_date'] ? $_POST['expiration_date'] : null;
            $storageLocation = sanitizeInput($_POST['storage_location']);
            $status = sanitizeInput($_POST['status']);
            $reorderLevel = intval($_POST['reorder_level']);
            
            // Check if ammunition code already exists
            $checkStmt = $db->prepare("SELECT ammo_id FROM ammunition WHERE ammo_code = :ammo_code");
            $checkStmt->bindParam(':ammo_code', $ammoCode);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Ammunition code already exists. Please use a unique code.');
            } else {
                $stmt = $db->prepare("INSERT INTO ammunition (ammo_code, name, category, caliber, quantity, manufacture_date, expiration_date, storage_location, status, reorder_level) VALUES (:ammo_code, :name, :category, :caliber, :quantity, :manufacture_date, :expiration_date, :storage_location, :status, :reorder_level)");
                
                $stmt->bindParam(':ammo_code', $ammoCode);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':caliber', $caliber);
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':manufacture_date', $manufactureDate);
                $stmt->bindParam(':expiration_date', $expirationDate);
                $stmt->bindParam(':storage_location', $storageLocation);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':reorder_level', $reorderLevel);
                
                if ($stmt->execute()) {
                    // Log transaction
                    $ammoId = $db->lastInsertId();
                    $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, to_location, quantity, transaction_date, notes) VALUES ('acquisition', 'ammunition', :item_id, :to_location, :quantity, NOW(), :notes)");
                    $transactionStmt->bindParam(':item_id', $ammoId);
                    $transactionStmt->bindParam(':to_location', $storageLocation);
                    $transactionStmt->bindParam(':quantity', $quantity);
                    $notes = "Added new ammunition: $name";
                    $transactionStmt->bindParam(':notes', $notes);
                    $transactionStmt->execute();
                    
                    setFlashMessage('success', 'Ammunition added successfully.');
                } else {
                    setFlashMessage('danger', 'Failed to add ammunition.');
                }
            }
        }
        
        // Update ammunition
        if (isset($_POST['update_ammo'])) {
            $id = intval($_POST['ammo_id']);
            $ammoCode = sanitizeInput($_POST['ammo_code']);
            $name = sanitizeInput($_POST['name']);
            $category = sanitizeInput($_POST['category']);
            $caliber = sanitizeInput($_POST['caliber']);
            $quantity = intval($_POST['quantity']);
            $manufactureDate = $_POST['manufacture_date'] ? $_POST['manufacture_date'] : null;
            $expirationDate = $_POST['expiration_date'] ? $_POST['expiration_date'] : null;
            $storageLocation = sanitizeInput($_POST['storage_location']);
            $status = sanitizeInput($_POST['status']);
            $reorderLevel = intval($_POST['reorder_level']);
            
            // Check if ammunition code already exists for other ammunition
            $checkStmt = $db->prepare("SELECT ammo_id FROM ammunition WHERE ammo_code = :ammo_code AND ammo_id != :ammo_id");
            $checkStmt->bindParam(':ammo_code', $ammoCode);
            $checkStmt->bindParam(':ammo_id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                setFlashMessage('danger', 'Ammunition code already exists. Please use a unique code.');
            } else {
                // Get original quantity to log the difference
                $origStmt = $db->prepare("SELECT quantity, storage_location FROM ammunition WHERE ammo_id = :ammo_id");
                $origStmt->bindParam(':ammo_id', $id);
                $origStmt->execute();
                $origData = $origStmt->fetch(PDO::FETCH_ASSOC);
                $quantityDiff = $quantity - $origData['quantity'];
                
                $stmt = $db->prepare("UPDATE ammunition SET ammo_code = :ammo_code, name = :name, category = :category, caliber = :caliber, quantity = :quantity, manufacture_date = :manufacture_date, expiration_date = :expiration_date, storage_location = :storage_location, status = :status, reorder_level = :reorder_level WHERE ammo_id = :ammo_id");
                
                $stmt->bindParam(':ammo_id', $id);
                $stmt->bindParam(':ammo_code', $ammoCode);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':caliber', $caliber);
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':manufacture_date', $manufactureDate);
                $stmt->bindParam(':expiration_date', $expirationDate);
                $stmt->bindParam(':storage_location', $storageLocation);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':reorder_level', $reorderLevel);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'Ammunition updated successfully.');
                    
                    // Log transaction if quantity or location changed
                    if ($quantityDiff != 0 || $origData['storage_location'] != $storageLocation) {
                        $transactionType = $quantityDiff > 0 ? 'acquisition' : 'consumption';
                        $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, from_location, to_location, quantity, transaction_date, notes) VALUES (:transaction_type, 'ammunition', :item_id, :from_location, :to_location, :quantity, NOW(), :notes)");
                        $transactionStmt->bindParam(':transaction_type', $transactionType);
                        $transactionStmt->bindParam(':item_id', $id);
                        $transactionStmt->bindParam(':from_location', $origData['storage_location']);
                        $transactionStmt->bindParam(':to_location', $storageLocation);
                        $transactionStmt->bindParam(':quantity', abs($quantityDiff));
                        $notes = "Updated ammunition: $name";
                        $transactionStmt->bindParam(':notes', $notes);
                        $transactionStmt->execute();
                    }
                } else {
                    setFlashMessage('danger', 'Failed to update ammunition.');
                }
            }
        }
        
        // Delete ammunition
        if (isset($_POST['delete_ammo'])) {
            $id = intval($_POST['ammo_id']);
            
            // Get ammunition info for transaction log
            $infoStmt = $db->prepare("SELECT name, quantity, storage_location FROM ammunition WHERE ammo_id = :ammo_id");
            $infoStmt->bindParam(':ammo_id', $id);
            $infoStmt->execute();
            $ammoInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("DELETE FROM ammunition WHERE ammo_id = :ammo_id");
            $stmt->bindParam(':ammo_id', $id);
            
            if ($stmt->execute()) {
                setFlashMessage('success', 'Ammunition deleted successfully.');
                // Log transaction
                $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, from_location, quantity, transaction_date, notes) VALUES ('consumption', 'ammunition', :item_id, :from_location, :quantity, NOW(), :notes)");
                $transactionStmt->bindParam(':item_id', $id);
                $transactionStmt->bindParam(':from_location', $ammoInfo['storage_location']);
                $transactionStmt->bindParam(':quantity', $ammoInfo['quantity']);
                $notes = "Deleted ammunition: {$ammoInfo['name']}";
                $transactionStmt->bindParam(':notes', $notes);
                $transactionStmt->execute();
            } else {
                setFlashMessage('danger', 'Failed to delete ammunition.');
            }
        }
        
        // Redirect to list after form processing - MOVED HERE BEFORE ANY OUTPUT
        header("Location: " . SITE_URL . "ammunition/");
        exit;
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get ammunition data for editing
$editData = null;
if ($action === 'edit' && $ammoId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM ammunition WHERE ammo_id = :ammo_id");
        $stmt->bindParam(':ammo_id', $ammoId);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editData) {
            setFlashMessage('danger', 'Ammunition not found.');
            header("Location: " . SITE_URL . "ammunition/");
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all ammunition for listing
$ammunition = [];
try {
    $stmt = $db->query("SELECT * FROM ammunition ORDER BY ammo_code");
    $ammunition = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <h1 class="h3 mb-0 text-gray-800">Ammunition Management</h1>
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Ammunition
                    </a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>ammunition/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($action === 'list'): ?>
                <!-- Ammunition List Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Ammunition Inventory</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="ammunitionTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Caliber</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Storage Location</th>
                                        <th>Expiration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($ammunition) > 0): ?>
                                        <?php foreach ($ammunition as $ammo): ?>
                                            <?php
                                            // Determine if ammo is close to expiry
                                            $isExpired = false;
                                            $isExpiringSoon = false;
                                            
                                            if ($ammo['expiration_date']) {
                                                $expiryDate = new DateTime($ammo['expiration_date']);
                                                $now = new DateTime();
                                                $diff = $now->diff($expiryDate);
                                                
                                                if ($expiryDate < $now) {
                                                    $isExpired = true;
                                                } elseif ($diff->days <= 90) {
                                                    $isExpiringSoon = true;
                                                }
                                            }
                                            
                                            // Determine if ammo is low on stock
                                            $isLowStock = $ammo['quantity'] <= $ammo['reorder_level'];
                                            ?>
                                            <tr class="<?php echo $isExpired ? 'table-danger' : ($isExpiringSoon ? 'table-warning' : ($isLowStock ? 'table-info' : '')); ?>">
                                                <td><?php echo $ammo['ammo_code']; ?></td>
                                                <td><?php echo $ammo['name']; ?></td>
                                                <td>
                                                    <?php
                                                    $categoryClass = '';
                                                    switch ($ammo['category']) {
                                                        case 'small_arms':
                                                            $categoryClass = 'primary';
                                                            break;
                                                        case 'artillery':
                                                            $categoryClass = 'danger';
                                                            break;
                                                        case 'explosive':
                                                            $categoryClass = 'warning';
                                                            break;
                                                        case 'specialized':
                                                            $categoryClass = 'info';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $categoryClass; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $ammo['category'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $ammo['caliber']; ?></td>
                                                <td>
                                                    <?php echo number_format($ammo['quantity']); ?>
                                                    <?php if ($isLowStock): ?>
                                                        <span class="badge badge-danger">Low</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($ammo['status']) {
                                                        case 'available':
                                                            $statusClass = 'success';
                                                            break;
                                                        case 'reserved':
                                                            $statusClass = 'primary';
                                                            break;
                                                        case 'depleted':
                                                            $statusClass = 'secondary';
                                                            break;
                                                        case 'expired':
                                                            $statusClass = 'danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($ammo['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $ammo['storage_location']; ?></td>
                                                <td>
                                                    <?php if ($ammo['expiration_date']): ?>
                                                        <?php echo date('M d, Y', strtotime($ammo['expiration_date'])); ?>
                                                        <?php if ($isExpired): ?>
                                                            <span class="badge badge-danger">Expired</span>
                                                        <?php elseif ($isExpiringSoon): ?>
                                                            <span class="badge badge-warning">Expiring Soon</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?action=edit&id=<?php echo $ammo['ammo_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $ammo['ammo_id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $ammo['ammo_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete <strong><?php echo $ammo['name']; ?></strong> (<?php echo $ammo['ammo_code']; ?>)?
                                                                    <br>
                                                                    This will remove <?php echo number_format($ammo['quantity']); ?> units from inventory.
                                                                    <br><br>
                                                                    This action cannot be undone.
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="ammo_id" value="<?php echo $ammo['ammo_id']; ?>">
                                                                        <button type="submit" name="delete_ammo" class="btn btn-danger">Delete</button>
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
                                            <td colspan="9" class="text-center">No ammunition found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'add' || $action === 'edit'): ?>
                <!-- Add/Edit Ammunition Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $action === 'add' ? 'Add New Ammunition' : 'Edit Ammunition'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="ammo_id" value="<?php echo $editData['ammo_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ammo_code" class="form-label">Ammunition Code</label>
                                    <input type="text" class="form-control" id="ammo_code" name="ammo_code" required 
                                           value="<?php echo $action === 'edit' ? $editData['ammo_code'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo $action === 'edit' ? $editData['name'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="small_arms" <?php echo ($action === 'edit' && $editData['category'] === 'small_arms') ? 'selected' : ''; ?>>Small Arms</option>
                                        <option value="artillery" <?php echo ($action === 'edit' && $editData['category'] === 'artillery') ? 'selected' : ''; ?>>Artillery</option>
                                        <option value="explosive" <?php echo ($action === 'edit' && $editData['category'] === 'explosive') ? 'selected' : ''; ?>>Explosive</option>
                                        <option value="specialized" <?php echo ($action === 'edit' && $editData['category'] === 'specialized') ? 'selected' : ''; ?>>Specialized</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="caliber" class="form-label">Caliber</label>
                                    <input type="text" class="form-control" id="caliber" name="caliber" 
                                           value="<?php echo $action === 'edit' ? $editData['caliber'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" required 
                                           value="<?php echo $action === 'edit' ? $editData['quantity'] : '0'; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reorder_level" class="form-label">Reorder Level</label>
                                    <input type="number" class="form-control" id="reorder_level" name="reorder_level" required 
                                           value="<?php echo $action === 'edit' ? $editData['reorder_level'] : '0'; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="manufacture_date" class="form-label">Manufacture Date</label>
                                    <input type="date" class="form-control" id="manufacture_date" name="manufacture_date" 
                                           value="<?php echo $action === 'edit' && $editData['manufacture_date'] ? date('Y-m-d', strtotime($editData['manufacture_date'])) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="expiration_date" class="form-label">Expiration Date</label>
                                    <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                           value="<?php echo $action === 'edit' && $editData['expiration_date'] ? date('Y-m-d', strtotime($editData['expiration_date'])) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="storage_location" class="form-label">Storage Location</label>
                                    <input type="text" class="form-control" id="storage_location" name="storage_location" required 
                                           value="<?php echo $action === 'edit' ? $editData['storage_location'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="available" <?php echo ($action === 'edit' && $editData['status'] === 'available') ? 'selected' : ''; ?>>Available</option>
                                        <option value="reserved" <?php echo ($action === 'edit' && $editData['status'] === 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                                        <option value="depleted" <?php echo ($action === 'edit' && $editData['status'] === 'depleted') ? 'selected' : ''; ?>>Depleted</option>
                                        <option value="expired" <?php echo ($action === 'edit' && $editData['status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($action === 'add'): ?>
                                    <button type="submit" name="add_ammo" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add Ammunition
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="update_ammo" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Ammunition
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>ammunition/" class="btn btn-secondary">
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
    const table = new DataTable('#ammunitionTable', {
        responsive: true,
        order: [[0, 'asc']]
    });
});
</script>

<?php
require_once '../includes/footer.php';
?> 