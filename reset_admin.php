<?php
// Display all errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection parameters - hardcoded for simplicity
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'military_inventory';

echo "<h2>Database Connection Test</h2>";

try {
    // Test direct database connection
    $db = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>✓ Database connection successful!</p>";
    
    // Check if users table exists
    $tableStmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($tableStmt->rowCount() == 0) {
        echo "<p style='color:red'>✗ The 'users' table does not exist. Please run the database setup script first.</p>";
        exit;
    }
    echo "<p style='color:green'>✓ Users table exists</p>";
    
    // Delete all existing admin users - fresh start
    $deleteStmt = $db->prepare("DELETE FROM users WHERE username = 'admin'");
    $deleteStmt->execute();
    echo "<p>Deleted any existing admin users</p>";
    
    // Create a new admin user with a simple password hash
    $username = 'admin';
    $password = 'password';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $fullName = 'System Administrator';
    $email = 'admin@example.com';
    
    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
    $result = $stmt->execute([$username, $hashedPassword, $fullName, $email]);
    
    if ($result) {
        echo "<div style='margin: 20px 0; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>";
        echo "<h3>Admin User Created Successfully</h3>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> password</p>";
        echo "<p>This admin account has been set to ACTIVE status.</p>";
        echo "<p><a href='auth/login.php' style='display: inline-block; margin-top: 10px; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Go to Login Page</a></p>";
        echo "</div>";
    } else {
        echo "<p style='color:red'>✗ Failed to create admin user</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    
    // Check if database exists
    try {
        $tempDb = new PDO("mysql:host={$dbHost}", $dbUser, $dbPass);
        $tempDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $checkDb = $tempDb->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbName}'");
        
        if ($checkDb->rowCount() == 0) {
            echo "<p style='color:red'>✗ Database '{$dbName}' does not exist!</p>";
            echo "<p>Please create the database first or import the military_inventory.sql file.</p>";
        }
    } catch (PDOException $e2) {
        echo "<p style='color:red'>✗ Could not connect to MySQL server: " . $e2->getMessage() . "</p>";
    }
}
?> 