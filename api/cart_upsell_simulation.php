<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/upsell_rules_helper.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/business_settings_helper.php';

try {
    Database::getInstance();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

// Ensure table exists (lightweight, safe at runtime)
try {
    $pdo = Database::getInstance();
    $sql = "CREATE TABLE IF NOT EXISTS upsell_simulations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        profile_json LONGTEXT NULL,
        cart_skus_json LONGTEXT NULL,
        criteria_json LONGTEXT NULL,
        upsells_json LONGTEXT NULL,
        rationale_json LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
} catch (Throwable $e) {
    // Non-fatal; continue without hard failing
}

Response::validateMethod(['POST']);
$input = Response::getJsonInput();
if (!is_array($input)) { $input = []; }

$seedProfile = isset($input['profile']) && is_array($input['profile']) ? $input['profile'] : [];
$limit = isset($input['limit']) ? max(1, (int)$input['limit']) : 4;

try {
    $data = wf_generate_cart_upsell_rules();
    $products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

    // Derive categories from products
    $categories = [];
    foreach ($products as $sku => $meta) {
        $cat = trim((string)($meta['category'] ?? ''));
        if ($cat !== '') { $categories[$cat] = ($categories[$cat] ?? 0) + 1; }
    }
    $categoryList = array_keys($categories);

    // Build a fictitious shopper profile (randomized but reproducible if seed provided)
    $rand = function(array $arr, $fallback = null) {
        if (!$arr) return $fallback;
        return $arr[random_int(0, count($arr) - 1)];
    };

    $budgets = ['low','mid','high'];
    $intents = [
        'gift','personal','replacement','upgrade','diy-project','home-decor','holiday','birthday',
        'anniversary','wedding','teacher-gift','office-decor','event-supplies','workshop-class'
    ];
    $devices = ['mobile','desktop'];

    $profile = [
        'preferredCategory' => $seedProfile['preferredCategory'] ?? $rand($categoryList, ''),
        'budget' => $seedProfile['budget'] ?? $rand($budgets, 'mid'),
        'intent' => $seedProfile['intent'] ?? $rand($intents, 'personal'),
        'device' => $seedProfile['device'] ?? $rand($devices, 'mobile'),
        'region' => $seedProfile['region'] ?? 'US',
    ];

    // Pick 1-2 cart seed SKUs from the preferred category using leaders/secondaries if available
    $cartSkus = [];
    $preferred = (string)$profile['preferredCategory'];
    $leaders = isset($metadata['category_leaders']) && is_array($metadata['category_leaders']) ? $metadata['category_leaders'] : [];
    $seconds = isset($metadata['category_secondaries']) && is_array($metadata['category_secondaries']) ? $metadata['category_secondaries'] : [];
    $defaults = isset($data['map']['_default']) && is_array($data['map']['_default']) ? $data['map']['_default'] : [];

    $pushSku = function($sku) use (&$cartSkus) {
        $s = strtoupper(trim((string)$sku));
        if ($s !== '' && !in_array($s, $cartSkus, true)) $cartSkus[] = $s;
    };

    if ($preferred !== '') {
        if (isset($leaders[$preferred])) $pushSku($leaders[$preferred]);
        if (isset($seconds[$preferred])) $pushSku($seconds[$preferred]);
    }
    if (!$cartSkus && $defaults) { $pushSku($defaults[0] ?? ''); }

    // Resolve upsells based on seed cart
    $resolved = wf_resolve_cart_upsells($cartSkus, $limit);
    $recommendations = isset($resolved['upsells']) && is_array($resolved['upsells']) ? $resolved['upsells'] : [];
    // Intent-aware reordering: stronger heuristics with cart context, budget, and popularity
    $intent = (string)($profile['intent'] ?? '');
    $intentReasonBySku = [];
    if ($intent !== '' && $recommendations) {
        $intentConfig = [
            'weights' => [
                'popularity_cap' => 3.0,
                'kw_positive' => 2.5,
                'cat_positive' => 3.5,
                'seasonal' => 2.0,
                'same_category' => 2.0,
                'upgrade_price_ratio_threshold' => 1.25,
                'upgrade_price_boost' => 3.0,
                'upgrade_label_boost' => 2.5,
                'replacement_label_boost' => 3.0,
                'gift_set_boost' => 1.0,
                'gift_price_boost' => 1.5,
                'teacher_price_ceiling' => 30.0,
                'teacher_price_boost' => 1.5,
                'budget_proximity_mult' => 2.0,
                'neg_keyword_penalty' => 2.0,
                'intent_badge_threshold' => 2.0,
            ],
            'budget_ranges' => [
                'low' => [8.0, 20.0],
                'mid' => [15.0, 40.0],
                'high' => [35.0, 120.0],
            ],
            'keywords' => [
                'positive' => [
                    'gift' => ['gift','set','bundle','present','pack','box'],
                    'personal' => [],
                    'replacement' => ['refill','replacement','spare','recharge','insert'],
                    'upgrade' => ['upgrade','pro','deluxe','premium','xl','plus','pro+','ultimate'],
                    'diy-project' => ['diy','kit','project','starter','make your own','how to'],
                    'home-decor' => ['decor','wall','frame','sign','plaque','art','canvas'],
                    'holiday' => ['holiday','christmas','xmas','easter','halloween','valentine','mother','father'],
                    'birthday' => ['birthday','party','celebration','cake','bday'],
                    'anniversary' => ['anniversary','love','heart','romance','romantic'],
                    'wedding' => ['wedding','bride','groom','bridal','mr & mrs','mr and mrs'],
                    'teacher-gift' => ['teacher','school','classroom','teach'],
                    'office-decor' => ['office','desk','workspace','cubicle'],
                    'event-supplies' => ['event','party','supplies','decoration','bulk'],
                    'workshop-class' => ['class','workshop','lesson','course','tutorial'],
                ],
                'negative' => [
                    'gift' => ['refill','replacement'],
                    'replacement' => ['gift','decor'],
                    'upgrade' => ['refill'],
                ],
                'categories' => [
                    'gift' => ['gifts','gift sets','bundles'],
                    'replacement' => ['supplies','refills','consumables'],
                    'diy-project' => ['diy','kits','craft kits','projects'],
                    'home-decor' => ['home decor','decor','wall art','signs'],
                    'holiday' => ['holiday','seasonal'],
                    'office-decor' => ['office decor'],
                    'event-supplies' => ['event supplies','party'],
                    'workshop-class' => ['classes','workshops'],
                ],
            ],
            'seasonal_months' => [
                1 => ['valentine'], 2 => ['valentine'], 3 => ['easter'], 4 => ['easter'],
                5 => ['mother'], 6 => ['father'], 9 => ['halloween'], 10 => ['halloween'],
                11 => ['christmas'], 12 => ['christmas'],
            ],
        ];
        $rawOverride = null; $override = null;
        try { if (class_exists('BusinessSettings')) { $rawOverride = BusinessSettings::get('cart_intent_heuristics', null); } } catch (Throwable $e) { $rawOverride = null; }
        if (is_string($rawOverride)) {
            $tmp = json_decode($rawOverride, true);
            if (is_array($tmp)) { $override = $tmp; }
        } elseif (is_array($rawOverride)) {
            $override = $rawOverride;
        }
        if (is_array($override)) {
            $merge = function(array $a, array $b) use (&$merge): array {
                foreach ($b as $k => $v) {
                    if (isset($a[$k]) && is_array($a[$k]) && is_array($v)) {
                        $a[$k] = $merge($a[$k], $v);
                    } else {
                        $a[$k] = $v;
                    }
                }
                return $a;
            };
            $intentConfig = $merge($intentConfig, $override);
        }
        // Derive cart context
        $seedCategories = [];
        $cartPriceSum = 0.0; $cartPriceN = 0;
        foreach ($cartSkus as $sSku) {
            $S = strtoupper((string)$sSku);
            if (isset($products[$S])) {
                $c = (string)($products[$S]['category'] ?? '');
                if ($c !== '') { $seedCategories[strtolower($c)] = ($seedCategories[strtolower($c)] ?? 0) + 1; }
                $p = (float)($products[$S]['price'] ?? 0);
                if ($p > 0) { $cartPriceSum += $p; $cartPriceN++; }
            }
        }
        $cartAvgPrice = $cartPriceN > 0 ? ($cartPriceSum / $cartPriceN) : 0.0;

        // Label map
        $labelFor = function(string $slug): string {
            switch ($slug) {
                case 'gift': return 'Gift';
                case 'personal': return 'Personal use';
                case 'replacement': return 'Replacement';
                case 'upgrade': return 'Upgrade';
                case 'diy-project': return 'DIY Project';
                case 'home-decor': return 'Home Decor';
                case 'holiday': return 'Holiday';
                case 'birthday': return 'Birthday';
                case 'anniversary': return 'Anniversary';
                case 'wedding': return 'Wedding';
                case 'teacher-gift': return 'Teacher Gift';
                case 'office-decor': return 'Office Decor';
                case 'event-supplies': return 'Event Supplies';
                case 'workshop-class': return 'Workshop/Class';
                default: return ucfirst(str_replace(['-','_'],' ', $slug));
            }
        };

        $W = $intentConfig['weights'];
        $kwPos = $intentConfig['keywords']['positive'];
        $kwNeg = $intentConfig['keywords']['negative'];
        $catPos = $intentConfig['keywords']['categories'];

        $br = $intentConfig['budget_ranges'][(string)$profile['budget']] ?? $intentConfig['budget_ranges']['high'];
        [$idealMin, $idealMax] = $br;

        $mon = (int)date('n');
        $seasonMap = $intentConfig['seasonal_months'] ?? [];
        $seasonHints = $seasonMap[$mon] ?? [];

        $slug = $intent;
        $intentKws = array_map('strtolower', $kwPos[$slug] ?? []);
        $intentNeg = array_map('strtolower', $kwNeg[$slug] ?? []);
        $intentCats = array_map('strtolower', $catPos[$slug] ?? []);

        $scored = [];
        foreach ($recommendations as $rec) {
            $sku = strtoupper((string)($rec['sku'] ?? ''));
            $meta = $products[$sku] ?? [];
            $name = strtolower((string)($meta['name'] ?? $rec['name'] ?? ''));
            $catName = strtolower((string)($meta['category'] ?? $rec['category'] ?? ''));
            $price = (float)($meta['price'] ?? $rec['price'] ?? 0);
            $units = (float)($meta['units'] ?? $rec['units'] ?? 0);

            $s = 0.0;
            // Popularity signal
            if ($units > 0) { $s += min((float)$W['popularity_cap'], log(1.0 + $units)); }

            // Positive keyword/category matches
            foreach ($intentKws as $needle) { if ($needle !== '' && strpos($name, $needle) !== false) { $s += (float)$W['kw_positive']; } }
            foreach ($intentCats as $cNeedle) { if ($cNeedle !== '' && strpos($catName, $cNeedle) !== false) { $s += (float)$W['cat_positive']; } }

            // Seasonal boost for Holiday
            if ($slug === 'holiday' && $seasonHints) {
                foreach ($seasonHints as $h) { if (strpos($name, $h) !== false) { $s += (float)$W['seasonal']; } }
            }

            // Replacement and Upgrade: relation to cart
            if (!empty($seedCategories) && $catName !== '') {
                if (isset($seedCategories[$catName])) { $s += (float)$W['same_category']; }
            }
            if ($slug === 'upgrade') {
                if ($cartAvgPrice > 0 && $price >= ($cartAvgPrice * (float)$W['upgrade_price_ratio_threshold'])) { $s += (float)$W['upgrade_price_boost']; }
                if (preg_match('/\b(pro|deluxe|premium|xl|plus|ultimate|pro\+)\b/i', $name)) { $s += (float)$W['upgrade_label_boost']; }
            }
            if ($slug === 'replacement') {
                if (preg_match('/\b(refill|replacement|spare|insert|recharge)\b/i', $name)) { $s += (float)$W['replacement_label_boost']; }
            }

            // Gift-specific price sweet spot
            if ($slug === 'gift') {
                if ($price >= $idealMin && $price <= $idealMax) { $s += (float)$W['gift_price_boost']; }
                if (preg_match('/\b(set|bundle|pack|box)\b/i', $name)) { $s += (float)$W['gift_set_boost']; }
            }
            // Teacher gift: nudge toward lower-mid price
            if ($slug === 'teacher-gift' && $price > 0 && $price <= (float)$W['teacher_price_ceiling']) { $s += (float)$W['teacher_price_boost']; }

            // Budget alignment proximity boost
            if ($price > 0 && $price <= $idealMax) {
                $center = ($idealMin + $idealMax) / 2.0;
                $dist = abs($price - $center);
                $span = max(1.0, $idealMax - $idealMin);
                $proximity = max(0.0, 1.0 - ($dist / $span)); // 0..1
                $s += ((float)$W['budget_proximity_mult']) * $proximity;
            }

            // Negative keywords demotion
            foreach ($intentNeg as $bad) { if ($bad !== '' && strpos($name, $bad) !== false) { $s -= (float)$W['neg_keyword_penalty']; } }

            if ($s >= (float)$W['intent_badge_threshold']) { $intentReasonBySku[$sku] = 'Matches shopping intent: ' . $labelFor($slug); }
            $scored[] = [$s, $rec];
        }
        usort($scored, static function($a, $b){ return $b[0] <=> $a[0]; });
        $recommendations = array_map(static function($pair){ return $pair[1]; }, $scored);
        if (count($recommendations) > $limit) { $recommendations = array_slice($recommendations, 0, $limit); }
    }

    // Compute rationale per recommendation
    $siteTop = isset($metadata['site_top']) ? (string)$metadata['site_top'] : '';
    $siteSecond = isset($metadata['site_second']) ? (string)$metadata['site_second'] : '';

    $budgetMax = function($budget) {
        switch ($budget) {
            case 'low': return 20.0;
            case 'mid': return 40.0;
            case 'high': default: return 9999.0;
        }
    };
    $maxPrice = $budgetMax($profile['budget']);

    $rationales = [];
    foreach ($recommendations as $rec) {
        $sku = strtoupper((string)($rec['sku'] ?? ''));
        $meta = $products[$sku] ?? [];
        $reasons = [];
        $cat = (string)($meta['category'] ?? '');
        if ($sku === strtoupper($siteTop)) $reasons[] = 'Site top seller';
        if ($sku === strtoupper($siteSecond)) $reasons[] = 'Site second-best seller';
        if ($preferred !== '' && $cat === $preferred) $reasons[] = 'Matches shopper\'s preferred category';
        if (isset($leaders[$preferred]) && strtoupper((string)$leaders[$preferred]) === $sku) $reasons[] = 'Category leader';
        if (isset($seconds[$preferred]) && strtoupper((string)$seconds[$preferred]) === $sku) $reasons[] = 'Strong performer in category';
        $price = (float)($meta['price'] ?? $rec['price'] ?? 0);
        if ($price <= $maxPrice) $reasons[] = 'Fits shopper budget';
        if (!empty($intentReasonBySku[$sku])) { $reasons[] = $intentReasonBySku[$sku]; }
        if (empty($reasons)) $reasons[] = 'High-performing item in catalog';
        $rationales[$sku] = $reasons;
    }

    // Persist to DB
    $createdBy = 0;
    try {
        $user = class_exists('AuthHelper') ? (AuthHelper::getCurrentUser() ?? []) : [];
        $createdBy = (int)($user['id'] ?? $user['userId'] ?? 0);
    } catch (Throwable $e) { $createdBy = 0; }

    $payload = [
        'profile' => $profile,
        'cart_skus' => $cartSkus,
        'recommendations' => $recommendations,
        'rationales' => $rationales,
        'criteria' => [
            'source' => 'sales_performance + cart_context',
            'limit' => $limit,
            'metadata_used' => [
                'site_top' => $siteTop,
                'site_second' => $siteSecond,
                'category' => $preferred,
            ],
        ],
    ];

    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO upsell_simulations (created_by, profile_json, cart_skus_json, criteria_json, upsells_json, rationale_json) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $createdBy ?: null,
            json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($cartSkus, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($payload['criteria'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($recommendations, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            json_encode($rationales, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $id = (int)Database::lastInsertId();
        $payload['id'] = $id;
    } catch (Throwable $e) {
        // Continue without failing the response; still return payload
    }

    Response::success($payload);
} catch (Throwable $e) {
    Response::serverError('Failed to simulate shopper upsells', $e->getMessage());
}
