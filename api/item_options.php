<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Auth with local dev bypass similar to other endpoints
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$devBypassHeader = isset($_SERVER['HTTP_X_WF_DEV_ADMIN']) && $_SERVER['HTTP_X_WF_DEV_ADMIN'] === '1';
$devBypassQuery = isset($_GET['wf_dev_admin']) && $_GET['wf_dev_admin'] === '1';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isLocalHost = is_string($hostHeader) && (strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false);
if (!$isAdmin && ($isLocalHost || $devBypassHeader || $devBypassQuery || strpos($referer, '/admin/') !== false)) {
    $isAdmin = true;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
// If action not provided via GET/POST (e.g., JSON body), attempt to parse from JSON
if ($action === '') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $parsed = json_decode($raw, true);
            if (is_array($parsed) && isset($parsed['action'])) {
                $action = (string)$parsed['action'];
            }
        }
    }
}

function ensure_settings_table() {
    Database::execute("CREATE TABLE IF NOT EXISTS item_option_settings (
        item_sku VARCHAR(64) PRIMARY KEY,
        cascade_order JSON NULL,
        enabled_dimensions JSON NULL,
        grouping_rules JSON NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function default_settings() {
    return [
        'cascade_order' => ['gender','size','color'],
        'enabled_dimensions' => ['gender','size','color'],
        'grouping_rules' => new stdClass(),
    ];
}

try {
    Database::getInstance();

    switch ($action) {
        case 'get_settings': {
            $sku = $_GET['item_sku'] ?? '';
            if (!$sku) throw new Exception('item_sku required');
            ensure_settings_table();
            $row = Database::queryOne("SELECT cascade_order, enabled_dimensions, grouping_rules FROM item_option_settings WHERE item_sku = ?", [$sku]);
            $defaults = default_settings();
            if (!$row) {
                echo json_encode(['success' => true, 'settings' => $defaults]);
                break;
            }
            $settings = [
                'cascade_order' => $row['cascade_order'] ? json_decode($row['cascade_order'], true) : $defaults['cascade_order'],
                'enabled_dimensions' => $row['enabled_dimensions'] ? json_decode($row['enabled_dimensions'], true) : $defaults['enabled_dimensions'],
                'grouping_rules' => $row['grouping_rules'] ? json_decode($row['grouping_rules'], true) : $defaults['grouping_rules'],
            ];
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
        }
        case 'update_settings': {
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $sku = $input['item_sku'] ?? '';
            $cascade = $input['cascade_order'] ?? null;
            $enabled = $input['enabled_dimensions'] ?? null;
            $grouping = $input['grouping_rules'] ?? null;
            if (!$sku) throw new Exception('item_sku required');
            ensure_settings_table();
            $exists = Database::queryOne("SELECT item_sku FROM item_option_settings WHERE item_sku = ?", [$sku]);
            if ($exists) {
                Database::execute(
                    "UPDATE item_option_settings SET cascade_order = ?, enabled_dimensions = ?, grouping_rules = ?, updated_at = NOW() WHERE item_sku = ?",
                    [json_encode($cascade), json_encode($enabled), json_encode($grouping), $sku]
                );
            } else {
                Database::execute(
                    "INSERT INTO item_option_settings (item_sku, cascade_order, enabled_dimensions, grouping_rules) VALUES (?, ?, ?, ?)",
                    [$sku, json_encode($cascade), json_encode($enabled), json_encode($grouping)]
                );
            }
            echo json_encode(['success' => true]);
            break;
        }
        case 'get_aggregates': {
            $sku = $_GET['item_sku'] ?? '';
            if (!$sku) throw new Exception('item_sku required');
            $gender = trim((string)($_GET['gender'] ?? ''));
            $sizeCode = trim((string)($_GET['size_code'] ?? ''));
            $colorId = isset($_GET['color_id']) && $_GET['color_id'] !== '' ? (int)$_GET['color_id'] : null;

            // Base where
            $where = ['item_sku = ?', 'is_active = 1'];
            $params = [$sku];

            // Detect if SKU has colors
            $hasColorsRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM item_colors WHERE item_sku = ? AND is_active = 1", [$sku]);
            $hasColors = ($hasColorsRow && (int)$hasColorsRow['cnt'] > 0);

            // Apply filters for certain aggregates
            $filterSql = '';
            $filterParams = [];
            // When a gender filter is provided, match exact gender only (no unisex) to avoid double-counting.
            // Also accept common synonyms case-insensitively (e.g., men/male, women/female)
            if ($gender !== '') {
                $g = strtolower($gender);
                $alts = [$g];
                if ($g === 'men') { $alts[] = 'male'; }
                if ($g === 'male') { $alts[] = 'men'; }
                if ($g === 'women') { $alts[] = 'female'; }
                if ($g === 'female') { $alts[] = 'women'; }
                // Build placeholders for IN clause
                $placeholders = implode(',', array_fill(0, count($alts), '?'));
                $filterSql .= " AND LOWER(gender) IN ($placeholders)";
                foreach ($alts as $a) { $filterParams[] = $a; }
            }
            if ($sizeCode !== '') { $filterSql .= ' AND size_code = ?'; $filterParams[] = $sizeCode; }
            if ($colorId !== null) { $filterSql .= ' AND color_id = ?'; $filterParams[] = $colorId; }
            // If the item has colors and no specific color filter is applied, exclude general (colorless) rows
            if ($hasColors && $colorId === null) { $filterSql .= ' AND color_id IS NOT NULL'; }

            // by_gender (sum over all sizes/colors)
            // by_gender (sum over all sizes/colors). If item has colors, count only rows tied to ACTIVE colors
            if ($hasColors) {
                $byGender = Database::queryAll(
                    "SELECT COALESCE(s.gender, 'Unisex') AS gender, COALESCE(SUM(s.stock_level),0) AS stock
                     FROM item_sizes s
                     INNER JOIN item_colors c ON c.item_sku = s.item_sku AND c.id = s.color_id AND c.is_active = 1
                     WHERE s.item_sku = ? AND s.is_active = 1
                     GROUP BY COALESCE(s.gender, 'Unisex')",
                    [$sku]
                );
            } else {
                $byGender = Database::queryAll(
                    "SELECT COALESCE(gender, 'Unisex') AS gender, COALESCE(SUM(stock_level),0) AS stock
                     FROM item_sizes
                     WHERE item_sku = ? AND is_active = 1
                     GROUP BY COALESCE(gender, 'Unisex')",
                    [$sku]
                );
            }

            // by_size (optionally filtered by gender and/or color)
            if ($hasColors) {
                // When item has colors, only count size stock attached to ACTIVE colors
                $bySize = Database::queryAll(
                    "SELECT s.size_code, MAX(s.size_name) AS size_name, COALESCE(SUM(s.stock_level), 0) AS stock
                     FROM item_sizes s
                     INNER JOIN item_colors c ON c.item_sku = s.item_sku AND c.id = s.color_id AND c.is_active = 1
                     WHERE s.item_sku = ? AND s.is_active = 1" . str_replace(['gender','size_code','color_id'], ['s.gender','s.size_code','s.color_id'], $filterSql) . "
                     GROUP BY s.size_code
                     ORDER BY s.size_code",
                    array_merge([$sku], $filterParams)
                );
            } else {
                $bySize = Database::queryAll(
                    "SELECT size_code, MAX(size_name) AS size_name, COALESCE(SUM(stock_level), 0) AS stock
                     FROM item_sizes
                     WHERE item_sku = ? AND is_active = 1" . $filterSql . "
                     GROUP BY size_code
                     ORDER BY size_code",
                    array_merge([$sku], $filterParams)
                );
            }

            // by_color (optionally filtered by gender and/or size)
            $filterSqlColor = '';
            $filterParamsColor = [];
            // When a gender filter is applied, match exact gender only (no unisex) to avoid double-counting; accept synonyms
            if ($gender !== '') {
                $g = strtolower($gender);
                $alts = [$g];
                if ($g === 'men') { $alts[] = 'male'; }
                if ($g === 'male') { $alts[] = 'men'; }
                if ($g === 'women') { $alts[] = 'female'; }
                if ($g === 'female') { $alts[] = 'women'; }
                $placeholders = implode(',', array_fill(0, count($alts), '?'));
                $filterSqlColor .= " AND LOWER(s.gender) IN ($placeholders)";
                foreach ($alts as $a) { $filterParamsColor[] = $a; }
            }
            if ($sizeCode !== '') { $filterSqlColor .= ' AND s.size_code = ?'; $filterParamsColor[] = $sizeCode; }
            // If the item has colors and no color filter at this level (we aggregate by color), ensure we only count per-color rows (s.color_id = c.id already enforces this)

            $byColor = Database::queryAll(
                "SELECT c.id, c.color_name, c.color_code, COALESCE(SUM(s.stock_level), 0) AS stock
                 FROM item_colors c
                 LEFT JOIN item_sizes s ON s.item_sku = c.item_sku AND s.color_id = c.id AND s.is_active = 1" . $filterSqlColor . "
                 WHERE c.item_sku = ? AND c.is_active = 1
                 GROUP BY c.id, c.color_name, c.color_code
                 ORDER BY c.display_order, c.color_name",
                array_merge($filterParamsColor, [$sku])
            );

            echo json_encode([
                'success' => true,
                'aggregates' => [
                    'by_gender' => $byGender,
                    'by_size' => $bySize,
                    'by_color' => $byColor,
                ]
            ]);
            break;
        }
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
