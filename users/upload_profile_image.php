<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . SITE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    // Validate the uploaded file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed with error code: " . $file['error'];
    } elseif ($file['size'] > 2000000) { // 2MB max size
        $error = "File is too large. Maximum size is 2MB.";
    } else {
        // Check file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } else {
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/profile_images/' . $new_filename;
            
            // Move the file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    $db = getDBConnection();
                    
                    // Get previous profile image
                    $stmt = $db->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $old_image = $stmt->fetchColumn();
                    
                    // Update profile image in database
                    $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$new_filename, $user_id]);
                    
                    // Delete old profile image if it exists
                    if ($old_image && file_exists('../uploads/profile_images/' . $old_image)) {
                        unlink('../uploads/profile_images/' . $old_image);
                    }
                    
                    // Log the transaction
                    logTransaction($db, 'users', $user_id, 'Updated profile image');
                    
                    $success = "Profile image updated successfully.";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = "Failed to move uploaded file.";
            }
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <h2>Upload Profile Image</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="profile_image">Select Profile Image (Max 2MB)</label>
            <input type="file" class="form-control-file" id="profile_image" name="profile_image" required>
            <small class="form-text text-muted">Only JPG, PNG, and GIF formats are allowed.</small>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Upload Image</button>
        <a href="<?php echo SITE_URL; ?>profile.php" class="btn btn-secondary mt-3">Back to Profile</a>
    </form>
</div>

<?php include_once '../includes/footer.php'; ?> 