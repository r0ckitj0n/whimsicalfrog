<?php
// includes/orders/helpers/OrderActionHelper.php

require_once __DIR__ . '/../../Constants.php';
require_once __DIR__ . '/../../helpers/BusinessDateTimeHelper.php';

class OrderActionHelper
{
    /**
     * Create an order and its items in a transaction
     */
    public static function processOrder($order_id, $input, $pricing, $schemaInfo)
    {
        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            // 1. Insert Order
            self::insertOrder($order_id, $input, $pricing, $schemaInfo);

            // 2. Increment Coupon Usage
            if (!empty($input['coupon_code'])) {
                Database::execute("UPDATE coupons SET usage_count = usage_count + 1 WHERE code = ?", [$input['coupon_code']]);
            }

            // 3. Insert Items and Reduce Stock
            self::processItems($order_id, $input, $pricing, $schemaInfo);

            // 4. Attribution
            self::processAttribution($order_id, $pricing['total']);

            Database::commit();
            return true;
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function insertOrder($order_id, $input, $pricing, $schemaInfo)
    {
        $cols = ['id', 'user_id', 'total_amount', 'tax_amount', 'shipping_cost', 'payment_method', 'status', 'created_at'];
        $vals = [
            $order_id,
            $input['user_id'],
            $pricing['total'],
            $pricing['tax'] ?? 0.00,
            $pricing['shipping'] ?? 0.00,
            $input['payment_method'],
            (in_array($input['payment_method'], [WF_Constants::PAYMENT_METHOD_CASH, WF_Constants::PAYMENT_METHOD_CHECK]) ? WF_Constants::ORDER_STATUS_PENDING : WF_Constants::ORDER_STATUS_PROCESSING),
            BusinessDateTimeHelper::nowUtcString()
        ];

        if ($schemaInfo['orders.shipping_method']) {
            $cols[] = 'shipping_method';
            $vals[] = $input['shipping_method'] ?? WF_Constants::SHIPPING_METHOD_USPS;
        }
        if ($schemaInfo['orders.shipping_address']) {
            $cols[] = 'shipping_address';
            $vals[] = !empty($input['shipping_address']) ? json_encode($input['shipping_address']) : '{}';
        }
        if (!empty($input['coupon_code'])) {
            $cols[] = 'coupon_code';
            $vals[] = $input['coupon_code'];
        }
        if ($pricing['discount'] > 0) {
            $cols[] = 'discount_amount';
            $vals[] = $pricing['discount'];
        }
        if ($schemaInfo['orders.payment_status']) {
            $cols[] = 'payment_status';
            $status = $input['payment_status'] ?? ($input['payment_method'] === WF_Constants::PAYMENT_METHOD_SQUARE ? WF_Constants::PAYMENT_STATUS_PAID : WF_Constants::PAYMENT_STATUS_PENDING);
            $vals[] = $status;

            if ($status === WF_Constants::PAYMENT_STATUS_PAID) {
                $cols[] = 'payment_at';
                $vals[] = BusinessDateTimeHelper::nowUtcString();
            }
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES ($placeholders)";
        Database::execute($sql, $vals);
    }

    private static function processItems($order_id, $input, $pricing, $schemaInfo)
    {
        $seq_info = OrderSchemaHelper::getOrderItemSequenceInfo();
        $item_ids = $input['item_ids'];
        $quantities = $input['quantities'];
        $colors = array_pad($input['colors'] ?? [], count($item_ids), null);
        $sizes = array_pad($input['sizes'] ?? [], count($item_ids), null);

        for ($i = 0; $i < count($item_ids); $i++) {
            $sku = $item_ids[$i];
            $qty = (int) $quantities[$i];
            $color = $colors[$i];
            $size = $sizes[$i];

            // Truncate size if needed
            if ($size && strlen($size) > $schemaInfo['sizeMaxLen']) {
                $size = substr($size, 0, $schemaInfo['sizeMaxLen']);
            }

            // Get resolved price for this specific item in the order
            $resolved = OrderPricingHelper::resolveItemPrice($sku);
            $item_price = $resolved['price'];
            $effective_sku = $resolved['sku'];

            // Apply sale discount if applicable
            $sale_items_sku_col = self::getSaleItemsSkuColumn();
            $sale_pct = self::getActiveSalePct($effective_sku, $sale_items_sku_col);
            if ($sale_pct > 0 && $item_price > 0) {
                $item_price = round($item_price * (1 - ($sale_pct / 100)), 2);
            }

            // Insert Order Item
            self::insertOrderItem($order_id, $effective_sku, $qty, $item_price, $color, $size, $seq_info, $i, $schemaInfo);

            // Reduce Stock
            self::reduceStock($effective_sku, $qty, $color, $size);
        }
    }

    private static function insertOrderItem($order_id, $sku, $qty, $price, $color, $size, $seq_info, $index, $schemaInfo)
    {
        $cols = ['order_id', 'sku', 'quantity', 'unit_price', 'color'];
        $vals = [$order_id, $sku, $qty, $price, $color];

        if ($seq_info['useStringIds']) {
            array_unshift($cols, 'id');
            array_unshift($vals, 'OI' . str_pad($seq_info['nextSequence'] + $index, 10, '0', STR_PAD_LEFT));
        }

        if ($schemaInfo['order_items.size']) {
            $cols[] = 'size';
            $vals[] = $size;
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        Database::execute("INSERT INTO order_items (" . implode(',', $cols) . ") VALUES ($placeholders)", $vals);
    }

    private static function reduceStock($sku, $qty, $color, $size)
    {
        $stock_reduced = false;

        if ($size) {
            $color_id = null;
            if ($color) {
                $color_row = Database::queryOne("SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1", [$sku, $color]);
                $color_id = $color_row ? $color_row['id'] : null;
            }

            $where = "item_sku = ? AND size_code = ? AND is_active = 1";
            $params = [$qty, $sku, $size];
            if ($color_id) {
                $where .= " AND color_id = ?";
                $params[] = $color_id;
            } else {
                $where .= " AND color_id IS NULL";
            }

            Database::execute("UPDATE item_sizes SET stock_level = GREATEST(stock_level - ?, 0) WHERE $where", $params);
            $stock_reduced = true;

            if ($color_id) {
                Database::execute("UPDATE item_colors SET stock_level = (SELECT COALESCE(SUM(stock_level), 0) FROM item_sizes WHERE item_sku = ? AND is_active = 1) WHERE item_sku = ?", [$sku, $sku]);
            }
        }

        if (!$stock_reduced && $color) {
            // Need to require stock_manager.php in conductor for reduceStockForSale
            if (function_exists('reduceStockForSale')) {
                $stock_reduced = reduceStockForSale(Database::getInstance(), $sku, $qty, $color, null, false);
            }
        }

        if (!$stock_reduced) {
            Database::execute("UPDATE items SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE sku = ?", [$qty, $sku]);
        }

        // 3. Low Stock Trigger (Post-Check)
        self::checkLowStock($sku, $color, $size);
    }

    private static function checkLowStock($sku, $color = null, $size = null)
    {
        try {
            $item = Database::queryOne("SELECT stock_quantity, reorder_point FROM items WHERE sku = ?", [$sku]);
            if (!$item)
                return;

            $new_stock = (int) $item['stock_quantity'];
            $threshold = (int) ($item['reorder_point'] ?? 0);

            if ($new_stock <= $threshold) {
                $description = "Low Stock Alert: Item $sku is at $new_stock (Threshold: $threshold)";
                if ($color || $size) {
                    $description .= " [" . implode('/', array_filter([$color, $size])) . "]";
                }

                // Log to database for now
                if (class_exists('DatabaseLogger')) {
                    DatabaseLogger::getInstance()->logAdminActivity(
                        'inventory_alert',
                        $description,
                        'inventory',
                        $sku
                    );
                } else {
                    error_log($description);
                }
            }
        } catch (Exception $e) {
            error_log("Low stock check failed: " . $e->getMessage());
        }
    }

    private static function processAttribution($order_id, $total)
    {
        $sid = $_COOKIE[session_name()] ?? '';
        if (!$sid)
            return;

        try {
            $sess = Database::queryOne("SELECT * FROM analytics_sessions WHERE session_id = ?", [$sid]);
            if (!$sess)
                return;

            $source = (string) ($sess['utm_source'] ?? '');
            $ref = (string) ($sess['referrer'] ?? '');

            $channel = $source;
            if ($channel === '' && $ref !== '') {
                $host = parse_url($ref, PHP_URL_HOST);
                $channel = $host ? str_replace('www.', '', strtolower($host)) : 'Direct';
            }
            if ($channel === '')
                $channel = 'Direct';

            Database::execute(
                "INSERT INTO order_attributions (order_id, session_id, channel, utm_source, utm_medium, utm_campaign, utm_term, utm_content, referrer, revenue)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE revenue = VALUES(revenue), channel = VALUES(channel)",
                [$order_id, $sid, $channel, $source, $sess['utm_medium'] ?? '', $sess['utm_campaign'] ?? '', $sess['utm_term'] ?? '', $sess['utm_content'] ?? '', $ref, $total]
            );

            Database::execute("UPDATE analytics_sessions SET converted = 1, conversion_value = GREATEST(conversion_value, ?) WHERE session_id = ?", [$total, $sid]);
        } catch (Exception $e) {
        }
    }

    private static function getSaleItemsSkuColumn()
    {
        try {
            return Database::queryOne("SHOW COLUMNS FROM sale_items LIKE 'sku'") ? 'sku' : 'item_sku';
        } catch (Throwable $e) {
            return 'sku';
        }
    }

    private static function getActiveSalePct($sku, $skuCol)
    {
        try {
            $row = Database::queryOne(
                "SELECT MAX(s.discount_percentage) AS discount_percentage
                 FROM sales s JOIN sale_items si ON s.id = si.sale_id
                 WHERE si.`$skuCol` = ? AND s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date",
                [$sku]
            );
            return $row ? (float) $row['discount_percentage'] : 0.0;
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}
