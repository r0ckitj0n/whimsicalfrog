<?php
// Item Assignment Management API - Assign global colors and sizes to specific items
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

    // Parse action from GET, POST, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // If no action found in GET/POST, try parsing from JSON body
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }

    switch ($action) {
        // ========== ITEM STRUCTURE MANAGEMENT ==========
        case 'get_item_structure':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            // Get item info
            $itemStmt = $pdo->prepare("SELECT sku, name, category FROM items WHERE sku = ?");
            $itemStmt->execute([$itemSku]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception('Item not found');
            }

            // Get assigned sizes
            $sizesStmt = $pdo->prepare("
                SELECT isa.id as assignment_id, isa.global_size_id, gs.size_name, gs.size_code, gs.category
                FROM item_size_assignments isa
                JOIN global_sizes gs ON isa.global_size_id = gs.id
                WHERE isa.item_sku = ? AND isa.is_active = 1
                ORDER BY gs.display_order ASC, gs.size_name ASC
            ");
            $sizesStmt->execute([$itemSku]);
            $sizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get color assignments for each size
            foreach ($sizes as &$size) {
                $colorsStmt = $pdo->prepare("
                    SELECT ica.id as assignment_id, ica.global_color_id, ica.stock_level, ica.price_adjustment,
                           gc.color_name, gc.color_code, gc.category
                    FROM item_color_assignments ica
                    JOIN global_colors gc ON ica.global_color_id = gc.id
                    WHERE ica.item_sku = ? AND ica.global_size_id = ? AND ica.is_active = 1
                    ORDER BY gc.display_order ASC, gc.color_name ASC
                ");
                $colorsStmt->execute([$itemSku, $size['global_size_id']]);
                $size['colors'] = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'success' => true,
                'item' => $item,
                'structure' => $sizes
            ]);
            break;

        case 'get_available_sizes':
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

        case 'get_available_colors':
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

        case 'assign_size_to_item':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);

            if (empty($itemSku) || $sizeId <= 0) {
                throw new Exception('Item SKU and size ID are required');
            }

            // Check if assignment already exists
            $checkStmt = $pdo->prepare("
                SELECT id FROM item_size_assignments 
                WHERE item_sku = ? AND global_size_id = ?
            ");
            $checkStmt->execute([$itemSku, $sizeId]);

            if ($checkStmt->fetchColumn()) {
                throw new Exception('Size already assigned to this item');
            }

            // Insert size assignment
            $insertStmt = $pdo->prepare("
                INSERT INTO item_size_assignments (item_sku, global_size_id) 
                VALUES (?, ?)
            ");
            $insertStmt->execute([$itemSku, $sizeId]);

            echo json_encode(['success' => true, 'message' => 'Size assigned successfully']);
            break;

        case 'remove_size_from_item':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);

            if (empty($itemSku) || $sizeId <= 0) {
                throw new Exception('Item SKU and size ID are required');
            }

            $pdo->beginTransaction();

            try {
                // Remove color assignments for this size
                $deleteColorsStmt = $pdo->prepare("
                    DELETE FROM item_color_assignments 
                    WHERE item_sku = ? AND global_size_id = ?
                ");
                $deleteColorsStmt->execute([$itemSku, $sizeId]);

                // Remove size assignment
                $deleteSizeStmt = $pdo->prepare("
                    DELETE FROM item_size_assignments 
                    WHERE item_sku = ? AND global_size_id = ?
                ");
                $deleteSizeStmt->execute([$itemSku, $sizeId]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Size and associated colors removed successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'assign_color_to_item_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);
            $colorId = (int)($data['color_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0);

            if (empty($itemSku) || $sizeId <= 0 || $colorId <= 0) {
                throw new Exception('Item SKU, size ID, and color ID are required');
            }

            // Check if color assignment already exists
            $checkStmt = $pdo->prepare("
                SELECT id FROM item_color_assignments 
                WHERE item_sku = ? AND global_size_id = ? AND global_color_id = ?
            ");
            $checkStmt->execute([$itemSku, $sizeId, $colorId]);

            if ($checkStmt->fetchColumn()) {
                throw new Exception('Color already assigned to this item/size combination');
            }

            // Insert color assignment
            $insertStmt = $pdo->prepare("
                INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$itemSku, $sizeId, $colorId, $stockLevel, $priceAdjustment]);

            echo json_encode(['success' => true, 'message' => 'Color assigned successfully']);
            break;

        case 'remove_color_from_item_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);
            $colorId = (int)($data['color_id'] ?? 0);

            if (empty($itemSku) || $sizeId <= 0 || $colorId <= 0) {
                throw new Exception('Item SKU, size ID, and color ID are required');
            }

            // Remove color assignment
            $deleteStmt = $pdo->prepare("
                DELETE FROM item_color_assignments 
                WHERE item_sku = ? AND global_size_id = ? AND global_color_id = ?
            ");
            $deleteStmt->execute([$itemSku, $sizeId, $colorId]);

            echo json_encode(['success' => true, 'message' => 'Color removed successfully']);
            break;

        case 'update_color_assignment':
            $data = json_decode(file_get_contents('php://input'), true);
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0);

            if ($assignmentId <= 0) {
                throw new Exception('Assignment ID is required');
            }

            // Update color assignment
            $updateStmt = $pdo->prepare("
                UPDATE item_color_assignments 
                SET stock_level = ?, price_adjustment = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([$stockLevel, $priceAdjustment, $assignmentId]);

            echo json_encode(['success' => true, 'message' => 'Assignment updated successfully']);
            break;

        case 'bulk_assign_colors_to_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeId = (int)($data['size_id'] ?? 0);
            $colorIds = $data['color_ids'] ?? [];
            $defaultStock = (int)($data['default_stock'] ?? 0);
            $defaultPriceAdjustment = (float)($data['default_price_adjustment'] ?? 0);

            if (empty($itemSku) || $sizeId <= 0 || empty($colorIds)) {
                throw new Exception('Item SKU, size ID, and color IDs are required');
            }

            $pdo->beginTransaction();

            try {
                $insertStmt = $pdo->prepare("
                    INSERT IGNORE INTO item_color_assignments 
                    (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) 
                    VALUES (?, ?, ?, ?, ?)
                ");

                $assignedCount = 0;
                foreach ($colorIds as $colorId) {
                    $colorId = (int)$colorId;
                    if ($colorId > 0) {
                        $insertStmt->execute([$itemSku, $sizeId, $colorId, $defaultStock, $defaultPriceAdjustment]);
                        if ($insertStmt->rowCount() > 0) {
                            $assignedCount++;
                        }
                    }
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'message' => "Successfully assigned $assignedCount colors to size",
                    'assigned_count' => $assignedCount
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'copy_structure_from_item':
            $data = json_decode(file_get_contents('php://input'), true);
            $sourceItemSku = $data['source_item_sku'] ?? '';
            $targetItemSku = $data['target_item_sku'] ?? '';
            $copyStock = $data['copy_stock'] ?? false;

            if (empty($sourceItemSku) || empty($targetItemSku)) {
                throw new Exception('Source and target item SKUs are required');
            }

            $pdo->beginTransaction();

            try {
                // Clear existing assignments for target item
                $pdo->prepare("DELETE FROM item_color_assignments WHERE item_sku = ?")->execute([$targetItemSku]);
                $pdo->prepare("DELETE FROM item_size_assignments WHERE item_sku = ?")->execute([$targetItemSku]);

                // Copy size assignments
                $copySizesStmt = $pdo->prepare("
                    INSERT INTO item_size_assignments (item_sku, global_size_id)
                    SELECT ?, global_size_id
                    FROM item_size_assignments
                    WHERE item_sku = ? AND is_active = 1
                ");
                $copySizesStmt->execute([$targetItemSku, $sourceItemSku]);

                // Copy color assignments
                if ($copyStock) {
                    $copyColorsStmt = $pdo->prepare("
                        INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment)
                        SELECT ?, global_size_id, global_color_id, stock_level, price_adjustment
                        FROM item_color_assignments
                        WHERE item_sku = ? AND is_active = 1
                    ");
                } else {
                    $copyColorsStmt = $pdo->prepare("
                        INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment)
                        SELECT ?, global_size_id, global_color_id, 0, price_adjustment
                        FROM item_color_assignments
                        WHERE item_sku = ? AND is_active = 1
                    ");
                }
                $copyColorsStmt->execute([$targetItemSku, $sourceItemSku]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Structure copied successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'get_total_stock_for_item':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            // Calculate total stock from all color assignments
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(stock_level), 0) as total_stock
                FROM item_color_assignments 
                WHERE item_sku = ? AND is_active = 1
            ");
            $stmt->execute([$itemSku]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'total_stock' => (int)$result['total_stock']
            ]);
            break;

        case 'sync_item_stock':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';

            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            // Calculate total stock from all color assignments
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(stock_level), 0) as total_stock
                FROM item_color_assignments 
                WHERE item_sku = ? AND is_active = 1
            ");
            $stmt->execute([$itemSku]);
            $totalStock = $stmt->fetchColumn();

            // Update main item stock
            $updateStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
            $updateStmt->execute([$totalStock, $itemSku]);

            echo json_encode([
                'success' => true,
                'message' => 'Item stock synchronized successfully',
                'total_stock' => (int)$totalStock
            ]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 