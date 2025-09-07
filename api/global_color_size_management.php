<?php
// Global Color and Size Management API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication


// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Check for admin token as fallback
$adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
$isValidToken = ($adminToken === 'whimsical_admin_2024');

if (!$isAdmin && !$isValidToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    try { Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }

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

            $colors = Database::queryAll(
                "SELECT id, color_name, color_code, category, description, display_order
                 FROM global_colors 
                 WHERE $whereClause 
                 ORDER BY display_order ASC, color_name ASC",
                $params
            );

            echo json_encode(['success' => true, 'colors' => $colors]);
            break;

        case 'get_color_categories':
            $categories = array_column(
                Database::queryAll("SELECT DISTINCT category FROM global_colors WHERE is_active = 1 ORDER BY category ASC"),
                'category'
            );

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

            Database::execute(
                "INSERT INTO global_colors (color_name, color_code, category, description, display_order) 
                 VALUES (?, ?, ?, ?, ?)",
                [$colorName, $colorCode, $category, $description, $displayOrder]
            );

            $colorId = Database::lastInsertId();

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

            Database::execute(
                "UPDATE global_colors 
                 SET color_name = ?, color_code = ?, category = ?, description = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$colorName, $colorCode, $category, $description, $displayOrder, $isActive, $colorId]
            );

            echo json_encode(['success' => true, 'message' => 'Global color updated successfully']);
            break;

        case 'delete_global_color':
            $data = json_decode(file_get_contents('php://input'), true);
            $colorId = (int)($data['color_id'] ?? 0);

            if ($colorId <= 0) {
                throw new Exception('Valid color ID is required');
            }

            // Check if color is in use
            $inUseRow = Database::queryOne("SELECT COUNT(*) as cnt FROM item_color_assignments WHERE global_color_id = ?", [$colorId]);
            $inUse = (int)($inUseRow['cnt'] ?? 0) > 0;

            if ($inUse) {
                // Soft delete - deactivate instead of deleting
                Database::execute("UPDATE global_colors SET is_active = 0 WHERE id = ?", [$colorId]);
                echo json_encode(['success' => true, 'message' => 'Global color deactivated (was in use by items)']);
            } else {
                // Hard delete
                Database::execute("DELETE FROM global_colors WHERE id = ?", [$colorId]);
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

            $sizes = Database::queryAll(
                "SELECT id, size_name, size_code, category, description, display_order
                 FROM global_sizes 
                 WHERE $whereClause 
                 ORDER BY display_order ASC, size_name ASC",
                $params
            );

            echo json_encode(['success' => true, 'sizes' => $sizes]);
            break;

        case 'get_size_categories':
            $categories = array_column(
                Database::queryAll("SELECT DISTINCT category FROM global_sizes WHERE is_active = 1 ORDER BY category ASC"),
                'category'
            );

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

            Database::execute(
                "INSERT INTO global_sizes (size_name, size_code, category, description, display_order) 
                 VALUES (?, ?, ?, ?, ?)",
                [$sizeName, $sizeCode, $category, $description, $displayOrder]
            );

            $sizeId = Database::lastInsertId();

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

            if ($sizeId <= 0 || empty($sizeName)) {
                throw new Exception('Size ID and size name are required');
            }

            Database::execute(
                "UPDATE global_sizes 
                 SET size_name = ?, size_code = ?, category = ?, description = ?, display_order = ?, is_active = ?
                 WHERE id = ?",
                [$sizeName, $sizeCode, $category, $description, $displayOrder, $isActive, $sizeId]
            );

            echo json_encode(['success' => true, 'message' => 'Global size updated successfully']);
            break;

        case 'delete_global_size':
            $data = json_decode(file_get_contents('php://input'), true);
            $sizeId = (int)($data['size_id'] ?? 0);

            if ($sizeId <= 0) {
                throw new Exception('Valid size ID is required');
            }

            // Check if size is in use
            $rows = Database::queryAll(
                "SELECT COUNT(*) as c FROM item_size_assignments WHERE global_size_id = ?
                 UNION ALL
                 SELECT COUNT(*) as c FROM item_color_assignments WHERE global_size_id = ?",
                [$sizeId, $sizeId]
            );
            $inUse = array_sum(array_map(function($r){return (int)($r['c'] ?? 0);}, $rows)) > 0;

            if ($inUse) {
                // Soft delete - deactivate instead of deleting
                Database::execute("UPDATE global_sizes SET is_active = 0 WHERE id = ?", [$sizeId]);
                echo json_encode(['success' => true, 'message' => 'Global size deactivated (was in use by items)']);
            } else {
                // Hard delete
                Database::execute("DELETE FROM global_sizes WHERE id = ?", [$sizeId]);
                echo json_encode(['success' => true, 'message' => 'Global size deleted successfully']);
            }
            break;

            // ========== ITEM ASSIGNMENTS MANAGEMENT ==========
        case 'get_item_size_assignments':
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }

            $assignments = Database::queryAll(
                "SELECT isa.id, isa.item_sku, isa.global_size_id, isa.is_active,
                       gs.size_name, gs.size_code, gs.category, gs.description
                FROM item_size_assignments isa
                JOIN global_sizes gs ON isa.global_size_id = gs.id
                WHERE isa.item_sku = ? AND isa.is_active = 1
                ORDER BY gs.display_order ASC, gs.size_name ASC",
                [$itemSku]
            );

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

            $assignments = Database::queryAll(
                "SELECT ica.id, ica.item_sku, ica.global_size_id, ica.global_color_id, 
                       ica.stock_level, ica.price_adjustment, ica.is_active,
                       gs.size_name, gs.size_code,
                       gc.color_name, gc.color_code, gc.category as color_category
                FROM item_color_assignments ica
                JOIN global_sizes gs ON ica.global_size_id = gs.id
                JOIN global_colors gc ON ica.global_color_id = gc.id
                WHERE $whereClause
                ORDER BY gs.display_order ASC, gc.display_order ASC, gc.color_name ASC",
                $params
            );

            echo json_encode(['success' => true, 'assignments' => $assignments]);
            break;

        case 'assign_sizes_to_item':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $sizeIds = $data['size_ids'] ?? [];

            if (empty($itemSku) || empty($sizeIds)) {
                throw new Exception('Item SKU and size IDs are required');
            }

            Database::beginTransaction();

            try {
                // Remove existing assignments if replace mode
                if ($data['replace_existing'] ?? false) {
                    Database::execute("DELETE FROM item_size_assignments WHERE item_sku = ?", [$itemSku]);
                }

                // Insert new assignments
                foreach ($sizeIds as $sizeId) {
                    Database::execute("INSERT IGNORE INTO item_size_assignments (item_sku, global_size_id) VALUES (?, ?)", [$itemSku, (int)$sizeId]);
                }

                Database::commit();
                echo json_encode(['success' => true, 'message' => 'Sizes assigned to item successfully']);

            } catch (Exception $e) {
                Database::rollBack();
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

            Database::beginTransaction();

            try {
                // Remove existing color assignments for this size if replace mode
                if ($data['replace_existing'] ?? false) {
                    Database::execute("DELETE FROM item_color_assignments WHERE item_sku = ? AND global_size_id = ?", [$itemSku, $sizeId]);
                }

                // Insert new color assignments
                foreach ($colorAssignments as $assignment) {
                    $colorId = (int)($assignment['color_id'] ?? 0);
                    $stockLevel = (int)($assignment['stock_level'] ?? 0);
                    $priceAdjustment = (float)($assignment['price_adjustment'] ?? 0);

                    if ($colorId > 0) {
                        Database::execute("\n                            INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) \n                            VALUES (?, ?, ?, ?, ?)\n                            ON DUPLICATE KEY UPDATE \n                            stock_level = VALUES(stock_level),\n                            price_adjustment = VALUES(price_adjustment)\n                        ", [$itemSku, $sizeId, $colorId, $stockLevel, $priceAdjustment]);
                    }
                }

                Database::commit();
                echo json_encode(['success' => true, 'message' => 'Colors assigned to item size successfully']);

            } catch (Exception $e) {
                Database::rollBack();
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

            Database::execute("UPDATE item_color_assignments SET stock_level = ? WHERE id = ?", [$stockLevel, $assignmentId]);

            echo json_encode(['success' => true, 'message' => 'Stock level updated successfully']);
            break;

        case 'bulk_update_item_structure':
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $structure = $data['structure'] ?? [];

            if (empty($itemSku) || empty($structure)) {
                throw new Exception('Item SKU and structure data are required');
            }

            Database::beginTransaction();

            try {
                // Clear existing assignments
                Database::execute("DELETE FROM item_size_assignments WHERE item_sku = ?", [$itemSku]);
                Database::execute("DELETE FROM item_color_assignments WHERE item_sku = ?", [$itemSku]);

                // Insert new structure
                foreach ($structure as $sizeData) {
                    $sizeId = (int)($sizeData['size_id'] ?? 0);
                    if ($sizeId <= 0) {
                        continue;
                    }

                    // Add size assignment
                    Database::execute("INSERT INTO item_size_assignments (item_sku, global_size_id) VALUES (?, ?)", [$itemSku, $sizeId]);

                    // Add color assignments for this size
                    foreach ($sizeData['colors'] ?? [] as $colorData) {
                        $colorId = (int)($colorData['color_id'] ?? 0);
                        $stockLevel = (int)($colorData['stock_level'] ?? 0);
                        $priceAdjustment = (float)($colorData['price_adjustment'] ?? 0);

                        if ($colorId > 0) {
                            Database::execute("INSERT INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment) VALUES (?, ?, ?, ?, ?)", [$itemSku, $sizeId, $colorId, $stockLevel, $priceAdjustment]);
                        }
                    }
                }

                Database::commit();
                echo json_encode(['success' => true, 'message' => 'Item structure updated successfully']);

            } catch (Exception $e) {
                Database::rollBack();
                throw $e;
            }
            break;

            // ========== GLOBAL GENDERS MANAGEMENT ==========
        case 'get_global_genders':
            $genders = Database::queryAll(
                "SELECT id, gender_name, description, display_order
                FROM global_genders 
                WHERE is_active = 1 
                ORDER BY display_order ASC, gender_name ASC"
            );

            echo json_encode(['success' => true, 'genders' => $genders]);
            break;

        case 'add_global_gender':
            $data = json_decode(file_get_contents('php://input'), true);

            $genderName = trim($data['gender_name'] ?? '');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);

            if (empty($genderName)) {
                throw new Exception('Gender name is required');
            }

            // Check if gender already exists
            $row = Database::queryOne("SELECT COUNT(*) AS c FROM global_genders WHERE gender_name = ? AND is_active = 1", [$genderName]);
            if ((int)($row['c'] ?? 0) > 0) {
                throw new Exception('A gender with this name already exists');
            }

            Database::execute(
                "INSERT INTO global_genders (gender_name, description, display_order) VALUES (?, ?, ?)",
                [$genderName, $description, $displayOrder]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Global gender added successfully',
                'gender_id' => Database::lastInsertId()
            ]);
            break;

        case 'update_global_gender':
            $data = json_decode(file_get_contents('php://input'), true);

            $genderId = (int)($data['gender_id'] ?? 0);
            $genderName = trim($data['gender_name'] ?? '');
            $description = trim($data['description'] ?? '');
            $displayOrder = (int)($data['display_order'] ?? 0);

            if ($genderId <= 0) {
                throw new Exception('Valid gender ID is required');
            }

            if (empty($genderName)) {
                throw new Exception('Gender name is required');
            }

            // Check if another gender with the same name exists (excluding current one)
            $row = Database::queryOne("SELECT COUNT(*) AS c FROM global_genders WHERE gender_name = ? AND id != ? AND is_active = 1", [$genderName, $genderId]);
            if ((int)($row['c'] ?? 0) > 0) {
                throw new Exception('A gender with this name already exists');
            }

            Database::execute(
                "UPDATE global_genders SET gender_name = ?, description = ?, display_order = ?, updated_at = NOW() WHERE id = ?",
                [$genderName, $description, $displayOrder, $genderId]
            );

            echo json_encode([
                'success' => true,
                'message' => 'Global gender updated successfully'
            ]);
            break;

        case 'delete_global_gender':
            $data = json_decode(file_get_contents('php://input'), true);
            $genderId = (int)($data['gender_id'] ?? 0);

            if ($genderId <= 0) {
                throw new Exception('Valid gender ID is required');
            }

            // Check if gender is in use by items
            $row = Database::queryOne("SELECT COUNT(*) AS c FROM item_genders WHERE gender = (SELECT gender_name FROM global_genders WHERE id = ?)", [$genderId]);
            $inUse = ((int)($row['c'] ?? 0)) > 0;

            if ($inUse) {
                // Soft delete - deactivate instead of deleting
                Database::execute("UPDATE global_genders SET is_active = 0 WHERE id = ?", [$genderId]);
                echo json_encode(['success' => true, 'message' => 'Global gender deactivated (was in use by items)']);
            } else {
                // Hard delete
                Database::execute("DELETE FROM global_genders WHERE id = ?", [$genderId]);
                echo json_encode(['success' => true, 'message' => 'Global gender deleted successfully']);
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