<?php
// Color Templates Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication


// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_all':
            // Get all color templates with color counts
            $templates = Database::queryAll(
                "SELECT ct.*, COUNT(cti.id) as color_count
                 FROM color_templates ct
                 LEFT JOIN color_template_items cti ON ct.id = cti.template_id AND cti.is_active = 1
                 WHERE ct.is_active = 1
                 GROUP BY ct.id
                 ORDER BY ct.template_name ASC"
            );

            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'get_template':
            // Get specific template with all colors
            $templateId = $_GET['template_id'] ?? 0;

            $template = Database::queryOne("SELECT * FROM color_templates WHERE id = ? AND is_active = 1", [$templateId]);

            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found']);
                break;
            }

            // Get colors for this template
            $colors = Database::queryAll(
                "SELECT * FROM color_template_items 
                 WHERE template_id = ? AND is_active = 1
                 ORDER BY display_order, color_name",
                [$templateId]
            );

            $template['colors'] = $colors;
            echo json_encode(['success' => true, 'template' => $template]);
            break;

        case 'get_categories':
            // Get all unique categories
            $categories = array_column(
                Database::queryAll("SELECT DISTINCT category FROM color_templates WHERE is_active = 1 ORDER BY category ASC"),
                'category'
            );

            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'create_template':
            // Create new color template
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_name']) || !isset($data['colors'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                break;
            }

            Database::beginTransaction();

            try {
                // Insert template
                Database::execute(
                    "INSERT INTO color_templates (template_name, description, category) VALUES (?, ?, ?)",
                    [$data['template_name'], $data['description'] ?? '', $data['category'] ?? 'General']
                );

                $templateId = Database::lastInsertId();

                // Insert colors
                if (isset($data['colors']) && is_array($data['colors'])) {
                    foreach ($data['colors'] as $idx => $c) {
                        $cname = trim($c['color_name'] ?? '');
                        if ($cname === '') {
                            continue;
                        }
                        Database::execute(
                            "INSERT INTO color_template_items (template_id, color_name, color_code, display_order) VALUES (?, ?, ?, ?)",
                            [$templateId, $cname, $c['color_code'] ?? '', $c['display_order'] ?? ($idx + 1)]
                        );
                    }
                }
                Database::commit();
                echo json_encode(['success' => true, 'template_id' => $templateId, 'message' => 'Template created successfully']);

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

        case 'update_template':
            // Update existing color template
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_id'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID required']);
                break;
            }

            Database::beginTransaction();

            try {
                // Update template info
                Database::execute(
                    "UPDATE color_templates SET template_name = ?, description = ?, category = ? WHERE id = ?",
                    [$data['template_name'], $data['description'] ?? '', $data['category'] ?? 'General', $data['template_id']]
                );

                // Delete existing colors
                Database::execute("DELETE FROM color_template_items WHERE template_id = ?", [$data['template_id']]);

                // Insert updated colors
                if (isset($data['colors']) && is_array($data['colors'])) {
                    foreach ($data['colors'] as $idx => $c) {
                        $cname = trim($c['color_name'] ?? '');
                        if ($cname === '') {
                            continue;
                        }
                        Database::execute(
                            "INSERT INTO color_template_items (template_id, color_name, color_code, display_order) VALUES (?, ?, ?, ?)",
                            [$data['template_id'], $cname, $c['color_code'] ?? '', $c['display_order'] ?? ($idx + 1)]
                        );
                    }
                }
                Database::commit();
                echo json_encode(['success' => true, 'message' => 'Template updated successfully']);

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

        case 'delete_template':
            // Delete color template (soft delete)
            $templateId = $_POST['template_id'] ?? 0;

            Database::execute("UPDATE color_templates SET is_active = 0 WHERE id = ?", [$templateId]);

            echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
            break;

        case 'apply_to_item':
            // Apply color template to an item
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_id']) || !isset($data['item_sku'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID and Item SKU required']);
                break;
            }

            // Get template colors
            $colors = Database::queryAll(
                "SELECT * FROM color_template_items WHERE template_id = ? AND is_active = 1 ORDER BY display_order",
                [$data['template_id']]
            );

            if (empty($colors)) {
                echo json_encode(['success' => false, 'message' => 'No colors found in template']);
                break;
            }

            Database::beginTransaction();

            try {
                if ($data['replace_existing'] ?? false) {
                    Database::execute("DELETE FROM item_colors WHERE item_sku = ?", [$data['item_sku']]);
                }

                // Insert template colors
                foreach ($colors as $c) {
                    Database::execute(
                        "INSERT INTO item_colors (item_sku, color_name, color_code, stock_level, display_order) 
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE 
                            color_code = VALUES(color_code),
                            stock_level = VALUES(stock_level),
                            display_order = VALUES(display_order)",
                        [$data['item_sku'], $c['color_name'], $c['color_code'], $data['default_stock'] ?? 0, $c['display_order'] ?? 0]
                    );
                }
                Database::commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Template applied successfully',
                    'applied_colors' => count($colors)
                ]);

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 