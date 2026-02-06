<?php
/**
 * Badge Score Management Logic
 */

function getBadgeContent()
{
    $db = Database::getInstance();
    $db->exec("CREATE TABLE IF NOT EXISTS badge_contents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        badge_type VARCHAR(20) NOT NULL,
        content VARCHAR(100) NOT NULL,
        weight DECIMAL(3,2) DEFAULT 1.00,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $count = Database::queryOne("SELECT COUNT(*) as count FROM badge_contents")['count'];
    if ($count == 0) {
        $defaults = [
            ['sale', 'SALE', 1.0], ['sale', 'HOT DEAL', 1.2], ['stock', 'LIMITED STOCK', 1.0],
            ['quality', 'PREMIUM', 1.0], ['trending', 'TRENDING', 1.0], ['bestseller', 'BESTSELLER', 1.0]
        ];
        foreach ($defaults as $d) {
            Database::execute("INSERT INTO badge_contents (badge_type, content, weight) VALUES (?, ?, ?)", $d);
        }
    }

    $results = Database::queryAll("SELECT * FROM badge_contents WHERE active = TRUE ORDER BY badge_type, weight DESC");
    $content = [];
    foreach ($results as $row) {
        $content[$row['badge_type']][] = $row;
    }
    return $content;
}

function calculateBadgeScores($item)
{
    $scores = ['sale' => 0, 'stock' => 0, 'quality' => 0, 'trending' => 0, 'bestseller' => 0];
    $sku = $item['sku'];
    $stock = (int)($item['stock_quantity'] ?? 0);

    // Sale score
    $sale = Database::queryOne("SELECT * FROM sales WHERE item_sku = ? AND is_active = 1 AND start_date <= NOW() AND end_date >= NOW()", [$sku]);
    if ($sale) $scores['sale'] = min(100, 20 + ((float)$sale['discount_percent'] * 2));

    // Stock score
    if ($stock <= 0) $scores['stock'] = 0;
    elseif ($stock <= 2) $scores['stock'] = 95;
    elseif ($stock <= 5) $scores['stock'] = 80;
    else $scores['stock'] = 10;

    // Quality score (simplified logic from original)
    $desc = strtolower($item['description'] ?? '');
    if (strpos($desc, 'premium') !== false || strpos($desc, 'artisan') !== false) $scores['quality'] = 80;

    return $scores;
}

function getTopBadges($scores, $badgeContent)
{
    arsort($scores);
    $top = [];
    $positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
    $i = 0;
    foreach ($scores as $type => $score) {
        if ($score < 40 || $i >= 4) continue;
        if (!empty($badgeContent[$type])) {
            $top[] = [
                'type' => $type,
                'content' => $badgeContent[$type][0]['content'],
                'score' => $score,
                'position_name' => $positions[$i++]
            ];
        }
    }
    return $top;
}
