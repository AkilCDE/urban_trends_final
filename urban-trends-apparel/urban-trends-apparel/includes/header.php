<?php
// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Urban Trends Apparel - <?php echo $page_title ?? 'Home'; ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">Urban Trends</a>
                </div>
                <nav>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About us</a></li>
                        <li><a href="contact.php">Contact us</a></li>
                        <li><a href="shop.php">Shop</a></li>
                    </ul>
                </nav>
                <div class="user-actions">
                    <?php if ($auth->isLoggedIn()): ?>
                        <a href="profile.php">Profile</a>
                        <?php if ($auth->isAdmin()): ?>
                            <a href="admin/dashboard.php">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php">Logout</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="register.php">Register</a>
                    <?php endif; ?>
                    <a href="cart.php" class="cart-link">Cart (<?php echo count($_SESSION['cart'] ?? []); ?>)</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
<?php
// End output buffering and store header content
$header = ob_get_clean();
?>