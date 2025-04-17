<?php
require_once '../includes/config.php';

try {
    $db = getDBConnection();
    
    // Check if status column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'status'");
    
    if ($checkColumn->rowCount() === 0) {
        // Add status column to users table
        $db->exec("ALTER TABLE users ADD COLUMN status ENUM('pending', 'active', 'suspended', 'archived') NOT NULL DEFAULT 'pending' AFTER role");
        
        // Update existing admin user(s) to active status
        $db->exec("UPDATE users SET status = 'active' WHERE role = 'admin'");
        
        echo "Status field added successfully to the users table.<br>";
        echo "Updated admin users to active status.<br>";
    } else {
        echo "Status field already exists in the users table.<br>";
    }
    
    echo "Database update completed. <a href='" . SITE_URL . "'>Return to dashboard</a>.";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 