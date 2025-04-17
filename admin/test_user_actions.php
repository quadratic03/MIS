<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    setFlashMessage('danger', 'Access denied. Admin privileges required.');
    redirect('index.php');
    exit;
}

// Process direct form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update user status
        if (isset($_POST['update_status'])) {
            $id = intval($_POST['user_id']);
            $status = sanitizeInput($_POST['status']);
            
            $stmt = $db->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $id);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                setFlashMessage('success', "User status updated successfully to: $status");
                
                // Log the transaction
                logAction($_SESSION['user_id'], 'user_management', "Updated user ID $id status to $status");
            } else {
                setFlashMessage('danger', 'Failed to update user status.');
            }
        }
        
        // Update user role
        if (isset($_POST['update_role'])) {
            $id = intval($_POST['user_id']);
            $role = sanitizeInput($_POST['role']);
            
            $stmt = $db->prepare("UPDATE users SET role = :role, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $id);
            $stmt->bindParam(':role', $role);
            
            if ($stmt->execute()) {
                setFlashMessage('success', "User role updated successfully to: $role");
                
                // Log the transaction
                logAction($_SESSION['user_id'], 'user_management', "Updated user ID $id role to $role");
            } else {
                setFlashMessage('danger', 'Failed to update user role.');
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all users for listing
$users = [];
try {
    $stmt = $db->query("SELECT * FROM users WHERE status != 'archived' ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Include header
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Management Test Page</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Test Page:</strong> This page allows direct testing of user status and role update functionality.
                    </div>
                    
                    <?php 
                    // Display flash messages if any
                    $flash_message = getFlashMessage();
                    if ($flash_message): 
                    ?>
                        <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show mb-4">
                            <?php echo $flash_message['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <h5>Debug Information</h5>
                        <div class="bg-light p-3">
                            <p><strong>POST Data:</strong> <?php echo !empty($_POST) ? json_encode($_POST) : 'No POST data'; ?></p>
                            <p><strong>JavaScript Loading:</strong> <span id="js-test">If you see this message, JavaScript might not be running properly.</span></p>
                            <script>document.getElementById('js-test').innerHTML = 'JavaScript is working correctly!';</script>
                            <p><strong>jQuery Version:</strong> <span id="jquery-version">Checking...</span></p>
                            <script>
                                document.getElementById('jquery-version').innerHTML = typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'Not loaded!';
                            </script>
                            <p><strong>Bootstrap Version:</strong> <span id="bootstrap-version">Checking...</span></p>
                            <script>
                                document.getElementById('bootstrap-version').innerHTML = typeof bootstrap !== 'undefined' ? 'Loaded' : 'Not loaded!';
                            </script>
                        </div>
                    </div>
                    
                    <h5>Direct Form Submission</h5>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Update User Status</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="user_id_status" class="form-label">Select User</label>
                                            <select class="form-select" id="user_id_status" name="user_id" required>
                                                <option value="">Select User</option>
                                                <?php foreach ($users as $user): ?>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <option value="<?php echo $user['user_id']; ?>">
                                                            <?php echo $user['username']; ?> (<?php echo $user['full_name']; ?>) - Current: <?php echo $user['status']; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="status" class="form-label">New Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="active">Active</option>
                                                <option value="pending">Pending</option>
                                                <option value="suspended">Suspended</option>
                                                <option value="archived">Archived</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="update_status" value="1" class="btn btn-primary">
                                            Update Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Update User Role</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="user_id_role" class="form-label">Select User</label>
                                            <select class="form-select" id="user_id_role" name="user_id" required>
                                                <option value="">Select User</option>
                                                <?php foreach ($users as $user): ?>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <option value="<?php echo $user['user_id']; ?>">
                                                            <?php echo $user['username']; ?> (<?php echo $user['full_name']; ?>) - Current: <?php echo $user['role']; ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="role" class="form-label">New Role</label>
                                            <select class="form-select" id="role" name="role" required>
                                                <option value="">Select Role</option>
                                                <option value="admin">Administrator</option>
                                                <option value="inventory_manager">Inventory Manager</option>
                                                <option value="field_officer">Field Officer</option>
                                                <option value="viewer">Viewer</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="update_role" value="1" class="btn btn-primary">
                                            Update Role
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="users.php" class="btn btn-secondary">Return to User Management</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 