<?php
require_once 'Database/datab.php';

// Start session
session_start();

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
}

$auth = new Auth($db);

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Urban Trends Apparel - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a1a1a;
            --secondary-color: #121212;
            --accent-color: #ff6b6b;
            --light-color: #f8f9fa;
            --dark-color: #0d0d0d;
            --text-color: #e0e0e0;
            --text-muted: #b0b0b0;
            --success-color: #4bb543;
            --error-color: #ff3333;
            --warning-color: #ffcc00;
            --border-radius: 8px;
            --box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s ease;
            --header-height: 70px;
            --footer-height: auto;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-color);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Container for responsive content */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header with mobile menu */
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            height: var(--header-height);
        }

        .header-container {
            width: 100%;
            height: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 15px;
        }

        .logo-nav-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            height: 100%;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .logo a {
            color: white;
            text-decoration: none;
        }

        .logo i {
            color: var(--accent-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Mobile menu toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 10px;
        }

        nav {
            height: 100%;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 1rem;
            height: 100%;
            margin: 0;
        }

        nav li {
            height: 100%;
            display: flex;
            align-items: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 100%;
            align-items: center;
        }

        nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: var(--transition);
        }

        nav a:hover::after {
            width: 70%;
        }

        .auth-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .auth-actions a {
            color: white;
            text-decoration: none;
            font-size: 1rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .auth-actions a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--accent-color);
        }

        .cart-count {
            position: relative;
        }

        .cart-count span {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }

        /* Hero Section */
        .hero {
            height: calc(100vh - var(--header-height));
            min-height: 500px;
            max-height: 800px;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            margin-bottom: 3rem;
            position: relative;
            padding: 0 15px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(0,0,0,0.8), rgba(0,0,0,0.4));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 1rem;
        }

        .hero h2 {
            font-size: clamp(1.2rem, 4vw, 2rem);
            margin-bottom: 1rem;
            font-weight: 300;
            letter-spacing: 2px;
            color: var(--accent-color);
        }

        .hero h1 {
            font-size: clamp(2rem, 8vw, 4rem);
            margin-bottom: 2rem;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .shop-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 1rem 2.5rem;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: clamp(1rem, 2vw, 1.2rem);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .shop-btn:hover {
            background-color: #ff5252;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }

        /* Featured Categories */
        .featured-categories {
            padding: 3rem 0;
            flex: 1;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .section-title h2 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            color: var(--text-color);
            display: inline-block;
            position: relative;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 0 15px;
        }

        .category-card {
            position: relative;
            height: 300px;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .category-card:hover {
            transform: translateY(-10px);
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .category-card:hover .category-image {
            transform: scale(1.05);
        }

        .category-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.8), rgba(0,0,0,0.3));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 1.5rem;
        }

        .category-name {
            font-size: clamp(1.2rem, 3vw, 1.5rem);
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
        }

        .category-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-color);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .category-link:hover {
            color: white;
            transform: translateX(5px);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 0 0;
            margin-top: auto;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 0 15px;
        }

        .footer-column h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            color: var(--accent-color);
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }

        .footer-column p {
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column li {
            margin-bottom: 0.8rem;
        }

        .footer-column a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-column a:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-column a i {
            width: 20px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--accent-color);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding: 2rem 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .categories-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .auth-actions a span {
                display: none;
            }
            
            .auth-actions a i {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            
            nav {
                position: fixed;
                top: var(--header-height);
                left: -100%;
                width: 80%;
                max-width: 300px;
                height: calc(100vh - var(--header-height));
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                transition: var(--transition);
                z-index: 999;
                box-shadow: 5px 0 15px rgba(0,0,0,0.3);
            }
            
            nav.active {
                left: 0;
            }
            
            nav ul {
                flex-direction: column;
                height: auto;
                padding: 1rem;
                gap: 0;
            }
            
            nav li {
                height: auto;
                width: 100%;
            }
            
            nav a {
                padding: 1rem;
                width: 100%;
                justify-content: flex-start;
            }
            
            .hero {
                background-attachment: scroll;
            }
            
            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .logo {
                font-size: 1.3rem;
            }
            
            .hero {
                min-height: 400px;
            }
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .auth-actions {
                gap: 0.5rem;
            }
            
            .auth-actions a {
                padding: 0.5rem;
            }
        }

        /* Very small devices (phones, 360px and down) */
        @media (max-width: 360px) {
            .logo span {
                display: none;
            }
            
            .logo i {
                font-size: 1.5rem;
            }
            
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .shop-btn {
                padding: 0.8rem 1.5rem;
            }
        }

        /* Accessibility improvements */
        a:focus, button:focus {
            outline: 2px solid var(--accent-color);
            outline-offset: 2px;
        }

        /* Skip to content link for accessibility */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--accent-color);
            color: white;
            padding: 8px;
            z-index: 100;
            transition: top 0.3s;
        }

        .skip-link:focus {
            top: 0;
        }
    </style>
