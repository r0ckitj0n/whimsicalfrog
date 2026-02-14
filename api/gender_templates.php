<?php
// Gender Templates Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Decode JSON body once so it can be reused by auth and action handlers
$json = [];

// Enforce admin auth with dev admin_token fallback for iframe usage
try {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $json = json_decode($rawBody, true) ?: [];
    }
    $token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? ($json['admin_token'] ?? null);
    if (!$token || $token !== (AuthHelper::ADMIN_TOKEN ?? 'whimsical_admin_2024')) {
        AuthHelper::requireAdmin();
    }
} catch (Throwable $____) {
    AuthHelper::requireAdmin();
}

function ensure_gender_templates_tables(PDO $db): void
{
    $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS gender_templates (
  id INT NOT NULL AUTO_INCREMENT,
  template_name VARCHAR(100) NOT NULL,
  description TEXT NULL,
  category VARCHAR(128) NOT NULL DEFAULT 'General',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_template_name (template_name),
  KEY idx_active_cat (is_active, category, template_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

    $db->exec(<<<SQL
CREATE TABLE IF NOT EXISTS gender_template_items (
  id INT NOT NULL AUTO_INCREMENT,
  template_id INT NOT NULL,
  gender_name VARCHAR(64) NOT NULL,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tpl (template_id, is_active, display_order),
  CONSTRAINT fk_gender_template_items_template
    FOREIGN KEY (template_id) REFERENCES gender_templates(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
}

try {
    $db = Database::getInstance();
    ensure_gender_templates_tables($db);

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_all': {
            $templates = Database::queryAll(
                "SELECT gt.*, COUNT(gti.id) AS gender_count
                 FROM gender_templates gt
                 LEFT JOIN gender_template_items gti ON gt.id = gti.template_id AND gti.is_active = 1
                 WHERE gt.is_active = 1
                 GROUP BY gt.id
                 ORDER BY gt.category, gt.template_name"
            );
            Response::success(['templates' => $templates]);
            break;
        }
        case 'get_template': {
            $templateId = (int)($_GET['template_id'] ?? 0);
            if ($templateId <= 0) {
                Response::error('Template ID required', null, 400);
            }
            $template = Database::queryOne("SELECT * FROM gender_templates WHERE id = ? AND is_active = 1", [$templateId]);
            if (!$template) {
                Response::error('Template not found', null, 404);
            }

            $genders = Database::queryAll(
                "SELECT * FROM gender_template_items WHERE template_id = ? AND is_active = 1 ORDER BY display_order, gender_name",
                [$templateId]
            );
            $template['genders'] = $genders;
            Response::success(['template' => $template]);
            break;
        }
        case 'get_categories': {
            $rows = Database::queryAll("SELECT DISTINCT category FROM gender_templates WHERE is_active = 1 ORDER BY category ASC");
            $categories = array_values(array_filter(array_map(fn($r) => (string)($r['category'] ?? ''), $rows ?: [])));
            Response::success(['categories' => $categories]);
            break;
        }
        case 'create_template': {
            $data = isset($json) && is_array($json) ? $json : [];
            if (!$data || !isset($data['template_name']) || !isset($data['genders'])) {
                Response::error('Invalid data provided', null, 422);
            }

            Database::beginTransaction();
            try {
                Database::execute(
                    "INSERT INTO gender_templates (template_name, description, category) VALUES (?, ?, ?)",
                    [trim((string)$data['template_name']), (string)($data['description'] ?? ''), trim((string)($data['category'] ?? 'General'))]
                );
                $templateId = (int)Database::lastInsertId();

                $items = is_array($data['genders']) ? $data['genders'] : [];
                foreach ($items as $idx => $g) {
                    $name = trim((string)($g['gender_name'] ?? ''));
                    if ($name === '') continue;
                    $order = isset($g['display_order']) ? (int)$g['display_order'] : ($idx + 1);
                    Database::execute(
                        "INSERT INTO gender_template_items (template_id, gender_name, display_order) VALUES (?, ?, ?)",
                        [$templateId, $name, $order]
                    );
                }

                Database::commit();
                Response::updated(['template_id' => $templateId]);
            } catch (Throwable $e) {
                Database::rollBack();
                throw $e;
            }
            break;
        }
        case 'update_template': {
            $data = isset($json) && is_array($json) ? $json : [];
            $templateId = (int)($data['template_id'] ?? ($data['id'] ?? 0));
            if (!$data || $templateId <= 0) {
                Response::error('Template ID required', null, 400);
            }

            Database::beginTransaction();
            try {
                Database::execute(
                    "UPDATE gender_templates SET template_name = ?, description = ?, category = ? WHERE id = ?",
                    [trim((string)($data['template_name'] ?? '')), (string)($data['description'] ?? ''), trim((string)($data['category'] ?? 'General')), $templateId]
                );

                Database::execute("DELETE FROM gender_template_items WHERE template_id = ?", [$templateId]);

                $items = isset($data['genders']) && is_array($data['genders']) ? $data['genders'] : [];
                foreach ($items as $idx => $g) {
                    $name = trim((string)($g['gender_name'] ?? ''));
                    if ($name === '') continue;
                    $order = isset($g['display_order']) ? (int)$g['display_order'] : ($idx + 1);
                    Database::execute(
                        "INSERT INTO gender_template_items (template_id, gender_name, display_order) VALUES (?, ?, ?)",
                        [$templateId, $name, $order]
                    );
                }

                Database::commit();
                Response::updated();
            } catch (Throwable $e) {
                Database::rollBack();
                throw $e;
            }
            break;
        }
        case 'delete_template': {
            $templateId = (int)($_GET['template_id'] ?? $_POST['template_id'] ?? ($json['template_id'] ?? 0));
            if ($templateId <= 0) {
                Response::error('Template ID required', null, 400);
            }
            $affected = Database::execute("UPDATE gender_templates SET is_active = 0 WHERE id = ?", [$templateId]);
            if ($affected > 0) {
                Response::updated();
            } else {
                $exists = Database::queryOne("SELECT id FROM gender_templates WHERE id = ? LIMIT 1", [$templateId]);
                if ($exists) Response::noChanges();
                else Response::error('Template not found', null, 404);
            }
            break;
        }
        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::serverError($e->getMessage(), null);
}

