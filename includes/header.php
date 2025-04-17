<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
    redirect('auth/login.php');
}

// Get current page for active menu item
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_directory = basename(dirname($_SERVER['PHP_SELF']));

// Get current user if logged in
$user = null;
if (isLoggedIn()) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get current time
$current_time = date('H:i');
$current_date = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting('general_site_title', SITE_NAME); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/style.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.0/css/responsive.bootstrap5.min.css">
    
    <!-- Custom CSS overrides from settings -->
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo SECONDARY_COLOR; ?>;
            --accent-color: <?php echo ACCENT_COLOR; ?>;
            --critical-color: <?php echo CRITICAL_COLOR; ?>;
            --warning-color: <?php echo WARNING_COLOR; ?>;
            --operational-color: <?php echo OPERATIONAL_COLOR; ?>;
            --sidebar-collapsed: <?php echo getSetting('appearance_sidebar_collapsed', 'false') === 'true' ? 'true' : 'false'; ?>;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            overflow-x: hidden;
        }
        
        /* Sidebar Styles */
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            background-color: #1B2A47 !important;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            z-index: 999;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        #sidebar.collapsed {
            margin-left: -250px;
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: var(--primary-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Logo styling */
        .logo-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 5px;
            margin: 0 auto;
        }
        
        .logo-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        #sidebar .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
        }
        
        #sidebar ul.components {
            padding: 20px 0;
        }
        
        #sidebar ul li a {
            padding: 12px 20px;
            font-size: 1rem;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        #sidebar ul li a:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--secondary-color);
        }
        
        #sidebar ul li a.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid var(--secondary-color);
        }
        
        #sidebar ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Content Styles */
        #content {
            width: calc(100% - 250px);
            min-height: 100vh;
            transition: all 0.3s;
            position: absolute;
            top: 0;
            right: 0;
        }
        
        #content.expanded {
            width: 100%;
        }
        
        /* Top Navbar */
        .top-navbar {
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .navbar-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .search-bar {
            position: relative;
            width: 300px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 8px 15px 8px 35px;
            border-radius: 50px;
            border: 1px solid #e0e0e0;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-bar input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(74, 93, 35, 0.25);
        }
        
        .search-bar i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
        }
        
        .navbar-right .nav-item {
            margin-left: 20px;
            position: relative;
        }
        
        .notification-bell {
            color: var(--primary-color);
            font-size: 1.1rem;
            cursor: pointer;
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--critical-color);
            color: #fff;
            font-size: 0.7rem;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-profile img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
        }
        
        .user-role {
            font-size: 0.7rem;
            color: #777;
        }
        
        .time-display {
            font-size: 0.85rem;
            color: #555;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .current-time {
            font-weight: 600;
            font-family: 'Courier New', monospace;
            background-color: #1B2A47;
            color: #39ff14;
            padding: 5px 10px;
            border-radius: 5px;
            box-shadow: 0 0 10px #39ff14, 0 0 20px rgba(57, 255, 20, 0.5);
            text-shadow: 0 0 5px #39ff14;
            letter-spacing: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .current-time:hover {
            box-shadow: 0 0 15px #39ff14, 0 0 30px rgba(57, 255, 20, 0.7);
        }
        
        .current-date {
            font-size: 0.75rem;
            margin-top: 5px;
        }
        
        /* Breadcrumbs */
        .breadcrumb-container {
            padding: 15px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eaeaea;
        }
        
        .breadcrumb {
            margin-bottom: 0;
            background: transparent;
            padding: 0;
        }
        
        .page-container {
            padding: 20px;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            #sidebar {
                margin-left: -250px;
            }
            
            #sidebar.active {
                margin-left: 0;
            }
            
            #content {
                width: 100%;
            }
            
            .search-bar {
                width: 200px;
            }
            
            .user-info {
                display: none;
            }
            
            .time-display {
                display: none;
            }
        }
        
        /* Flash Messages */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }
        
        .alert {
            margin-bottom: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.5s ease-in-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        body.sidebar-collapsed #sidebar {
            width: 60px;
        }
        
        body.sidebar-collapsed #content {
            margin-left: 60px;
        }
        
        body.sidebar-collapsed .logo-text {
            display: none;
        }
        
        body.sidebar-collapsed .sidebar-header {
            padding: 10px 0;
        }
        
        body.sidebar-collapsed .logo-circle {
            width: 60px;
            height: 60px;
            margin: 0 auto;
        }
        
        .btn-primary, .bg-primary {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        /* Print styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
                background-color: white;
            }
            
            /* Hide non-printable elements */
            #sidebar, 
            .top-navbar, 
            .breadcrumb-container, 
            .non-printable, 
            .dataTables_filter, 
            .dataTables_length, 
            .dataTables_paginate, 
            .dataTables_info,
            .buttons-html5,
            .alert-container,
            .btn-close,
            button[onclick="window.print()"] {
                display: none !important;
            }
            
            /* Adjust content area */
            #content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .container-fluid, 
            .page-container {
                width: 100% !important;
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* Adjust card styling */
            .card {
                border: none !important;
                box-shadow: none !important;
            }
            
            .card-header {
                background-color: white !important;
                padding-bottom: 1rem !important;
                border-bottom: 1px solid #ddd !important;
                margin-bottom: 1rem !important;
            }
            
            /* Table styling for print */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            
            table th, table td {
                padding: 8px !important;
                font-size: 11pt !important;
                border: 1px solid #ddd !important;
            }
            
            /* Ensure proper heading spacing */
            .h3 {
                font-size: 16pt !important;
                margin-top: 0 !important;
                margin-bottom: 1rem !important;
                text-align: center !important;
            }
            
            /* Adjust report summary appearance */
            .card-title {
                font-size: 14pt !important;
                margin-bottom: 0.5rem !important;
            }
            
            .badge {
                color: black !important;
                background-color: transparent !important;
                border: 1px solid #ddd !important;
                padding: 2px 5px !important;
                font-weight: normal !important;
            }
            
            /* Ensure everything fits on printed page */
            .mb-4 {
                margin-bottom: 0.5rem !important;
            }
            
            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
    
    <!-- jQuery (required for DataTables and Bootstrap JS components) -->
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>
</head>
<body class="<?php echo getSetting('appearance_sidebar_collapsed', 'false') === 'true' ? 'sidebar-collapsed' : ''; ?>">
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <div class="d-flex justify-content-center">
                    <div class="logo-circle">
                        <img src="<?php echo ASSETS_URL; ?>img/logo.jpg" alt="Logo" class="rounded-circle img-fluid">
                    </div>
                </div>
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="<?php echo SITE_URL; ?>index.php" class="<?php echo ($current_page == 'index') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li>
                    <a href="<?php echo SITE_URL; ?>admin/users.php" class="<?php echo ($current_page == 'users' && $current_directory == 'admin') ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> User Management
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo SITE_URL; ?>vehicles/" class="<?php echo ($current_directory == 'vehicles') ? 'active' : ''; ?>">
                        <i class="fas fa-truck-moving"></i> Vehicle Management
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>ammunition/" class="<?php echo ($current_directory == 'ammunition') ? 'active' : ''; ?>">
                        <i class="fas fa-bomb"></i> Ammunition Inventory
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>personnel/" class="<?php echo ($current_directory == 'personnel') ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Personnel Records
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>maintenance/" class="<?php echo ($current_directory == 'maintenance') ? 'active' : ''; ?>">
                        <i class="fas fa-tools"></i> Maintenance
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>reports/" class="<?php echo ($current_directory == 'reports') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>settings/" class="<?php echo ($current_directory == 'settings') ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="d-flex align-items-center">
                    <button class="navbar-toggle me-3" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>
                
                <div class="navbar-right">
                    <div class="nav-item time-display">
                        <span class="current-time" id="digitalClock"><?php echo $current_time; ?></span>
                        <span class="current-date"><?php echo $current_date; ?></span>
                    </div>
                    
                    <div class="nav-item">
                        <div class="user-profile">
                            <a href="<?php echo SITE_URL; ?>user/profile.php" style="text-decoration: none; color: inherit;">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo SITE_URL . $user['profile_image']; ?>" alt="User Avatar">
                                <?php else: ?>
                                    <img src="<?php echo ASSETS_URL; ?>img/logo.jpg" alt="User Avatar">
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Breadcrumbs -->
            <div class="breadcrumb-container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                        <?php if ($current_directory != '' && $current_directory != '.'): ?>
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL . $current_directory; ?>"><?php echo ucfirst($current_directory); ?></a></li>
                        <?php endif; ?>
                        <?php if ($current_page != 'index'): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo ucfirst(str_replace('_', ' ', $current_page)); ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
            
            <!-- Flash Messages Container -->
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
            
            <!-- Page Content Container -->
            <div class="page-container"> 