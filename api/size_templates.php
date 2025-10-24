<?php
// Size Templates Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Enforce admin auth with dev admin_token fallback for iframe usage
try {
    $json = json_decode(file_get_contents('php://input'), true) ?: [];
    $token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? ($json['admin_token'] ?? null);
    if (!$token || $token !== (AuthHelper::ADMIN_TOKEN ?? 'whimsical_admin_2024')) {
        AuthHelper::requireAdmin();
    }
} catch (Throwable $____) {
    AuthHelper::requireAdmin();
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'get_all':
            // Get all size templates with size counts
            $templates = Database::queryAll(
                "SELECT st.*, COUNT(sti.id) as size_count
                 FROM size_templates st
                 LEFT JOIN size_template_items sti ON st.id = sti.template_id AND sti.is_active = 1
                 WHERE st.is_active = 1
                 GROUP BY st.id
                 ORDER BY st.category, st.template_name"
            );

            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'get_template':
            // Get specific template with all sizes
            $templateId = $_GET['template_id'] ?? 0;

            $template = Database::queryOne("SELECT * FROM size_templates WHERE id = ? AND is_active = 1", [$templateId]);

            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found']);
                break;
            }

            // Get sizes for this template
            $sizes = Database::queryAll(
                "SELECT * FROM size_template_items WHERE template_id = ? AND is_active = 1 ORDER BY display_order, size_name",
                [$templateId]
            );

            $template['sizes'] = $sizes;
            echo json_encode(['success' => true, 'template' => $template]);
            break;

        case 'get_categories':
            // Get all unique categories
            $categories = array_column(
                Database::queryAll("SELECT DISTINCT category FROM size_templates WHERE is_active = 1 ORDER BY category"),
                'category'
            );

            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'create_template':
            // Create new size template
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_name']) || !isset($data['sizes'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                break;
            }

            Database::beginTransaction();

            try {
                // Insert template
                Database::execute(
                    "INSERT INTO size_templates (template_name, description, category) VALUES (?, ?, ?)",
                    [$data['template_name'], $data['description'] ?? '', $data['category'] ?? 'General']
                );

                $templateId = Database::lastInsertId();

                // Insert sizes
                foreach ($data['sizes'] as $index => $size) {
                    Database::execute(
                        "INSERT INTO size_template_items (template_id, size_name, size_code, price_adjustment, display_order) VALUES (?, ?, ?, ?, ?)",
                        [$templateId, $size['size_name'], $size['size_code'], $size['price_adjustment'] ?? 0.00, $index]
                    );
                }
                Database::commit();
                Response::updated(['template_id' => $templateId]);

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

        case 'update_template':
            // Update existing size template
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_id'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID required']);
                break;
            }

            Database::beginTransaction();

            try {
                // Update template info
                Database::execute(
                    "UPDATE size_templates SET template_name = ?, description = ?, category = ? WHERE id = ?",
                    [$data['template_name'], $data['description'] ?? '', $data['category'] ?? 'General', $data['template_id']]
                );

                // Delete existing sizes
                Database::execute("DELETE FROM size_template_items WHERE template_id = ?", [$data['template_id']]);

                // Insert updated sizes
                if (isset($data['sizes']) && is_array($data['sizes'])) {
                    foreach ($data['sizes'] as $index => $size) {
                        Database::execute(
                            "INSERT INTO size_template_items (template_id, size_name, size_code, price_adjustment, display_order) VALUES (?, ?, ?, ?, ?)",
                            [$data['template_id'], $size['size_name'], $size['size_code'], $size['price_adjustment'] ?? 0.00, $index]
                        );
                    }
                }
                Database::commit();
                Response::updated();

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

        case 'delete_template':
            // Delete size template (soft delete)
            $templateId = $_POST['template_id'] ?? 0;

            $affected = Database::execute("UPDATE size_templates SET is_active = 0 WHERE id = ?", [$templateId]);
            if ($affected > 0) {
                Response::updated();
            } else {
                $exists = Database::queryOne('SELECT id FROM size_templates WHERE id = ?', [$templateId]);
                if ($exists) { Response::noChanges(); } else { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Template not found']); }
            }
            break;

        case 'apply_to_item':
            // Apply size template to an item
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_id']) || !isset($data['item_sku'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID and Item SKU required']);
                break;
            }

            // Get template sizes
            $templateSizes = Database::queryAll("SELECT * FROM size_template_items WHERE template_id = ? AND is_active = 1 ORDER BY display_order", [$data['template_id']]);

            if (empty($templateSizes)) {
                echo json_encode(['success' => false, 'message' => 'No sizes found in template']);
                break;
            }

            Database::beginTransaction();

            try {
                // Clear existing sizes if requested
                if ($data['replace_existing'] ?? false) {
                    if ($data['apply_mode'] === 'color_specific') {
                        // Clear sizes for specific color
                        Database::execute("DELETE FROM item_sizes WHERE item_sku = ? AND color_id = ?", [$data['item_sku'], $data['color_id']]);
                    } else {
                        Database::execute("DELETE FROM item_sizes WHERE item_sku = ?", [$data['item_sku']]);
                    }
                }

                // Insert template sizes
                foreach ($templateSizes as $idx => $sz) {
                    Database::execute(
                        "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE 
                            stock_level = VALUES(stock_level),
                            price_adjustment = VALUES(price_adjustment),
                            display_order = VALUES(display_order)",
                        [$data['item_sku'], $data['color_id'] ?? null, $sz['size_name'], $sz['size_code'], $data['default_stock'] ?? 0, $sz['price_adjustment'] ?? 0.00, $idx]
                    );
                }
                Database::commit();
                Response::updated(['applied_sizes' => count($templateSizes)]);

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