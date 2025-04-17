<?php
require_once '../includes/config.php';

// Get database connection
$db = getDBConnection();

// Define settings categories and their default values
$settingsCategories = [
    'general' => 'General Settings',
    'appearance' => 'Appearance Settings',
    'notifications' => 'Notification Settings',
    'maintenance' => 'Maintenance Settings'
];

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_settings'])) {
            foreach ($_POST['settings'] as $settingName => $settingValue) {
                // Skip submit button and any non-setting fields
                if ($settingName === 'update_settings') continue;
                
                // Sanitize input
                $settingName = sanitizeInput($settingName);
                $settingValue = sanitizeInput($settingValue);
                
                // Check if setting exists
                $checkStmt = $db->prepare("SELECT setting_id FROM settings WHERE setting_name = :setting_name");
                $checkStmt->bindParam(':setting_name', $settingName);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing setting
                    $stmt = $db->prepare("UPDATE settings SET setting_value = :setting_value, updated_at = NOW() WHERE setting_name = :setting_name");
                } else {
                    // Insert new setting
                    $stmt = $db->prepare("INSERT INTO settings (setting_name, setting_value, description) VALUES (:setting_name, :setting_value, :description)");
                    $description = "Setting added from settings page";
                    $stmt->bindParam(':description', $description);
                }
                
                $stmt->bindParam(':setting_name', $settingName);
                $stmt->bindParam(':setting_value', $settingValue);
                $stmt->execute();
            }
            
            // Log the action in inventory_transactions
            $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, transaction_date, notes) VALUES ('system', 'settings', 0, NOW(), :notes)");
            $notes = "Settings updated by administrator";
            $transactionStmt->bindParam(':notes', $notes);
            $transactionStmt->execute();
            
            $message = 'Settings updated successfully.';
            $messageType = 'success';
            
            // Redirect to avoid form resubmission
            header("Location: " . SITE_URL . "settings/?message=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;
        }
        
        // Handle restore defaults
        if (isset($_POST['restore_defaults'])) {
            // Delete all existing settings
            $db->exec("DELETE FROM settings");
            
            // Re-initialize default settings
            initializeDefaultSettings();
            
            // Log the action in inventory_transactions
            $transactionStmt = $db->prepare("INSERT INTO inventory_transactions (transaction_type, item_type, item_id, transaction_date, notes) VALUES ('system', 'settings', 0, NOW(), :notes)");
            $notes = "Settings reset to defaults by administrator";
            $transactionStmt->bindParam(':notes', $notes);
            $transactionStmt->execute();
            
            $message = 'All settings have been restored to their default values.';
            $messageType = 'success';
            
            // Redirect to avoid form resubmission
            header("Location: " . SITE_URL . "settings/?message=" . urlencode($message) . "&type=" . urlencode($messageType));
            exit;
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Check for message in URL parameters
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
}

// Get all settings
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM settings ORDER BY setting_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_name']] = $row;
    }
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

// Group settings by category
$groupedSettings = [];
foreach ($settings as $setting) {
    $category = 'general'; // Default category
    
    // Determine category based on setting name prefix
    foreach (array_keys($settingsCategories) as $categoryKey) {
        if (strpos($setting['setting_name'], $categoryKey . '_') === 0) {
            $category = $categoryKey;
            break;
        }
    }
    
    if (!isset($groupedSettings[$category])) {
        $groupedSettings[$category] = [];
    }
    
    $groupedSettings[$category][] = $setting;
}

