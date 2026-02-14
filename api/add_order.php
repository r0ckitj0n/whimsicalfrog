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
require_once __DIR__ . '/../includes/auth.php';
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
    $sessionUser = getCurrentUser();
    if (!$sessionUser) {
        Response::error('Authentication required', null, 401);
    }
    $sessionUserId = (string) ($sessionUser['user_id'] ?? ($sessionUser['id'] ?? ''));
    $targetUserId = trim((string) $input['user_id']);
    if ($targetUserId === '') {
        Response::error('Invalid user_id', null, 422);
    }
    if (!isAdmin() && $sessionUserId !== $targetUserId) {
        Response::error('Forbidden: cannot create order for another user', null, 403);
    }
    if (!is_array($input['item_ids']) || !is_array($input['quantities'])) {
        Response::error('item_ids and quantities must be arrays', null, 422);
    }
    if (count($input['item_ids']) === 0 || count($input['item_ids']) !== count($input['quantities'])) {
        Response::error('item_ids and quantities must be non-empty and same length', null, 422);
    }
    $allowedPaymentMethods = [
        WF_Constants::PAYMENT_METHOD_SQUARE,
        WF_Constants::PAYMENT_METHOD_CASH,
        WF_Constants::PAYMENT_METHOD_CHECK,
        WF_Constants::PAYMENT_METHOD_PAYPAL,
        WF_Constants::PAYMENT_METHOD_VENMO,
        WF_Constants::PAYMENT_METHOD_OTHER
    ];
    if (!in_array((string) $input['payment_method'], $allowedPaymentMethods, true)) {
        Response::error('Invalid payment_method', null, 422);
    }
    if (isset($input['shipping_method'])) {
        $allowedShippingMethods = [
            WF_Constants::SHIPPING_METHOD_PICKUP,
            WF_Constants::SHIPPING_METHOD_LOCAL,
            WF_Constants::SHIPPING_METHOD_USPS,
            WF_Constants::SHIPPING_METHOD_FEDEX,
            WF_Constants::SHIPPING_METHOD_UPS
        ];
        if (!in_array((string) $input['shipping_method'], $allowedShippingMethods, true)) {
            Response::error('Invalid shipping_method', null, 422);
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
        $targetUserId
    );
    if ($debug)
        $debugData['pricing'] = $pricing;

    // 3.5. Oversell Guard (Pre-Check)
    $item_ids = $input['item_ids'];
    $quantities = $input['quantities'];
    $colors = array_pad($input['colors'] ?? [], count($item_ids), null);
    $sizes = array_pad($input['sizes'] ?? [], count($item_ids), null);

    // Master stock is enforced at the SKU level (across all variations).
    $requestedBySku = [];
    for ($i = 0; $i < count($item_ids); $i++) {
        $sku = trim((string) $item_ids[$i]);
        $qty = (int) $quantities[$i];

        if ($sku === '' || strlen($sku) > 64 || $qty <= 0 || $qty > 100000) {
            Response::error('Invalid item payload', null, 422);
        }
        $requestedBySku[$sku] = ($requestedBySku[$sku] ?? 0) + $qty;
    }

    foreach ($requestedBySku as $sku => $requestedQty) {
        $currentStock = getStockLevel(Database::getInstance(), $sku, null, null);
        if ($currentStock === false || $requestedQty > $currentStock) {
            $itemInfo = Database::queryOne("SELECT name, retail_price FROM items WHERE sku = ?", [$sku]);
            $itemName = $itemInfo ? $itemInfo['name'] : $sku;
            $itemPrice = $itemInfo ? (float) $itemInfo['retail_price'] : 0.0;

            $msg = "Sorry, \"$itemName\" is out of stock. ";
            $msg .= "We have " . ($currentStock ?: 0) . " available (you requested $requestedQty). ";
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
    $order_id = OrderSchemaHelper::generateOrderId($targetUserId, $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS);

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
