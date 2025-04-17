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

// Handle test actions
$message = '';
$messageType = '';
$testAction = isset($_POST['test_action']) ? $_POST['test_action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($testAction === 'test_user_status') {
        try {
            $userId = intval($_POST['user_id']);
            $status = sanitizeInput($_POST['status']);
            
            // Get current user status first
            $checkStmt = $db->prepare("SELECT status FROM users WHERE user_id = :user_id");
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            $currentStatus = $checkStmt->fetchColumn();
            
            // Attempt status update
            $stmt = $db->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $message = "User status successfully updated from '{$currentStatus}' to '{$status}'";
                $messageType = 'success';
                
                // Log the action
                logAction($_SESSION['user_id'], 'user_management', "Updated user ID {$userId} status to {$status} (debug test)");
            } else {
                $message = "Failed to update user status. Database did not report an error.";
                $messageType = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($testAction === 'test_user_role') {
        try {
            $userId = intval($_POST['user_id']);
            $role = sanitizeInput($_POST['role']);
            
            // Get current user role first
            $checkStmt = $db->prepare("SELECT role FROM users WHERE user_id = :user_id");
            $checkStmt->bindParam(':user_id', $userId);
            $checkStmt->execute();
            $currentRole = $checkStmt->fetchColumn();
            
            // Attempt role update
            $stmt = $db->prepare("UPDATE users SET role = :role, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':role', $role);
            
            if ($stmt->execute()) {
                $message = "User role successfully updated from '{$currentRole}' to '{$role}'";
                $messageType = 'success';
                
                // Log the action
                logAction($_SESSION['user_id'], 'user_management', "Updated user ID {$userId} role to {$role} (debug test)");
            } else {
                $message = "Failed to update user role. Database did not report an error.";
                $messageType = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($testAction === 'check_permissions') {
        try {
            // Check table permissions
            $tables = ['users', 'user_logs', 'transactions'];
            $results = [];
            
            foreach ($tables as $table) {
                // Test SELECT
                try {
                    $db->query("SELECT 1 FROM {$table} LIMIT 1");
                    $results[$table]['select'] = true;
                } catch (PDOException $e) {
                    $results[$table]['select'] = false;
                    $results[$table]['select_error'] = $e->getMessage();
                }
                
                // Test UPDATE (on a dummy condition that won't affect real data)
                try {
                    $db->query("UPDATE {$table} SET updated_at = updated_at WHERE 1=0");
                    $results[$table]['update'] = true;
                } catch (PDOException $e) {
                    $results[$table]['update'] = false;
                    $results[$table]['update_error'] = $e->getMessage();
                }
                
                // Test INSERT (with rollback so no data is actually inserted)
                try {
                    $db->beginTransaction();
                    
                    if ($table === 'users') {
                        $db->query("INSERT INTO users (username, password, full_name, email, role, status) 
                                   VALUES ('_test_user', '_test_pass', '_Test User', '_test@example.com', 'viewer', 'pending')");
                    } elseif ($table === 'user_logs') {
                        $db->query("INSERT INTO user_logs (user_id, action, details, ip_address) 
                                   VALUES (1, 'test', 'Permission test', '127.0.0.1')");
                    } elseif ($table === 'transactions') {
                        $db->query("INSERT INTO transactions (transaction_type, item_id, user_id, quantity, status)
                                   VALUES ('test', 1, 1, 1, 'pending')");
                    }
                    
                    $results[$table]['insert'] = true;
                    $db->rollBack();
                } catch (PDOException $e) {
                    $results[$table]['insert'] = false;
                    $results[$table]['insert_error'] = $e->getMessage();
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                }
            }
            
            $message = "Permission check complete. See results below.";
            $messageType = 'info';
            $permissionResults = $results;
        } catch (PDOException $e) {
            $message = "Error checking permissions: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get all users for listing
$users = [];
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching users: " . $e->getMessage();
    $messageType = 'danger';
}

// Database information
$dbInfo = [];
try {
    $dbInfo['version'] = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    $dbInfo['driver'] = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $dbInfo['connection'] = $db->getAttribute(PDO::ATTR_CONNECTION_STATUS);
} catch (PDOException $e) {
    $dbInfo['error'] = $e->getMessage();
}

// Include header
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">User Management Debug Tool</h6>
                    <a href="users.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to User Management
                    </a>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Warning:</strong> 
                        This is a diagnostic tool for administrators only. Use these tests to diagnose issues with the user management system.
                    </div>
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Test User Status Update</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="user_id_status" class="form-label">Select User</label>
                                            <select class="form-select" id="user_id_status" name="user_id" required>
                                                <option value="">Select User</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['user_id']; ?>">
                                                        <?php echo $user['username']; ?> (<?php echo $user['full_name']; ?>) - Current: <?php echo $user['status']; ?>
                                                    </option>
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
                                        
                                        <input type="hidden" name="test_action" value="test_user_status">
                                        <button type="submit" class="btn btn-primary">
                                            Test Status Update
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Test User Role Update</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="mb-3">
                                            <label for="user_id_role" class="form-label">Select User</label>
                                            <select class="form-select" id="user_id_role" name="user_id" required>
                                                <option value="">Select User</option>
                                                <?php foreach ($users as $user): ?>
                                                    <option value="<?php echo $user['user_id']; ?>">
                                                        <?php echo $user['username']; ?> (<?php echo $user['full_name']; ?>) - Current: <?php echo $user['role']; ?>
                                                    </option>
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
                                        
                                        <input type="hidden" name="test_action" value="test_user_role">
                                        <button type="submit" class="btn btn-primary">
                                            Test Role Update
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Database Permissions Test</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" class="mb-4">
                                <input type="hidden" name="test_action" value="check_permissions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-database"></i> Check Database Permissions
                                </button>
                            </form>
                            
                            <?php if (isset($permissionResults)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Table</th>
                                                <th>SELECT</th>
                                                <th>UPDATE</th>
                                                <th>INSERT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($permissionResults as $table => $perms): ?>
                                                <tr>
                                                    <td><strong><?php echo $table; ?></strong></td>
                                                    <td>
                                                        <?php if ($perms['select']): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle"></i> OK</span>
                                                        <?php else: ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle"></i> Failed</span>
                                                            <div class="small text-muted"><?php echo $perms['select_error'] ?? ''; ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($perms['update']): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle"></i> OK</span>
                                                        <?php else: ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle"></i> Failed</span>
                                                            <div class="small text-muted"><?php echo $perms['update_error'] ?? ''; ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($perms['insert']): ?>
                                                            <span class="text-success"><i class="fas fa-check-circle"></i> OK</span>
                                                        <?php else: ?>
                                                            <span class="text-danger"><i class="fas fa-times-circle"></i> Failed</span>
                                                            <div class="small text-muted"><?php echo $perms['insert_error'] ?? ''; ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">System Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>PHP Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th>PHP Version</th>
                                            <td><?php echo phpversion(); ?></td>
                                        </tr>
                                        <tr>
                                            <th>PDO Drivers</th>
                                            <td><?php echo implode(', ', PDO::getAvailableDrivers()); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Max Execution Time</th>
                                            <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
                                        </tr>
                                        <tr>
                                            <th>Memory Limit</th>
                                            <td><?php echo ini_get('memory_limit'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6>Database Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Database Driver</th>
                                            <td><?php echo $dbInfo['driver'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Database Version</th>
                                            <td><?php echo $dbInfo['version'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Connection Status</th>
                                            <td><?php echo $dbInfo['connection'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <?php if (isset($dbInfo['error'])): ?>
                                            <tr>
                                                <th>Error</th>
                                                <td class="text-danger"><?php echo $dbInfo['error']; ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 