// Include header file after all potential redirects
require_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Settings Form -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Configure System Settings</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                            <?php $firstTab = true; ?>
                            <?php foreach ($settingsCategories as $categoryKey => $categoryName): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>" 
                                            id="<?php echo $categoryKey; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?php echo $categoryKey; ?>-pane" 
                                            type="button" 
                                            role="tab" 
                                            aria-controls="<?php echo $categoryKey; ?>-pane" 
                                            aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                                        <?php echo $categoryName; ?>
                                    </button>
                                </li>
                                <?php $firstTab = false; ?>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="tab-content" id="settingsTabsContent">
                            <?php $firstTab = true; ?>
                            <?php foreach ($settingsCategories as $categoryKey => $categoryName): ?>
                                <div class="tab-pane fade <?php echo $firstTab ? 'show active' : ''; ?>" 
                                     id="<?php echo $categoryKey; ?>-pane" 
                                     role="tabpanel" 
                                     aria-labelledby="<?php echo $categoryKey; ?>-tab" 
                                     tabindex="0">
                                    
                                    <div class="row">
                                        <?php if (isset($groupedSettings[$categoryKey])): ?>
                                            <?php foreach ($groupedSettings[$categoryKey] as $setting): ?>
                                                <div class="col-md-6 mb-3">
                                                    <label for="<?php echo $setting['setting_name']; ?>" class="form-label">
                                                        <?php 
                                                        // Format the setting name for display
                                                        $displayName = ucwords(str_replace(['_', $categoryKey . '_'], [' ', ''], $setting['setting_name']));
                                                        echo $displayName;
                                                        ?>
                                                    </label>
                                                    
                                                    <?php if (strpos($setting['setting_name'], 'enable_') === 0 || strpos($setting['setting_value'], 'true') !== false || strpos($setting['setting_value'], 'false') !== false): ?>
                                                        <!-- Boolean setting -->
                                                        <select class="form-select" name="settings[<?php echo $setting['setting_name']; ?>]" id="<?php echo $setting['setting_name']; ?>">
                                                            <option value="true" <?php echo $setting['setting_value'] === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                                            <option value="false" <?php echo $setting['setting_value'] === 'false' ? 'selected' : ''; ?>>Disabled</option>
                                                        </select>
                                                    <?php elseif (strpos($setting['setting_name'], 'color') !== false): ?>
                                                        <!-- Color setting -->
                                                        <input type="color" class="form-control form-control-color" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" value="<?php echo $setting['setting_value']; ?>">
                                                    <?php elseif (strpos($setting['setting_name'], 'date') !== false): ?>
                                                        <!-- Date setting -->
                                                        <input type="date" class="form-control" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" value="<?php echo $setting['setting_value']; ?>">
                                                    <?php elseif (strpos($setting['setting_name'], 'email') !== false): ?>
                                                        <!-- Email setting -->
                                                        <input type="email" class="form-control" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" value="<?php echo $setting['setting_value']; ?>">
                                                    <?php elseif (strpos($setting['setting_name'], 'password') !== false || strpos($setting['setting_name'], 'key') !== false || strpos($setting['setting_name'], 'secret') !== false): ?>
                                                        <!-- Password/API key setting -->
                                                        <input type="password" class="form-control" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" value="<?php echo $setting['setting_value']; ?>">
                                                    <?php elseif (strlen($setting['setting_value']) > 100): ?>
                                                        <!-- Long text setting -->
                                                        <textarea class="form-control" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" rows="3"><?php echo $setting['setting_value']; ?></textarea>
                                                    <?php else: ?>
                                                        <!-- Default text setting -->
                                                        <input type="text" class="form-control" id="<?php echo $setting['setting_name']; ?>" name="settings[<?php echo $setting['setting_name']; ?>]" value="<?php echo $setting['setting_value']; ?>">
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($setting['description'])): ?>
                                                        <div class="form-text"><?php echo $setting['description']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    No settings found for this category. Add settings by using the "Add New Setting" section below.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php $firstTab = false; ?>
                            <?php endforeach; ?>
                            
                            <!-- Add New Setting Tab -->
                            <div class="tab-pane fade" id="add-setting-pane" role="tabpanel" aria-labelledby="add-setting-tab" tabindex="0">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_setting_name" class="form-label">Setting Name</label>
                                        <input type="text" class="form-control" id="new_setting_name" name="settings[new_setting_name]" placeholder="e.g., general_site_title">
                                        <div class="form-text">Use lowercase letters, underscores, and category prefix (e.g., general_, appearance_)</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="new_setting_value" class="form-label">Setting Value</label>
                                        <input type="text" class="form-control" id="new_setting_value" name="settings[new_setting_value]">
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="new_setting_description" class="form-label">Description (Optional)</label>
                                        <textarea class="form-control" id="new_setting_description" name="settings[new_setting_description]" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Default Settings Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Default System Settings</h6>
                    <button class="btn btn-sm btn-primary" id="restoreDefaultsBtn">
                        <i class="fas fa-sync-alt"></i> Restore Defaults
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Setting Name</th>
                                    <th>Default Value</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>general_site_title</td>
                                    <td>Military Inventory System</td>
                                    <td>The title of the site displayed in the browser tab and header</td>
                                </tr>
                                <tr>
                                    <td>general_items_per_page</td>
                                    <td>25</td>
                                    <td>Number of items to display per page in tables</td>
                                </tr>
                                <tr>
                                    <td>appearance_primary_color</td>
                                    <td>#4e73df</td>
                                    <td>Primary color for UI elements</td>
                                </tr>
                                <tr>
                                    <td>appearance_sidebar_collapsed</td>
                                    <td>false</td>
                                    <td>Whether the sidebar should be collapsed by default</td>
                                </tr>
                                <tr>
                                    <td>maintenance_auto_schedule</td>
                                    <td>true</td>
                                    <td>Automatically schedule maintenance for vehicles</td>
                                </tr>
                                <tr>
                                    <td>maintenance_interval_days</td>
                                    <td>180</td>
                                    <td>Default maintenance interval in days</td>
                                </tr>
                                <tr>
                                    <td>notifications_enable_email</td>
                                    <td>false</td>
                                    <td>Enable email notifications</td>
                                </tr>
                                <tr>
                                    <td>notifications_admin_email</td>
                                    <td>admin@military-inventory.com</td>
                                    <td>Administrator email for notifications</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Restore Defaults Confirmation Modal -->
<div class="modal fade" id="restoreDefaultsModal" tabindex="-1" aria-labelledby="restoreDefaultsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restoreDefaultsModalLabel">Restore Default Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to restore all settings to their default values? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="">
                    <input type="hidden" name="restore_defaults" value="1">
                    <button type="submit" class="btn btn-danger">Restore Defaults</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show restore defaults confirmation modal
    document.getElementById('restoreDefaultsBtn').addEventListener('click', function() {
        var restoreModal = new bootstrap.Modal(document.getElementById('restoreDefaultsModal'));
        restoreModal.show();
    });
    
    // Add tooltip for setting descriptions
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
});
</script>

<?php
require_once '../includes/footer.php';
?> 