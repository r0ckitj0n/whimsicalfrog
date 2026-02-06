<?php
/**
 * Add Order API - Conductor
 * Standardized order creation and payment processing.
 * Delegating logic to specialized helper classes in includes/orders/helpers/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/database_logger.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/stock_manager.php';
require_once __DIR__ . '/../includes/tax_service.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/email_notifications.php';

// Conductor Helpers
require_once __DIR__ . '/../includes/orders/helpers/OrderSchemaHelper.php';
require_once __DIR__ . '/../includes/orders/helpers/OrderPricingHelper.php';
require_once __DIR__ . '/../includes/orders/helpers/OrderPaymentHelper.php';
require_once __DIR__ . '/../includes/orders/helpers/OrderActionHelper.php';

// Standardized API Headers via Response class (handled in conductor logic)
header('Content-Type: application/json');

try {
    Database::getInstance();
} catch (Exception $e) {
    Response::serverError('Database connection failed: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Only POST requests are allowed');
}

$input = Response::getJsonInput();
if (!$input) {
    Response::error('Invalid JSON input', null, 400);
}

$debug = !empty($input['debug']);
$debugData = ['received' => $input, 'notes' => []];

try {
    // 1. Ensure Schema and Migrations
    $schemaInfo = OrderSchemaHelper::ensureSchema();

    // 1.5. Ensure Logging Schema (Prevents implicit commit in transaction)
    try {
        DatabaseLogger::getInstance();
    } catch (Exception $e) {
        error_log("Early DatabaseLogger initialization failed: " . $e->getMessage());
    }

    if ($debug)
        $debugData['schema'] = $schemaInfo;

    // 2. Validate Required Fields
    $required = ['user_id', 'item_ids', 'quantities', 'payment_method', 'total'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            Response::error('Missing required field: ' . $field, null, 400);
        }
    }

    // 3. Compute Pricing (Server-side validation)
    $pricing = OrderPricingHelper::computePricing(
        $input['item_ids'],
        $input['quantities'],
        $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS,
        $input['shipping_address'] ?? null,
        $input['coupon_code'] ?? null,
        $debug,
        $input['user_id']
    );
    if ($debug)
        $debugData['pricing'] = $pricing;

    // 3.5. Oversell Guard (Pre-Check)
    $item_ids = $input['item_ids'];
    $quantities = $input['quantities'];
    $colors = array_pad($input['colors'] ?? [], count($item_ids), null);
    $sizes = array_pad($input['sizes'] ?? [], count($item_ids), null);

    for ($i = 0; $i < count($item_ids); $i++) {
        $sku = $item_ids[$i];
        $qty = (int) $quantities[$i];
        $color = $colors[$i];
        $size = $sizes[$i];

        $currentStock = getStockLevel(Database::getInstance(), $sku, $color, $size);
        if ($currentStock === false || $qty > $currentStock) {
            // Fetch name and price for better error UX
            $itemInfo = Database::queryOne("SELECT name, retail_price FROM items WHERE sku = ?", [$sku]);
            $itemName = $itemInfo ? $itemInfo['name'] : $sku;
            $itemPrice = $itemInfo ? (float) $itemInfo['retail_price'] : 0.0;

            $msg = "Sorry, \"$itemName\" is out of stock. ";
            $msg .= "We have " . ($currentStock ?: 0) . " available (you requested $qty). ";
            if ($color || $size) {
                $msg .= "Variation: " . implode(', ', array_filter([$color, $size])) . ". ";
            }
            $msg .= "Price: $" . number_format($itemPrice, 2) . ".";

            Response::error($msg, ['available' => $currentStock], 409);
        }
    }

    // 4. Compare with Client Total (Log only)
    $clientTotal = (float) $input['total'];
    if (abs($clientTotal - $pricing['total']) > 0.01) {
        if (class_exists('Logger')) {
            Logger::info('add-order total mismatch', [
                'client' => $clientTotal,
                'server' => $pricing['total']
            ]);
        }
    }

    // 5. Process Payment if Square
    $squarePaymentId = null;
    if ($input['payment_method'] === WF_Constants::PAYMENT_METHOD_SQUARE) {
        if (empty($input['square_token'])) {
            Response::error('Missing Square payment token', null, 400);
        }
        $squarePaymentId = OrderPaymentHelper::processSquarePayment(
            $input['square_token'],
            $pricing['total'],
            $input['shipping_address'] ?? null
        );
    }

    // 6. Generate Unique Order ID
    $order_id = OrderSchemaHelper::generateOrderId($input['user_id'], $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS);

    // 7. Execute Order Creation Transaction
    OrderActionHelper::processOrder($order_id, $input, $pricing, $schemaInfo);

    // 8. Log Activity
    try {
        $dbLogger = DatabaseLogger::getInstance();
        $dbLogger->logOrderActivity(
            $order_id,
            'order_created',
            'Order created via API',
            null,
            null,
            $input['user_id']
        );
    } catch (Exception $e) {
    }

    // 9. Send Notifications
    $emailResults = null;
    try {
        if (function_exists('sendOrderConfirmationEmails')) {
            $emailResults = sendOrderConfirmationEmails($order_id, Database::getInstance());
        }
    } catch (Exception $e) {
        error_log("Email notification failed for order $order_id: " . $e->getMessage());
    }

    // 10. Success Response
    $resp = ['success' => true, 'order_id' => $order_id];
    if ($debug) {
        $resp['debug'] = $debugData;
        $resp['debug']['emailResults'] = $emailResults;
    }
    Response::success($resp);

} catch (Exception $e) {
    error_log("Add Order Error: " . $e->getMessage());
    $resp = ['error' => $e->getMessage()];
    if ($debug)
        $resp['debug'] = $debugData;
    Response::serverError($e->getMessage(), $resp);
}
