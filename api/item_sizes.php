<?php
// Item Sizes Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication


// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Function to sync color stock with its sizes
function syncColorStockWithSizes($colorId)
{
    try {
        $row = Database::queryOne(
            "SELECT COALESCE(SUM(stock_level), 0) as total_size_stock FROM item_sizes WHERE color_id = ? AND is_active = 1",
            [$colorId]
        );
        $totalSizeStock = $row['total_size_stock'] ?? 0;

        // Update the color's stock level
        Database::execute("UPDATE item_colors SET stock_level = ? WHERE id = ?", [$totalSizeStock, $colorId]);

        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing color stock for color ID $colorId: " . $e->getMessage());
        return false;
    }
}

// Function to sync total item stock with all sizes
function syncTotalStockWithSizes($itemSku)
{
    try {
        // Calculate total stock from all active sizes
        $row = Database::queryOne(
            "SELECT COALESCE(SUM(stock_level), 0) as total_size_stock FROM item_sizes WHERE item_sku = ? AND is_active = 1",
            [$itemSku]
        );
        $totalSizeStock = $row['total_size_stock'] ?? 0;

        // Update the main item's stock level
        Database::execute("UPDATE items SET stockLevel = ? WHERE sku = ?", [$totalSizeStock, $itemSku]);

        // Also sync color stocks if there are color-specific sizes
        $colorIds = array_column(
            Database::queryAll("SELECT DISTINCT color_id FROM item_sizes WHERE item_sku = ? AND color_id IS NOT NULL", [$itemSku]),
            'color_id'
        );

        foreach ($colorIds as $colorId) {
            syncColorStockWithSizes($colorId);
        }

        return $totalSizeStock;
    } catch (Exception $e) {
        error_log("Error syncing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

// Function to reduce stock for a sale
function reduceStockForSale($itemSku, $colorId, $sizeCode, $quantity)
{
    try {
        Database::beginTransaction();

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
        Database::execute(
            "UPDATE item_sizes SET stock_level = GREATEST(stock_level - ?, 0) WHERE $whereClause",
            array_merge([$quantity], $params)
        );

        // Sync stock levels
        if ($colorId) {
            syncColorStockWithSizes($colorId);
        }
        syncTotalStockWithSizes($itemSku);

        Database::commit();
        return true;
    } catch (Exception $e) {
        Database::rollBack();
        error_log("Error reducing stock for $itemSku: " . $e->getMessage());
        return false;
    }
}

try {
    try {
        Database::getInstance();
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

            $sizes = Database::queryAll("
                SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code, 
                       s.stock_level, s.price_adjustment, s.is_active, s.display_order,
                       c.color_name, c.color_code
                FROM item_sizes s
                LEFT JOIN item_colors c ON s.color_id = c.id
                WHERE $whereClause 
                ORDER BY s.display_order ASC, s.size_name ASC
            ", $params);

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

            $sizes = Database::queryAll("
                SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code, 
                       s.stock_level, s.price_adjustment, s.is_active, s.display_order,
                       c.color_name, c.color_code
                FROM item_sizes s
                LEFT JOIN item_colors c ON s.color_id = c.id
                WHERE s.item_sku = ? 
                ORDER BY s.color_id ASC, s.display_order ASC, s.size_name ASC
            ", [$itemSku]);

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
            $initialStock = (int)($data['initial_stock'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0.00);
            $displayOrder = (int)($data['display_order'] ?? 0);

            if (empty($itemSku) || empty($sizeName) || empty($sizeCode)) {
                throw new Exception('Item SKU, size name, and size code are required');
            }

            Database::execute(
                "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$itemSku, $colorId, $sizeName, $sizeCode, $initialStock, $priceAdjustment, $displayOrder]
            );

            $sizeId = Database::lastInsertId();

            // Sync stock levels
            if ($colorId) {
                syncColorStockWithSizes($colorId);
            }
            $newTotalStock = syncTotalStockWithSizes($itemSku);

            echo json_encode([
                'success' => true,
                'message' => 'Size added successfully',
                'size_id' => $sizeId,
                'new_total_stock' => $newTotalStock
            ]);
            break;

        case 'add_size_from_global':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $globalSizeId = (int)($data['global_size_id'] ?? 0);
            $colorId = !empty($data['color_id']) ? (int)$data['color_id'] : null;
            $initialStock = (int)($data['initial_stock'] ?? 0);
            $priceAdjustment = (float)($data['price_adjustment'] ?? 0.00);
            $displayOrder = (int)($data['display_order'] ?? 0);

            if (empty($itemSku) || $globalSizeId <= 0) {
                throw new Exception('Item SKU and global size ID are required');
            }

            // Get global size data
            $globalSize = Database::queryOne("SELECT size_name, size_code FROM global_sizes WHERE id = ? AND is_active = 1", [$globalSizeId]);

            if (!$globalSize) {
                throw new Exception('Global size not found or inactive');
            }

            // Check if this size already exists for this item (and color if specified)
            $checkWhere = "item_sku = ? AND size_name = ? AND size_code = ?";
            $checkParams = [$itemSku, $globalSize['size_name'], $globalSize['size_code']];

            if ($colorId) {
                $checkWhere .= " AND color_id = ?";
                $checkParams[] = $colorId;
            } else {
                $checkWhere .= " AND color_id IS NULL";
            }

            $exists = Database::queryOne("SELECT id FROM item_sizes WHERE $checkWhere", $checkParams);
            if ($exists) {
                echo json_encode(['success' => false, 'message' => 'This size already exists for the specified item/color.']);
                break;
            }

            // Add the size from global data
            Database::execute(
                "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                [$itemSku, $colorId, $globalSize['size_name'], $globalSize['size_code'], $initialStock, $priceAdjustment, $displayOrder]
            );

            $sizeId = Database::lastInsertId();

            // Sync stock levels
            if ($colorId) {
                syncColorStockWithSizes($colorId);
            }
            $newTotalStock = syncTotalStockWithSizes($itemSku);

            echo json_encode([
                'success' => true,
                'message' => 'Size added from global successfully',
                'size_id' => $sizeId,
                'size_name' => $globalSize['size_name'],
                'size_code' => $globalSize['size_code'],
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
            $currentSize = Database::queryOne("SELECT item_sku, color_id FROM item_sizes WHERE id = ?", [$sizeId]);

            Database::execute(
                "UPDATE item_sizes 
                 SET size_name = ?, size_code = ?, stock_level = ?, price_adjustment = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$sizeName, $sizeCode, $stockLevel, $priceAdjustment, $displayOrder, $isActive, $sizeId]
            );

            // Sync stock levels
            if (!empty($currentSize['color_id'])) {
                syncColorStockWithSizes($currentSize['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes($currentSize['item_sku']);

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
            $sizeInfo = Database::queryOne("SELECT item_sku, color_id FROM item_sizes WHERE id = ?", [$sizeId]);

            if (!$sizeInfo) {
                throw new Exception('Size not found');
            }

            Database::execute("DELETE FROM item_sizes WHERE id = ?", [$sizeId]);

            // Sync stock levels
            if (!empty($sizeInfo['color_id'])) {
                syncColorStockWithSizes($sizeInfo['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes($sizeInfo['item_sku']);

            echo json_encode([
                'success' => true,
                'message' => 'Size deleted successfully',
                'new_total_stock' => $newTotalStock
            ]);
            break;

        case 'update_stock':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);
            $stockLevel = (int)($data['stock_level'] ?? 0);

            if ($sizeId <= 0) {
                throw new Exception('Size ID is required');
            }

            // Get the current size info for stock sync
            $currentSize = Database::queryOne("SELECT item_sku, color_id FROM item_sizes WHERE id = ?", [$sizeId]);

            if (!$currentSize) {
                throw new Exception('Size not found');
            }

            // Update only the stock level
            Database::execute("UPDATE item_sizes SET stock_level = ? WHERE id = ?", [$stockLevel, $sizeId]);

            // Sync stock levels
            if ($currentSize['color_id']) {
                syncColorStockWithSizes($currentSize['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes($currentSize['item_sku']);

            echo json_encode([
                'success' => true,
                'message' => 'Size stock updated successfully',
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

            $newTotalStock = syncTotalStockWithSizes($itemSku);

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

        case 'delete_size_by_name':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeName = trim($data['size_name'] ?? '');

            if (empty($itemSku) || empty($sizeName)) {
                throw new Exception('Item SKU and size name are required');
            }

            // Get all size records for this size name to sync stock levels afterwards
            $affectedColorIds = array_column(
                Database::queryAll("SELECT DISTINCT color_id FROM item_sizes WHERE item_sku = ? AND size_name = ?", [$itemSku, $sizeName]),
                'color_id'
            );

            // Delete all size records for this size name
            $deletedCount = Database::execute("DELETE FROM item_sizes WHERE item_sku = ? AND size_name = ?", [$itemSku, $sizeName]);

            if ($deletedCount === 0) {
                throw new Exception('No size records found for the specified size name');
            }

            // Sync stock levels for affected colors
            foreach ($affectedColorIds as $colorId) {
                if ($colorId) {
                    syncColorStockWithSizes($colorId);
                }
            }

            // Sync total stock
            $newTotalStock = syncTotalStockWithSizes($itemSku);

            echo json_encode([
                'success' => true,
                'message' => "Size '{$sizeName}' deleted successfully ({$deletedCount} combinations removed)",
                'deleted_count' => $deletedCount,
                'new_total_stock' => $newTotalStock
            ]);
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