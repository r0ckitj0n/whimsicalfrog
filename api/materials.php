<?php
/**
 * Materials API (Admin)
 * Provides CRUD for common item materials (e.g., cotton, wood, ceramic).
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

function ensure_materials_table(PDO $db): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS materials (
  id INT NOT NULL AUTO_INCREMENT,
  material_name VARCHAR(128) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_material_name (material_name),
  KEY idx_active_sort (is_active, sort_order, material_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $db->exec($sql);
}

function seed_common_materials_if_empty(PDO $db): void
{
    $row = Database::queryOne("SELECT COUNT(*) AS cnt FROM materials", []);
    $cnt = (int) ($row['cnt'] ?? 0);
    if ($cnt > 0) {
        return;
    }

    $common = [
        'Cotton',
        'Organic Cotton',
        'Cotton/Polyester 50/50',
        'Polyester',
        'Nylon',
        'Spandex (Elastane)',
        'Rayon',
        'Viscose',
        'Linen',
        'Wool',
        'Fleece',
        'Denim',
        'Canvas',
        'Leather',
        'Faux Leather (PU)',
        'Suede',
        'Silk',
        'Satin',
        'Velvet',
        'Bamboo',
        'Hemp',
        'Jute',
        'Felt',
        'Neoprene',
        'Acrylic',
        'PLA (Polylactic Acid)',
        'Resin (Epoxy)',
        'Vinyl',
        'Heat Transfer Vinyl (HTV)',
        'Sublimation Polyester',
        'Paper',
        'Cardstock',
        'Kraft Paper',
        'Wood',
        'Plywood',
        'MDF',
        'Balsa Wood',
        'Cork',
        'Glass',
        'Ceramic',
        'Porcelain',
        'Stone',
        'Concrete',
        'Clay (Polymer)',
        'Clay (Air-Dry)',
        'Steel',
        'Stainless Steel',
        'Aluminum',
        'Copper',
        'Brass',
        'Iron',
        'Silver-Plated',
        'Gold-Plated',
        'Acrylic (Plastic)',
        'Polycarbonate',
        'ABS Plastic',
        'PVC',
        'Rubber',
        'Silicone',
    ];

    Database::beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO materials (material_name, sort_order, is_active) VALUES (?, ?, 1)");
        foreach ($common as $idx => $name) {
            $stmt->execute([$name, $idx]);
        }
        Database::commit();
    } catch (Throwable $e) {
        Database::rollBack();
        throw $e;
    }
}

try {
    $db = Database::getInstance();
    ensure_materials_table($db);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'list');

    switch ($action) {
        case 'list': {
            seed_common_materials_if_empty($db);
            $rows = Database::queryAll(
                "SELECT id, material_name, description, sort_order, is_active
                 FROM materials
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, material_name ASC"
            );
            $materials = array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'material_name' => (string) $r['material_name'],
                    'description' => $r['description'] !== null ? (string) $r['description'] : null,
                    'sort_order' => (int) ($r['sort_order'] ?? 0),
                    'is_active' => (int) ($r['is_active'] ?? 1) === 1,
                ];
            }, $rows ?: []);
            Response::success(['materials' => $materials]);
            break;
        }

        case 'create': {
            $name = trim((string) ($input['material_name'] ?? ''));
            $description = isset($input['description']) ? (string) $input['description'] : null;
            if ($name === '') {
                Response::error('material_name required', null, 400);
            }

            $next = Database::queryOne("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM materials", []);
            $sort_order = (int) ($next['next_sort'] ?? 0);

            Database::execute(
                "INSERT INTO materials (material_name, description, sort_order, is_active) VALUES (?, ?, ?, 1)",
                [$name, $description, $sort_order]
            );
            Response::updated(['id' => (int) Database::lastInsertId()]);
            break;
        }

        case 'update': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                Response::error('id required', null, 400);
            }

            $fields = [];
            $params = [];

            if (array_key_exists('material_name', $input)) {
                $name = trim((string) ($input['material_name'] ?? ''));
                if ($name === '') {
                    Response::error('material_name cannot be empty', null, 400);
                }
                $fields[] = 'material_name = ?';
                $params[] = $name;
            }
            if (array_key_exists('description', $input)) {
                $fields[] = 'description = ?';
                $params[] = $input['description'] !== null ? (string) $input['description'] : null;
            }
            if (array_key_exists('sort_order', $input)) {
                $fields[] = 'sort_order = ?';
                $params[] = (int) $input['sort_order'];
            }
            if (array_key_exists('is_active', $input)) {
                $fields[] = 'is_active = ?';
                $params[] = ((int) $input['is_active']) ? 1 : 0;
            }

            if (count($fields) === 0) {
                Response::error('No fields to update', null, 400);
            }

            $params[] = $id;
            Database::execute("UPDATE materials SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?", $params);
            Response::updated();
            break;
        }

        case 'delete': {
            $id = (int) ($input['id'] ?? 0);
            if ($id <= 0) {
                Response::error('id required', null, 400);
            }

            $affected = (int) Database::execute("UPDATE materials SET is_active = 0, updated_at = NOW() WHERE id = ?", [$id]);
            if ($affected > 0) {
                Response::updated();
            } else {
                Response::noChanges();
            }
            break;
        }

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::serverError('Materials API error', $e->getMessage());
}

