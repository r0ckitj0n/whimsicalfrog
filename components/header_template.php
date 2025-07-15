<?php
/**
 * Header Template Component
 * Comprehensive header with navigation, search, and user features
 * 
 * Usage Examples:
 * <?php include 'components/header_template.php'; ?>
 * 
 * Or with custom options:
 * <?php 
 * $header_config = [
 *     'show_search' => true,
 *     'show_cart' => true,
 *     'show_user_menu' => true,
 *     'navigation_items' => [
 *         ['label' => 'Home', 'url' => '/', 'active' => true],
 *         ['label' => 'Shop', 'url' => '/shop', 'active' => false],
 *         ['label' => 'About', 'url' => '/about', 'active' => false],
 *     ]
 * ];
 * include 'components/header_template.php'; 
 * ?>
 */

// Default configuration
$default_config = [
    'show_search' => true,
    'show_cart' => true,
    'show_user_menu' => true,
    'show_logo' => true,
    'logo_text' => 'WhimsicalFrog',
    'logo_tagline' => 'Enchanted Treasures',
    'logo_image' => 'images/logo.png',
    'navigation_items' => [
        ['label' => 'Home', 'url' => '/', 'active' => false],
        ['label' => 'Shop', 'url' => '/shop', 'active' => false],
        ['label' => 'Rooms', 'url' => '/rooms', 'active' => false],
        ['label' => 'About', 'url' => '/about', 'active' => false],
        ['label' => 'Contact', 'url' => '/contact', 'active' => false],
    ],
    'search_placeholder' => 'Search magical items...',
    'mobile_breakpoint' => '768px'
];

// Merge with provided config
$config = isset($header_config) ? array_merge($default_config, $header_config) : $default_config;

// Get current page for active navigation
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

// Cart information (you may need to adjust this based on your cart system)
$cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$cart_total = isset($_SESSION['cart_total']) ? $_SESSION['cart_total'] : 0;

// User information
$is_logged_in = isset($_SESSION['user_id']);
$username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : null;
?>

<header class="site-header" role="banner">
    <div class="header-container">
        <div class="header-content">
            
            <!- Left Section: Logo ->
            <div class="header-left">
                <?php if ($config['show_logo']): ?>
                    <a href="/" class="logo-link" aria-label="<?php echo htmlspecialchars($config['logo_text']); ?> - Home">
                        <?php if (!empty($config['logo_image']) && file_exists($config['logo_image'])): ?>
                            <img src="<?php echo htmlspecialchars($config['logo_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($config['logo_text']); ?>" 
                                 class="header-logo">
                        <?php endif; ?>
                        
                        <div class="logo-text-container">
                            <div class="logo-text"><?php echo htmlspecialchars($config['logo_text']); ?></div>
                            <?php if (!empty($config['logo_tagline'])): ?>
                                <div class="logo-tagline"><?php echo htmlspecialchars($config['logo_tagline']); ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

            <!- Center Section: Search ->
            <?php if ($config['show_search']): ?>
                <div class="header-center">
                    <div class="search-container">
                        <form action="/search" method="GET" role="search">
                            <div class="search-input-container">
                                <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input type="search" 
                                       name="q" 
                                       class="search-bar" 
                                       placeholder="<?php echo htmlspecialchars($config['search_placeholder']); ?>"
                                       aria-label="Search"
                                       autocomplete="off">
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!- Right Section: Navigation, Cart, User Menu ->
            <div class="header-right">
                
                <!- Desktop Navigation ->
                <nav class="nav-links" role="navigation" aria-label="Main navigation">
                    <?php foreach ($config['navigation_items'] as $item): ?>
                        <?php 
                        $is_active = $item['active'] ?? (rtrim($current_page, '/') === rtrim($item['url'], '/'));
                        ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                           class="nav-link <?php echo $is_active ? 'active' : ''; ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!- User Menu: Logout then Username ->
                <?php if ($config['show_user_menu']): ?>
                    <?php if ($is_logged_in): ?>
                        <div class="user-menu">
                            <span class="welcome-message">
                                <a href="/logout">Logout</a> | <a href="/profile"><?php echo htmlspecialchars($username); ?></a>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="auth-links">
                            <a href="/login" class="nav-link">Login</a>
                            <a href="/register" class="nav-link">Register</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!- Cart Link ->
                <?php if ($config['show_cart']): ?>
                    <a href="/cart" class="cart-link" aria-label="Shopping cart with <?php echo $cart_count; ?> items">
                        <svg class="cart-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                        </svg>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php if ($cart_total > 0): ?>
                            <span class="cart-total">$<?php echo number_format($cart_total, 2); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <!- Mobile Menu Toggle ->
                <button class="mobile-menu-toggle" 
                        aria-label="Toggle mobile menu" 
                        aria-expanded="false" 
                        aria-controls="mobile-menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!- Mobile Menu ->
        <div class="mobile-menu" id="mobile-menu" role="navigation" aria-label="Mobile navigation">
            <div class="mobile-nav-links">
                <?php foreach ($config['navigation_items'] as $item): ?>
                    <?php 
                    $is_active = $item['active'] ?? (rtrim($current_page, '/') === rtrim($item['url'], '/'));
                    ?>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                       class="mobile-nav-link <?php echo $is_active ? 'active' : ''; ?>"
                       <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
                
                <?php if ($config['show_user_menu']): ?>
                    <div class="mobile-auth-section">
                        <?php if ($is_logged_in): ?>
                            <a href="/profile" class="mobile-nav-link">Profile</a>
                            <a href="/logout" class="mobile-nav-link">Logout</a>
                        <?php else: ?>
                            <a href="/login" class="mobile-nav-link">Login</a>
                            <a href="/register" class="mobile-nav-link">Register</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($config['show_search']): ?>
                <div class="mobile-search">
                    <form action="/search" method="GET" role="search">
                        <input type="search" 
                               name="q" 
                               class="search-bar" 
                               placeholder="<?php echo htmlspecialchars($config['search_placeholder']); ?>"
                               aria-label="Search">
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Mobile menu toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            const isExpanded = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
            
            mobileMenuToggle.setAttribute('aria-expanded', !isExpanded);
            mobileMenu.classList.toggle('active');
            
            // Update icon
            const icon = mobileMenuToggle.querySelector('svg path');
            if (icon) {
                if (isExpanded) {
                    icon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16'); // Hamburger
                } else {
                    icon.setAttribute('d', 'M6 18L18 6M6 6l12 12'); // X
                }
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuToggle.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
                mobileMenu.classList.remove('active');
                
                const icon = mobileMenuToggle.querySelector('svg path');
                if (icon) {
                    icon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16'); // Hamburger
                }
            }
        });
        
        // Close mobile menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && mobileMenu.classList.contains('active')) {
                mobileMenuToggle.setAttribute('aria-expanded', 'false');
                mobileMenu.classList.remove('active');
                mobileMenuToggle.focus();
            }
        });
    }
    
    // Search functionality enhancement
    const searchForms = document.querySelectorAll('form[role="search"]');
    searchForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const input = form.querySelector('input[name="q"]');
            if (input && input.value.trim() === '') {
                e.preventDefault();
                input.focus();
            }
        });
    });
});
</script>

 