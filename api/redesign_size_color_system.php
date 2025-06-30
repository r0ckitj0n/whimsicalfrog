<?php
/**
 * Redesign Size/Color System API
 * 
 * New Structure: Item → Sizes → Colors (per size) → Stock levels
 * This makes much more logical sense for inventory management
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session for authentication
session_start();

// Authentication check
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Admin token bypass for development
$adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
if ($adminToken === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_if_backwards':
            // Quick check to see if item structure is backwards (for conditional display)
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            // Check if we have the CORRECT structure: color-size combinations with size_id
            $correctStructureStmt = $pdo->prepare("
                SELECT COUNT(*) FROM item_colors 
                WHERE item_sku = ? AND size_id IS NOT NULL AND is_active = 1
            ");
            $correctStructureStmt->execute([$itemSku]);
            $correctCombinations = $correctStructureStmt->fetchColumn();
            
            // Count main sizes
            $sizesCountStmt = $pdo->prepare("SELECT COUNT(*) FROM item_sizes WHERE item_sku = ? AND is_active = 1");
            $sizesCountStmt->execute([$itemSku]);
            $sizeCount = $sizesCountStmt->fetchColumn();
            
            // Count total color entries (including old duplicate structure)
            $colorsCountStmt = $pdo->prepare("SELECT COUNT(*) FROM item_colors WHERE item_sku = ? AND is_active = 1");
            $colorsCountStmt->execute([$itemSku]);
            $colorCount = $colorsCountStmt->fetchColumn();
            
            // Structure is CORRECT if we have color-size combinations with size_id
            // Structure is BACKWARDS if we have colors without size_id (old duplicate structure)
            $isBackwards = false;
            
            if ($correctCombinations > 0) {
                // We have the correct structure: color-size combinations
                $isBackwards = false;
            } else {
                // No correct combinations found - check for old backwards structure
                $oldColorsStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM item_colors 
                    WHERE item_sku = ? AND size_id IS NULL AND is_active = 1
                ");
                $oldColorsStmt->execute([$itemSku]);
                $oldColors = $oldColorsStmt->fetchColumn();
                
                if ($oldColors > $sizeCount && $oldColors > 8) {
                    $isBackwards = true;
                }
            }
            
            echo json_encode([
                'success' => true, 
                'is_backwards' => $isBackwards,
                'color_count' => $colorCount,
                'size_count' => $sizeCount,
                'correct_combinations' => $correctCombinations,
                'structure_type' => $correctCombinations > 0 ? 'correct_combinations' : 'old_structure'
            ]);
            break;

        case 'analyze_current_structure':
            // Analyze the current messy structure
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            // Get current colors
            $colorsStmt = $pdo->prepare("
                SELECT id, color_name, color_code, stock_level, is_active 
                FROM item_colors 
                WHERE item_sku = ? 
                ORDER BY display_order ASC, color_name ASC
            ");
            $colorsStmt->execute([$itemSku]);
            $colors = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current sizes
            $sizesStmt = $pdo->prepare("
                SELECT id, color_id, size_name, size_code, stock_level, price_adjustment, is_active 
                FROM item_sizes 
                WHERE item_sku = ? 
                ORDER BY display_order ASC, size_name ASC
            ");
            $sizesStmt->execute([$itemSku]);
            $sizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Analyze the structure
            $analysis = [
                'item_sku' => $itemSku,
                'total_colors' => count($colors),
                'total_sizes' => count($sizes),
                'colors' => $colors,
                'sizes' => $sizes,
                'structure_issues' => [],
                'recommendations' => [],
                'is_backwards' => false
            ];
            
            // Detect backwards structure with multiple checks
            $isBackwards = false;
            
            // Check 1: Too many colors compared to sizes
            if (count($colors) > count($sizes) && count($colors) > 8) {
                $isBackwards = true;
                $analysis['structure_issues'][] = "More colors (" . count($colors) . ") than sizes (" . count($sizes) . ") - this is backwards!";
            }
            
            // Check 2: Excessive number of sizes
            if (count($sizes) > 12) {
                $isBackwards = true;
                $analysis['structure_issues'][] = "Excessive number of sizes (" . count($sizes) . ") - likely backwards structure";
            }
            
            // Check 3: Color names that look like sizes
            $sizeKeywords = ['small', 'medium', 'large', 'xl', 'xxl', 's', 'm', 'l', '2xl', '3xl'];
            foreach ($colors as $color) {
                $colorNameLower = strtolower($color['color_name']);
                foreach ($sizeKeywords as $sizeKeyword) {
                    if (strpos($colorNameLower, $sizeKeyword) !== false) {
                        $isBackwards = true;
                        $analysis['structure_issues'][] = 'Color "' . $color['color_name'] . '" looks like a size';
                        break;
                    }
                }
            }
            
            // Check 4: Size names that look like colors
            $colorKeywords = ['black', 'white', 'red', 'blue', 'green', 'yellow', 'pink', 'purple', 'gray', 'grey'];
            foreach ($sizes as $size) {
                $sizeNameLower = strtolower($size['size_name']);
                foreach ($colorKeywords as $colorKeyword) {
                    if (strpos($sizeNameLower, $colorKeyword) !== false) {
                        $isBackwards = true;
                        $analysis['structure_issues'][] = 'Size "' . $size['size_name'] . '" looks like a color';
                        break;
                    }
                }
            }
            
            // Check for orphaned sizes (sizes without colors)
            $orphanedSizes = array_filter($sizes, function($size) {
                return is_null($size['color_id']);
            });
            
            if (count($orphanedSizes) > 0) {
                $analysis['structure_issues'][] = count($orphanedSizes) . " sizes exist without color associations";
                $analysis['recommendations'][] = "Convert general sizes to size-first structure";
            }
            
            $analysis['is_backwards'] = $isBackwards;
            
            if ($isBackwards) {
                $analysis['recommendations'][] = "Restructure to have sizes first (S, M, L, XL, XXL), then colors for each size";
                $analysis['recommendations'][] = "This will provide better inventory management and customer experience";
            }
            
            echo json_encode(['success' => true, 'analysis' => $analysis]);
            break;
            
        case 'propose_new_structure':
            // Get current data with CORRECT hierarchy
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $sizesStmt = $pdo->prepare("SELECT id, size_name, size_code, price_adjustment, stock_level FROM item_sizes WHERE item_sku = ? AND is_active = 1 ORDER BY display_order");
            $sizesStmt->execute([$itemSku]);
            $sizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $colorsStmt = $pdo->prepare("SELECT c.id, c.size_id, c.color_name, c.color_code, c.stock_level, s.size_name 
                                        FROM item_colors c 
                                        JOIN item_sizes s ON c.size_id = s.id 
                                        WHERE c.item_sku = ? AND c.is_active = 1 
                                        ORDER BY s.display_order, c.display_order");
            $colorsStmt->execute([$itemSku]);
            $colors = $colorsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build the CORRECT structure: Sizes -> Colors
            $proposedSizes = [];
            $allColors = [];
            $totalCombinations = 0;
            
            // Group colors by size
            $colorsBySize = [];
            foreach ($colors as $color) {
                $colorsBySize[$color['size_id']][] = $color;
                
                // Collect unique colors
                if (!isset($allColors[$color['color_name']])) {
                    $allColors[$color['color_name']] = [
                        'name' => $color['color_name'],
                        'code' => $color['color_code']
                    ];
                }
            }
            
            // Build sizes with their colors
            foreach ($sizes as $size) {
                $sizeColors = $colorsBySize[$size['id']] ?? [];
                $sizeStock = array_sum(array_column($sizeColors, 'stock_level'));
                
                $proposedSizes[] = [
                    'id' => $size['id'],
                    'name' => $size['size_name'],
                    'code' => $size['size_code'],
                    'price_adjustment' => $size['price_adjustment'],
                    'stock' => $sizeStock,
                    'colors' => $sizeColors
                ];
                
                $totalCombinations += count($sizeColors);
            }
            
            echo json_encode([
                'success' => true,
                'proposedSizes' => $proposedSizes,
                'allColors' => array_values($allColors),
                'totalCombinations' => $totalCombinations,
                'structure' => 'sizes_with_colors', // Indicates correct hierarchy
                'message' => count($proposedSizes) . ' sizes with ' . count($allColors) . ' colors each = ' . $totalCombinations . ' combinations'
            ]);
            break;
            
        case 'migrate_to_new_structure':
            // Migrate an item to the new logical structure
            $data = json_decode(file_get_contents('php://input'), true);
            $itemSku = $data['item_sku'] ?? '';
            $newStructure = $data['new_structure'] ?? $data['structure'] ?? [];
            $preserveStock = $data['preserve_stock'] ?? true;
            
            if (empty($itemSku) || empty($newStructure)) {
                throw new Exception('Item SKU and new structure are required');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Step 1: Backup current data
                $backupStmt = $pdo->prepare("
                    CREATE TEMPORARY TABLE IF NOT EXISTS temp_color_backup AS 
                    SELECT * FROM item_colors WHERE item_sku = ?
                ");
                $backupStmt->execute([$itemSku]);
                
                $backupSizeStmt = $pdo->prepare("
                    CREATE TEMPORARY TABLE IF NOT EXISTS temp_size_backup AS 
                    SELECT * FROM item_sizes WHERE item_sku = ?
                ");
                $backupSizeStmt->execute([$itemSku]);
                
                // Step 2: Clear existing data for this item (sizes first due to foreign keys)
                $deleteSizesStmt = $pdo->prepare("DELETE FROM item_sizes WHERE item_sku = ?");
                $deleteSizesStmt->execute([$itemSku]);
                $sizesDeletedCount = $deleteSizesStmt->rowCount();
                
                $deleteColorsStmt = $pdo->prepare("DELETE FROM item_colors WHERE item_sku = ?");
                $deleteColorsStmt->execute([$itemSku]);
                $colorsDeletedCount = $deleteColorsStmt->rowCount();
                
                error_log("Migration for $itemSku: Deleted $sizesDeletedCount sizes, $colorsDeletedCount colors");
                
                // Step 3: Create new structure - FIXED LOGIC
                $totalStock = 0;
                $colorMap = [];
                
                // Collect all unique colors first
                $allColors = [];
                foreach ($newStructure as $sizeData) {
                    $colors = $sizeData['colors'] ?? [];
                    foreach ($colors as $colorData) {
                        $colorName = $colorData['color_name'] ?? '';
                        $colorCode = $colorData['color_code'] ?? '#000000';
                        if (!empty($colorName) && !isset($allColors[$colorName])) {
                            $allColors[$colorName] = $colorCode;
                        }
                    }
                }
                
                // Insert unique colors first
                $colorOrder = 1;
                foreach ($allColors as $colorName => $colorCode) {
                    $insertColorStmt = $pdo->prepare("
                        INSERT INTO item_colors (item_sku, color_name, color_code, stock_level, display_order, is_active) 
                        VALUES (?, ?, ?, 0, ?, 1)
                    ");
                    
                    $insertColorStmt->execute([$itemSku, $colorName, $colorCode, $colorOrder]);
                    $colorId = $pdo->lastInsertId();
                    $colorMap[$colorName] = $colorId;
                    error_log("Inserted color $colorName with ID $colorId");
                    $colorOrder++;
                }
                
                // Insert size-color combinations (the constraint expects unique item_sku + color_id + size_name)
                $sizeOrder = 1;
                foreach ($newStructure as $sizeData) {
                    $sizeName = $sizeData['size_name'] ?? '';
                    $sizeCode = $sizeData['size_code'] ?? '';
                    $priceAdjustment = (float)($sizeData['price_adjustment'] ?? 0.00);
                    $colors = $sizeData['colors'] ?? [];
                    
                    if (empty($sizeName)) continue;
                    
                    foreach ($colors as $colorData) {
                        $colorName = $colorData['color_name'] ?? '';
                        $stockLevel = (int)($colorData['stock_level'] ?? 0);
                        
                        if (empty($colorName) || !isset($colorMap[$colorName])) continue;
                        
                        $colorId = $colorMap[$colorName];
                        
                        // Each size-color combination gets its own row
                        $insertSizeStmt = $pdo->prepare("
                            INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        
                        $insertSizeStmt->execute([$itemSku, $colorId, $sizeName, $sizeCode, $stockLevel, $priceAdjustment, $sizeOrder]);
                        error_log("Inserted size-color: $sizeName ($sizeCode) in $colorName with stock $stockLevel");
                        $totalStock += $stockLevel;
                    }
                    
                    $sizeOrder++;
                }
                
                // Update color totals
                foreach ($colorMap as $colorName => $colorId) {
                    $colorStockStmt = $pdo->prepare("
                        UPDATE item_colors 
                        SET stock_level = (
                            SELECT COALESCE(SUM(stock_level), 0) 
                            FROM item_sizes 
                            WHERE color_id = ? AND item_sku = ?
                        ) 
                        WHERE id = ?
                    ");
                    $colorStockStmt->execute([$colorId, $itemSku, $colorId]);
                    error_log("Updated color $colorName total stock");
                }
                
                // Step 4: Update main item stock
                $updateItemStmt = $pdo->prepare("UPDATE items SET stockLevel = ? WHERE sku = ?");
                $updateItemStmt->execute([$totalStock, $itemSku]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item structure migrated successfully',
                    'new_total_stock' => $totalStock,
                    'structure_created' => count($newStructure) . ' sizes with colors'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'get_restructured_view':
            // Get the new logical view of an item's structure
            $itemSku = $_GET['item_sku'] ?? '';
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            // Get all size-color combinations
            $stmt = $pdo->prepare("
                SELECT 
                    s.size_name, s.size_code, s.price_adjustment,
                    c.color_name, c.color_code, s.stock_level,
                    s.is_active
                FROM item_sizes s
                JOIN item_colors c ON s.color_id = c.id
                WHERE s.item_sku = ? AND s.is_active = 1 AND c.is_active = 1
                ORDER BY s.display_order ASC, s.size_name ASC, c.display_order ASC, c.color_name ASC
            ");
            $stmt->execute([$itemSku]);
            $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by size
            $structure = [];
            foreach ($combinations as $combo) {
                $sizeKey = $combo['size_code'];
                
                if (!isset($structure[$sizeKey])) {
                    $structure[$sizeKey] = [
                        'size_name' => $combo['size_name'],
                        'size_code' => $combo['size_code'],
                        'price_adjustment' => $combo['price_adjustment'],
                        'colors' => [],
                        'total_stock' => 0
                    ];
                }
                
                $structure[$sizeKey]['colors'][] = [
                    'color_name' => $combo['color_name'],
                    'color_code' => $combo['color_code'],
                    'stock_level' => $combo['stock_level']
                ];
                
                $structure[$sizeKey]['total_stock'] += $combo['stock_level'];
            }
            
            echo json_encode([
                'success' => true, 
                'item_sku' => $itemSku,
                'structure' => array_values($structure),
                'total_combinations' => count($combinations)
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