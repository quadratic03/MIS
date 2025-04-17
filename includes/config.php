<?php
/**
 * Database Configuration
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'military_inventory');

/**
 * Application Configuration
 */
define('SITE_NAME', 'Military Inventory System');
define('SITE_URL', 'http://localhost/MilinS/');
define('ASSETS_URL', SITE_URL . 'assets/');

/**
 * Color Scheme
 */
define('PRIMARY_COLOR', '#1B2A47');      // Navy Blue
define('SECONDARY_COLOR', '#4A5D23');    // Military Green
define('ACCENT_COLOR', '#707B8C');       // Steel Gray
define('CRITICAL_COLOR', '#DC3545');     // Red
define('WARNING_COLOR', '#FFC107');      // Amber
define('OPERATIONAL_COLOR', '#28A745');  // Green

/**
 * Error Reporting
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Session Configuration
 */
session_start();

/**
 * Database Connection
 */
function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Database Connection Failed: " . $e->getMessage());
    }
}

/**
 * Authentication Function
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Basic session check passes, now check if user status is active
    // Only do this check if we're not in the database update script
    $script = $_SERVER['PHP_SELF'];
    if (strpos($script, 'add_user_status.php') === false) {
        try {
            $db = getDBConnection();
            
            // First check if status column exists
            $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'status'");
            
            // If status column exists, verify user is active
            if ($checkColumn->rowCount() > 0) {
                $stmt = $db->prepare("SELECT status FROM users WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user['status'] !== 'active') {
                        // User is not active, destroy session
                        session_unset();
                        session_destroy();
                        return false;
                    }
                }
            }
        } catch (PDOException $e) {
            // On database error, fall back to basic session check
        }
    }
    
    return true;
}

/**
 * Redirect Function
 */
function redirect($location) {
    header("Location: " . SITE_URL . $location);
    exit();
}

/**
 * Flash Messages
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Security Function
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get a setting from the database with fallback to default value
function getSetting($settingName, $defaultValue = '') {
    static $settingsCache = [];
    
    // Check if setting is already in cache
    if (isset($settingsCache[$settingName])) {
        return $settingsCache[$settingName];
    }
    
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_name = :setting_name");
        $stmt->bindParam(':setting_name', $settingName);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $settingsCache[$settingName] = $row['setting_value'];
            return $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Error retrieving setting, fallback to default
    }
    
    // Setting not found, return default
    $settingsCache[$settingName] = $defaultValue;
    return $defaultValue;
}

// Initialize default settings if they don't exist
function initializeDefaultSettings() {
    try {
        $db = getDBConnection();
        
        // Default settings with their values and descriptions
        $defaultSettings = [
            'general_site_title' => [
                'value' => 'Military Inventory System',
                'description' => 'The title of the site displayed in the browser tab and header'
            ],
            'general_items_per_page' => [
                'value' => '25',
                'description' => 'Number of items to display per page in tables'
            ],
            'appearance_primary_color' => [
                'value' => '#1B2A47',
                'description' => 'Primary color for UI elements'
            ],
            'appearance_sidebar_collapsed' => [
                'value' => 'false',
                'description' => 'Whether the sidebar should be collapsed by default'
            ],
            'maintenance_auto_schedule' => [
                'value' => 'true',
                'description' => 'Automatically schedule maintenance for vehicles'
            ],
            'maintenance_interval_days' => [
                'value' => '180',
                'description' => 'Default maintenance interval in days'
            ],
            'notifications_enable_email' => [
                'value' => 'false',
                'description' => 'Enable email notifications'
            ],
            'notifications_admin_email' => [
                'value' => 'admin@military-inventory.com',
                'description' => 'Administrator email for notifications'
            ]
        ];
        
        // Check if settings table is empty
        $checkStmt = $db->query("SELECT COUNT(*) as count FROM settings");
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($checkResult['count'] == 0) {
            // Insert default settings if no settings exist
            $insertStmt = $db->prepare("INSERT INTO settings (setting_name, setting_value, description) VALUES (:name, :value, :description)");
            
            foreach ($defaultSettings as $name => $data) {
                $insertStmt->bindParam(':name', $name);
                $insertStmt->bindParam(':value', $data['value']);
                $insertStmt->bindParam(':description', $data['description']);
                $insertStmt->execute();
            }
        } else {
            // Just check for missing settings and add them
            $checkSettingStmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE setting_name = :name");
            $insertStmt = $db->prepare("INSERT INTO settings (setting_name, setting_value, description) VALUES (:name, :value, :description)");
            
            foreach ($defaultSettings as $name => $data) {
                $checkSettingStmt->bindParam(':name', $name);
                $checkSettingStmt->execute();
                $result = $checkSettingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] == 0) {
                    $insertStmt->bindParam(':name', $name);
                    $insertStmt->bindParam(':value', $data['value']);
                    $insertStmt->bindParam(':description', $data['description']);
                    $insertStmt->execute();
                }
            }
        }
    } catch (PDOException $e) {
        // Error initializing settings
    }
}

// Initialize settings if we're not in an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    initializeDefaultSettings();
}
?> 