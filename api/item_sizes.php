<?php
// Item Sizes Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication
session_start();

// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Function to sync color stock with its sizes
function syncColorStockWithSizes($pdo, $colorId) {
    try {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock
            FROM item_sizes 
            WHERE color_id = ? AND is_active = 1
        ");
        $stmt->execute([$colorId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSizeStock = $result['total_size_stock'];
        
        // Update the color's stock level
        $updateStmt = $pdo->prepare("UPDATE item_colors SET stock_level = ? WHERE id = ?");
        $updateStmt->execute([$totalSizeStock, $colorId]);
        
        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing color stock for color ID $colorId: " . $e->getMessage());
        return false;
    }
}

// Function to sync total item stock with all sizes
function syncTotalStockWithSizes($pdo, $itemSku) {
    try {
        // Calculate total stock from all active sizes
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(stock_level), 0) as total_size_stock
            FROM item_sizes 
            WHERE item_sku = ? AND is_active = 1
        ");
        $stmt->execute([$itemSku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSizeStock = $result['total_size_stock'];
        
        // Update the main item's stock level
        $updateStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
        $updateStmt->execute([$totalSizeStock, $itemSku]);
        
        // Also sync color stocks if there are color-specific sizes
        $colorStmt = $pdo->prepare("
            SELECT DISTINCT color_id 
            FROM item_sizes 
            WHERE item_sku = ? AND color_id IS NOT NULL
        ");
        $colorStmt->execute([$itemSku]);
        $colorIds = $colorStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($colorIds as $colorId) {
            syncColorStockWithSizes($pdo, $colorId);
        }
        
        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

// Function to reduce stock for a sale
function reduceStockForSale($pdo, $itemSku, $colorId, $sizeCode, $quantity) {
    try {
        $pdo->beginTransaction();
        
        // Find the specific size record
        $whereClause = "item_sku = ? AND size_code = ? AND is_active = 1";
        $params = [$itemSku, $sizeCode];
        
        if ($colorId) {
            $whereClause .= " AND color_id = ?";
            $params[] = $colorId;
        } else {
            $whereClause .= " AND color_id IS NULL";
        }
        
        // Reduce size-specific stock
        $stmt = $pdo->prepare("
            UPDATE item_sizes 
            SET stock_level = GREATEST(stock_level - ?, 0) 
            WHERE $whereClause
        ");
        $stmt->execute(array_merge([$quantity], $params));
        
        // Sync stock levels
        if ($colorId) {
            syncColorStockWithSizes($pdo, $colorId);
        }
        syncTotalStockWithSizes($pdo, $itemSku);
        
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
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    switch ($action) {
        case 'get_sizes':
            $itemSku = $_GET['item_sku'] ?? '';
            $colorId = $_GET['color_id'] ?? null;
            
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $whereClause = "s.item_sku = ? AND s.is_active = 1";
            $params = [$itemSku];
            
            if ($colorId !== null) {
                if ($colorId === '0' || $colorId === 'null') {
                    // Get general sizes (no color association)
                    $whereClause .= " AND s.color_id IS NULL";
                } else {
                    // Get sizes for specific color
                    $whereClause .= " AND s.color_id = ?";
                    $params[] = (int)$colorId;
                }
            }
            
            $stmt = $pdo->prepare("
                SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code, 
                       s.stock_level, s.price_adjustment, s.is_active, s.display_order,
                       c.color_name, c.color_code
                FROM item_sizes s
                LEFT JOIN item_colors c ON s.color_id = c.id
                WHERE $whereClause 
                ORDER BY s.display_order ASC, s.size_name ASC
            ");
            $stmt->execute($params);
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'sizes' => $sizes]);
            break;
            
        case 'get_all_sizes':
            // Admin only - get all sizes for an item including inactive ones
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
                SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code, 
                       s.stock_level, s.price_adjustment, s.is_active, s.display_order,
                       c.color_name, c.color_code
                FROM item_sizes s
                LEFT JOIN item_colors c ON s.color_id = c.id
                WHERE s.item_sku = ? 
                ORDER BY s.color_id ASC, s.display_order ASC, s.size_name ASC
            ");
            $stmt->execute([$itemSku]);
            $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'sizes' => $sizes]);
            break;
            
        case 'add_size':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $colorId = !empty($data['color_id']) ? (int)$data['color_id'] : null;
            $sizeName = trim($data['size_name'] ?? '');
            $sizeCode = trim($data['size_code'] ?? '');
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0.00);
            $displayOrder = (int)($data['display_order'] ?? 0);
            
            if (empty($itemSku) || empty($sizeName) || empty($sizeCode)) {
                throw new Exception('Item SKU, size name, and size code are required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$itemSku, $colorId, $sizeName, $sizeCode, $stockLevel, $priceAdjustment, $displayOrder]);
            
            $sizeId = $pdo->lastInsertId();
            
            // Sync stock levels
            if ($colorId) {
                syncColorStockWithSizes($pdo, $colorId);
            }
            $newTotalStock = syncTotalStockWithSizes($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Size added successfully',
                'size_id' => $sizeId,
                'new_total_stock' => $newTotalStock
            ]);
            break;
            
        case 'update_size':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);
            $sizeName = trim($data['size_name'] ?? '');
            $sizeCode = trim($data['size_code'] ?? '');
            $stockLevel = (int)($data['stock_level'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0.00);
            $displayOrder = (int)($data['display_order'] ?? 0);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            if ($sizeId <= 0 || empty($sizeName) || empty($sizeCode)) {
                throw new Exception('Size ID, size name, and size code are required');
            }
            
            // Get the current size info for stock sync
            $currentStmt = $pdo->prepare("SELECT item_sku, color_id FROM item_sizes WHERE id = ?");
            $currentStmt->execute([$sizeId]);
            $currentSize = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                UPDATE item_sizes 
                SET size_name = ?, size_code = ?, stock_level = ?, price_adjustment = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$sizeName, $sizeCode, $stockLevel, $priceAdjustment, $displayOrder, $isActive, $sizeId]);
            
            // Sync stock levels
            if ($currentSize['color_id']) {
                syncColorStockWithSizes($pdo, $currentSize['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes($pdo, $currentSize['item_sku']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Size updated successfully',
                'new_total_stock' => $newTotalStock
            ]);
            break;
            
        case 'delete_size':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);
            
            if ($sizeId <= 0) {
                throw new Exception('Size ID is required');
            }
            
            // Get size info before deletion for stock sync
            $sizeStmt = $pdo->prepare("SELECT item_sku, color_id FROM item_sizes WHERE id = ?");
            $sizeStmt->execute([$sizeId]);
            $sizeInfo = $sizeStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sizeInfo) {
                throw new Exception('Size not found');
            }
            
            $stmt = $pdo->prepare("DELETE FROM item_sizes WHERE id = ?");
            $stmt->execute([$sizeId]);
            
            // Sync stock levels
            if ($sizeInfo['color_id']) {
                syncColorStockWithSizes($pdo, $sizeInfo['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes($pdo, $sizeInfo['item_sku']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Size deleted successfully',
                'new_total_stock' => $newTotalStock
            ]);
            break;
            
        case 'sync_stock':
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
            
            $newTotalStock = syncTotalStockWithSizes($pdo, $itemSku);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stock levels synchronized successfully',
                'new_total_stock' => $newTotalStock
            ]);
            break;
            
        case 'get_size_options':
            // Get available size options for dropdown
            $sizeOptions = [
                ['code' => 'XS', 'name' => 'Extra Small'],
                ['code' => 'S', 'name' => 'Small'],
                ['code' => 'M', 'name' => 'Medium'],
                ['code' => 'L', 'name' => 'Large'],
                ['code' => 'XL', 'name' => 'Extra Large'],
                ['code' => 'XXL', 'name' => 'Double XL'],
                ['code' => 'XXXL', 'name' => 'Triple XL'],
                ['code' => 'OS', 'name' => 'One Size']
            ];
            
            echo json_encode(['success' => true, 'options' => $sizeOptions]);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 