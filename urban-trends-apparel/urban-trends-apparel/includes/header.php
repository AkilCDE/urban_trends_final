<?php
// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
    
    <!-- Page Specific CSS -->
    <?php if (isset($page_css)): ?>
        <?php foreach ($page_css as $css): ?>
            <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css; ?>.css">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="header">
        <nav class="nav container">
            <a href="<?php echo SITE_URL; ?>" class="logo">
                <?php echo SITE_NAME; ?>
            </a>
            
            <div class="nav-links">
                <a href="<?php echo SITE_URL; ?>/shop.php">Shop</a>
                <a href="<?php echo SITE_URL; ?>/about.php">About</a>
                <a href="<?php echo SITE_URL; ?>/contact.php">Contact</a>
                
                <?php if (is_logged_in()): ?>
                    <a href="<?php echo SITE_URL; ?>/profile.php">My Account</a>
                    <a href="<?php echo SITE_URL; ?>/logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php">Login</a>
                    <a href="<?php echo SITE_URL; ?>/register.php">Register</a>
                <?php endif; ?>
                
                <a href="<?php echo SITE_URL; ?>/cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </a>
            </div>
        </nav>
    </header>
    
    <main class="main-content">
        <div class="container">
<?php
// End output buffering and store header content
$header = ob_get_clean();
?>