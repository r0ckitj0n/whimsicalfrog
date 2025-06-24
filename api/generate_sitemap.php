<?php
/**
 * Dynamic Sitemap Generator
 * Generates XML sitemap based on database-driven room structure
 */

require_once 'config.php';

header('Content-Type: application/xml; charset=utf-8');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Start XML sitemap
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Base URL
    $baseUrl = 'https://whimsicalfrog.us';
    
    // Add homepage
    echo "    <url>\n";
    echo "        <loc>{$baseUrl}/</loc>\n";
    echo "        <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "        <changefreq>daily</changefreq>\n";
    echo "        <priority>1.0</priority>\n";
    echo "    </url>\n";
    
    // Add main room
    echo "    <url>\n";
    echo "        <loc>{$baseUrl}/?page=main_room</loc>\n";
    echo "        <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "        <changefreq>weekly</changefreq>\n";
    echo "        <priority>0.9</priority>\n";
    echo "    </url>\n";
    
    // Get all room-category assignments
    $stmt = $pdo->prepare("
        SELECT DISTINCT rca.room_number, c.name as category_name, rs.room_name, rs.description
        FROM room_category_assignments rca 
        JOIN categories c ON rca.category_id = c.id 
        LEFT JOIN room_settings rs ON rca.room_number = rs.room_number
        WHERE rca.is_primary = 1
        ORDER BY rca.room_number
    ");
    $stmt->execute();
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add room pages
    foreach ($rooms as $room) {
        $roomUrl = "{$baseUrl}/?page=room{$room['room_number']}";
        echo "    <url>\n";
        echo "        <loc>{$roomUrl}</loc>\n";
        echo "        <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        echo "        <changefreq>weekly</changefreq>\n";
        echo "        <priority>0.8</priority>\n";
        echo "    </url>\n";
    }
    
    // Add static pages
    $staticPages = [
        'about' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'contact' => ['priority' => '0.7', 'changefreq' => 'monthly'],
        'cart' => ['priority' => '0.6', 'changefreq' => 'daily'],
        'admin' => ['priority' => '0.3', 'changefreq' => 'monthly']
    ];
    
    foreach ($staticPages as $page => $settings) {
        echo "    <url>\n";
        echo "        <loc>{$baseUrl}/?page={$page}</loc>\n";
        echo "        <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        echo "        <changefreq>{$settings['changefreq']}</changefreq>\n";
        echo "        <priority>{$settings['priority']}</priority>\n";
        echo "    </url>\n";
    }
    
    // Close XML sitemap
    echo "</urlset>\n";
    
} catch (Exception $e) {
    error_log("Sitemap generation error: " . $e->getMessage());
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo "    <url>\n";
    echo "        <loc>https://whimsicalfrog.us/</loc>\n";
    echo "        <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "        <changefreq>daily</changefreq>\n";
    echo "        <priority>1.0</priority>\n";
    echo "    </url>\n";
    echo "</urlset>\n";
}
?> 