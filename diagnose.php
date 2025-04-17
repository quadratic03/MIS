<?php
// Display all errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Output styled header
echo "<!DOCTYPE html>
<html>
<head>
    <title>System Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; line-height: 1.6; }
        .section { background: #f5f5f5; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        h2 { margin-top: 0; color: #333; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>System Diagnostic Report</h1>";

// PHP Environment Info
echo "<div class='section'>
    <h2>PHP Environment</h2>
    <p><strong>PHP Version:</strong> " . phpversion() . "</p>
    <p><strong>Server:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>
    <p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '<span class="success">Active</span>' : '<span class="error">Inactive</span>') . "</p>
    <p><strong>Session ID:</strong> " . session_id() . "</p>";

// Check if sessions are writable
$sessionFile = session_save_path() . '/test_' . uniqid();
$canWriteSession = @file_put_contents($sessionFile, 'test');
if ($canWriteSession !== false) {
    @unlink($sessionFile);
    echo "<p><strong>Session Directory Writable:</strong> <span class='success'>Yes</span></p>";
} else {
    echo "<p><strong>Session Directory Writable:</strong> <span class='error'>No</span></p>";
}

echo "<p><strong>Session Save Path:</strong> " . session_save_path() . "</p>";

// Check session data
echo "<p><strong>Current Session Data:</strong></p>";
echo "<table>";
echo "<tr><th>Key</th><th>Value</th></tr>";
if (count($_SESSION) > 0) {
    foreach ($_SESSION as $key => $value) {
        echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars(print_r($value, true)) . "</td></tr>";
    }
} else {
    echo "<tr><td colspan='2'><em>No session data found</em></td></tr>";
}
echo "</table>";
echo "</div>";

// Database Configuration
echo "<div class='section'>
    <h2>Database Configuration</h2>";

// Hardcoded DB info
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'military_inventory';

echo "<p><strong>Host:</strong> {$dbHost}</p>";
echo "<p><strong>User:</strong> {$dbUser}</p>";
echo "<p><strong>Database:</strong> {$dbName}</p>";

// Test connection
try {
    $db = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p><strong>Connection Status:</strong> <span class='success'>Connected successfully</span></p>";
    
    // Check users table
    $tablesStmt = $db->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $tablesStmt->rowCount() > 0;
    echo "<p><strong>Users Table:</strong> " . ($usersTableExists ? '<span class="success">Exists</span>' : '<span class="error">Missing</span>') . "</p>";
    
    if ($usersTableExists) {
        // Check admin users
        $adminStmt = $db->query("SELECT user_id, username, role, status, last_login FROM users WHERE role = 'admin'");
        $adminUsers = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Admin Users Found:</strong> " . count($adminUsers) . "</p>";
        
        if (count($adminUsers) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th></tr>";
            
            foreach ($adminUsers as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['user_id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>" . (isset($user['status']) ? htmlspecialchars($user['status']) : '<span class="warning">Not set</span>') . "</td>";
                echo "<td>" . (empty($user['last_login']) ? 'Never' : htmlspecialchars($user['last_login'])) . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        // Check users table structure
        $columnsStmt = $db->query("SHOW COLUMNS FROM users");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Users Table Structure:</strong></p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . (isset($column['Default']) ? htmlspecialchars($column['Default']) : 'NULL') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Check status column
        $hasStatusColumn = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'status') {
                $hasStatusColumn = true;
                break;
            }
        }
        
        echo "<p><strong>Status Column:</strong> " . ($hasStatusColumn ? '<span class="success">Exists</span>' : '<span class="error">Missing</span>') . "</p>";
    }
} catch (PDOException $e) {
    echo "<p><strong>Connection Status:</strong> <span class='error'>Failed to connect</span></p>";
    echo "<p><strong>Error Message:</strong> <span class='error'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
}

echo "</div>";

// Available Tools
echo "<div class='section'>
    <h2>Available Tools</h2>
    <ul>
        <li><a href='reset_admin.php'>Reset Admin User</a> - Creates a fresh admin user with username 'admin' and password 'password'</li>
        <li><a href='direct_login.php'>Direct Login</a> - Bypasses the login system and directly sets session variables</li>
        <li><a href='auth/login.php'>Standard Login</a> - Regular login page</li>
    </ul>
</div>";

echo "</body></html>";
?> 