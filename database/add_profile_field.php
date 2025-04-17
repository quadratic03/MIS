<?php
require_once '../includes/config.php';

try {
    $db = getDBConnection();
    
    // Check if profile_image column already exists
    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    
    if ($checkColumn->rowCount() === 0) {
        // Add profile_image column to users table
        $db->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) AFTER role");
        echo "Profile image field added successfully to the users table.<br>";
    } else {
        echo "Profile image field already exists in the users table.<br>";
    }
    
    echo "Database update completed. <a href='" . SITE_URL . "'>Return to dashboard</a>.";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 