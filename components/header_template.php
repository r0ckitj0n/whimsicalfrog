<?php
/**
 * Universal Page Header Component
 * Consistent header with fade effect (darker top to lighter bottom) used across all pages except rooms 1-5
 *
 * Features:
 * - Gradient fade effect from dark to light
 * - Centered search field with left-justified navigation items
 * - Responsive design
 * - Consistent styling across all pages
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
        ['label' => 'Shop', 'url' => '/shop', 'active' => false],
        ['label' => 'About', 'url' => '/about', 'active' => false],
        ['label' => 'Contact', 'url' => '/contact', 'active' => false],
    ],
    'search_placeholder' => 'Search for items...',
    'mobile_breakpoint' => '768px'
];

// Merge with provided config
$config = isset($header_config) ? array_merge($default_config, $header_config) : $default_config;

// Get current page for active navigation
$current_page = $_SERVER['REQUEST_URI'] ?? '/';

// Cart information - use same logic as room_main.php
$cart_count = 0;
$cart_total = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'] ?? 0;
        $cart_total += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
    }
}

// User information - use auth functions if available
if (function_exists('isLoggedIn') && function_exists('getUsername')) {
    $is_logged_in = isLoggedIn();
    $username = $is_logged_in ? getUsername() : null;
    // Determine admin role using centralized helper if available
    if (function_exists('isAdmin')) {
        $is_admin = isAdmin();
    } else {
        // Fallback to session-based role check
        $is_admin = isset($_SESSION['user']) && is_array($_SESSION['user']) &&
                    isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';
    }
} else {
    // Fallback to session-based detection
    $is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['user']);
    $username = $is_logged_in ? ($_SESSION['username'] ?? 'User') : null;
    $is_admin = isset($_SESSION['user']) && is_array($_SESSION['user']) &&
                isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';
}
?>

<header class="site-header universal-page-header" role="banner">
    <div class="header-container">
        <div class="header-content">

            <!-- Left Section: Logo and Navigation -->
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

                <!-- Desktop Navigation (Left-justified) - Excluding Shop which is now in right section -->
                <nav class="nav-links" role="navigation" aria-label="Main navigation">
                    <?php foreach ($config['navigation_items'] as $item): ?>
                        <?php
                        // Skip Shop (rendered on right) and Home (logo already links home)
                        $label_lc = strtolower($item['label']);
                        if ($label_lc === 'shop' || $label_lc === 'home') continue;

                        $is_active = $item['active'] ?? (rtrim($current_page, '/') === rtrim($item['url'], '/'));
                        ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>"
                           class="nav-link <?php echo $is_active ? 'active' : ''; ?> <?php echo isset($item['is_image']) ? 'nav-image-link' : ''; ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <?php
                            if (isset($item['is_image']) && $item['is_image']) {
                                echo $item['label']; // Don't escape HTML for image content
                            } else {
                                echo htmlspecialchars($item['label']);
                            }
                            ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Center Section: Search (Centered) -->
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

            <!-- Right Section: Shop, User Menu, Cart -->
            <div class="header-right">

                <!-- Shop Navigation Link -->
                <?php
                // Find and display Shop link from navigation items
                foreach ($config['navigation_items'] as $item):
                    if (strtolower($item['label']) === 'shop'):
                        $is_active = $item['active'] ?? (rtrim($current_page, '/') === rtrim($item['url'], '/'));
                ?>
                        <a href="<?php echo htmlspecialchars($item['url']); ?>"
                           class="nav-link <?php echo $is_active ? 'active' : ''; ?>"
                           <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                <?php
                        break;
                    endif;
                endforeach;
                ?>

                <!-- User Menu: Login/Logout -->
                <?php if ($config['show_user_menu']): ?>
                    <?php if ($is_logged_in): ?>
                        <span class="welcome-message">
                            <a href="/account_settings" class="nav-link"><?php echo htmlspecialchars($username); ?></a>
                        </span>
                        <?php if (!empty($is_admin)): ?>
                            <a href="/admin/settings" class="nav-link">Settings</a>
                        <?php endif; ?>
                        <a href="/logout.php" class="nav-link">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="nav-link" data-action="open-login-modal">Login</a>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Cart Link -->
                <?php if ($config['show_cart']): ?>
                    <a href="/cart" class="cart-link" aria-label="Shopping cart with <?php echo $cart_count; ?> items">
                        <div class="flex items-center space-x-1 md:space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap"><?php echo $cart_count; ?> items</span>
                            <svg class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap hidden md:inline">$<?php echo number_format($cart_total, 2); ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Mobile Menu Toggle -->
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

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobile-menu" role="navigation" aria-label="Mobile navigation">
            <div class="mobile-nav-links">
                <?php foreach ($config['navigation_items'] as $item): ?>
                    <?php
                    // Skip Home in mobile too
                    if (strtolower($item['label']) === 'home') continue;
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
                            <?php if (!empty($is_admin)): ?>
                                <a href="/admin/settings" class="mobile-nav-link">Settings</a>
                            <?php endif; ?>
                            <a href="/profile" class="mobile-nav-link">Profile</a>
                            <a href="/logout.php" class="mobile-nav-link">Logout</a>
                        <?php else: ?>
                            <a href="/login" class="mobile-nav-link" data-action="open-login-modal">Login</a>
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



 