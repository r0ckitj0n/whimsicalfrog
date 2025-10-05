<?php
// Item Sizes Management API
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

// Stock syncing is centrally handled in includes/stock_manager.php

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
            $gender = $_GET['gender'] ?? null;

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

            if ($gender !== null && $gender !== '') {
                $whereClause .= " AND (s.gender = ? OR s.gender IS NULL)";
                $params[] = $gender;
            }

            $sizes = Database::queryAll("\n                SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code,\n                       s.stock_level, s.price_adjustment, s.is_active, s.display_order,\n                       s.gender,\n                       c.color_name, c.color_code\n                FROM item_sizes s\n                LEFT JOIN item_colors c ON s.color_id = c.id\n                WHERE $whereClause AND (s.color_id IS NULL OR c.is_active = 1)\n                ORDER BY s.display_order ASC, s.size_name ASC\n            ", $params);

            Response::json(['success' => true, 'sizes' => $sizes]);
            break;

        case 'get_all_sizes':
            // Admin only - get all sizes for an item including inactive ones
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }

            $itemSku = $_GET['item_sku'] ?? '';
            $colorId = $_GET['color_id'] ?? null;
            $gender = $_GET['gender'] ?? null;
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $whereAll = 's.item_sku = ?';
            $paramsAll = [$itemSku];

            if ($colorId !== null) {
                if ($colorId === '0' || $colorId === 'null') {
                    $whereAll .= ' AND s.color_id IS NULL';
                } else {
                    $whereAll .= ' AND s.color_id = ?';
                    $paramsAll[] = (int)$colorId;
                }
            }

            if ($gender !== null && $gender !== '') {
                $whereAll .= ' AND (s.gender = ? OR s.gender IS NULL)';
                $paramsAll[] = $gender;
            }

            $sizes = Database::queryAll(
                "SELECT s.id, s.item_sku, s.color_id, s.size_name, s.size_code,
                        s.stock_level, s.price_adjustment, s.is_active, s.display_order,
                        s.gender,
                        c.color_name, c.color_code, c.is_active AS color_is_active
                 FROM item_sizes s
                 LEFT JOIN item_colors c ON s.color_id = c.id
                 WHERE $whereAll
                 ORDER BY s.color_id ASC, s.display_order ASC, s.size_name ASC",
                $paramsAll
            );

            Response::json(['success' => true, 'sizes' => $sizes]);
            break;

        case 'add_size':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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
                syncColorStockWithSizes(Database::getInstance(), $colorId);
            }
            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $itemSku);

            Response::updated(['size_id' => $sizeId, 'new_total_stock' => $newTotalStock]);
            break;

        case 'add_size_from_global':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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
                Response::json(['success' => false, 'message' => 'This size already exists for the specified item/color.']);
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

            Response::updated(['size_id' => $sizeId, 'size_name' => $globalSize['size_name'], 'size_code' => $globalSize['size_code'], 'new_total_stock' => $newTotalStock]);
            break;

        case 'update_size':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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

            $affected = Database::execute(
                "UPDATE item_sizes 
                 SET size_name = ?, size_code = ?, stock_level = ?, price_adjustment = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$sizeName, $sizeCode, $stockLevel, $priceAdjustment, $displayOrder, $isActive, $sizeId]
            );

            // Sync stock levels
            if (!empty($currentSize['color_id'])) {
                syncColorStockWithSizes(Database::getInstance(), $currentSize['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $currentSize['item_sku']);

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock]);
            } else {
                $exists = Database::queryOne('SELECT id FROM item_sizes WHERE id = ?', [$sizeId]);
                if ($exists) {
                    Response::noChanges(['new_total_stock' => $newTotalStock]);
                } else {
                    Response::error('Size not found', null, 404);
                }
            }
            break;

        case 'delete_size':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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

            $affected = Database::execute("DELETE FROM item_sizes WHERE id = ?", [$sizeId]);

            // Sync stock levels
            if (!empty($sizeInfo['color_id'])) {
                syncColorStockWithSizes(Database::getInstance(), $sizeInfo['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $sizeInfo['item_sku']);

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock]);
            } else {
                Response::error('Size not found', null, 404);
            }
            break;

        case 'update_stock':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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
            $affected = Database::execute("UPDATE item_sizes SET stock_level = ? WHERE id = ?", [$stockLevel, $sizeId]);

            // Sync stock levels
            if ($currentSize['color_id']) {
                syncColorStockWithSizes(Database::getInstance(), $currentSize['color_id']);
            }
            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $currentSize['item_sku']);

            if ($affected > 0) {
                Response::updated(['new_total_stock' => $newTotalStock]);
            } else {
                $exists = Database::queryOne('SELECT id FROM item_sizes WHERE id = ?', [$sizeId]);
                if ($exists) {
                    Response::noChanges(['new_total_stock' => $newTotalStock]);
                } else {
                    Response::error('Size not found', null, 404);
                }
            }
            break;

        case 'sync_stock':
            // Sync an item's total stock from all of its sizes (and update color stocks too)
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }

            // Accept item_sku from query, form, or JSON body
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            $itemSku = $_GET['item_sku'] ?? $_POST['item_sku'] ?? ($jsonInput['item_sku'] ?? '');

            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $itemSku);
            if ($newTotalStock === false) {
                throw new Exception('Failed to synchronize stock from sizes');
            }

            Response::updated(['new_total_stock' => $newTotalStock]);
            break;

        case 'get_size_options':
            // Get available size options for dropdown
            $sizeOptions = [
                ['code' => 'S', 'name' => 'Small'],
                ['code' => 'M', 'name' => 'Medium'],
                ['code' => 'L', 'name' => 'Large'],
                ['code' => 'XL', 'name' => 'Extra Large'],
                ['code' => 'XXL', 'name' => 'Double XL'],
                ['code' => 'XXXL', 'name' => 'Triple XL'],
                ['code' => 'OS', 'name' => 'One Size']
            ];

            Response::json(['success' => true, 'options' => $sizeOptions]);
            break;

        case 'delete_size_by_name':
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
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
                    syncColorStockWithSizes(Database::getInstance(), $colorId);
                }
            }

            // Sync total stock
            $newTotalStock = syncTotalStockWithSizes($itemSku);

            Response::updated(['deleted_count' => $deletedCount, 'new_total_stock' => $newTotalStock]);
            break;

        case 'ensure_color_sizes':
            // Ensure that for each item color, we have per-color size rows matching the general sizes (color_id IS NULL)
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $_GET['item_sku'] ?? $_POST['item_sku'] ?? ($data['item_sku'] ?? '');
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            // Get all active colors for the item
            $colors = Database::queryAll("SELECT id, color_name FROM item_colors WHERE item_sku = ? AND is_active = 1 ORDER BY display_order ASC, id ASC", [$itemSku]);

            // Get the set of general sizes (no color association)
            $generalSizes = Database::queryAll("SELECT size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender FROM item_sizes WHERE item_sku = ? AND color_id IS NULL ORDER BY display_order ASC, size_name ASC", [$itemSku]);

            $created = 0; $skipped = 0; $colorsCount = count($colors);
            foreach ($colors as $c) {
                $colorId = (int)$c['id'];
                foreach ($generalSizes as $gs) {
                    // Check if this size already exists for this color
                    $exists = Database::queryOne("SELECT id FROM item_sizes WHERE item_sku = ? AND color_id = ? AND size_code = ?", [$itemSku, $colorId, $gs['size_code']]);
                    if ($exists) { $skipped++; continue; }
                    // Create a color-specific size. Initialize stock to 0 to avoid inflating totals; admin can set per color.
                    Database::execute(
                        "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender) VALUES (?,?,?,?,?,?,?,?,?)",
                        [
                            $itemSku,
                            $colorId,
                            $gs['size_name'],
                            $gs['size_code'],
                            0,
                            (float)$gs['price_adjustment'],
                            (int)$gs['display_order'],
                            (int)$gs['is_active'],
                            $gs['gender']
                        ]
                    );
                    $created++;
                }
            }

            // After ensuring sizes exist, sync color totals (0 or existing) and item total
            foreach ($colors as $c) { syncColorStockWithSizes(Database::getInstance(), (int)$c['id']); }
            $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $itemSku);

            Response::updated(['created' => $created, 'skipped' => $skipped, 'colors' => $colorsCount, 'new_total_stock' => $newTotalStock]);
            break;

        case 'distribute_general_stock_evenly':
            // Move general size stock (color_id IS NULL) evenly across all active colors for each size
            if (!$isAdmin) {
                Response::forbidden('Admin access required');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $_GET['item_sku'] ?? $_POST['item_sku'] ?? ($data['item_sku'] ?? '');
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            // Get active colors
            $colors = Database::queryAll("SELECT id, color_name FROM item_colors WHERE item_sku = ? AND is_active = 1 ORDER BY display_order ASC, id ASC", [$itemSku]);
            $colorCount = count($colors);
            if ($colorCount === 0) {
                throw new Exception('No active colors found for item');
            }

            // Get active general sizes
            $generalSizes = Database::queryAll("SELECT id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender FROM item_sizes WHERE item_sku = ? AND color_id IS NULL AND is_active = 1 ORDER BY display_order ASC, size_name ASC", [$itemSku]);

            $created = 0; $updated = 0; $movedTotal = 0;
            Database::beginTransaction();
            try {
                foreach ($generalSizes as $gs) {
                    $sizeCode = $gs['size_code'];
                    $stock = (int)$gs['stock_level'];
                    if ($stock <= 0) continue;
                    $base = intdiv($stock, $colorCount);
                    $rem = $stock % $colorCount;

                    // Ensure rows exist for each color and set new stock
                    for ($i = 0; $i < $colorCount; $i++) {
                        $colorId = (int)$colors[$i]['id'];
                        $row = Database::queryOne("SELECT id, stock_level FROM item_sizes WHERE item_sku = ? AND color_id = ? AND size_code = ?", [$itemSku, $colorId, $sizeCode]);
                        if (!$row) {
                            Database::execute(
                                "INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender) VALUES (?,?,?,?,?,?,?,?,?)",
                                [
                                    $itemSku,
                                    $colorId,
                                    $gs['size_name'],
                                    $gs['size_code'],
                                    0,
                                    (float)$gs['price_adjustment'],
                                    (int)$gs['display_order'],
                                    (int)$gs['is_active'],
                                    $gs['gender']
                                ]
                            );
                            $rowId = Database::lastInsertId();
                            $created++;
                        } else {
                            $rowId = $row['id'];
                        }
                        $add = $base + ($i < $rem ? 1 : 0);
                        if ($add > 0) {
                            Database::execute("UPDATE item_sizes SET stock_level = stock_level + ? WHERE id = ?", [$add, $rowId]);
                            $updated++;
                        }
                    }

                    // Zero out the general size stock (move semantics)
                    Database::execute("UPDATE item_sizes SET stock_level = 0 WHERE id = ?", [$gs['id']]);
                    $movedTotal += $stock;
                }

                // Sync color totals and overall item stock
                foreach ($colors as $c) { syncColorStockWithSizes(Database::getInstance(), (int)$c['id']); }
                $newTotalStock = syncTotalStockWithSizes(Database::getInstance(), $itemSku);

                Database::commit();
            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }

            Response::updated(['created' => $created, 'updated' => $updated, 'moved_total' => $movedTotal, 'colors' => $colorCount, 'new_total_stock' => $newTotalStock]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (PDOException $e) {
    Response::serverError('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
?> 
