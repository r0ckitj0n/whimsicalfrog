<?php
// Item Colors Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/stock_manager.php';
require_once __DIR__ . '/../includes/response.php';

// Start session for authentication


// Authentication check with local dev bypass
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Local dev/admin bypass: allow admin endpoints when running on localhost or when explicitly requested
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$devBypassHeader = isset($_SERVER['HTTP_X_WF_DEV_ADMIN']) && $_SERVER['HTTP_X_WF_DEV_ADMIN'] === '1';
$devBypassQuery = isset($_GET['wf_dev_admin']) && $_GET['wf_dev_admin'] === '1';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isLocalHost = is_string($hostHeader) && (strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false);
if (!$isAdmin && ($isLocalHost || $devBypassHeader || $devBypassQuery || strpos($referer, '/admin/') !== false)) {
    $isAdmin = true;
}

// Stock sync is handled via includes/stock_manager.php

// (Removed local reduceStockForSale helper to use centralized stock_manager functions)

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

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    switch ($action) {
        case 'get_colors':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $colors = Database::queryAll(
                "SELECT id, item_sku, color_name, color_code, image_path, stock_level, is_active, display_order
                 FROM item_colors 
                 WHERE item_sku = ? AND is_active = 1 
                 ORDER BY display_order ASC, color_name ASC",
                [$itemSku]
            );

            Response::json(['success' => true, 'colors' => $colors]);
            break;

        case 'get_all_colors':
            // Admin only - get all colors for an item including inactive ones
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }

            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $colors = Database::queryAll(
                "SELECT id, item_sku, color_name, color_code, image_path, stock_level, is_active, display_order
                 FROM item_colors 
                 WHERE item_sku = ? 
                 ORDER BY display_order ASC, color_name ASC",
                [$itemSku]
            );

            echo json_encode(['success' => true, 'colors' => $colors]);
            break;

        case 'add_color':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $colorName = trim($data['color_name'] ?? '');
            $colorCode = $data['color_code'] ?? '';
            $imagePath = trim($data['image_path'] ?? '');
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $displayOrder = (int)($data['display_order'] ?? 0);

            if (empty($itemSku) || empty($colorName)) {
                throw new Exception('Item SKU and color name are required');
            }

            // Validate color code format if provided
            if (!empty($colorCode) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCode)) {
                throw new Exception('Invalid color code format. Use #RRGGBB format.');
            }

            $affected = Database::execute(
                "INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$itemSku, $colorName, $colorCode, $imagePath, $stockLevel, $displayOrder]
            );

            $colorId = Database::lastInsertId();

            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);

            Response::updated(['color_id' => $colorId, 'new_total_stock' => $newTotalStock]);
            break;

        case 'add_color_from_global':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $globalColorId = (int)($data['global_color_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $displayOrder = (int)($data['display_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            $imagePath = trim($data['image_path'] ?? '');

            if (empty($itemSku) || $globalColorId <= 0) {
                throw new Exception('Item SKU and global color ID are required');
            }

            // Get global color data
            $globalColor = Database::queryOne("SELECT color_name, color_code FROM global_colors WHERE id = ? AND is_active = 1", [$globalColorId]);

            if (!$globalColor) {
                throw new Exception('Global color not found or inactive');
            }

            // Check if this color already exists for this item
            $exists = Database::queryOne("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND color_code = ?", [$itemSku, $globalColor['color_name'], $globalColor['color_code']]);

            if ($exists) {
                throw new Exception('This color already exists for this item');
            }

            // Add the color from global data
            $affected = Database::execute(
                "INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$itemSku, $globalColor['color_name'], $globalColor['color_code'], $imagePath, $stockLevel, $displayOrder, $isActive]
            );

            $colorId = Database::lastInsertId();

            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);

            Response::updated(['color_id' => $colorId, 'color_name' => $globalColor['color_name'], 'color_code' => $globalColor['color_code'], 'new_total_stock' => $newTotalStock]);
            break;

        case 'update_color':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            $colorName = trim($data['color_name'] ?? '');
            $colorCode = $data['color_code'] ?? '';
            $imagePath = trim($data['image_path'] ?? '');
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $displayOrder = (int)($data['display_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;

            if ($colorId <= 0 || empty($colorName)) {
                throw new Exception('Color ID and color name are required');
            }

            // Validate color code format if provided
            if (!empty($colorCode) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCode)) {
                throw new Exception('Invalid color code format. Use #RRGGBB format.');
            }

            // Get the item SKU for this color
            $row = Database::queryOne("SELECT item_sku FROM item_colors WHERE id = ?", [$colorId]);
            $itemSku = $row ? $row['item_sku'] : null;

            $affected = Database::execute(
                "UPDATE item_colors 
                 SET color_name = ?, color_code = ?, image_path = ?, stock_level = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$colorName, $colorCode, $imagePath, $stockLevel, $displayOrder, $isActive, $colorId]
            );

            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock]);
            } else {
                // Check if color still exists
                $exists = Database::queryOne('SELECT id FROM item_colors WHERE id = ?', [$colorId]);
                if ($exists) {
                    Response::noChanges(['new_total_stock' => $newTotalStock]);
                } else {
                    Response::error('Color not found', null, 404);
                }
            }
            break;

        case 'delete_color':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            // Handle both JSON body and form data
            $input = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($input['color_id'] ?? $_POST['color_id'] ?? $_GET['color_id'] ?? 0);

            if ($colorId <= 0) {
                throw new Exception('Valid color ID is required');
            }

            // Get the item SKU before deleting
            $row = Database::queryOne("SELECT item_sku FROM item_colors WHERE id = ?", [$colorId]);
            $itemSku = $row['item_sku'] ?? null;

            $affected = Database::execute("DELETE FROM item_colors WHERE id = ?", [$colorId]);

            // Sync total stock with remaining color quantities
            if ($itemSku) {
                $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            }

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock ?? 0]);
            } else {
                Response::error('Color not found', null, 404);
            }
            break;

        case 'update_stock':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);

            if ($colorId <= 0) {
                throw new Exception('Valid color ID is required');
            }

            // Get the item SKU for this color
            $row = Database::queryOne("SELECT item_sku FROM item_colors WHERE id = ?", [$colorId]);
            $itemSku = $row['item_sku'] ?? null;

            $affected = Database::execute("UPDATE item_colors SET stock_level = ? WHERE id = ?", [$stockLevel, $colorId]);

            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock]);
            } else {
                $exists = Database::queryOne('SELECT id FROM item_colors WHERE id = ?', [$colorId]);
                if ($exists) {
                    Response::noChanges(['new_total_stock' => $newTotalStock]);
                } else {
                    Response::error('Color not found', null, 404);
                }
            }
            break;

        case 'update_color_code':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            $colorCode = $data['color_code'] ?? '';

            if ($colorId <= 0 || empty($colorCode)) {
                throw new Exception('Color ID and color code are required');
            }
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorCode)) {
                throw new Exception('Invalid color code format. Use #RRGGBB format.');
            }

            // Update only the color code to avoid overwriting other fields
            $affected = Database::execute("UPDATE item_colors SET color_code = ? WHERE id = ?", [$colorCode, $colorId]);
            if ($affected > 0) {
                Response::updated(['color_id' => $colorId, 'color_code' => $colorCode]);
            } else {
                $exists = Database::queryOne('SELECT id FROM item_colors WHERE id = ?', [$colorId]);
                if ($exists) {
                    Response::noChanges(['color_id' => $colorId, 'color_code' => $colorCode]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Color not found']);
                }
            }
            break;

        case 'update_color_name':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);
            $colorName = trim($data['color_name'] ?? '');

            if ($colorId <= 0 || $colorName === '') {
                throw new Exception('Color ID and color name are required');
            }

            $affected = Database::execute("UPDATE item_colors SET color_name = ? WHERE id = ?", [$colorName, $colorId]);
            if ($affected > 0) {
                Response::updated(['color_id' => $colorId, 'color_name' => $colorName]);
            } else {
                $exists = Database::queryOne('SELECT id FROM item_colors WHERE id = ?', [$colorId]);
                if ($exists) {
                    Response::noChanges(['color_id' => $colorId, 'color_name' => $colorName]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Color not found']);
                }
            }
            break;

        case 'reduce_stock_for_sale':
            // This action can be called during checkout to reduce both color and total stock
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $colorName = $data['color_name'] ?? '';
            $quantity = (int)($data['quantity'] ?? 1);

            if (empty($itemSku) || $quantity <= 0) {
                throw new Exception('Item SKU and valid quantity are required');
            }

            // Use centralized stock manager: reduce by size/color/general
            $success = reduceStockForSale($pdo, $itemSku, $quantity, $colorName, null);

            if ($success) {
                Response::json([
                    'success' => true,
                    'message' => 'Stock reduced successfully for sale'
                ]);
            } else {
                throw new Exception('Failed to reduce stock for sale');
            }
            break;

        case 'sync_stock':
            // Manual stock synchronization
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';

            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);

            if ($newTotalStock !== false) {
                Response::json([
                    'success' => true,
                    'message' => 'Stock synchronized successfully',
                    'new_total_stock' => $newTotalStock
                ]);
            } else {
                throw new Exception('Failed to synchronize stock');
            }
            break;

        case 'check_availability':
            $itemSku = $_GET['item_sku'] ?? '';
            $colorName = $_GET['color_name'] ?? '';
            $quantity = (int)($_GET['quantity'] ?? 1);

            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            if (empty($colorName)) {
                // No color specified, check if item has colors
                $result = Database::queryOne("SELECT COUNT(*) as color_count FROM item_colors WHERE item_sku = ? AND is_active = 1", [$itemSku]);

                if ($result['color_count'] > 0) {
                    Response::json([
                        'success' => true,
                        'has_colors' => true,
                        'requires_color_selection' => true,
                        'message' => 'This item requires color selection'
                    ]);
                } else {
                    // No colors, check main item stock
                    $item = Database::queryOne("SELECT stockLevel FROM items WHERE sku = ?", [$itemSku]);

                    $available = $item && $item['stockLevel'] >= $quantity;
                    Response::json([
                        'success' => true,
                        'has_colors' => false,
                        'available' => $available,
                        'stock_level' => $item['stockLevel'] ?? 0
                    ]);
                }
            } else {
                // Check specific color availability
                $color = Database::queryOne("SELECT stock_level FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1", [$itemSku, $colorName]);

                if ($color) {
                    $available = $color['stock_level'] >= $quantity;
                    Response::json([
                        'success' => true,
                        'has_colors' => true,
                        'available' => $available,
                        'stock_level' => $color['stock_level']
                    ]);
                } else {
                    Response::json([
                        'success' => false,
                        'message' => 'Color not found or not available'
                    ]);
                }
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
