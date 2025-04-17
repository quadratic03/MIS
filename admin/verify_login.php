<?php
require_once '../includes/config.php';

// Display form if not submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login Verification Tool</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px; }
            .form-group { margin-bottom: 15px; }
            label { display: block; margin-bottom: 5px; }
            input[type="text"], input[type="password"] { width: 100%; padding: 8px; }
            button { padding: 8px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
            .result { margin-top: 20px; padding: 10px; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <h2>Login Verification Tool</h2>
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Verify Credentials</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Process form submission
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

try {
    $db = getDBConnection();
    
    // Get user details
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    echo "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 30px auto; padding: 20px;'>";
    echo "<h2>Login Verification Results</h2>";
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>User found:</strong> Yes</p>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($user['role']) . "</p>";
        echo "<p><strong>Status:</strong> " . (isset($user['status']) ? htmlspecialchars($user['status']) : "Not set") . "</p>";
        
        // Verify password
        $passwordVerified = password_verify($password, $user['password']);
        echo "<p><strong>Password verification:</strong> " . ($passwordVerified ? "Success" : "Failed") . "</p>";
        
        if (!$passwordVerified) {
            echo "<p><strong>Stored password hash:</strong> " . htmlspecialchars($user['password']) . "</p>";
            // Generate what the hash would be for the input password
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            echo "<p><strong>Generated hash for input password:</strong> " . htmlspecialchars($newHash) . "</p>";
            echo "<p><em>Note: The hashes will be different even for the same password due to the salt, but password_verify() handles this.</em></p>";
        }
    } else {
        echo "<p><strong>User found:</strong> No</p>";
        echo "<p>No user with username '" . htmlspecialchars($username) . "' was found in the database.</p>";
    }
    
    echo "<p><a href='verify_login.php'>Try again</a> | <a href='" . SITE_URL . "auth/login.php'>Go to login page</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red;'>";
    echo "Database error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?> 