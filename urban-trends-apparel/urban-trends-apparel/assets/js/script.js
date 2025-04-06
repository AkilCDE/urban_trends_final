// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all scripts
    
    // Mobile menu toggle (if needed)
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
        });
    }
    
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                    // Update cart count
                    const cartLink = document.querySelector('.cart-link');
                    if (cartLink) {
                        cartLink.textContent = `Cart (${data.cart_count})`;
                    }
                } else {
                    alert('Error adding to cart: ' + data.message);
                }
            });
        });
    });
    
    // Wishlist buttons
    document.querySelectorAll('.wishlist').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const isActive = this.classList.contains('active');
            
            fetch('wishlist_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&action=${isActive ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            fetch('search_products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `search_term=${searchTerm}`
            })
            .then(response => response.text())
            .then(html => {
                const productsContainer = document.getElementById('productsContainer');
                if (productsContainer) {
                    productsContainer.innerHTML = html;
                    // Re-attach event listeners to new elements
                    attachEventListeners();
                }
            });
        });
    }
    
    // Profile section navigation
    document.querySelectorAll('.profile-sidebar a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            document.querySelectorAll('.profile-sidebar a').forEach(l => {
                l.classList.remove('active');
            });
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Hide all sections
            document.querySelectorAll('.profile-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const sectionId = this.getAttribute('href');
            document.querySelector(sectionId).style.display = 'block';
        });
    });
    
    // Remove from wishlist buttons
    document.querySelectorAll('.remove-wishlist').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            
            fetch('wishlist_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&action=remove`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.closest('.wishlist-item').remove();
                    
                    // If no items left, show empty message
                    if (document.querySelectorAll('.wishlist-item').length === 0) {
                        const wishlistSection = document.querySelector('#wishlist');
                        if (wishlistSection) {
                            wishlistSection.innerHTML = `
                                <h3>Wishlist</h3>
                                <p>Your wishlist is empty.</p>
                            `;
                        }
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    });
});

// Function to re-attach event listeners after AJAX updates
function attachEventListeners() {
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                    // Update cart count
                    const cartLink = document.querySelector('.cart-link');
                    if (cartLink) {
                        cartLink.textContent = `Cart (${data.cart_count})`;
                    }
                } else {
                    alert('Error adding to cart: ' + data.message);
                }
            });
        });
    });
    
    // Wishlist buttons
    document.querySelectorAll('.wishlist').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const isActive = this.classList.contains('active');
            
            fetch('wishlist_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&action=${isActive ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
    });
}