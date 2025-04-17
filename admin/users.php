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

// Check if an action is requested
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new user
        if (isset($_POST['add_user'])) {
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            $fullName = sanitizeInput($_POST['full_name']);
            $email = sanitizeInput($_POST['email']);
            $role = sanitizeInput($_POST['role']);
            
            // Validate inputs
            $errors = [];
            
            if (empty($username)) $errors[] = "Username is required.";
            if (empty($password)) $errors[] = "Password is required.";
            if (empty($fullName)) $errors[] = "Full name is required.";
            if (empty($email)) $errors[] = "Email is required.";
            if (empty($role)) $errors[] = "Role is required.";
            
            if ($password !== $confirmPassword) {
                $errors[] = "Passwords do not match.";
            }
            
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            }
            
            // Check if username or email already exists
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                $errors[] = "Username or email already exists.";
            }
            
            if (empty($errors)) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (:username, :password, :full_name, :email, :role, 'pending')");
                
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':full_name', $fullName);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':role', $role);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'User added successfully. User must be activated by an admin before they can log in.');
                    
                    // Redirect to user list
                    header("Location: " . SITE_URL . "admin/users.php");
                    exit;
                } else {
                    setFlashMessage('danger', 'Failed to add user.');
                }
            } else {
                // Display validation errors
                $errorMessage = implode('<br>', $errors);
                setFlashMessage('danger', $errorMessage);
            }
        }
        
        // Update user status
        if (isset($_POST['update_status'])) {
            $id = intval($_POST['user_id']);
            $status = sanitizeInput($_POST['status']);
            
            $stmt = $db->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $id);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                if ($status === 'archived') {
                    setFlashMessage('success', 'User has been archived successfully.');
                } else {
                    setFlashMessage('success', 'User status updated successfully.');
                }
                
                // Redirect to user list
                header("Location: " . SITE_URL . "admin/users.php");
                exit;
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
                setFlashMessage('success', 'User role updated successfully.');
                
                // Redirect to user list
                header("Location: " . SITE_URL . "admin/users.php");
                exit;
            } else {
                setFlashMessage('danger', 'Failed to update user role.');
            }
        }
        
        // Reset user password
        if (isset($_POST['reset_password'])) {
            $id = intval($_POST['user_id']);
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($newPassword !== $confirmPassword) {
                setFlashMessage('danger', 'Passwords do not match.');
            } elseif (strlen($newPassword) < 8) {
                setFlashMessage('danger', 'Password must be at least 8 characters long.');
            } else {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $id);
                $stmt->bindParam(':password', $hashedPassword);
                
                if ($stmt->execute()) {
                    setFlashMessage('success', 'User password reset successfully.');
                    
                    // Redirect to user list
                    header("Location: " . SITE_URL . "admin/users.php");
                    exit;
                } else {
                    setFlashMessage('danger', 'Failed to reset user password.');
                }
            }
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get user data for editing
$editData = null;
if ($action === 'edit' && $userId > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $editData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$editData) {
            setFlashMessage('danger', 'User not found.');
            header("Location: " . SITE_URL . "admin/users.php");
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    }
}

