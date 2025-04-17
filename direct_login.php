<?php
// Display all errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start the session
session_start();

// Database connection parameters - hardcoded for simplicity
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'military_inventory';

echo "<h2>Direct Login Test</h2>";

try {
    // Test direct database connection
    $db = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>✓ Database connection successful!</p>";
    
    // Check if admin user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin' AND role = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red'>✗ Admin user not found. Please run reset_admin.php first.</p>";
        exit;
    }
    
    echo "<p style='color:green'>✓ Admin user found: ID {$user['user_id']}</p>";
    
    // Set session variables manually
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    
    // Update last login time
    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $updateStmt->execute([$user['user_id']]);
    
    echo "<div style='margin: 20px 0; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>";
    echo "<h3>Admin Login Successful!</h3>";
    echo "<p>You have been logged in as admin.</p>";
    echo "<p>Session variables:</p>";
    echo "<ul>";
    echo "<li>user_id = {$_SESSION['user_id']}</li>";
    echo "<li>username = {$_SESSION['username']}</li>";
    echo "<li>role = {$_SESSION['role']}</li>";
    echo "</ul>";
    echo "<p><a href='index.php' style='display: inline-block; margin-top: 10px; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Go to Dashboard</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Database error: " . $e->getMessage() . "</p>";
}
?> 