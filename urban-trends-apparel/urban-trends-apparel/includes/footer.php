<?php
ob_start();
?>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About Urban Trends</h3>
                    <p>Your premier destination for the latest in urban fashion trends.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="shop.php">Shop</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p>Email: info@urbantrends.com</p>
                    <p>Phone: (123) 456-7890</p>
                </div>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Urban Trends Apparel. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="../assets/js/script.js"></script>
</body>
</html>
<?php
$footer = ob_get_clean();
?>