<?php
require_once '../includes/config.php';

try {
    $db = getDBConnection();
    
    // Generate a fresh hash for "password"
    $plainPassword = "password";
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    // Update admin user password
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'admin' AND role = 'admin'");
    $stmt->bindParam(':password', $hashedPassword);
    
    if ($stmt->execute()) {
        echo "Admin password has been reset successfully to '$plainPassword'.<br>";
        echo "Generated hash: $hashedPassword<br>";
        echo "<a href='" . SITE_URL . "auth/login.php'>Go to login page</a>";
    } else {
        echo "Failed to reset admin password.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 