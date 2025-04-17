<?php
require_once '../includes/config.php';

// Already logged in? Redirect to dashboard
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Validate credentials
    if (empty($username) || empty($password)) {
        setFlashMessage('danger', 'Username and password are required.');
    } else {
        try {
            $db = getDBConnection();
            
            // Get user by username
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password (using PHP's password_verify)
                if (password_verify($password, $user['password'])) {
                    // Check if user account is active
                    if (isset($user['status']) && $user['status'] !== 'active') {
                        if ($user['status'] === 'pending') {
                            setFlashMessage('warning', 'Your account is pending approval by an administrator.');
                        } elseif ($user['status'] === 'suspended') {
                            setFlashMessage('danger', 'Your account has been suspended. Please contact an administrator.');
                        } else {
                            setFlashMessage('danger', 'Your account is not active. Please contact an administrator.');
                        }
                    } else {
                        // Set session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Update last login
                        $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
                        $update->bindParam(':user_id', $user['user_id']);
                        $update->execute();
                        
                        // Redirect to dashboard
                        redirect('index.php');
                        exit;
                    }
                } else {
                    setFlashMessage('danger', 'Invalid password.');
                }
            } else {
                setFlashMessage('danger', 'User not found.');
            }
        } catch (PDOException $e) {
            setFlashMessage('danger', 'Database error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: url('<?php echo ASSETS_URL; ?>img/military-background.jpg');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 900px;
            max-width: 90%;
            z-index: 10;
            position: relative;
        }
        
        .row {
            margin: 0;
        }
        
        .login-image {
            background-color: var(--primary-color);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        
        .login-logo-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 30px;
            border: 5px solid white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
            margin-left: auto;
            margin-right: auto;
        }
        
        .login-logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .login-image h2 {
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .login-image p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .login-form {
            padding: 40px;
        }
        
        .login-form h3 {
            margin-bottom: 30px;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px;
            font-weight: 500;
            width: 100%;
        }
        
        .btn-login:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .alert-container {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="row g-0">
            <div class="col-md-6 login-image">
                <div class="login-logo-circle">
                    <img src="<?php echo ASSETS_URL; ?>img/logo.jpg" alt="Military Logo" class="rounded-circle">
                </div>
                <h2><?php echo SITE_NAME; ?></h2>
                <p>Secure access to military resources and inventory management system. Authorized personnel only.</p>
            </div>
            <div class="col-md-6 login-form">
                <h3>Secure Login</h3>
                
                <!-- Alert Container -->
                <div class="alert-container">
                    <?php 
                    $flash_message = getFlashMessage();
                    if ($flash_message): 
                    ?>
                        <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show">
                            <?php echo $flash_message['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form method="post" action="">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username">Username</label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="btn btn-login">Log In</button>
                </form>
                
                <div class="login-footer">
                    <p>Forgot your password? Contact your system administrator.</p>
                    <p>&copy; <?php echo date('Y'); ?> Military Inventory System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 