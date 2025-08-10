<?php
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../includes/database.php';

require_once 'config.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Initialize analytics tables
    initializeAnalyticsTables($pdo);

    // Handle different actions
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'track_visit':
            trackVisit($pdo);
            break;
        case 'track_page_view':
            trackPageView($pdo);
            break;
        case 'track_interaction':
            trackInteraction($pdo);
            break;
        case 'track_conversion':
            trackConversion($pdo);
            break;
        case 'get_analytics_report':
            getAnalyticsReport($pdo);
            break;
        case 'get_optimization_suggestions':
            getOptimizationSuggestions($pdo);
            break;
        case 'track_product_view':
        case 'track_item_view':
            trackItemView($pdo);
            break;
        case 'track_cart_action':
            trackCartAction($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
    }

} catch (PDOException $e) {
    error_log("Analytics tracker PDO error: " . $e->getMessage());
    // Don't fail completely - just return success to prevent breaking the site
    echo json_encode(['success' => true, 'note' => 'Analytics temporarily unavailable']);
} catch (Exception $e) {
    error_log("Analytics tracker error: " . $e->getMessage());
    // Don't fail completely - just return success to prevent breaking the site
    echo json_encode(['success' => true, 'note' => 'Analytics temporarily unavailable']);
}

function initializeAnalyticsTables($pdo)
{
    // User sessions table
    $sessionsTable = "CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        user_id INT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referrer TEXT,
        landing_page VARCHAR(255),
        device_type ENUM('desktop', 'tablet', 'mobile') DEFAULT 'desktop',
        browser VARCHAR(100),
        operating_system VARCHAR(100),
        country VARCHAR(100),
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        session_duration INT DEFAULT 0,
        total_page_views INT DEFAULT 0,
        bounce BOOLEAN DEFAULT TRUE,
        converted BOOLEAN DEFAULT FALSE,
        conversion_value DECIMAL(10,2) DEFAULT 0,
        INDEX idx_session_id (session_id),
        INDEX idx_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Page views table
    $pageViewsTable = "CREATE TABLE IF NOT EXISTS page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        page_url VARCHAR(500),
        page_title VARCHAR(255),
        page_type VARCHAR(100),
        item_sku VARCHAR(50),
        time_on_page INT DEFAULT 0,
        scroll_depth INT DEFAULT 0,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        exit_page BOOLEAN DEFAULT FALSE,
        INDEX idx_session_id (session_id),
        INDEX idx_page_type (page_type),
        INDEX idx_item_sku (item_sku),
        INDEX idx_viewed_at (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // User interactions table
    $interactionsTable = "CREATE TABLE IF NOT EXISTS user_interactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        page_url VARCHAR(500),
        interaction_type ENUM('click', 'hover', 'scroll', 'form_submit', 'search', 'filter', 'cart_add', 'cart_remove', 'checkout_start', 'checkout_complete') NOT NULL,
        element_type VARCHAR(100),
        element_id VARCHAR(255),
        element_text TEXT,
        item_sku VARCHAR(50),
        interaction_data JSON,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_id (session_id),
        INDEX idx_interaction_type (interaction_type),
        INDEX idx_item_sku (item_sku),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Item analytics table
    $itemAnalyticsTable = "CREATE TABLE IF NOT EXISTS item_analytics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(50) NOT NULL,
        views_count INT DEFAULT 0,
        unique_views_count INT DEFAULT 0,
        cart_adds_count INT DEFAULT 0,
        cart_removes_count INT DEFAULT 0,
        purchases_count INT DEFAULT 0,
        avg_time_on_page DECIMAL(8,2) DEFAULT 0,
        bounce_rate DECIMAL(5,2) DEFAULT 0,
        conversion_rate DECIMAL(5,2) DEFAULT 0,
        revenue_generated DECIMAL(10,2) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_sku (item_sku)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Conversion funnels table
    $conversionFunnelsTable = "CREATE TABLE IF NOT EXISTS conversion_funnels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(128) NOT NULL,
        funnel_step ENUM('landing', 'item_view', 'cart_add', 'checkout_start', 'checkout_complete') NOT NULL,
        page_url VARCHAR(500),
        item_sku VARCHAR(50),
        step_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        time_to_next_step INT,
        dropped_off BOOLEAN DEFAULT FALSE,
        INDEX idx_session_id (session_id),
        INDEX idx_funnel_step (funnel_step),
        INDEX idx_step_timestamp (step_timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Optimization suggestions table
    $optimizationTable = "CREATE TABLE IF NOT EXISTS optimization_suggestions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        suggestion_type ENUM('performance', 'conversion', 'ui_ux', 'content', 'product', 'marketing') NOT NULL,
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        title VARCHAR(255) NOT NULL,
        description TEXT,
        suggested_action TEXT,
        data_source TEXT,
        confidence_score DECIMAL(3,2) DEFAULT 0.5,
        potential_impact ENUM('low', 'medium', 'high') DEFAULT 'medium',
        implementation_effort ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        status ENUM('new', 'reviewed', 'implemented', 'dismissed') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_suggestion_type (suggestion_type),
        INDEX idx_priority (priority),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sessionsTable);
    $pdo->exec($pageViewsTable);
    $pdo->exec($interactionsTable);
    $pdo->exec($itemAnalyticsTable);
    $pdo->exec($conversionFunnelsTable);
    $pdo->exec($optimizationTable);
}

function trackVisit($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $sessionId = session_id();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $landingPage = $input['landing_page'] ?? '';

    // Parse user agent for device/browser info
    $deviceInfo = parseUserAgent($userAgent);

    // Check if session already exists
    $stmt = $pdo->prepare("SELECT id FROM user_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    if (!$stmt->fetch()) {
        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (session_id, ip_address, user_agent, referrer, landing_page, device_type, browser, operating_system) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $ipAddress,
            $userAgent,
            $referrer,
            $landingPage,
            $deviceInfo['device_type'],
            $deviceInfo['browser'],
            $deviceInfo['os']
        ]);
    }

    echo json_encode(['success' => true, 'session_id' => $sessionId]);
}

