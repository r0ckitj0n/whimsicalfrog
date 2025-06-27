<?php
// Size Templates Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication
session_start();

// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all':
            // Get all size templates with size counts
            $stmt = $pdo->prepare("
                SELECT st.*, COUNT(sti.id) as size_count
                FROM size_templates st
                LEFT JOIN size_template_items sti ON st.id = sti.template_id AND sti.is_active = 1
                WHERE st.is_active = 1
                GROUP BY st.id
                ORDER BY st.category, st.template_name
            ");
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;
            
        case 'get_template':
            // Get specific template with all sizes
            $templateId = $_GET['template_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT * FROM size_templates 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found']);
                break;
            }
            
            // Get sizes for this template
            $stmt = $pdo->prepare("
                SELECT * FROM size_template_items 
                WHERE template_id = ? AND is_active = 1
                ORDER BY display_order, size_name
            ");
            $stmt->execute([$templateId]);
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $template['sizes'] = $sizes;
            echo json_encode(['success' => true, 'template' => $template]);
            break;
            
        case 'get_categories':
            // Get all unique categories
            $stmt = $pdo->prepare("
                SELECT DISTINCT category 
                FROM size_templates 
                WHERE is_active = 1
                ORDER BY category
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'create_template':
            // Create new size template
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['template_name']) || !isset($data['sizes'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
                break;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Insert template
                $stmt = $pdo->prepare("
                    INSERT INTO size_templates (template_name, description, category) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $data['template_name'],
                    $data['description'] ?? '',
                    $data['category'] ?? 'General'
                ]);
                
                $templateId = $pdo->lastInsertId();
                
                // Insert sizes
                $sizeStmt = $pdo->prepare("
                    INSERT INTO size_template_items (template_id, size_name, size_code, price_adjustment, display_order) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($data['sizes'] as $index => $size) {
                    $sizeStmt->execute([
                        $templateId,
                        $size['size_name'],
                        $size['size_code'],
                        $size['price_adjustment'] ?? 0.00,
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
            // Update existing size template
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['template_id'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID required']);
                break;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Update template info
                $stmt = $pdo->prepare("
                    UPDATE size_templates 
                    SET template_name = ?, description = ?, category = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['template_name'],
                    $data['description'] ?? '',
                    $data['category'] ?? 'General',
                    $data['template_id']
                ]);
                
                // Delete existing sizes
                $stmt = $pdo->prepare("DELETE FROM size_template_items WHERE template_id = ?");
                $stmt->execute([$data['template_id']]);
                
                // Insert updated sizes
                if (isset($data['sizes']) && is_array($data['sizes'])) {
                    $sizeStmt = $pdo->prepare("
                        INSERT INTO size_template_items (template_id, size_name, size_code, price_adjustment, display_order) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($data['sizes'] as $index => $size) {
                        $sizeStmt->execute([
                            $data['template_id'],
                            $size['size_name'],
                            $size['size_code'],
                            $size['price_adjustment'] ?? 0.00,
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
            // Delete size template (soft delete)
            $templateId = $_POST['template_id'] ?? 0;
            
            $stmt = $pdo->prepare("
                UPDATE size_templates 
                SET is_active = 0 
                WHERE id = ?
            ");
            $stmt->execute([$templateId]);
            
            echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
            break;
            
        case 'apply_to_item':
            // Apply size template to an item
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['template_id']) || !isset($data['item_sku'])) {
                echo json_encode(['success' => false, 'message' => 'Template ID and Item SKU required']);
                break;
            }
            
            // Get template sizes
            $stmt = $pdo->prepare("
                SELECT * FROM size_template_items 
                WHERE template_id = ? AND is_active = 1
                ORDER BY display_order
            ");
            $stmt->execute([$data['template_id']]);
            $templateSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($templateSizes)) {
                echo json_encode(['success' => false, 'message' => 'No sizes found in template']);
                break;
            }
            
            $pdo->beginTransaction();
            
            try {
                // Clear existing sizes if requested
                if ($data['replace_existing'] ?? false) {
                    if ($data['apply_mode'] === 'color_specific') {
                        // Clear sizes for specific color
                        $stmt = $pdo->prepare("DELETE FROM item_sizes WHERE item_sku = ? AND color_id = ?");
                        $stmt->execute([$data['item_sku'], $data['color_id']]);
                    } else {
                        // Clear general sizes
                        $stmt = $pdo->prepare("DELETE FROM item_sizes WHERE item_sku = ? AND color_id IS NULL");
                        $stmt->execute([$data['item_sku']]);
                    }
                }
                
                // Insert template sizes
                $insertStmt = $pdo->prepare("
                    INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    price_adjustment = VALUES(price_adjustment),
                    display_order = VALUES(display_order)
                ");
                
                $defaultStock = $data['default_stock'] ?? 0;
                $colorId = ($data['apply_mode'] === 'color_specific') ? $data['color_id'] : null;
                
                foreach ($templateSizes as $size) {
                    $insertStmt->execute([
                        $data['item_sku'],
                        $colorId,
                        $size['size_name'],
                        $size['size_code'],
                        $defaultStock,
                        $size['price_adjustment'],
                        $size['display_order']
                    ]);
                }
                
                $pdo->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Template applied successfully',
                    'sizes_added' => count($templateSizes)
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