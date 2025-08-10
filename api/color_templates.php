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
            $stmt = $pdo->prepare("
                SELECT ct.*, COUNT(cti.id) as color_count
                FROM color_templates ct
                LEFT JOIN color_template_items cti ON ct.id = cti.template_id AND cti.is_active = 1
                WHERE ct.is_active = 1
                GROUP BY ct.id
                ORDER BY ct.category, ct.template_name
            ");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'get_template':
            // Get specific template with all colors
            $templateId = $_GET['template_id'] ?? 0;

            $stmt = $pdo->prepare("
                SELECT * FROM color_templates 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found']);
                break;
            }

            // Get colors for this template
            $stmt = $pdo->prepare("
                SELECT * FROM color_template_items 
                WHERE template_id = ? AND is_active = 1
                ORDER BY display_order, color_name
            ");
            $stmt->execute([$templateId]);
            $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $template['colors'] = $colors;
            echo json_encode(['success' => true, 'template' => $template]);
            break;

        case 'get_categories':
            // Get all unique categories
            $stmt = $pdo->prepare("
                SELECT DISTINCT category 
                FROM color_templates 
                WHERE is_active = 1
                ORDER BY category
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'create_template':
            // Create new color template
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data || !isset($data['template_name']) || !isset($data['colors'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Insert template
                $stmt = $pdo->prepare("
                    INSERT INTO color_templates (template_name, description, category) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $data['template_name'],
                    $data['description'] ?? '',
                    $data['category'] ?? 'General'
                ]);

                $templateId = $pdo->lastInsertId();

                // Insert colors
                $colorStmt = $pdo->prepare("
                    INSERT INTO color_template_items (template_id, color_name, color_code, display_order) 
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($data['colors'] as $index => $color) {
                    $colorStmt->execute([
                        $templateId,
                        $color['color_name'],
                        $color['color_code'] ?? null,
                        $index
                    ]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'template_id' => $templateId, 'message' => 'Template created successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
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

            $pdo->beginTransaction();

            try {
                // Update template info
                $stmt = $pdo->prepare("
                    UPDATE color_templates 
                    SET template_name = ?, description = ?, category = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['template_name'],
                    $data['description'] ?? '',
                    $data['category'] ?? 'General',
                    $data['template_id']
                ]);

                // Delete existing colors
                $stmt = $pdo->prepare("DELETE FROM color_template_items WHERE template_id = ?");
                $stmt->execute([$data['template_id']]);

                // Insert updated colors
                if (isset($data['colors']) && is_array($data['colors'])) {
                    $colorStmt = $pdo->prepare("
                        INSERT INTO color_template_items (template_id, color_name, color_code, display_order) 
                        VALUES (?, ?, ?, ?)
                    ");

                    foreach ($data['colors'] as $index => $color) {
                        $colorStmt->execute([
                            $data['template_id'],
                            $color['color_name'],
                            $color['color_code'] ?? null,
                            $index
                        ]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Template updated successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'delete_template':
            // Delete color template (soft delete)
            $templateId = $_POST['template_id'] ?? 0;

            $stmt = $pdo->prepare("
                UPDATE color_templates 
                SET is_active = 0 
                WHERE id = ?
            ");
            $stmt->execute([$templateId]);

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
            $stmt = $pdo->prepare("
                SELECT * FROM color_template_items 
                WHERE template_id = ? AND is_active = 1
                ORDER BY display_order
            ");
            $stmt->execute([$data['template_id']]);
            $templateColors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($templateColors)) {
                echo json_encode(['success' => false, 'message' => 'No colors found in template']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Clear existing colors if requested
                if ($data['replace_existing'] ?? false) {
                    $stmt = $pdo->prepare("DELETE FROM item_colors WHERE item_sku = ?");
                    $stmt->execute([$data['item_sku']]);
                }

                // Insert template colors
                $insertStmt = $pdo->prepare("
                    INSERT INTO item_colors (item_sku, color_name, color_code, stock_level, display_order) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    color_code = VALUES(color_code),
                    display_order = VALUES(display_order)
                ");

                $defaultStock = $data['default_stock'] ?? 0;

                foreach ($templateColors as $color) {
                    $insertStmt->execute([
                        $data['item_sku'],
                        $color['color_name'],
                        $color['color_code'],
                        $defaultStock,
                        $color['display_order']
                    ]);
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => 'Template applied successfully',
                    'colors_added' => count($templateColors)
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
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