<?php
require_once 'includes/config.php';

try {
    $db = getDBConnection();
    
    // Update the primary color to the original navy blue
    $stmt = $db->prepare("UPDATE settings SET setting_value = :color WHERE setting_name = 'appearance_primary_color'");
    $navyBlue = '#1B2A47';
    $stmt->bindParam(':color', $navyBlue);
    
    if($stmt->execute()) {
        echo "Primary color has been reset to Navy Blue (#1B2A47).<br>";
        echo "Please <a href='index.php'>return to the dashboard</a>.";
    } else {
        echo "Failed to reset color.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 