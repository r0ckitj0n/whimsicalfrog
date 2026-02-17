<?php
// includes/area_mappings/helpers/AreaMappingSitemapHelper.php

class AreaMappingSitemapHelper
{
    /**
     * Get sitemap entries (pages and modals)
     */
    public static function getSitemapEntries()
    {
        try {
            // Seed defaults if empty
            $count = (int) Database::queryOne("SELECT COUNT(*) AS c FROM sitemap_entries")['c'];
            if ($count === 0) {
                self::seedDefaults();
            }

            return Database::queryAll(
                "SELECT slug, url, label, kind, is_active, lastmod FROM sitemap_entries WHERE is_active = 1 ORDER BY label ASC"
            );
        } catch (Exception $e) {
            throw new Exception('Sitemap retrieval failed: ' . $e->getMessage());
        }
    }

    /**
     * Get door sign destinations for a specific room
     * @param string|null $room Room number (defaults to '0' for Main Room)
     * @return array Array of door sign destinations with area_selector, label, target, image
     */
    public static function getDoorSignDestinationsForRoom($room = '0')
    {
        try {
            $room = $room ?? '0';

            // Fetch all active mappings for the room that have content targets
            $destRows = Database::queryAll(
                "SELECT am.area_selector, am.mapping_type, am.link_url, am.link_label, am.content_target, am.content_image,
                        rs.is_active as room_active
                 FROM area_mappings am
                 LEFT JOIN room_settings rs ON (
                    CASE 
                        WHEN am.content_target REGEXP '^[0-9A-Za-z]+$' THEN am.content_target = rs.room_number
                        WHEN am.content_target REGEXP '^room:[0-9A-Za-z]+$' THEN SUBSTRING(am.content_target, 6) = rs.room_number
                        ELSE rs.room_number = NULL
                    END
                 )
                 WHERE am.room_number = ? 
                   AND am.is_active = 1 
                   AND am.area_selector REGEXP '^\\\\.area-[0-9]+$'
                   AND am.mapping_type IN ('content', 'button', 'item', 'category')
                 HAVING room_active IS NULL OR room_active = 1
                 ORDER BY am.area_selector ASC",
                [$room]
            );

            $rows = [];
            foreach ($destRows as $r) {
                if (preg_match('/\\.area-(\\d+)/', $r['area_selector'], $m)) {
                    $areaNum = (int) $m[1];
                    $rows[] = [
                        'area_selector' => $r['area_selector'],
                        'label' => $r['link_label'] ?? "Door Sign {$areaNum}",
                        'target' => $r['content_target'] ?? $r['link_url'] ?? (string) $areaNum,
                        'image' => $r['content_image'] ?? "/images/signs/sign-door-room{$areaNum}.png",
                    ];
                }
            }
            return $rows;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get door sign destinations for Main Room (all configured areas)
     */
    public static function getDoorSignDestinations()
    {
        try {
            $room = '0';

            // Fetch all active mappings for Main Room that have content targets (no hardcoded area limit)
            $destRows = Database::queryAll(
                "SELECT am.area_selector, am.mapping_type, am.link_url, am.link_label, am.content_target, am.content_image,
                        rs.is_active as room_active
                 FROM area_mappings am
                 LEFT JOIN room_settings rs ON (
                    CASE 
                        WHEN am.content_target REGEXP '^[0-9A-Za-z]+$' THEN am.content_target = rs.room_number
                        WHEN am.content_target REGEXP '^room:[0-9A-Za-z]+$' THEN SUBSTRING(am.content_target, 6) = rs.room_number
                        ELSE rs.room_number = NULL
                    END
                 )
                 WHERE am.room_number = ? 
                   AND am.is_active = 1 
                   AND am.area_selector REGEXP '^\\\\.area-[0-9]+$'
                   AND am.mapping_type IN ('content', 'button', 'item', 'category')
                 HAVING room_active IS NULL OR room_active = 1
                 ORDER BY am.area_selector ASC",
                [$room]
            );

            $rows = [];
            foreach ($destRows as $r) {
                if (preg_match('/\\.area-(\\d+)/', $r['area_selector'], $m)) {
                    $areaNum = (int) $m[1];
                    $rows[] = [
                        'area_selector' => $r['area_selector'],
                        'label' => $r['link_label'] ?? "Door Sign {$areaNum}",
                        'target' => $r['content_target'] ?? $r['link_url'] ?? (string) $areaNum,
                        'image' => $r['content_image'] ?? "/images/signs/sign-door-room{$areaNum}.png",
                    ];
                }
            }
            return $rows;
        } catch (Exception $e) {
            // Fallback to empty - let coordinates determine what to show
            return [];
        }
    }

    private static function seedDefaults()
    {
        $defaults = [
            // ============ PAGES ============
            ['home', '/', 'Home', 'page'],
            ['landing', '/landing.php', 'Landing', 'page'],
            ['main-room', '/room_main.php', 'Main Room', 'page'],
            ['shop', '/shop.php', 'Shop', 'page'],
            ['cart', '/cart.php', 'Cart', 'page'],
            ['about', '/about.php', 'About', 'page'],
            ['contact', '/contact.php', 'Contact', 'page'],
            ['policy', '/policy.php', 'Policy', 'page'],
            ['privacy', '/privacy.php', 'Privacy', 'page'],
            ['terms', '/terms.php', 'Terms', 'page'],
            ['login', '/login.php', 'Login', 'page'],

            // ============ MODALS ============
            // Commerce
            ['modal-cart', 'cart', 'Open Cart', 'modal'],
            ['modal-checkout', 'checkout', 'Checkout', 'modal'],
            ['modal-payment', 'payment', 'Payment Form', 'modal'],

            // Authentication
            ['modal-login', 'login', 'Login / Register', 'modal'],
            ['modal-account', 'account-settings', 'Account Settings', 'modal'],
            ['modal-forgot-password', 'forgot-password', 'Forgot Password', 'modal'],

            // Product Discovery
            ['modal-item-details', 'item-details', 'Product Details', 'modal'],
            ['modal-quick-view', 'quick-view', 'Quick View', 'modal'],
            ['modal-size-chart', 'size-chart', 'Size Chart', 'modal'],
            ['modal-color-picker', 'color-picker', 'Color Options', 'modal'],

            // Information
            ['modal-contact', 'contact', 'Contact Form', 'modal'],
            ['modal-newsletter', 'newsletter', 'Newsletter Signup', 'modal'],
            ['modal-help', 'help', 'Help & FAQ', 'modal'],
            ['modal-policy', 'policy', 'Policy Viewer', 'modal'],
            ['modal-shipping-info', 'shipping-info', 'Shipping Information', 'modal'],
            ['modal-returns', 'returns', 'Returns Policy', 'modal'],

            // Interactive
            ['modal-search', 'search', 'Search Products', 'modal'],
            ['modal-wishlist', 'wishlist', 'View Wishlist', 'modal'],
            ['modal-compare', 'compare', 'Compare Products', 'modal'],
            ['modal-gallery', 'gallery', 'Image Gallery', 'modal'],

            // ============ GLOBAL ACTIONS ============
            // Cart Actions
            ['action-open-cart', 'open-cart', 'Open Shopping Cart', 'action'],
            ['action-clear-cart', 'clear-cart', 'Clear Cart', 'action'],
            ['action-apply-coupon', 'apply-coupon', 'Apply Coupon', 'action'],

            // Auth Actions
            ['action-open-login', 'open-login', 'Open Login Modal', 'action'],
            ['action-open-register', 'open-register', 'Open Register Modal', 'action'],
            ['action-open-account', 'open-account-settings', 'Open Account Settings', 'action'],
            ['action-logout', 'logout', 'Log Out', 'action'],

            // Navigation Actions
            ['action-go-back', 'go-back', 'Navigate Back', 'action'],
            ['action-go-forward', 'go-forward', 'Navigate Forward', 'action'],
            ['action-go-home', 'go-home', 'Go to Home Page', 'action'],
            ['action-go-shop', 'go-shop', 'Go to Shop', 'action'],
            ['action-scroll-top', 'scroll-to-top', 'Scroll to Top', 'action'],
            ['action-scroll-bottom', 'scroll-to-bottom', 'Scroll to Bottom', 'action'],

            // Page Actions
            ['action-refresh', 'refresh-page', 'Refresh Page', 'action'],
            ['action-print-page', 'print-page', 'Print Current Page', 'action'],
            ['action-share-page', 'share-page', 'Share Page', 'action'],
            ['action-copy-link', 'copy-link', 'Copy Page Link', 'action'],
            ['action-fullscreen', 'toggle-fullscreen', 'Toggle Fullscreen', 'action'],

            // Interactive Actions
            ['action-play-sound', 'play-sound', 'Play Sound Effect', 'action'],
            ['action-show-notification', 'show-notification', 'Show Notification', 'action'],
            ['action-toggle-music', 'toggle-music', 'Toggle Background Music', 'action'],
            ['action-confetti', 'trigger-confetti', 'Celebrate! (Confetti)', 'action'],

            // Utility Actions  
            ['action-toggle-dark', 'toggle-dark-mode', 'Toggle Dark Mode', 'action'],
            ['action-toggle-accessibility', 'toggle-accessibility', 'Accessibility Options', 'action'],
            ['action-zoom-in', 'zoom-in', 'Zoom In', 'action'],
            ['action-zoom-out', 'zoom-out', 'Zoom Out', 'action'],
        ];

        foreach ($defaults as [$slug, $url, $label, $kind]) {
            try {
                Database::execute(
                    "INSERT IGNORE INTO sitemap_entries (slug, url, label, kind, source, is_active) VALUES (?, ?, ?, ?, 'seed', 1)",
                    [$slug, $url, $label, $kind]
                );
            } catch (Exception $e) {
            }
        }
    }
}
