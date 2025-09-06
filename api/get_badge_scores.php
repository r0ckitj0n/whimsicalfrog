<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'SKU parameter required']);
    exit;
}

try {
    $db = Database::getInstance();

    // Get item details
    $item = getItemDetails($sku, $db);
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }

    // Get badge content from database
    $badgeContent = getBadgeContent($db);

    // Calculate badge scores
    $scores = calculateBadgeScores($item, $db);

    // Get top badges based on scores
    $topBadges = getTopBadges($scores, $badgeContent);

    echo json_encode([
        'success' => true,
        'sku' => $sku,
        'scores' => $scores,
        'badges' => $topBadges
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getItemDetails($sku, $db)
{
    $stmt = $db->prepare("SELECT * FROM items WHERE sku = ?");
    $stmt->execute([$sku]);
    return $stmt->fetch();
}

function getBadgeContent($db)
{
    // Create table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS badge_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        badge_type VARCHAR(20) NOT NULL,
        content VARCHAR(100) NOT NULL,
        weight DECIMAL(3,2) DEFAULT 1.00,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $db->exec($createTableQuery);

    // Check if table is empty and populate with default content
    $countResult = $db->query("SELECT COUNT(*) as count FROM badge_content");
    $count = $countResult->fetch()['count'];

    if ($count == 0) {
        // Insert default content ONLY if table is empty
        $defaultContent = [
            ['sale', 'SALE', 1.0],
            ['sale', 'HOT DEAL', 1.2],
            ['sale', 'SPECIAL PRICE', 1.1],
            ['sale', 'SAVE NOW', 1.0],
            ['sale', 'PROMO', 0.9],
            ['sale', 'DISCOUNT', 1.0],
            ['stock', 'LIMITED STOCK', 1.0],
            ['stock', 'ALMOST GONE', 1.3],
            ['stock', 'LAST CHANCE', 1.2],
            ['stock', 'HURRY UP', 1.1],
            ['stock', 'FEW LEFT', 1.0],
            ['stock', 'LOW STOCK', 0.9],
            ['quality', 'PREMIUM', 1.0],
            ['quality', 'QUALITY', 0.9],
            ['quality', 'ARTISAN', 1.2],
            ['quality', 'HANDMADE', 1.1],
            ['quality', 'EXCLUSIVE', 1.3],
            ['quality', 'DELUXE', 1.1],
            ['trending', 'TRENDING', 1.0],
            ['trending', 'HOT ITEM', 1.1],
            ['trending', 'POPULAR', 0.9],
            ['trending', 'FEATURED', 1.0],
            ['trending', 'NEW ARRIVAL', 1.2],
            ['trending', 'VIRAL', 1.3],
            ['bestseller', 'BESTSELLER', 1.0],
            ['bestseller', 'TOP SELLER', 1.1],
            ['bestseller', 'CUSTOMER FAVORITE', 1.2],
            ['bestseller', 'STAFF PICK', 1.0],
            ['bestseller', 'AWARD WINNER', 1.3],
            ['bestseller', '#1 CHOICE', 1.2]
        ];

        $stmt = $db->prepare("INSERT INTO badge_content (badge_type, content, weight) VALUES (?, ?, ?)");
        foreach ($defaultContent as $content) {
            $stmt->execute($content);
        }
    }

    // ALWAYS get content from database (never use hardcoded values)
    $result = $db->query("SELECT * FROM badge_content WHERE active = TRUE ORDER BY badge_type, weight DESC");
    $content = [];
    while ($row = $result->fetch()) {
        $content[$row['badge_type']][] = $row;
    }

    return $content;
}

function calculateBadgeScores($item, $db)
{
    $scores = [
        'sale' => 0,
        'stock' => 0,
        'quality' => 0,
        'trending' => 0,
        'bestseller' => 0
    ];

    $stockLevel = intval($item['stockLevel'] ?? 0);
    $retailPrice = floatval($item['retailPrice'] ?? 0);
    $costPrice = floatval($item['costPrice'] ?? 0);

    // SALE BADGE SCORING
    // Check if item is on sale
    try {
        $saleQuery = "SELECT * FROM sales WHERE item_sku = ? AND is_active = 1 AND start_date <= NOW() AND end_date >= NOW()";
        $saleResult = $db->prepare($saleQuery);
        $saleResult->execute([$item['sku']]);
        $activeSale = $saleResult->fetch();

        if ($activeSale) {
            $discountPercent = floatval($activeSale['discount_percent'] ?? 0);
            $scores['sale'] = min(100, 20 + ($discountPercent * 2)); // 20-100 based on discount
        }
    } catch (Exception $e) {
        // Sales table might not exist, use price-based scoring
        $margin = $retailPrice > 0 ? (($retailPrice - $costPrice) / $retailPrice) * 100 : 0;
        if ($margin < 30) { // Low margin suggests sale pricing
            $scores['sale'] = 30 + (30 - $margin);
        }
    }

    // STOCK BADGE SCORING
    if ($stockLevel <= 0) {
        $scores['stock'] = 0; // No stock = no badge
    } elseif ($stockLevel <= 2) {
        $scores['stock'] = 95; // Very low stock
    } elseif ($stockLevel <= 5) {
        $scores['stock'] = 80; // Low stock
    } elseif ($stockLevel <= 10) {
        $scores['stock'] = 60; // Medium-low stock
    } elseif ($stockLevel <= 20) {
        $scores['stock'] = 30; // Moderate stock
    } else {
        $scores['stock'] = 10; // High stock
    }

    // QUALITY BADGE SCORING
    $qualityFactors = 0;

    // Price-based quality indicator
    if ($retailPrice > 100) {
        $qualityFactors += 20;
    } elseif ($retailPrice > 50) {
        $qualityFactors += 10;
    } elseif ($retailPrice > 25) {
        $qualityFactors += 5;
    }

    // Description quality indicators
    $description = strtolower($item['description'] ?? '');
    $qualityKeywords = ['premium', 'quality', 'artisan', 'handmade', 'exclusive', 'deluxe', 'luxury', 'high-end', 'professional', 'superior'];
    foreach ($qualityKeywords as $keyword) {
        if (strpos($description, $keyword) !== false) {
            $qualityFactors += 15;
        }
    }

    // Category-based quality (some categories inherently suggest quality)
    $category = strtolower($item['category'] ?? '');
    $qualityCategories = ['premium', 'deluxe', 'professional', 'luxury'];
    foreach ($qualityCategories as $qualCat) {
        if (strpos($category, $qualCat) !== false) {
            $qualityFactors += 20;
        }
    }

    $scores['quality'] = min(100, $qualityFactors);

    // TRENDING BADGE SCORING
    // Check recent sales activity
    try {
        $trendingQuery = "SELECT COUNT(*) as recent_sales FROM order_items oi 
                         JOIN orders o ON oi.order_id = o.id 
                         WHERE oi.item_sku = ? AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $trendingResult = $db->prepare($trendingQuery);
        $trendingResult->execute([$item['sku']]);
        $recentSales = $trendingResult->fetch()['recent_sales'] ?? 0;

        if ($recentSales >= 10) {
            $scores['trending'] = 100;
        } elseif ($recentSales >= 5) {
            $scores['trending'] = 80;
        } elseif ($recentSales >= 3) {
            $scores['trending'] = 60;
        } elseif ($recentSales >= 1) {
            $scores['trending'] = 40;
        } else {
            $scores['trending'] = 20;
        }
    } catch (Exception $e) {
        // Orders table might not exist, use item age as proxy
        $scores['trending'] = 25; // Default trending score
    }

    // BESTSELLER BADGE SCORING
    // Check total sales volume
    try {
        $bestsellerQuery = "SELECT SUM(oi.quantity) as total_sold FROM order_items oi 
                           JOIN orders o ON oi.order_id = o.id 
                           WHERE oi.item_sku = ? AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $bestsellerResult = $db->prepare($bestsellerQuery);
        $bestsellerResult->execute([$item['sku']]);
        $totalSold = $bestsellerResult->fetch()['total_sold'] ?? 0;

        if ($totalSold >= 50) {
            $scores['bestseller'] = 100;
        } elseif ($totalSold >= 20) {
            $scores['bestseller'] = 80;
        } elseif ($totalSold >= 10) {
            $scores['bestseller'] = 60;
        } elseif ($totalSold >= 5) {
            $scores['bestseller'] = 40;
        } else {
            $scores['bestseller'] = 20;
        }
    } catch (Exception $e) {
        // Orders table might not exist, use stock turnover as proxy
        $scores['bestseller'] = 30; // Default bestseller score
    }

    return $scores;
}

function getTopBadges($scores, $badgeContent)
{
    // Set minimum score threshold
    $threshold = 40;

    // Get badges that meet threshold
    $qualifiedBadges = [];
    foreach ($scores as $type => $score) {
        if ($score >= $threshold) {
            $qualifiedBadges[$type] = $score;
        }
    }

    // Sort by score descending
    arsort($qualifiedBadges);

    // Get top 4 badges (for 4 corners)
    $topBadges = [];
    $positions = [
        'top-left' => ['top' => '0.5rem', 'left' => '0.5rem'],
        'top-right' => ['top' => '0.5rem', 'right' => '0.5rem'],
        'bottom-left' => ['bottom' => '0.5rem', 'left' => '0.5rem'],
        'bottom-right' => ['bottom' => '0.5rem', 'right' => '0.5rem']
    ];

    $positionKeys = array_keys($positions);
    $index = 0;

    foreach ($qualifiedBadges as $type => $score) {
        if ($index >= 4) {
            break;
        }

        // Get random content for this badge type
        $content = $badgeContent[$type] ?? [];
        if (!empty($content)) {
            // Weight-based selection
            $totalWeight = array_sum(array_column($content, 'weight'));
            $random = mt_rand(1, $totalWeight * 100) / 100;

            $selectedContent = $content[0]; // fallback
            $weightSum = 0;
            foreach ($content as $item) {
                $weightSum += $item['weight'];
                if ($random <= $weightSum) {
                    $selectedContent = $item;
                    break;
                }
            }

            $topBadges[] = [
                'type' => $type,
                'content' => $selectedContent['content'],
                'score' => $score,
                'position' => $positions[$positionKeys[$index]],
                'position_name' => $positionKeys[$index]
            ];

            $index++;
        }
    }

    // If we have fewer than 2 badges, add generic ones
    while (count($topBadges) < 2) {
        $genericTypes = ['sale', 'quality'];
        $type = $genericTypes[count($topBadges) % 2];
        $content = $badgeContent[$type] ?? [];

        if (!empty($content)) {
            $topBadges[] = [
                'type' => $type,
                'content' => $content[0]['content'],
                'score' => 25, // Generic score
                'position' => $positions[$positionKeys[count($topBadges)]],
                'position_name' => $positionKeys[count($topBadges)]
            ];
        }
    }

    return $topBadges;
}
?> 