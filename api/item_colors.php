<?php
// Item Colors Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication
session_start();

// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Function to sync total stock with color quantities
function syncTotalStockWithColors($pdo, $itemSku) {
    try {
        // Calculate total stock from all active colors
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_color_stock
            FROM item_colors 
            WHERE item_sku = ? AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalColorStock = $result['total_color_stock'];
        
        // Update the main item's stock level
        $updateStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
        $updateStmt->execute([$totalColorStock, $itemSku]);
        
        return $totalColorStock;
    } catch (Exception $e) {
        error_log("Error syncing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

// Function to reduce stock for a sale (both color and total stock)
function reduceStockForSale($pdo, $itemSku, $colorName, $quantity) {
    try {
        $pdo->beginTransaction();
        
        if (!empty($colorName)) {
            // Reduce color-specific stock
            $stmt = $pdo->prepare("
                UPDATE item_colors 
                SET stock_level = GREATEST(stock_level - ?, 0) 
                WHERE item_sku = ? AND color_name = ? AND is_active = 1
            ");
            $stmt->execute([$quantity, $itemSku, $colorName]);
            
            // Sync total stock with color quantities
            syncTotalStockWithColors($pdo, $itemSku);
        } else {
            // No color specified, reduce total stock only
            $stmt = $pdo->prepare("
                UPDATE items 
                SET stockLevel = GREATEST(stockLevel - ?, 0) 
                WHERE sku = ?
            ");
            $stmt->execute([$quantity, $itemSku]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error reducing stock for $itemSku: " . $e->getMessage());
        return false;
    }
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
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    switch ($action) {
        case 'get_colors':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT id, item_sku, color_name, color_code, image_path, stock_level, is_active, display_order
                FROM item_colors 
                WHERE item_sku = ? AND is_active = 1 
                ORDER BY display_order ASC, color_name ASC
            ");
            $stmt->execute([$itemSku]);
            $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'colors' => $colors]);
            break;
            
        case 'get_all_colors':
            // Admin only - get all colors for an item including inactive ones
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT id, item_sku, color_name, color_code, image_path, stock_level, is_active, display_order
                FROM item_colors 
                WHERE item_sku = ? 
                ORDER BY display_order ASC, color_name ASC
            ");
            $stmt->execute([$itemSku]);
            $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
            
            $stmt = $pdo->prepare("
                INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$itemSku, $colorName, $colorCode, $imagePath, $stockLevel, $displayOrder]);
            
            $colorId = $pdo->lastInsertId();
            
            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Color added successfully',
                'color_id' => $colorId,
                'new_total_stock' => $newTotalStock
            ]);
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
            $globalStmt = $pdo->prepare("SELECT color_name, color_code FROM global_colors WHERE id = ? AND is_active = 1");
            $globalStmt->execute([$globalColorId]);
            $globalColor = $globalStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$globalColor) {
                throw new Exception('Global color not found or inactive');
            }
            
            // Check if this color already exists for this item
            $checkStmt = $pdo->prepare("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND color_code = ?");
            $checkStmt->execute([$itemSku, $globalColor['color_name'], $globalColor['color_code']]);
            
            if ($checkStmt->fetch()) {
                throw new Exception('This color already exists for this item');
            }
            
            // Add the color from global data
            $stmt = $pdo->prepare("
                INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$itemSku, $globalColor['color_name'], $globalColor['color_code'], $imagePath, $stockLevel, $displayOrder, $isActive]);
            
            $colorId = $pdo->lastInsertId();
            
            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Color added from global successfully',
                'color_id' => $colorId,
                'color_name' => $globalColor['color_name'],
                'color_code' => $globalColor['color_code'],
                'new_total_stock' => $newTotalStock
            ]);
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
            $skuStmt = $pdo->prepare("SELECT item_sku FROM item_colors WHERE id = ?");
            $skuStmt->execute([$colorId]);
            $itemSku = $skuStmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                UPDATE item_colors 
                SET color_name = ?, color_code = ?, image_path = ?, stock_level = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$colorName, $colorCode, $imagePath, $stockLevel, $displayOrder, $isActive, $colorId]);
            
            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Color updated successfully',
                'new_total_stock' => $newTotalStock
            ]);
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
            $skuStmt = $pdo->prepare("SELECT item_sku FROM item_colors WHERE id = ?");
            $skuStmt->execute([$colorId]);
            $itemSku = $skuStmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM item_colors WHERE id = ?");
            $stmt->execute([$colorId]);
            
            // Sync total stock with remaining color quantities
            if ($itemSku) {
                $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Color deleted successfully',
                'new_total_stock' => $newTotalStock ?? 0
            ]);
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
            $skuStmt = $pdo->prepare("SELECT item_sku FROM item_colors WHERE id = ?");
            $skuStmt->execute([$colorId]);
            $itemSku = $skuStmt->fetchColumn();
            
            $stmt = $pdo->prepare("UPDATE item_colors SET stock_level = ? WHERE id = ?");
            $stmt->execute([$stockLevel, $colorId]);
            
            // Sync total stock with color quantities
            $newTotalStock = syncTotalStockWithColors($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stock level updated successfully',
                'new_total_stock' => $newTotalStock
            ]);
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
            
            $success = reduceStockForSale($pdo, $itemSku, $colorName, $quantity);
            
            if ($success) {
                echo json_encode([
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
                echo json_encode([
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
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as color_count
                    FROM item_colors 
                    WHERE item_sku = ? AND is_active = 1
                ");
                $stmt->execute([$itemSku]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['color_count'] > 0) {
                    echo json_encode([
                        'success' => true, 
                        'has_colors' => true,
                        'requires_color_selection' => true,
                        'message' => 'This item requires color selection'
                    ]);
                } else {
                    // No colors, check main item stock
                    $stmt = $pdo->prepare("SELECT stockLevel FROM items WHERE sku = ?");
                    $stmt->execute([$itemSku]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $available = $item && $item['stockLevel'] >= $quantity;
                    echo json_encode([
                        'success' => true, 
                        'has_colors' => false,
                        'available' => $available,
                        'stock_level' => $item['stockLevel'] ?? 0
                    ]);
                }
            } else {
                // Check specific color availability
                $stmt = $pdo->prepare("
                    SELECT stock_level
                    FROM item_colors 
                    WHERE item_sku = ? AND color_name = ? AND is_active = 1
                ");
                $stmt->execute([$itemSku, $colorName]);
                $color = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($color) {
                    $available = $color['stock_level'] >= $quantity;
                    echo json_encode([
                        'success' => true, 
                        'has_colors' => true,
                        'available' => $available,
                        'stock_level' => $color['stock_level']
                    ]);
                } else {
                    echo json_encode([
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