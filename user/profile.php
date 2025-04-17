<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Ensure user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
    exit; // Add explicit exit
}

// Get current user data
$userId = $_SESSION['user_id'];
$userData = null;

try {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        setFlashMessage('danger', 'User not found.');
        redirect('index.php');
        exit; // Add explicit exit
    }
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
    redirect('index.php');
    exit; // Add explicit exit
}

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle profile information update
        if (isset($_POST['update_profile'])) {
            $fullName = sanitizeInput($_POST['full_name']);
            $email = sanitizeInput($_POST['email']);
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validate current password if trying to change password
            $passwordChanged = false;
            if (!empty($newPassword) || !empty($confirmPassword)) {
                if (empty($currentPassword)) {
                    $message = 'Current password is required to change password.';
                    $messageType = 'danger';
                } elseif (!password_verify($currentPassword, $userData['password'])) {
                    $message = 'Current password is incorrect.';
                    $messageType = 'danger';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = 'New password and confirmation do not match.';
                    $messageType = 'danger';
                } elseif (strlen($newPassword) < 8) {
                    $message = 'New password must be at least 8 characters long.';
                    $messageType = 'danger';
                } else {
                    $passwordChanged = true;
                }
            }
            
            if (empty($message)) {
                // Update user information
                if ($passwordChanged) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, password = :password, updated_at = NOW() WHERE user_id = :user_id");
                    $stmt->bindParam(':password', $hashedPassword);
                } else {
                    $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, updated_at = NOW() WHERE user_id = :user_id");
                }
                
                $stmt->bindParam(':full_name', $fullName);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':user_id', $userId);
                
                if ($stmt->execute()) {
                    $message = 'Profile updated successfully.';
                    $messageType = 'success';
                    
                    // Update session data
                    $_SESSION['username'] = $userData['username'];
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();
                    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = 'Failed to update profile.';
                    $messageType = 'danger';
                }
            }
        }
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/img/profile/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Get file extension
            $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            
            // Allowed file types
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($fileExtension, $allowedTypes)) {
                $message = 'Invalid file type. Allowed types: JPG, JPEG, PNG, GIF.';
                $messageType = 'danger';
            } else {
                // Generate unique filename
                $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                    // Update profile image in database
                    $profileImagePath = 'assets/img/profile/' . $newFileName;
                    $stmt = $db->prepare("UPDATE users SET profile_image = :profile_image, updated_at = NOW() WHERE user_id = :user_id");
                    $stmt->bindParam(':profile_image', $profileImagePath);
                    $stmt->bindParam(':user_id', $userId);
                    
                    if ($stmt->execute()) {
                        $message = 'Profile image updated successfully.';
                        $messageType = 'success';
                        
                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
                        $stmt->bindParam(':user_id', $userId);
                        $stmt->execute();
                        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $message = 'Failed to update profile image in database.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Failed to upload profile image.';
                    $messageType = 'danger';
                }
            }
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Include header - moved after all potential redirects
require_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">My Profile</h1>
                <div>
                    <?php if (isset($userData['role']) && $userData['role'] === 'admin'): ?>
                    <a href="<?php echo SITE_URL; ?>admin/users.php" class="btn btn-primary me-2">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Image Card -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Profile Image</h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <?php if (!empty($userData['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . $userData['profile_image']; ?>" class="img-profile rounded-circle img-thumbnail" style="width: 200px; height: 200px; object-fit: cover;">
                                <?php else: ?>
                                    <img src="<?php echo ASSETS_URL; ?>img/user-avatar.png" class="img-profile rounded-circle img-thumbnail" style="width: 200px; height: 200px; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            
                            <form method="post" action="" enctype="multipart/form-data" class="mt-3">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Change Profile Image</label>
                                    <input class="form-control" type="file" id="profile_image" name="profile_image" accept="image/*">
                                    <div class="form-text">Recommended size: 200x200 pixels. Max file size: 2MB.</div>
                                </div>
                                
                                <button type="submit" name="upload_image" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Image
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- User Details Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-user me-2"></i> Username</span>
                                    <span class="text-muted"><?php echo $userData['username']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-id-badge me-2"></i> Role</span>
                                    <span class="badge bg-primary"><?php echo ucfirst($userData['role']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-calendar-alt me-2"></i> Last Login</span>
                                    <span class="text-muted"><?php echo $userData['last_login'] ? date('M d, Y h:i A', strtotime($userData['last_login'])) : 'Never'; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-clock me-2"></i> Account Created</span>
                                    <span class="text-muted"><?php echo date('M d, Y', strtotime($userData['created_at'])); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Settings Card -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $userData['full_name']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $userData['email']; ?>" required>
                                </div>
                                
                                <hr class="my-4">
                                <h5 class="mb-3">Change Password</h5>
                                <p class="text-muted mb-3">Leave fields blank if you don't want to change your password.</p>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <div class="form-text">Required only if changing password.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                                
                                <div class="mt-4">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?> 