function trackPageView($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $sessionId = session_id();
    $pageUrl = $input['page_url'] ?? '';
    $pageTitle = $input['page_title'] ?? '';
    $pageType = $input['page_type'] ?? '';
    $itemSku = $input['item_sku'] ?? $input['product_sku'] ?? null; // Support both for backward compatibility
    $timeOnPage = $input['time_on_page'] ?? 0;
    $scrollDepth = $input['scroll_depth'] ?? 0;

    // Insert page view
    $stmt = $pdo->prepare("
        INSERT INTO page_views (session_id, page_url, page_title, page_type, item_sku, time_on_page, scroll_depth) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sessionId, $pageUrl, $pageTitle, $pageType, $itemSku, $timeOnPage, $scrollDepth]);

    // Update session stats
    $stmt = $pdo->prepare("
        UPDATE user_sessions 
        SET total_page_views = total_page_views + 1, 
            bounce = (total_page_views <= 1),
            last_activity = CURRENT_TIMESTAMP
        WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);

    // Track conversion funnel
    if ($pageType === 'item' || $pageType === 'product' || $pageType === 'shop') {
        trackFunnelStep($pdo, $sessionId, 'item_view', $pageUrl, $itemSku);
    }

    echo json_encode(['success' => true]);
}

function trackInteraction($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $sessionId = session_id();
    $pageUrl = $input['page_url'] ?? '';
    $interactionType = $input['interaction_type'] ?? '';
    $elementType = $input['element_type'] ?? '';
    $elementId = $input['element_id'] ?? '';
    $elementText = $input['element_text'] ?? '';
    $itemSku = $input['item_sku'] ?? $input['product_sku'] ?? null; // Support both for backward compatibility
    $interactionData = json_encode($input['interaction_data'] ?? []);

    // Insert interaction
    $stmt = $pdo->prepare("
        INSERT INTO user_interactions (session_id, page_url, interaction_type, element_type, element_id, element_text, item_sku, interaction_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$sessionId, $pageUrl, $interactionType, $elementType, $elementId, $elementText, $itemSku, $interactionData]);

    // Track specific funnel steps
    if ($interactionType === 'cart_add') {
        trackFunnelStep($pdo, $sessionId, 'cart_add', $pageUrl, $itemSku);
    } elseif ($interactionType === 'checkout_start') {
        trackFunnelStep($pdo, $sessionId, 'checkout_start', $pageUrl, $itemSku);
    } elseif ($interactionType === 'checkout_complete') {
        trackFunnelStep($pdo, $sessionId, 'checkout_complete', $pageUrl, $itemSku);

        // Mark session as converted
        $stmt = $pdo->prepare("UPDATE user_sessions SET converted = TRUE WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }

    echo json_encode(['success' => true]);
}

function trackFunnelStep($pdo, $sessionId, $step, $pageUrl, $itemSku)
{
    $stmt = $pdo->prepare("
        INSERT INTO conversion_funnels (session_id, funnel_step, page_url, item_sku) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$sessionId, $step, $pageUrl, $itemSku]);
}

function trackItemView($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $itemSku = $input['item_sku'] ?? $input['product_sku'] ?? ''; // Support both for backward compatibility
    $timeOnPage = $input['time_on_page'] ?? 0;

    if (!$itemSku) {
        echo json_encode(['success' => false, 'error' => 'Item SKU required']);
        return;
    }

    // Update or insert item analytics
    $stmt = $pdo->prepare("
        INSERT INTO item_analytics (item_sku, views_count, unique_views_count, avg_time_on_page) 
        VALUES (?, 1, 1, ?) 
        ON DUPLICATE KEY UPDATE 
            views_count = views_count + 1,
            avg_time_on_page = (avg_time_on_page * (views_count - 1) + ?) / views_count
    ");
    $stmt->execute([$itemSku, $timeOnPage, $timeOnPage]);

    echo json_encode(['success' => true]);
}

function trackCartAction($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $itemSku = $input['item_sku'] ?? $input['product_sku'] ?? ''; // Support both for backward compatibility
    $action = $input['action'] ?? ''; // 'add' or 'remove'

    if (!$itemSku || !$action) {
        echo json_encode(['success' => false, 'error' => 'Item SKU and action required']);
        return;
    }

    $field = $action === 'add' ? 'cart_adds_count' : 'cart_removes_count';

    $stmt = $pdo->prepare("
        INSERT INTO item_analytics (item_sku, {$field}) 
        VALUES (?, 1) 
        ON DUPLICATE KEY UPDATE {$field} = {$field} + 1
    ");
    $stmt->execute([$itemSku]);

    echo json_encode(['success' => true]);
}

function getAnalyticsReport($pdo)
{
    $timeframe = $_GET['timeframe'] ?? '7d';
    $dateFilter = getDateFilter($timeframe);

    // Overall stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT session_id) as total_sessions,
            COUNT(DISTINCT CASE WHEN converted = 1 THEN session_id END) as conversions,
            AVG(session_duration) as avg_session_duration,
            AVG(total_page_views) as avg_pages_per_session,
            (COUNT(DISTINCT CASE WHEN bounce = 1 THEN session_id END) / COUNT(DISTINCT session_id)) * 100 as bounce_rate
        FROM user_sessions 
        WHERE started_at >= ?
    ");
    $stmt->execute([$dateFilter]);
    $overallStats = $stmt->fetch();

    // Top pages
    $stmt = $pdo->prepare("
        SELECT page_url, page_type, COUNT(*) as views, AVG(time_on_page) as avg_time
        FROM page_views 
        WHERE viewed_at >= ?
        GROUP BY page_url, page_type
        ORDER BY views DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFilter]);
    $topPages = $stmt->fetchAll();

    // Item performance
    $stmt = $pdo->prepare("
        SELECT 
            p.item_sku,
            i.name as item_name,
            p.views_count,
            p.cart_adds_count,
            p.purchases_count,
            p.conversion_rate,
            p.revenue_generated
        FROM item_analytics p
        LEFT JOIN items i ON p.item_sku = i.sku
        ORDER BY p.views_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $itemPerformance = $stmt->fetchAll();

    // Conversion funnel
    $stmt = $pdo->prepare("
        SELECT 
            funnel_step,
            COUNT(DISTINCT session_id) as sessions_count
        FROM conversion_funnels 
        WHERE step_timestamp >= ?
        GROUP BY funnel_step
        ORDER BY FIELD(funnel_step, 'landing', 'item_view', 'cart_add', 'checkout_start', 'checkout_complete')
    ");
    $stmt->execute([$dateFilter]);
    $conversionFunnel = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'overall_stats' => $overallStats,
            'top_pages' => $topPages,
            'item_performance' => $itemPerformance,
            'conversion_funnel' => $conversionFunnel,
            'timeframe' => $timeframe
        ]
    ]);
}

function getOptimizationSuggestions($pdo)
{
    // Generate AI-powered optimization suggestions based on data
    $suggestions = [];

    // Analyze bounce rate
    $stmt = $pdo->prepare("
        SELECT 
            page_url,
            page_type,
            (COUNT(CASE WHEN bounce = 1 THEN 1 END) / COUNT(*)) * 100 as bounce_rate,
            COUNT(*) as total_sessions
        FROM user_sessions s
        JOIN page_views p ON s.session_id = p.session_id
        WHERE s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY page_url, page_type
        HAVING total_sessions >= 10 AND bounce_rate > 70
        ORDER BY bounce_rate DESC
        LIMIT 5
    ");
    $stmt->execute();
    $highBouncePagesPages = $stmt->fetchAll();

    foreach ($highBouncePagesPages as $page) {
        $suggestions[] = [
            'type' => 'conversion',
            'priority' => 'high',
            'title' => 'High Bounce Rate on ' . $page['page_url'],
            'description' => "This page has a {$page['bounce_rate']}% bounce rate, which is above the 70% threshold.",
            'suggested_action' => 'Consider improving page loading speed, adding more engaging content, or clearer call-to-action buttons.',
            'confidence_score' => 0.85,
            'potential_impact' => 'high'
        ];
    }

    // Analyze cart abandonment
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cart_adds, 
               COUNT(CASE WHEN cf2.funnel_step = 'checkout_complete' THEN 1 END) as completions
        FROM conversion_funnels cf1
        LEFT JOIN conversion_funnels cf2 ON cf1.session_id = cf2.session_id AND cf2.funnel_step = 'checkout_complete'
        WHERE cf1.funnel_step = 'cart_add' 
        AND cf1.step_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $cartData = $stmt->fetch();

    if ($cartData['cart_adds'] > 0) {
        $abandonment_rate = (($cartData['cart_adds'] - $cartData['completions']) / $cartData['cart_adds']) * 100;

        if ($abandonment_rate > 70) {
            $suggestions[] = [
                'type' => 'conversion',
                'priority' => 'critical',
                'title' => 'High Cart Abandonment Rate',
                'description' => "Cart abandonment rate is {$abandonment_rate}%, indicating potential checkout issues.",
                'suggested_action' => 'Simplify checkout process, add trust badges, offer guest checkout, or implement cart recovery emails.',
                'confidence_score' => 0.90,
                'potential_impact' => 'high'
            ];
        }
    }

    // Analyze slow-loading pages
    $stmt = $pdo->prepare("
        SELECT page_url, AVG(time_on_page) as avg_time, COUNT(*) as views
        FROM page_views 
        WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY page_url
        HAVING views >= 10 AND avg_time < 30
        ORDER BY avg_time ASC
        LIMIT 3
    ");
    $stmt->execute();
    $quickExitPages = $stmt->fetchAll();

    foreach ($quickExitPages as $page) {
        $suggestions[] = [
            'type' => 'ui_ux',
            'priority' => 'medium',
            'title' => 'Quick Exit on ' . $page['page_url'],
            'description' => "Users spend only {$page['avg_time']} seconds on this page on average.",
            'suggested_action' => 'Review page content relevance, improve loading speed, or add more engaging elements.',
            'confidence_score' => 0.75,
            'potential_impact' => 'medium'
        ];
    }

    // Analyze item performance
    $stmt = $pdo->prepare("
        SELECT item_sku, views_count, cart_adds_count, 
               (cart_adds_count / views_count) * 100 as conversion_rate
        FROM item_analytics 
        WHERE views_count >= 20
        HAVING conversion_rate < 5
        ORDER BY views_count DESC
        LIMIT 3
    ");
    $stmt->execute();
    $poorPerformingItems = $stmt->fetchAll();

    foreach ($poorPerformingItems as $item) {
        $suggestions[] = [
            'type' => 'item',
            'priority' => 'medium',
            'title' => 'Low Conversion Item: ' . $item['item_sku'],
            'description' => "Item has {$item['views_count']} views but only {$item['conversion_rate']}% conversion rate.",
            'suggested_action' => 'Review item images, improve description, adjust pricing, or enhance marketing copy.',
            'confidence_score' => 0.80,
            'potential_impact' => 'medium'
        ];
    }

    // Save suggestions to database
    foreach ($suggestions as $suggestion) {
        $stmt = $pdo->prepare("
            INSERT INTO optimization_suggestions 
            (suggestion_type, priority, title, description, suggested_action, confidence_score, potential_impact) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $suggestion['type'],
            $suggestion['priority'],
            $suggestion['title'],
            $suggestion['description'],
            $suggestion['suggested_action'],
            $suggestion['confidence_score'],
            $suggestion['potential_impact']
        ]);
    }

    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
}

function parseUserAgent($userAgent)
{
    $deviceType = 'desktop';
    $browser = 'Unknown';
    $os = 'Unknown';

    // Device detection
    if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
        $deviceType = preg_match('/iPad/', $userAgent) ? 'tablet' : 'mobile';
    }

    // Browser detection
    if (preg_match('/Chrome/', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox/', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/', $userAgent)) {
        $browser = 'Edge';
    }

    // OS detection
    if (preg_match('/Windows/', $userAgent)) {
        $os = 'Windows';
    } elseif (preg_match('/Macintosh/', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/', $userAgent)) {
        $os = 'Linux';
    } elseif (preg_match('/Android/', $userAgent)) {
        $os = 'Android';
    } elseif (preg_match('/iOS/', $userAgent)) {
        $os = 'iOS';
    }

    return [
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os
    ];
}

function getDateFilter($timeframe)
{
    switch ($timeframe) {
        case '1d': return date('Y-m-d H:i:s', strtotime('-1 day'));
        case '7d': return date('Y-m-d H:i:s', strtotime('-7 days'));
        case '30d': return date('Y-m-d H:i:s', strtotime('-30 days'));
        case '90d': return date('Y-m-d H:i:s', strtotime('-90 days'));
        default: return date('Y-m-d H:i:s', strtotime('-7 days'));
    }
}
?> 