<?php
require_once './includes/config.php';

// Ensure user is logged in and is an admin
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    setFlashMessage('danger', 'Access denied. Admin privileges required.');
    redirect('index.php');
    exit;
}

$message = '';
$messageType = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    // Create directory if it doesn't exist
    $targetDir = 'assets/img/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetFile = $targetDir . 'military-logo.png';
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($_FILES['logo']['tmp_name']);
    if ($check === false) {
        $message = 'File is not an image.';
        $messageType = 'danger';
        $uploadOk = 0;
    }
    
    // Check file size (max 2MB)
    if ($_FILES['logo']['size'] > 2000000) {
        $message = 'Sorry, your file is too large (max 2MB).';
        $messageType = 'danger';
        $uploadOk = 0;
    }
    
    // Allow only certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        $message = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
        $messageType = 'danger';
        $uploadOk = 0;
    }
    
    // If all checks pass, try to upload the file
    if ($uploadOk) {
        // Backup the old logo if it exists
        if (file_exists($targetFile)) {
            copy($targetFile, $targetDir . 'military-logo-backup-' . date('Ymd') . '.png');
        }
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
            $message = 'The logo has been uploaded successfully.';
            $messageType = 'success';
            
            // Log the action
            logAction($_SESSION['user_id'], 'system', 'Updated system logo');
        } else {
            $message = 'Sorry, there was an error uploading your file.';
            $messageType = 'danger';
        }
    }
}

// Include header
require_once './includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upload System Logo</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Current Logo</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="d-flex flex-column align-items-center">
                                        <div style="background-color: #1B2A47; padding: 20px; border-radius: 5px; margin-bottom: 15px;">
                                            <div class="logo-circle" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; background-color: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); padding: 5px; display: flex; align-items: center; justify-content: center;">
                                                <img src="<?php echo ASSETS_URL; ?>img/military-logo.png" alt="Current Logo" class="img-fluid rounded-circle" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                        </div>
                                        <p class="text-muted">Current logo as displayed in the sidebar</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Upload New Logo</h5>
                                </div>
                                <div class="card-body">
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="logo" class="form-label">Select Logo Image</label>
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" required>
                                            <div class="form-text">
                                                <strong>Important:</strong> For best results, use a square image (1:1 ratio).<br>
                                                Recommended size: 200x200 pixels or larger. Maximum file size: 2MB.<br>
                                                The logo will be displayed as a circle in the sidebar.<br>
                                                Supported formats: JPG, PNG, GIF.
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> The new logo will replace the existing one. A backup of the current logo will be created automatically.
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload"></i> Upload Logo
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <div class="alert alert-warning">
                            <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong> After uploading a new logo, you may need to clear your browser cache or refresh the page to see the changes.
                        </div>
                        
                        <a href="settings/" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Go to Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once './includes/footer.php'; ?> 