// Get all users for listing
$users = [];
try {
    // Exclude archived users by default, unless viewing archived users
    $showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
    
    if ($showArchived) {
        $stmt = $db->query("SELECT * FROM users WHERE status = 'archived' ORDER BY username");
    } else {
        $stmt = $db->query("SELECT * FROM users WHERE status != 'archived' ORDER BY username");
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Include header AFTER all potential redirects
require_once '../includes/header.php';
?>

<!-- Custom CSS for dropdown forms -->
<style>
    .dropdown-item-form {
        margin: 0;
        padding: 0;
    }
    .dropdown-item-form button.dropdown-item {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        padding: 0.25rem 1rem;
        clear: both;
        font-weight: 400;
        color: inherit;
        text-decoration: none;
        white-space: nowrap;
    }
    .dropdown-item-form button.dropdown-item:hover, 
    .dropdown-item-form button.dropdown-item:focus {
        color: #16181b;
        text-decoration: none;
        background-color: #f8f9fa;
    }
    
    /* New styles for action buttons */
    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    .action-buttons form {
        display: inline-block;
        margin-right: 0.25rem;
        margin-bottom: 0.25rem;
    }
    .action-buttons .btn-group {
        margin-bottom: 0.5rem;
    }
    
    /* DataTables buttons styling */
    .dt-buttons {
        padding: 15px 0;
    }
    .dt-buttons .btn {
        font-size: 0.9rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        margin-right: 0.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    .dt-buttons .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .dataTables_filter {
        padding: 15px 0;
    }
</style>

<!-- Hidden forms for status and role updates -->
<form id="statusUpdateForm" method="post" action="" style="display: none;">
    <input type="hidden" name="user_id" id="statusUserId" value="">
    <input type="hidden" name="status" id="statusValue" value="">
    <input type="hidden" name="update_status" value="1">
</form>

<form id="roleUpdateForm" method="post" action="" style="display: none;">
    <input type="hidden" name="user_id" id="roleUserId" value="">
    <input type="hidden" name="role" id="roleValue" value="">
    <input type="hidden" name="update_role" value="1">
</form>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">User Management</h1>
                <div>
                    <?php if (!isset($_GET['show_archived']) || $_GET['show_archived'] !== '1'): ?>
                        <a href="?show_archived=1" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-archive"></i> Show Archived Users
                        </a>
                    <?php else: ?>
                        <a href="?" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-users"></i> Show Active Users
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($action === 'list'): ?>
                        <a href="?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New User
                        </a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>admin/users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to User List
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php 
            // Display flash messages if any
            $flash_message = getFlashMessage();
            if ($flash_message): 
            ?>
                <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show mb-4">
                    <strong>
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <i class="fas fa-check-circle"></i> Success:
                        <?php elseif ($flash_message['type'] === 'danger'): ?>
                            <i class="fas fa-exclamation-circle"></i> Error:
                        <?php elseif ($flash_message['type'] === 'warning'): ?>
                            <i class="fas fa-exclamation-triangle"></i> Warning:
                        <?php else: ?>
                            <i class="fas fa-info-circle"></i> Info:
                        <?php endif; ?>
                    </strong>
                    <?php echo $flash_message['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($action === 'list'): ?>
                <!-- User List Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?php echo $showArchived ? 'Archived Users' : 'System Users'; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="usersTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getRoleBadgeClass($user['role']); ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeClass($user['status']); ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <!-- Direct action buttons for status and role -->
                                                        <div class="action-buttons">
                                                            <div class="btn-group mb-2">
                                                                <?php if ($user['status'] !== 'active'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="status" value="active">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-success" onclick="return confirm('Grant access to this user?');">
                                                                            <i class="fas fa-check-circle"></i> Activate
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($user['status'] !== 'suspended'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="status" value="suspended">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-warning" onclick="return confirm('Suspend access for this user?');">
                                                                            <i class="fas fa-ban"></i> Suspend
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($user['status'] !== 'archived'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="status" value="archived">
                                                                        <button type="submit" name="update_status" class="btn btn-sm btn-danger" onclick="return confirm('Archive this user? They will no longer appear in the main user list.');">
                                                                            <i class="fas fa-archive"></i> Archive
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Role buttons -->
                                                            <div class="d-flex flex-wrap mb-2">
                                                                <?php if ($user['role'] !== 'admin'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="role" value="admin">
                                                                        <button type="submit" name="update_role" class="btn btn-sm btn-outline-danger" onclick="return confirm('Change this user\'s role to Administrator?');">
                                                                            <i class="fas fa-user-shield"></i> Admin
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($user['role'] !== 'inventory_manager'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="role" value="inventory_manager">
                                                                        <button type="submit" name="update_role" class="btn btn-sm btn-outline-success" onclick="return confirm('Change this user\'s role to Inventory Manager?');">
                                                                            <i class="fas fa-boxes"></i> Inventory
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($user['role'] !== 'field_officer'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="role" value="field_officer">
                                                                        <button type="submit" name="update_role" class="btn btn-sm btn-outline-primary" onclick="return confirm('Change this user\'s role to Field Officer?');">
                                                                            <i class="fas fa-clipboard-check"></i> Field
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($user['role'] !== 'viewer'): ?>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                        <input type="hidden" name="role" value="viewer">
                                                                        <button type="submit" name="update_role" class="btn btn-sm btn-outline-info" onclick="return confirm('Change this user\'s role to Viewer?');">
                                                                            <i class="fas fa-eye"></i> Viewer
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Password Reset Button -->
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $user['user_id']; ?>">
                                                                <i class="fas fa-key"></i> Reset Password
                                                            </button>
                                                        </div>
                                                        
                                                        <!-- Password Reset Modal -->
                                                        <div class="modal fade" id="resetPasswordModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <form method="post" action="">
                                                                        <div class="modal-body">
                                                                            <p>Reset password for <strong><?php echo $user['full_name']; ?></strong> (<?php echo $user['username']; ?>)</p>
                                                                            
                                                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                            
                                                                            <div class="mb-3">
                                                                                <label for="new_password<?php echo $user['user_id']; ?>" class="form-label">New Password</label>
                                                                                <input type="password" class="form-control" id="new_password<?php echo $user['user_id']; ?>" name="new_password" required minlength="8">
                                                                                <div class="form-text">At least 8 characters.</div>
                                                                            </div>
                                                                            
                                                                            <div class="mb-3">
                                                                                <label for="confirm_password<?php echo $user['user_id']; ?>" class="form-label">Confirm New Password</label>
                                                                                <input type="password" class="form-control" id="confirm_password<?php echo $user['user_id']; ?>" name="confirm_password" required minlength="8">
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Current User</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'add'): ?>
                <!-- Add User Form -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Add New User</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Select Role</option>
                                            <option value="admin">Administrator</option>
                                            <option value="inventory_manager">Inventory Manager</option>
                                            <option value="field_officer">Field Officer</option>
                                            <option value="viewer">Viewer (Read-only)</option>
                                        </select>
                                        <div class="form-text">
                                            <ul class="mt-2">
                                                <li><strong>Administrator:</strong> Full access to all system features</li>
                                                <li><strong>Inventory Manager:</strong> Can receive and manage inventory</li>
                                                <li><strong>Field Officer:</strong> Can track and report on inventory usage</li>
                                                <li><strong>Viewer:</strong> Read-only access to reports and information</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                        <div class="form-text">At least 8 characters.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> New users will be created with <strong>pending</strong> status. An administrator must grant them access before they can log in.
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="add_user" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Add User
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-undo"></i> Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom JavaScript for User Management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables on users table
    $('#usersTable').DataTable({
        responsive: true,
        order: [[0, 'asc']], // Sort by username
        pageLength: 25,
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ users per page",
            info: "Showing _START_ to _END_ of _TOTAL_ users",
            emptyTable: "No users found",
            zeroRecords: "No matching users found"
        },
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row"<"col-md-6"l><"col-md-6"p>>',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copy',
                className: 'btn btn-secondary me-2'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success me-2'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger me-2'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-primary me-2'
            }
        ]
    });
    
    // Force Bootstrap dropdown components to initialize
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    });
    
    // Handle status change clicks - both ways (jQuery and vanilla JS)
    // jQuery method for better compatibility
    $('[data-status-action]').on('click', function(e) {
        e.preventDefault();
        
        const userId = $(this).data('user-id');
        const status = $(this).data('status-action');
        let message = '';
        
        if (status === 'active') {
            message = 'Grant access to this user?';
        } else if (status === 'suspended') {
            message = 'Suspend access for this user?';
        } else if (status === 'archived') {
            message = 'Archive this user? They will no longer appear in the main user list.';
        }
        
        if (confirm(message)) {
            $('#statusUserId').val(userId);
            $('#statusValue').val(status);
            $('#statusUpdateForm').submit();
        }
    });
    
    // Vanilla JS method as backup
    document.querySelectorAll('[data-status-action]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.getAttribute('data-user-id');
            const status = this.getAttribute('data-status-action');
            let message = '';
            
            if (status === 'active') {
                message = 'Grant access to this user?';
            } else if (status === 'suspended') {
                message = 'Suspend access for this user?';
            } else if (status === 'archived') {
                message = 'Archive this user? They will no longer appear in the main user list.';
            }
            
            if (confirm(message)) {
                document.getElementById('statusUserId').value = userId;
                document.getElementById('statusValue').value = status;
                document.getElementById('statusUpdateForm').submit();
            }
        });
    });
    
    // Handle role change clicks - both ways
    // jQuery method for better compatibility
    $('[data-role-action]').on('click', function(e) {
        e.preventDefault();
        
        const userId = $(this).data('user-id');
        const role = $(this).data('role-action');
        
        if (confirm('Change this user\'s role to ' + role + '?')) {
            $('#roleUserId').val(userId);
            $('#roleValue').val(role);
            $('#roleUpdateForm').submit();
        }
    });
    
    // Vanilla JS method as backup
    document.querySelectorAll('[data-role-action]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const userId = this.getAttribute('data-user-id');
            const role = this.getAttribute('data-role-action');
            
            if (confirm('Change this user\'s role to ' + role + '?')) {
                document.getElementById('roleUserId').value = userId;
                document.getElementById('roleValue').value = role;
                document.getElementById('roleUpdateForm').submit();
            }
        });
    });
    
    // Make regular dropdown forms work too (backup method)
    document.querySelectorAll('form [name="update_status"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            const status = this.closest('form').querySelector('[name="status"]').value;
            let message = '';
            
            if (status === 'active') {
                message = 'Grant access to this user?';
            } else if (status === 'suspended') {
                message = 'Suspend access for this user?';
            } else if (status === 'archived') {
                message = 'Archive this user? They will no longer appear in the main user list.';
            }
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    document.querySelectorAll('form [name="update_role"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            const role = this.closest('form').querySelector('[name="role"]').value;
            
            if (!confirm('Change this user\'s role to ' + role + '?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// Helper functions for roles and status badges
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'inventory_manager':
            return 'success';
        case 'field_officer':
            return 'primary';
        case 'viewer':
            return 'info';
        default:
            return 'secondary';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'success';
        case 'pending':
            return 'warning';
        case 'suspended':
            return 'danger';
        case 'archived':
            return 'secondary';
        default:
            return 'light';
    }
}

require_once '../includes/footer.php';
?> 