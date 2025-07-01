<?php
// Early error capture
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("add-order.php: Fatal error detected: " . print_r($error, true));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message']]);
        }
    }
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/email_notifications.php';

// Ensure no HTML errors are displayed for this API endpoint
ini_set('display_errors', 0);
ini_set('html_errors', 0);

header('Content-Type: application/json');

// Add error handling for fatal errors
try {

// Add debugging
error_log("add-order.php: Received request");
error_log("add-order.php: Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("add-order.php: Headers: " . print_r(getallheaders(), true));
$rawInput = file_get_contents('php://input');
error_log("add-order.php: Raw input: " . $rawInput);
error_log("add-order.php: Raw input length: " . strlen($rawInput));

// Stock management functions are now available from includes/functions.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

// Read raw input
$rawInput = file_get_contents('php://input');
error_log("add-order.php: Raw input received: " . $rawInput);

$input = json_decode($rawInput, true);
if (!$input) {
    $jsonError = json_last_error_msg();
    error_log("add-order.php: JSON decode error: " . $jsonError);
    echo json_encode(['success'=>false,'error'=>'Invalid JSON: ' . $jsonError]);
    exit;
}

// Debug the parsed input
error_log("add-order.php: Parsed input: " . print_r($input, true));

try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }

// Ensure order_items table has size column (migration)
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'size'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN size VARCHAR(10) DEFAULT NULL AFTER color");
        error_log("add-order.php: Added 'size' column to order_items table");
    }
} catch (Exception $e) {
    error_log("add-order.php: Warning - Could not check/add size column: " . $e->getMessage());
}

// Validate required fields
$required = ['customerId','itemIds','quantities','paymentMethod','total'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success'=>false,'error'=>'Missing field: '.$field]);
        exit;
    }
}
    $itemIds = $input['itemIds'];  // These are actually SKUs now
    $quantities = $input['quantities'];
    $colors = $input['colors'] ?? []; // Color information for each item
    $sizes = $input['sizes'] ?? []; // Size information for each item
    
    // Debug the itemIds array
    error_log("add-order.php: itemIds array: " . print_r($itemIds, true));
    error_log("add-order.php: quantities array: " . print_r($quantities, true));
    error_log("add-order.php: colors array: " . print_r($colors, true));
    error_log("add-order.php: sizes array: " . print_r($sizes, true));
    
    if (!is_array($itemIds) || !is_array($quantities) || count($itemIds)!==count($quantities)) {
    echo json_encode(['success'=>false,'error'=>'Invalid items array']);
    exit;
}

// Ensure colors and sizes arrays have same length as items (fill with nulls if needed)
if (count($colors) < count($itemIds)) {
    $colors = array_pad($colors, count($itemIds), null);
}
if (count($sizes) < count($itemIds)) {
    $sizes = array_pad($sizes, count($itemIds), null);
}

$paymentMethod = $input['paymentMethod'];
$shippingMethod = $input['shippingMethod'] ?? 'Customer Pickup'; // Default to Customer Pickup if not provided
$shippingAddress = $input['shippingAddress'] ?? null; // Shipping address data (optional)

// Payment status defaults to Pending unless it's a verified credit card payment
$paymentStatus = 'Pending'; // All payments start as Pending until manually verified
$orderStatus   = in_array($paymentMethod, ['Cash','Check']) ? 'Pending' : 'Processing';

// Generate compact order ID format: [CustomerNum][MonthDay][ShippingCode][RandomNum]
// Example: 01A15P23 (8 characters total)
$date = date('Y-m-d H:i:s');
$customerId = $input['customerId'];

// Get last 2 digits of customer number
$customerNum = '00';
if (preg_match('/U(\d+)/', $customerId, $matches)) {
    $customerNum = str_pad($matches[1] % 100, 2, '0', STR_PAD_LEFT);
} else {
    // For non-standard user IDs, create a hash-based 2-digit number
    $customerNum = str_pad(abs(crc32($customerId)) % 100, 2, '0', STR_PAD_LEFT);
}

// Get compact date format: Month letter (A-L) + Day (01-31)
$monthLetters = ['A','B','C','D','E','F','G','H','I','J','K','L'];
$monthLetter = $monthLetters[date('n') - 1]; // n = 1-12, array is 0-11
$dayOfMonth = date('d');
$compactDate = $monthLetter . $dayOfMonth;

// Get single-character shipping method code
$shippingCodes = [
    'Customer Pickup' => 'P',
    'Local Delivery' => 'L',
    'USPS' => 'U',
    'FedEx' => 'F',
    'UPS' => 'X'
];
$shippingCode = $shippingCodes[$shippingMethod] ?? 'P';