</head>
<body>
    <!-- Skip to content link for accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header>
        <div class="header-container">
            <div class="logo-nav-container">
                <button class="menu-toggle" aria-label="Toggle navigation menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <a href="index.php"><i class="fas fa-tshirt"></i> <span>Urban Trends</span></a>
                </div>
                <nav id="main-nav">
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> <span>Home</span></a></li>
                        <li><a href="shop.php"><i class="fas fa-store"></i> <span>Shop</span></a></li>
                        <li><a href="about.php"><i class="fas fa-info-circle"></i> <span>About</span></a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> <span>Contact</span></a></li>
                    </ul>
                </nav>
            </div>
            
            <div class="auth-actions">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="profile.php" title="Profile" aria-label="Profile">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                    <a href="?logout=1" title="Logout" aria-label="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="admin/dashboard.php" title="Admin" aria-label="Admin Dashboard">
                            <i class="fas fa-cog"></i>
                            <span>Admin</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php" title="Login" aria-label="Login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </a>
                    <a href="register.php" title="Register" aria-label="Register">
                        <i class="fas fa-user-plus"></i>
                        <span>Register</span>
                    </a>
                <?php endif; ?>
                
            </div>
        </div>
    </header>

    <main id="main-content">
        <section class="hero">
            <div class="hero-content">
                <h2>WELCOME TO</h2>
                <h1>URBAN TRENDS</h1>
                <a href="shop.php" class="shop-btn">
                    <i class="fas fa-shopping-bag"></i> SHOP NOW
                </a>
            </div>
        </section>

        <section class="featured-categories">
            <div class="container">
                <div class="section-title">
                    <h2>SHOP BY CATEGORY</h2>
                </div>
                <div class="categories-grid">
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1854&q=80" alt="Men's Fashion" class="category-image">
                        <div class="category-overlay">
                            <h3 class="category-name">Men's Fashion</h3>
                            <a href="shop.php?category=men" class="category-link">
                                Shop Now <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1551232864-3f0890e580d9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1887&q=80" alt="Women's Fashion" class="category-image">
                        <div class="category-overlay">
                            <h3 class="category-name">Women's Fashion</h3>
                            <a href="shop.php?category=women" class="category-link">
                                Shop Now <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1600269452121-4f2416e55c28?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1965&q=80" alt="Footwear" class="category-image">
                        <div class="category-overlay">
                            <h3 class="category-name">Footwear</h3>
                            <a href="shop.php?category=shoes" class="category-link">
                                Shop Now <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1592155931584-901ac15763e3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1875&q=80" alt="Accessories" class="category-image">
                        <div class="category-overlay">
                            <h3 class="category-name">Accessories</h3>
                            <a href="shop.php?category=accessories" class="category-link">
                                Shop Now <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>About Urban Trends</h3>
                    <p>Your premier destination for the latest in urban fashion trends. We offer high-quality apparel and accessories for the modern urban lifestyle.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Pinterest"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                        <li><a href="about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul>
                        <li><a href="profile.php"><i class="fas fa-chevron-right"></i> My Account</a></li>
                        <li><a href="profile.php#orders"><i class="fas fa-chevron-right"></i> Order Tracking</a></li>
                        <li><a href="profile.php#returns"><i class="fas fa-chevron-right"></i> Returns & Refunds</a></li>
                        <li><a href="privacy.php"><i class="fas fa-chevron-right"></i> Privacy Policy</a></li>
                        <li><a href="terms.php"><i class="fas fa-chevron-right"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Urban Street, Fashion District, City</li>
                        <li><i class="fas fa-phone"></i> +1 (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@urbantrends.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM - 6PM</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Urban Trends Apparel. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        const mainNav = document.querySelector('#main-nav');
        
        menuToggle.addEventListener('click', () => {
            mainNav.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', mainNav.classList.contains('active'));
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mainNav.contains(e.target) && !menuToggle.contains(e.target)) {
                mainNav.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        });
        
        // Update cart counter
        function updateCartCounter() {
            fetch('get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cart-counter').textContent = data.count;
                })
                .catch(error => {
                    console.error('Error fetching cart count:', error);
                });
        }

        // Initialize cart counter
        updateCartCounter();
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>