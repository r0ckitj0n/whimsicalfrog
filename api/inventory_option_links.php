<?php
/**
 * Inventory Option Links API
 * Links an "inventory option" (size_template / color_template / material) to either:
 * - a category (categories.id)
 * - OR a sku (items.sku)
 * Never both.
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

AuthHelper::requireAdmin();

function ensure_inventory_option_links_table(PDO $db): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS inventory_option_links (
  id INT NOT NULL AUTO_INCREMENT,
  option_type VARCHAR(32) NOT NULL,
  option_id INT NOT NULL,
  applies_to_type VARCHAR(16) NOT NULL,
  category_id INT NULL,
  item_sku VARCHAR(64) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_category (category_id),
  KEY idx_sku (item_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $db->exec($sql);

    // Migrate older single-link schema to multi-link schema (best-effort, idempotent).
    try {
        $db->exec("ALTER TABLE inventory_option_links DROP INDEX uniq_option");
    } catch (Throwable $____) {
    }
    // Prevent duplicate target links per option.
    try {
        $db->exec("ALTER TABLE inventory_option_links ADD UNIQUE KEY uniq_option_target (option_type, option_id, applies_to_type, category_id, item_sku)");
    } catch (Throwable $____) {
    }
}

function normalize_option_type(string $t): string
{
    $t = strtolower(trim($t));
    if ($t === 'size_template' || $t === 'color_template' || $t === 'material') {
        return $t;
    }
    return '';
}

function normalize_applies_to_type(string $t): string
{
    $t = strtolower(trim($t));
    if ($t === 'category' || $t === 'sku') {
        return $t;
    }
    return '';
}

try {
    $db = Database::getInstance();
    ensure_inventory_option_links_table($db);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'list');

    switch ($action) {
        case 'list': {
            $rows = Database::queryAll(
                "SELECT l.id, l.option_type, l.option_id,
                        CASE
                          WHEN l.option_type = 'size_template' THEN (SELECT template_name FROM size_templates st WHERE st.id = l.option_id LIMIT 1)
                          WHEN l.option_type = 'color_template' THEN (SELECT template_name FROM color_templates ct WHERE ct.id = l.option_id LIMIT 1)
                          WHEN l.option_type = 'material' THEN (SELECT material_name FROM materials m WHERE m.id = l.option_id LIMIT 1)
                          ELSE NULL
                        END AS option_label,
                        l.applies_to_type, l.category_id, l.item_sku, l.updated_at,
                        c.name AS category_name,
                        i.name AS item_name
                 FROM inventory_option_links l
                 LEFT JOIN categories c ON c.id = l.category_id
                 LEFT JOIN items i ON i.sku COLLATE utf8mb4_unicode_ci = l.item_sku COLLATE utf8mb4_unicode_ci
                 ORDER BY l.option_type ASC, l.option_id ASC"
            );

            $links = array_map(function ($r) {
                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'option_type' => (string) ($r['option_type'] ?? ''),
                    'option_id' => (int) ($r['option_id'] ?? 0),
                    'option_label' => $r['option_label'] !== null ? (string) $r['option_label'] : null,
                    'applies_to_type' => (string) ($r['applies_to_type'] ?? ''),
                    'category_id' => isset($r['category_id']) ? (int) $r['category_id'] : null,
                    'category_name' => $r['category_name'] !== null ? (string) $r['category_name'] : null,
                    'item_sku' => $r['item_sku'] !== null ? (string) $r['item_sku'] : null,
                    'item_name' => $r['item_name'] !== null ? (string) $r['item_name'] : null,
                    'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                ];
            }, $rows ?: []);

            Response::success(['links' => $links]);
            break;
        }

        case 'get_effective': {
            $sku = trim((string) ($_GET['item_sku'] ?? ''));
            if ($sku === '') {
                Response::error('item_sku required', null, 400);
            }
            $item = Database::queryOne("SELECT category_id FROM items WHERE sku = ? LIMIT 1", [$sku]);
            if (!$item) {
                Response::error('Item SKU not found', null, 404);
            }
            $catId = isset($item['category_id']) ? (int) $item['category_id'] : 0;

            $rows = Database::queryAll(
                "SELECT l.id, l.option_type, l.option_id,
                        CASE
                          WHEN l.option_type = 'size_template' THEN (SELECT template_name FROM size_templates st WHERE st.id = l.option_id LIMIT 1)
                          WHEN l.option_type = 'color_template' THEN (SELECT template_name FROM color_templates ct WHERE ct.id = l.option_id LIMIT 1)
                          WHEN l.option_type = 'material' THEN (SELECT material_name FROM materials m WHERE m.id = l.option_id LIMIT 1)
                          ELSE NULL
                        END AS option_label,
                        l.applies_to_type, l.category_id, l.item_sku, l.updated_at,
                        c.name AS category_name,
                        i.name AS item_name,
                        CASE WHEN l.applies_to_type = 'sku' THEN 'sku' ELSE 'category' END AS source
                 FROM inventory_option_links l
                 LEFT JOIN categories c ON c.id = l.category_id
                 LEFT JOIN items i ON i.sku COLLATE utf8mb4_unicode_ci = l.item_sku COLLATE utf8mb4_unicode_ci
                 WHERE (l.applies_to_type = 'sku' AND l.item_sku = ?)
                    OR (l.applies_to_type = 'category' AND l.category_id = ?)
                 ORDER BY l.option_type ASC, l.option_id ASC, source ASC, COALESCE(c.name, l.item_sku) ASC",
                [$sku, $catId]
            );

            $links = array_map(function ($r) {
                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'option_type' => (string) ($r['option_type'] ?? ''),
                    'option_id' => (int) ($r['option_id'] ?? 0),
                    'option_label' => $r['option_label'] !== null ? (string) $r['option_label'] : null,
                    'applies_to_type' => (string) ($r['applies_to_type'] ?? ''),
                    'category_id' => isset($r['category_id']) ? (int) $r['category_id'] : null,
                    'category_name' => $r['category_name'] !== null ? (string) $r['category_name'] : null,
                    'item_sku' => $r['item_sku'] !== null ? (string) $r['item_sku'] : null,
                    'item_name' => $r['item_name'] !== null ? (string) $r['item_name'] : null,
                    'updated_at' => $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
                    'source' => $r['source'] !== null ? (string) $r['source'] : null,
                ];
            }, $rows ?: []);

            Response::success(['links' => $links]);
            break;
        }

        case 'add': {
            $option_type = normalize_option_type((string) ($input['option_type'] ?? ''));
            $option_id = (int) ($input['option_id'] ?? 0);
            $applies_to_type = normalize_applies_to_type((string) ($input['applies_to_type'] ?? ''));

            if ($option_type === '') {
                Response::error('Invalid option_type', null, 400);
            }
            if ($option_id <= 0) {
                Response::error('option_id required', null, 400);
            }
            if ($applies_to_type === '') {
                Response::error('Invalid applies_to_type', null, 400);
            }

            $category_id = null;
            $item_sku = null;

            if ($applies_to_type === 'category') {
                $category_id = isset($input['category_id']) && $input['category_id'] !== '' ? (int) $input['category_id'] : 0;
                if ($category_id <= 0) {
                    Response::error('category_id required when applies_to_type=category', null, 400);
                }
                $exists = Database::queryOne("SELECT 1 FROM categories WHERE id = ? LIMIT 1", [$category_id]);
                if (!$exists) {
                    Response::error('Category not found', null, 404);
                }
                $item_sku = null;
            } else {
                $item_sku = trim((string) ($input['item_sku'] ?? ''));
                if ($item_sku === '') {
                    Response::error('item_sku required when applies_to_type=sku', null, 400);
                }
                $exists = Database::queryOne("SELECT 1 FROM items WHERE sku = ? LIMIT 1", [$item_sku]);
                if (!$exists) {
                    Response::error('Item SKU not found', null, 404);
                }
                $category_id = null;
            }

            // Insert if not already linked (uniq_option_target).
            Database::execute(
                "INSERT INTO inventory_option_links (option_type, option_id, applies_to_type, category_id, item_sku)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE updated_at = NOW()",
                [$option_type, $option_id, $applies_to_type, $category_id, $item_sku]
            );

            // Return the link id (new or existing).
            $row = Database::queryOne(
                "SELECT id FROM inventory_option_links
                 WHERE option_type = ? AND option_id = ? AND applies_to_type = ?
                   AND ((? IS NULL AND category_id IS NULL) OR category_id = ?)
                   AND ((? IS NULL AND item_sku IS NULL) OR item_sku = ?)
                 ORDER BY id DESC
                 LIMIT 1",
                [$option_type, $option_id, $applies_to_type, $category_id, $category_id, $item_sku, $item_sku]
            );

            Response::updated(['message' => 'Link added', 'id' => $row ? (int) $row['id'] : null]);
            break;
        }

        case 'delete': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                Response::error('id required', null, 400);
            }
            $affected = (int) Database::execute("DELETE FROM inventory_option_links WHERE id = ? LIMIT 1", [$id]);
            if ($affected > 0) {
                Response::updated(['message' => 'Link deleted']);
            } else {
                Response::noChanges();
            }
            break;
        }

        case 'clear_option': {
            $option_type = normalize_option_type((string) ($input['option_type'] ?? ''));
            $option_id = (int) ($input['option_id'] ?? 0);
            if ($option_type === '') {
                Response::error('Invalid option_type', null, 400);
            }
            if ($option_id <= 0) {
                Response::error('option_id required', null, 400);
            }
            $affected = (int) Database::execute("DELETE FROM inventory_option_links WHERE option_type = ? AND option_id = ?", [$option_type, $option_id]);
            if ($affected > 0) {
                Response::updated(['message' => 'Links cleared']);
            } else {
                Response::noChanges();
            }
            break;
        }

        // Legacy single-link behavior: clear all then add one
        case 'upsert': {
            $option_type = normalize_option_type((string) ($input['option_type'] ?? ''));
            $option_id = (int) ($input['option_id'] ?? 0);
            $applies_to_type = normalize_applies_to_type((string) ($input['applies_to_type'] ?? ''));
            if ($option_type === '' || $option_id <= 0 || $applies_to_type === '') {
                Response::error('Invalid payload', null, 400);
            }
            Database::execute("DELETE FROM inventory_option_links WHERE option_type = ? AND option_id = ?", [$option_type, $option_id]);
            $input['action'] = 'add';
            // Re-dispatch to add logic
            $_POST['action'] = 'add';
            $action = 'add';
            // simple fallthrough via recursive call is overkill; just duplicate minimal:
            // Use the same handler by setting $action and breaking is messy; instead respond with error.
            Response::error('Legacy upsert unsupported in multi-link mode. Use action=add.', null, 400);
            break;
        }
        case 'clear': {
            Response::error('Legacy clear unsupported in multi-link mode. Use action=clear_option.', null, 400);
            break;
        }

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::serverError('Inventory option links API error', $e->getMessage());
}