// Generate sequence-based number instead of random to prevent duplicates
// Find the highest existing order ID with this prefix pattern to get next sequence
$orderPrefix = $customerNum . $compactDate . $shippingCode;
$maxOrderStmt = $pdo->prepare("SELECT id FROM orders WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
$maxOrderStmt->execute([$orderPrefix . '%']);
$maxOrderId = $maxOrderStmt->fetchColumn();

$sequenceNum = '01'; // Default starting sequence
if ($maxOrderId) {
    // Extract the last 2 digits and increment
    $currentSequence = (int)substr($maxOrderId, -2);
    $nextSequence = $currentSequence + 1;
    $sequenceNum = str_pad($nextSequence, 2, '0', STR_PAD_LEFT);
    error_log("add-order.php: Found max existing order ID '$maxOrderId' with prefix '$orderPrefix', next sequence will be $nextSequence");
} else {
    error_log("add-order.php: No existing order IDs found with prefix '$orderPrefix', starting from sequence 01");
}

// Create compact order ID: 01A15P01 (sequence-based, not random)
$orderId = $orderPrefix . $sequenceNum;

$pdo->beginTransaction();
try {
    // Format shipping address for storage
    $shippingAddressJson = null;
    if ($shippingAddress && is_array($shippingAddress)) {
        $shippingAddressJson = json_encode($shippingAddress);
    }
    
    // Add shippingMethod and shippingAddress to the insert statement
    $stmt = $pdo->prepare("INSERT INTO orders (id, userId, total, paymentMethod, shippingMethod, shippingAddress, order_status, date, paymentStatus) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$orderId, $input['customerId'], $input['total'], $paymentMethod, $shippingMethod, $shippingAddressJson, $orderStatus, $date, $paymentStatus]);
    
    // Get the next order item ID sequence number by finding the highest existing ID
    $maxIdStmt = $pdo->prepare("SELECT id FROM order_items WHERE id REGEXP '^OI[0-9]+$' ORDER BY CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC LIMIT 1");
    $maxIdStmt->execute();
    $maxId = $maxIdStmt->fetchColumn();
    
    // Extract the sequence number from the highest ID
    $nextSequence = 1; // Default starting sequence
    if ($maxId) {
        $currentSequence = (int)substr($maxId, 2); // Remove 'OI' prefix and convert to int
        $nextSequence = $currentSequence + 1;
        error_log("add-order.php: Found max existing ID '$maxId', next sequence will be $nextSequence");
    } else {
        error_log("add-order.php: No existing order item IDs found, starting from sequence 1");
    }
    
    // Prepare statements for order items and stock updates
    $priceStmt = $pdo->prepare("SELECT retailPrice FROM items WHERE sku = ?");
    $orderItemStmt = $pdo->prepare("INSERT INTO order_items (id, orderId, sku, quantity, price, color, size) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Process each item (SKU)
    for ($i = 0; $i < count($itemIds); $i++) {
        $sku = $itemIds[$i];
        $quantity = (int)$quantities[$i];
        $color = !empty($colors[$i]) ? $colors[$i] : null;
        $size = !empty($sizes[$i]) ? $sizes[$i] : null;
        
        // Debug each SKU being processed
        error_log("add-order.php: Processing item $i: SKU='$sku', Quantity=$quantity, Color='$color', Size='$size'");
        
        // Check if SKU is null or empty
        if (empty($sku)) {
            error_log("add-order.php: ERROR - SKU is empty for item $i");
            throw new Exception("SKU is empty for item at index $i");
        }
        
        // Get item price
        $priceStmt->execute([$sku]);
        $price = $priceStmt->fetchColumn();
        
        if ($price === false || $price === null) {
            error_log("add-order.php: WARNING - No price found for SKU '$sku', using 0.00");
            $price = 0.00;  // Fallback price
        }
        
        // Generate order item ID using the next sequence number
        $orderItemId = 'OI' . str_pad($nextSequence + $i, 10, '0', STR_PAD_LEFT);
        
        // Insert order item with color and size information
        error_log("add-order.php: Inserting order item: ID=$orderItemId, OrderID=$orderId, SKU=$sku, Qty=$quantity, Price=$price, Color=$color, Size=$size");
        $orderItemStmt->execute([$orderItemId, $orderId, $sku, $quantity, $price, $color, $size]);
        
        // Handle stock reduction - prioritize size-specific, then color-specific, then general
        $stockReduced = false;
        
        if (!empty($size)) {
            // Size-specific stock reduction - need to get color ID if color is specified
            $colorId = null;
            if (!empty($color)) {
                $colorStmt = $pdo->prepare("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1");
                $colorStmt->execute([$sku, $color]);
                $colorResult = $colorStmt->fetch(PDO::FETCH_ASSOC);
                $colorId = $colorResult ? $colorResult['id'] : null;
            }
            
            // Use size-specific stock reduction function
            
            // Manually reduce size-specific stock
            try {
                $whereClause = "item_sku = ? AND size_code = ? AND is_active = 1";
                $params = [$sku, $size];
                
                if ($colorId) {
                    $whereClause .= " AND color_id = ?";
                    $params[] = $colorId;
                } else {
                    $whereClause .= " AND color_id IS NULL";
                }
                
                // Reduce size-specific stock
                $sizeStockStmt = $pdo->prepare("UPDATE item_sizes SET stock_level = GREATEST(stock_level - ?, 0) WHERE $whereClause");
                $sizeStockStmt->execute(array_merge([$quantity], $params));
                
                // Sync stock levels using functions from item_sizes.php
                if ($colorId) {
                    // Sync color stock with its sizes
                    $colorSyncStmt = $pdo->prepare("
                        UPDATE item_colors 
                        SET stock_level = (
                            SELECT COALESCE(SUM(stock_level), 0) 
                            FROM item_sizes 
                            WHERE color_id = ? AND is_active = 1
                        ) 
                        WHERE id = ?
                    ");
                    $colorSyncStmt->execute([$colorId, $colorId]);
                }
                
                // Sync total item stock with all sizes
                $totalSyncStmt = $pdo->prepare("
                    UPDATE items 
                    SET stockLevel = (
                        SELECT COALESCE(SUM(stock_level), 0) 
                        FROM item_sizes 
                        WHERE item_sku = ? AND is_active = 1
                    ) 
                    WHERE sku = ?
                ");
                $totalSyncStmt->execute([$sku, $sku]);
                
                $stockReduced = true;
                error_log("add-order.php: Size-specific stock reduced for SKU '$sku', Size '$size', Color '$color'");
            } catch (Exception $e) {
                error_log("add-order.php: ERROR - Failed to reduce size-specific stock: " . $e->getMessage());
            }
        }
        
        if (!$stockReduced && !empty($color) && !empty($size)) {
            // Use size + color specific stock reduction (most specific)
            $stockReduced = reduceStockForSale($pdo, $sku, $quantity, $color, $size, false);
        }
        
        if (!$stockReduced && !empty($color)) {
            // Use color-specific stock reduction
            $stockReduced = reduceStockForSale($pdo, $sku, $quantity, $color, null, false);
            if ($stockReduced) {
                error_log("add-order.php: Color-specific stock reduced for SKU '$sku', Color '$color'");
            } else {
                error_log("add-order.php: WARNING - Failed to reduce color stock for SKU '$sku', Color '$color'");
            }
        }
        
        if (!$stockReduced) {
            // Fall back to regular stock reduction for items without colors/sizes
            $updateStockStmt = $pdo->prepare("UPDATE items SET stockLevel = GREATEST(stockLevel - ?, 0) WHERE sku = ?");
            $updateStockStmt->execute([$quantity, $sku]);
            error_log("add-order.php: General stock reduced for SKU '$sku'");
        }
    }
    
    $pdo->commit();
    
    // Send order confirmation emails
    $emailResults = null;
    if (function_exists('sendOrderConfirmationEmails')) {
        $emailResults = sendOrderConfirmationEmails($orderId, $pdo);
    } else {
        error_log("Order $orderId: sendOrderConfirmationEmails function not available");
    }
    
    // Log email results but don't fail the order if emails fail
    if ($emailResults) {
        if ($emailResults['customer']) {
            error_log("Order $orderId: Customer confirmation email sent successfully");
        } else {
            error_log("Order $orderId: Failed to send customer confirmation email");
        }
        
        if ($emailResults['admin']) {
            error_log("Order $orderId: Admin notification email sent successfully");
        } else {
            error_log("Order $orderId: Failed to send admin notification email");
        }
    }
    
    error_log("add-order.php: Order created successfully: $orderId");
    echo json_encode(['success'=>true,'orderId'=>$orderId]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("add-order.php: Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("add-order.php: General error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

} catch (Exception $e) {
    error_log("add-order.php: Fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Fatal error: ' . $e->getMessage()]);
}
?> 