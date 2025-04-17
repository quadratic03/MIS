<?php
require_once '../includes/config.php';

// Display the form if not submitted
if (!isset($_POST['confirm_create'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Create Admin User</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; }
            input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 8px; }
            button { padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
            .warning { background-color: #FFDDDD; padding: 10px; border: 1px solid #FF0000; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h2>Create New Admin User</h2>
        
        <div class="warning">
            <strong>Warning:</strong> This will create a new admin user with full system access.
        </div>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="admin" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" value="password" required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" value="System Administrator" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="admin@example.com" required>
            </div>
            
            <button type="submit" name="confirm_create">Create Admin User</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Process form submission
try {
    $db = getDBConnection();
    
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $fullName = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    
    // Check if username or email already exists
    $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
    $checkStmt->bindParam(':username', $username);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // This username/email exists - we'll update the existing record
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("UPDATE users SET password = :password, full_name = :full_name, email = :email, role = 'admin', status = 'active', updated_at = NOW() WHERE user_id = :user_id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':user_id', $user['user_id']);
        
        if ($stmt->execute()) {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
            echo "<h2>Admin User Updated</h2>";
            echo "<p>The existing admin user has been updated with the new credentials.</p>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
            echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
            echo "<p><a href='" . SITE_URL . "auth/login.php'>Go to login page</a></p>";
            echo "</div>";
        } else {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
            echo "<h2>Error</h2>";
            echo "<p>Failed to update admin user.</p>";
            echo "<p><a href='create_admin.php'>Try again</a></p>";
            echo "</div>";
        }
    } else {
        // Create new user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (:username, :password, :full_name, :email, 'admin', 'active')");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute()) {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
            echo "<h2>Admin User Created</h2>";
            echo "<p>A new admin user has been created successfully.</p>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
            echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
            echo "<p><a href='" . SITE_URL . "auth/login.php'>Go to login page</a></p>";
            echo "</div>";
        } else {
            echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
            echo "<h2>Error</h2>";
            echo "<p>Failed to create admin user.</p>";
            echo "<p><a href='create_admin.php'>Try again</a></p>";
            echo "</div>";
        }
    }
} catch (PDOException $e) {
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
    echo "<h2>Database Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='create_admin.php'>Try again</a></p>";
    echo "</div>";
}
?> 