<?php
/**
 * Inventory Option Cascade Settings API
 * Stores cascade/grouping settings scoped to either:
 * - a category (categories.id)
 * - OR a sku (items.sku)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/Constants.php';

AuthHelper::requireAdmin();

function ensure_cascade_table(PDO $db): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS item_option_cascade_settings (
  id INT NOT NULL AUTO_INCREMENT,
  applies_to_type VARCHAR(16) NOT NULL,
  category_id INT NULL,
  item_sku VARCHAR(64) NULL,
  cascade_order JSON NULL,
  enabled_dimensions JSON NULL,
  grouping_rules JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_scope (applies_to_type, category_id, item_sku),
  KEY idx_category (category_id),
  KEY idx_sku (item_sku),
  KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $db->exec($sql);

    // One-time best-effort migration from legacy per-SKU table (item_option_settings).
    try {
        $cntRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM item_option_cascade_settings", []);
        $cnt = (int) ($cntRow['cnt'] ?? 0);
        if ($cnt === 0) {
            try {
                $legacy = Database::queryAll("SELECT item_sku, cascade_order, enabled_dimensions, grouping_rules FROM item_option_settings", []);
                if (is_array($legacy) && count($legacy) > 0) {
                    Database::beginTransaction();
                    try {
                        $stmt = $db->prepare(
                            "INSERT INTO item_option_cascade_settings (applies_to_type, category_id, item_sku, cascade_order, enabled_dimensions, grouping_rules, is_active)
                             VALUES ('sku', NULL, ?, ?, ?, ?, 1)
                             ON DUPLICATE KEY UPDATE cascade_order = VALUES(cascade_order), enabled_dimensions = VALUES(enabled_dimensions), grouping_rules = VALUES(grouping_rules), is_active = 1, updated_at = NOW()"
                        );
                        foreach ($legacy as $row) {
                            $sku = (string) ($row['item_sku'] ?? '');
                            if ($sku === '') continue;
                            $stmt->execute([
                                $sku,
                                $row['cascade_order'] ?? null,
                                $row['enabled_dimensions'] ?? null,
                                $row['grouping_rules'] ?? null
                            ]);
                        }
                        Database::commit();
                    } catch (Throwable $e) {
                        Database::rollBack();
                        throw $e;
                    }
                }
            } catch (Throwable $____) {
            }
        }
    } catch (Throwable $____) {
    }
}

function default_settings(): array
{
    return [
        'cascade_order' => [WF_Constants::DIMENSION_GENDER, WF_Constants::DIMENSION_SIZE, WF_Constants::DIMENSION_COLOR],
        'enabled_dimensions' => [WF_Constants::DIMENSION_GENDER, WF_Constants::DIMENSION_SIZE, WF_Constants::DIMENSION_COLOR],
        'grouping_rules' => new stdClass(),
    ];
}

function normalize_applies_to(string $t): string
{
    $t = strtolower(trim($t));
    if ($t === 'category' || $t === 'sku') return $t;
    return '';
}

function normalize_settings($s): array
{
    $defaults = default_settings();
    $out = $defaults;
    if (is_array($s)) {
        if (isset($s['cascade_order']) && is_array($s['cascade_order'])) $out['cascade_order'] = $s['cascade_order'];
        if (isset($s['enabled_dimensions']) && is_array($s['enabled_dimensions'])) $out['enabled_dimensions'] = $s['enabled_dimensions'];
        if (array_key_exists('grouping_rules', $s)) $out['grouping_rules'] = $s['grouping_rules'] ?? new stdClass();
    }
    return $out;
}

try {
    $db = Database::getInstance();
    ensure_cascade_table($db);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'list');

    switch ($action) {
        case 'list': {
            $rows = Database::queryAll(
                "SELECT s.id, s.applies_to_type, s.category_id, s.item_sku, s.cascade_order, s.enabled_dimensions, s.grouping_rules, s.updated_at,
                        c.name AS category_name,
                        i.name AS item_name
                 FROM item_option_cascade_settings s
                 LEFT JOIN categories c ON c.id = s.category_id
                 LEFT JOIN items i ON i.sku COLLATE utf8mb4_unicode_ci = s.item_sku COLLATE utf8mb4_unicode_ci
                 WHERE s.is_active = 1
                 ORDER BY s.applies_to_type ASC, COALESCE(c.name, s.item_sku) ASC"
            );

            $configs = array_map(function ($r) {
                $settings = [
                    'cascade_order' => $r['cascade_order'] ? json_decode($r['cascade_order'], true) : null,
                    'enabled_dimensions' => $r['enabled_dimensions'] ? json_decode($r['enabled_dimensions'], true) : null,
                    'grouping_rules' => $r['grouping_rules'] ? json_decode($r['grouping_rules'], true) : null,
                ];
                $norm = normalize_settings($settings);
                return [
                    'id' => (int) $r['id'],
                    'applies_to_type' => (string) $r['applies_to_type'],
                    'category_id' => isset($r['category_id']) ? (int) $r['category_id'] : null,
                    'category_name' => $r['category_name'] !== null ? (string) $r['category_name'] : null,
                    'item_sku' => $r['item_sku'] !== null ? (string) $r['item_sku'] : null,
                    'item_name' => $r['item_name'] !== null ? (string) $r['item_name'] : null,
                    'settings' => $norm,
                    'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                ];
            }, $rows ?: []);

            Response::success(['configs' => $configs]);
            break;
        }

        case 'upsert': {
            $id = isset($input['id']) ? (int) $input['id'] : 0;
            $applies = normalize_applies_to((string) ($input['applies_to_type'] ?? ''));
            if ($applies === '') Response::error('Invalid applies_to_type', null, 400);

            $category_id = null;
            $item_sku = null;

            if ($applies === 'category') {
                $category_id = isset($input['category_id']) && $input['category_id'] !== '' ? (int) $input['category_id'] : 0;
                if ($category_id <= 0) Response::error('category_id required', null, 400);
                $exists = Database::queryOne("SELECT 1 FROM categories WHERE id = ? LIMIT 1", [$category_id]);
                if (!$exists) Response::error('Category not found', null, 404);
            } else {
                $item_sku = trim((string) ($input['item_sku'] ?? ''));
                if ($item_sku === '') Response::error('item_sku required', null, 400);
                $exists = Database::queryOne("SELECT 1 FROM items WHERE sku = ? LIMIT 1", [$item_sku]);
                if (!$exists) Response::error('Item SKU not found', null, 404);
            }

            $settings = normalize_settings($input['settings'] ?? null);

            if ($id > 0) {
                Database::execute(
                    "UPDATE item_option_cascade_settings
                     SET applies_to_type = ?, category_id = ?, item_sku = ?, cascade_order = ?, enabled_dimensions = ?, grouping_rules = ?, is_active = 1, updated_at = NOW()
                     WHERE id = ?",
                    [
                        $applies,
                        $applies === 'category' ? $category_id : null,
                        $applies === 'sku' ? $item_sku : null,
                        json_encode($settings['cascade_order']),
                        json_encode($settings['enabled_dimensions']),
                        json_encode($settings['grouping_rules']),
                        $id
                    ]
                );
                Response::updated(['id' => $id]);
                break;
            }

            // Upsert by scope.
            $existing = Database::queryOne(
                "SELECT id FROM item_option_cascade_settings WHERE applies_to_type = ? AND ((? IS NULL AND category_id IS NULL) OR category_id = ?) AND ((? IS NULL AND item_sku IS NULL) OR item_sku = ?) LIMIT 1",
                [$applies, $applies === 'category' ? $category_id : null, $applies === 'category' ? $category_id : null, $applies === 'sku' ? $item_sku : null, $applies === 'sku' ? $item_sku : null]
            );
            if ($existing) {
                $eid = (int) $existing['id'];
                Database::execute(
                    "UPDATE item_option_cascade_settings
                     SET cascade_order = ?, enabled_dimensions = ?, grouping_rules = ?, is_active = 1, updated_at = NOW()
                     WHERE id = ?",
                    [
                        json_encode($settings['cascade_order']),
                        json_encode($settings['enabled_dimensions']),
                        json_encode($settings['grouping_rules']),
                        $eid
                    ]
                );
                Response::updated(['id' => $eid]);
                break;
            }

            Database::execute(
                "INSERT INTO item_option_cascade_settings (applies_to_type, category_id, item_sku, cascade_order, enabled_dimensions, grouping_rules, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)",
                [
                    $applies,
                    $applies === 'category' ? $category_id : null,
                    $applies === 'sku' ? $item_sku : null,
                    json_encode($settings['cascade_order']),
                    json_encode($settings['enabled_dimensions']),
                    json_encode($settings['grouping_rules'])
                ]
            );
            Response::updated(['id' => (int) Database::lastInsertId()]);
            break;
        }

        case 'delete': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) Response::error('id required', null, 400);
            $affected = (int) Database::execute("UPDATE item_option_cascade_settings SET is_active = 0, updated_at = NOW() WHERE id = ?", [$id]);
            if ($affected > 0) Response::updated();
            else Response::noChanges();
            break;
        }

        case 'get_effective': {
            $sku = trim((string) ($_GET['item_sku'] ?? ''));
            if ($sku === '') Response::error('item_sku required', null, 400);

            $defaults = default_settings();

            // SKU override
            $rowSku = Database::queryOne(
                "SELECT id, cascade_order, enabled_dimensions, grouping_rules
                 FROM item_option_cascade_settings
                 WHERE is_active = 1 AND applies_to_type = 'sku' AND item_sku = ?
                 ORDER BY updated_at DESC
                 LIMIT 1",
                [$sku]
            );
            if ($rowSku) {
                $settings = normalize_settings([
                    'cascade_order' => $rowSku['cascade_order'] ? json_decode($rowSku['cascade_order'], true) : null,
                    'enabled_dimensions' => $rowSku['enabled_dimensions'] ? json_decode($rowSku['enabled_dimensions'], true) : null,
                    'grouping_rules' => $rowSku['grouping_rules'] ? json_decode($rowSku['grouping_rules'], true) : null,
                ]);
                Response::success(['source' => 'sku', 'item_sku' => $sku, 'settings' => $settings]);
                break;
            }

            // Category fallback
            $item = Database::queryOne("SELECT category_id FROM items WHERE sku = ? LIMIT 1", [$sku]);
            $catId = $item && isset($item['category_id']) ? (int) $item['category_id'] : 0;
            if ($catId > 0) {
                $rowCat = Database::queryOne(
                    "SELECT id, cascade_order, enabled_dimensions, grouping_rules
                     FROM item_option_cascade_settings
                     WHERE is_active = 1 AND applies_to_type = 'category' AND category_id = ?
                     ORDER BY updated_at DESC
                     LIMIT 1",
                    [$catId]
                );
                if ($rowCat) {
                    $settings = normalize_settings([
                        'cascade_order' => $rowCat['cascade_order'] ? json_decode($rowCat['cascade_order'], true) : null,
                        'enabled_dimensions' => $rowCat['enabled_dimensions'] ? json_decode($rowCat['enabled_dimensions'], true) : null,
                        'grouping_rules' => $rowCat['grouping_rules'] ? json_decode($rowCat['grouping_rules'], true) : null,
                    ]);
                    Response::success(['source' => 'category', 'category_id' => $catId, 'settings' => $settings]);
                    break;
                }
            }

            Response::success(['source' => 'default', 'settings' => $defaults]);
            break;
        }

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::serverError('Cascade settings API error', $e->getMessage());
}
