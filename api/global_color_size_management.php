<?php
// Global Color and Size Management API
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
    
    // Parse action from GET, POST, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // If no action found in GET/POST, try parsing from JSON body
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }
    
    switch ($action) {
        // ========== GLOBAL COLORS MANAGEMENT ==========
        case 'get_global_colors':
            $category = $_GET['category'] ?? '';
            $whereClause = "is_active = 1";
            $params = [];
            
            if (!empty($category)) {
                $whereClause .= " AND category = ?";
                $params[] = $category;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, color_name, color_code, category, description, display_order
                FROM global_colors 
                WHERE $whereClause 
                ORDER BY display_order ASC, color_name ASC
            ");
            $stmt->execute($params);
            $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'colors' => $colors]);
            break;
            
        case 'get_color_categories':
            $stmt = $pdo->query("
                SELECT DISTINCT category 
                FROM global_colors 
                WHERE is_active = 1 
                ORDER BY category ASC
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'add_global_color':
            $data = json_decode(file_get_contents('php://input'), true);
            $colorName = trim($data['color_name'] ?? '');
            $colorCode = $data['color_code'] ?? '';
            $category = trim($data['category'] ?? 'General');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);
            
            if (empty($colorName)) {
                throw new Exception('Color name is required');
            }
            
            // Validate color code format if provided
            if (!empty($colorCode) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCode)) {
                throw new Exception('Invalid color code format. Use #RRGGBB format.');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO global_colors (color_name, color_code, category, description, display_order) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$colorName, $colorCode, $category, $description, $displayOrder]);
            
            $colorId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Global color added successfully',
                'color_id' => $colorId
            ]);
            break;
            
        case 'update_global_color':
            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            $colorName = trim($data['color_name'] ?? '');
            $colorCode = $data['color_code'] ?? '';
            $category = trim($data['category'] ?? 'General');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            if ($colorId <= 0 || empty($colorName)) {
                throw new Exception('Color ID and color name are required');
            }
            
            // Validate color code format if provided
            if (!empty($colorCode) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCode)) {
                throw new Exception('Invalid color code format. Use #RRGGBB format.');
            }
            
            $stmt = $pdo->prepare("
                UPDATE global_colors 
                SET color_name = ?, color_code = ?, category = ?, description = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$colorName, $colorCode, $category, $description, $displayOrder, $isActive, $colorId]);
            
            echo json_encode(['success' => true, 'message' => 'Global color updated successfully']);
            break;
            
        case 'delete_global_color':
            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            
            if ($colorId <= 0) {
                throw new Exception('Valid color ID is required');
            }
            
            // Check if color is in use
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM item_color_assignments WHERE global_color_id = ?");
            $checkStmt->execute([$colorId]);
            $inUse = $checkStmt->fetchColumn() > 0;
            
            if ($inUse) {
                // Soft delete - deactivate instead of deleting
                $stmt = $pdo->prepare("UPDATE global_colors SET is_active = 0 WHERE id = ?");
                $stmt->execute([$colorId]);
                echo json_encode(['success' => true, 'message' => 'Global color deactivated (was in use by items)']);
            } else {
                // Hard delete
                $stmt = $pdo->prepare("DELETE FROM global_colors WHERE id = ?");
                $stmt->execute([$colorId]);
                echo json_encode(['success' => true, 'message' => 'Global color deleted successfully']);
            }
            break;
            
        // ========== GLOBAL SIZES MANAGEMENT ==========
        case 'get_global_sizes':
            $category = $_GET['category'] ?? '';
            $whereClause = "is_active = 1";
            $params = [];
            
            if (!empty($category)) {
                $whereClause .= " AND category = ?";
                $params[] = $category;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, size_name, size_code, category, description, display_order
                FROM global_sizes 
                WHERE $whereClause 
                ORDER BY display_order ASC, size_name ASC
            ");
            $stmt->execute($params);
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'sizes' => $sizes]);
            break;
            
        case 'get_size_categories':
            $stmt = $pdo->query("
                SELECT DISTINCT category 
                FROM global_sizes 
                WHERE is_active = 1 
                ORDER BY category ASC
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'add_global_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeName = trim($data['size_name'] ?? '');
            $sizeCode = trim($data['size_code'] ?? '');
            $category = trim($data['category'] ?? 'General');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);
            
            if (empty($sizeName) || empty($sizeCode)) {
                throw new Exception('Size name and size code are required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO global_sizes (size_name, size_code, category, description, display_order) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sizeName, $sizeCode, $category, $description, $displayOrder]);
            
            $sizeId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Global size added successfully',
                'size_id' => $sizeId
            ]);
            break;
            
        case 'update_global_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);
            $sizeName = trim($data['size_name'] ?? '');
            $sizeCode = trim($data['size_code'] ?? '');
            $category = trim($data['category'] ?? 'General');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            if ($sizeId <= 0 || empty($sizeName) || empty($sizeCode)) {
                throw new Exception('Size ID, size name, and size code are required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE global_sizes 
                SET size_name = ?, size_code = ?, category = ?, description = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$sizeName, $sizeCode, $category, $description, $displayOrder, $isActive, $sizeId]);
            
            echo json_encode(['success' => true, 'message' => 'Global size updated successfully']);
            break;
            
        case 'delete_global_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);
            
            if ($sizeId <= 0) {
                throw new Exception('Valid size ID is required');
            }
            
            // Check if size is in use
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM item_size_assignments WHERE global_size_id = ?
                UNION ALL
                SELECT COUNT(*) FROM item_color_assignments WHERE global_size_id = ?
            ");
            $checkStmt->execute([$sizeId, $sizeId]);
            $inUse = array_sum($checkStmt->fetchAll(PDO::FETCH_COLUMN)) > 0;
            
            if ($inUse) {
                // Soft delete - deactivate instead of deleting
                $stmt = $pdo->prepare("UPDATE global_sizes SET is_active = 0 WHERE id = ?");
                $stmt->execute([$sizeId]);
                echo json_encode(['success' => true, 'message' => 'Global size deactivated (was in use by items)']);
            } else {
                // Hard delete
                $stmt = $pdo->prepare("DELETE FROM global_sizes WHERE id = ?");
                $stmt->execute([$sizeId]);
                echo json_encode(['success' => true, 'message' => 'Global size deleted successfully']);
            }
            break;
            
        // ========== ITEM ASSIGNMENTS MANAGEMENT ==========
        case 'get_item_size_assignments':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT isa.id, isa.item_sku, isa.global_size_id, isa.is_active,
                       gs.size_name, gs.size_code, gs.category, gs.description
                FROM item_size_assignments isa
                JOIN global_sizes gs ON isa.global_size_id = gs.id
                WHERE isa.item_sku = ? AND isa.is_active = 1
                ORDER BY gs.display_order ASC, gs.size_name ASC
            ");
            $stmt->execute([$itemSku]);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'assignments' => $assignments]);
            break;
            
        case 'get_item_color_assignments':
            $itemSku = $_GET['item_sku'] ?? '';
            $sizeId = $_GET['size_id'] ?? null;
            
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $whereClause = "ica.item_sku = ? AND ica.is_active = 1";
            $params = [$itemSku];
            
            if ($sizeId !== null) {
                $whereClause .= " AND ica.global_size_id = ?";
                $params[] = (int)$sizeId;
            }
            
            $stmt = $pdo->prepare("
                SELECT ica.id, ica.item_sku, ica.global_size_id, ica.global_color_id, 
                       ica.stock_level, ica.price_adjustment, ica.is_active,
                       gs.size_name, gs.size_code,
                       gc.color_name, gc.color_code, gc.category as color_category
                FROM item_color_assignments ica
                JOIN global_sizes gs ON ica.global_size_id = gs.id
                JOIN global_colors gc ON ica.global_color_id = gc.id
                WHERE $whereClause
                ORDER BY gs.display_order ASC, gc.display_order ASC, gc.color_name ASC
            ");
            $stmt->execute($params);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'assignments' => $assignments]);
            break;
            
        case 'assign_sizes_to_item':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeIds = $data['size_ids'] ?? [];
            
            if (empty($itemSku) || empty($sizeIds)) {
                throw new Exception('Item SKU and size IDs are required');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Remove existing assignments if replace mode
                if ($data['replace_existing'] ?? false) {
                    $stmt = $pdo->prepare("DELETE FROM item_size_assignments WHERE item_sku = ?");
                    $stmt->execute([$itemSku]);
                }
                
                // Insert new assignments
                $insertStmt = $pdo->prepare("
                    INSERT IGNORE INTO item_size_assignments (item_sku, global_size_id) 
                    VALUES (?, ?)
                ");
                
                foreach ($sizeIds as $sizeId) {
                    $insertStmt->execute([$itemSku, (int)$sizeId]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Sizes assigned to item successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'assign_colors_to_item_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);
            $colorAssignments = $data['color_assignments'] ?? [];
            
            if (empty($itemSku) || $sizeId <= 0 || empty($colorAssignments)) {
                throw new Exception('Item SKU, size ID, and color assignments are required');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Remove existing color assignments for this size if replace mode
                if ($data['replace_existing'] ?? false) {
                    $stmt = $pdo->prepare("DELETE FROM item_color_assignments WHERE item_sku = ? AND global_size_id = ?");
                    $stmt->execute([$itemSku, $sizeId]);
                }
                
                // Insert new color assignments
                $insertStmt = $pdo->prepare("
                    INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    stock_level = VALUES(stock_level),
                    price_adjustment = VALUES(price_adjustment)
                ");
                
                foreach ($colorAssignments as $assignment) {
                    $colorId = (int)($assignment['color_id'] ?? 0);
                    $stockLevel = (int)($assignment['stock_level'] ?? 0);
                    $priceAdjustment = (float)($assignment['price_adjustment'] ?? 0);
                    
                    if ($colorId > 0) {
                        $insertStmt->execute([$itemSku, $sizeId, $colorId, $stockLevel, $priceAdjustment]);
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Colors assigned to item size successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'update_color_assignment_stock':
            $data = json_decode(file_get_contents('php://input'), true);
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);
            
            if ($assignmentId <= 0) {
                throw new Exception('Valid assignment ID is required');
            }
            
            $stmt = $pdo->prepare("UPDATE item_color_assignments SET stock_level = ? WHERE id = ?");
            $stmt->execute([$stockLevel, $assignmentId]);
            
            echo json_encode(['success' => true, 'message' => 'Stock level updated successfully']);
            break;
            
        case 'bulk_update_item_structure':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $structure = $data['structure'] ?? [];
            
            if (empty($itemSku) || empty($structure)) {
                throw new Exception('Item SKU and structure data are required');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Clear existing assignments
                $pdo->prepare("DELETE FROM item_size_assignments WHERE item_sku = ?")->execute([$itemSku]);
                $pdo->prepare("DELETE FROM item_color_assignments WHERE item_sku = ?")->execute([$itemSku]);
                
                // Insert new structure
                $sizeStmt = $pdo->prepare("INSERT INTO item_size_assignments (item_sku, global_size_id) VALUES (?, ?)");
                $colorStmt = $pdo->prepare("INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($structure as $sizeData) {
                    $sizeId = (int)($sizeData['size_id'] ?? 0);
                    if ($sizeId <= 0) continue;
                    
                    // Add size assignment
                    $sizeStmt->execute([$itemSku, $sizeId]);
                    
                    // Add color assignments for this size
                    foreach ($sizeData['colors'] ?? [] as $colorData) {
                        $colorId = (int)($colorData['color_id'] ?? 0);
                        $stockLevel = (int)($colorData['stock_level'] ?? 0);
                        $priceAdjustment = (float)($colorData['price_adjustment'] ?? 0);
                        
                        if ($colorId > 0) {
                            $colorStmt->execute([$itemSku, $sizeId, $colorId, $stockLevel, $priceAdjustment]);
                        }
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Item structure updated successfully']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 