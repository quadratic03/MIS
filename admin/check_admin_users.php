<?php
require_once '../includes/config.php';

try {
    $db = getDBConnection();
    
    // Check if users table exists
    $tableStmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($tableStmt->rowCount() == 0) {
        echo "<h2>Error: 'users' table does not exist!</h2>";
        echo "<p>The database appears to be missing the users table. Make sure you've run the database setup script.</p>";
        exit;
    }
    
    // Get the column structure of the users table
    echo "<h2>Users Table Structure</h2>";
    $columnsStmt = $db->query("SHOW COLUMNS FROM users");
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($column = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check admin users
    echo "<h2>Admin Users in Database</h2>";
    
    $adminStmt = $db->query("SELECT * FROM users WHERE role = 'admin'");
    $adminUsers = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($adminUsers) > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr>";
        foreach (array_keys($adminUsers[0]) as $column) {
            echo "<th>" . htmlspecialchars($column) . "</th>";
        }
        echo "</tr>";
        
        foreach ($adminUsers as $user) {
            echo "<tr>";
            foreach ($user as $key => $value) {
                if ($key === 'password') {
                    // Truncate password hash for display
                    $displayValue = substr($value, 0, 25) . '...';
                } else {
                    $displayValue = $value;
                }
                echo "<td>" . htmlspecialchars($displayValue) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Create New Admin User</h3>";
        echo "<form method='post' action='create_admin.php'>";
        echo "<button type='submit' name='create_admin'>Create a New Admin User</button>";
        echo "</form>";
    } else {
        echo "<p>No admin users found in the database.</p>";
        echo "<h3>Create New Admin User</h3>";
        echo "<form method='post' action='create_admin.php'>";
        echo "<button type='submit' name='create_admin'>Create a New Admin User</button>";
        echo "</form>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Database Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 