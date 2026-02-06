<?php
// includes/orders/helpers/OrderSchemaHelper.php

require_once __DIR__ . '/../../Constants.php';

class OrderSchemaHelper
{
    /**
     * Ensure necessary columns exist in orders and order_items tables
     */
    public static function ensureSchema()
    {
        $schema = [
            'order_items.size' => false,
            'orders.shipping_method' => false,
            'orders.shipping_address' => false,
            'orders.payment_status' => false,
            'sizeMaxLen' => 32
        ];

        try {
            // order_items.size
            $cols = Database::queryAll("SHOW COLUMNS FROM order_items LIKE 'size'");
            if (empty($cols)) {
                Database::execute("ALTER TABLE order_items ADD COLUMN size VARCHAR(32) DEFAULT NULL AFTER color");
                $schema['order_items.size'] = true;
            } else {
                $schema['order_items.size'] = true;
                if (preg_match('/varchar\((\d+)\)/i', $cols[0]['Type'], $m)) {
                    $schema['sizeMaxLen'] = (int) $m[1];
                    if ($schema['sizeMaxLen'] < 32) {
                        Database::execute("ALTER TABLE order_items MODIFY COLUMN size VARCHAR(32) DEFAULT NULL");
                        $schema['sizeMaxLen'] = 32;
                    }
                }
            }

            // orders.shipping_method
            if (empty(Database::queryAll("SHOW COLUMNS FROM orders LIKE 'shipping_method'"))) {
                Database::execute("ALTER TABLE orders ADD COLUMN shipping_method VARCHAR(50) DEFAULT ? AFTER payment_method", [WF_Constants::SHIPPING_METHOD_PICKUP]);
                $schema['orders.shipping_method'] = true;
            } else {
                $schema['orders.shipping_method'] = true;
            }

            // orders.shipping_address
            if (empty(Database::queryAll("SHOW COLUMNS FROM orders LIKE 'shipping_address'"))) {
                Database::execute("ALTER TABLE orders ADD COLUMN shipping_address JSON NULL AFTER shipping_method");
                $schema['orders.shipping_address'] = true;
            } else {
                $schema['orders.shipping_address'] = true;
            }

            // orders.payment_status
            if (!empty(Database::queryAll("SHOW COLUMNS FROM orders LIKE 'payment_status'"))) {
                $schema['orders.payment_status'] = true;
            }

        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::debug('Schema migration warning', ['error' => $e->getMessage()]);
            }
        }

        return $schema;
    }

    /**
     * Generate a unique compact order ID
     */
    public static function generateOrderId($user_id, $shipping_method)
    {
        $customerNum = self::getCompactCustomerNum($user_id);
        $compactDate = self::getCompactDate();
        $shippingCode = self::getShippingCode($shipping_method);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $randomNum = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            $candidate = $customerNum . $compactDate . $shippingCode . $randomNum;
            if (!Database::queryOne('SELECT 1 FROM orders WHERE id = ? LIMIT 1', [$candidate])) {
                return $candidate;
            }
        }

        // Fallback
        $suffix = substr(strtoupper(bin2hex(random_bytes(2))), 0, 2);
        return $customerNum . $compactDate . $shippingCode . $suffix;
    }

    private static function getCompactCustomerNum($user_id)
    {
        if (preg_match('/U(\d+)/', $user_id, $matches)) {
            return str_pad($matches[1] % 100, 2, '0', STR_PAD_LEFT);
        }
        return str_pad(abs(crc32($user_id)) % 100, 2, '0', STR_PAD_LEFT);
    }

    private static function getCompactDate()
    {
        $monthLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
        return $monthLetters[date('n') - 1] . date('d');
    }

    private static function getShippingCode($method)
    {
        $codes = [
            WF_Constants::SHIPPING_METHOD_PICKUP => 'P',
            WF_Constants::SHIPPING_METHOD_LOCAL => 'L',
            WF_Constants::SHIPPING_METHOD_USPS => 'U',
            WF_Constants::SHIPPING_METHOD_FEDEX => 'F',
            WF_Constants::SHIPPING_METHOD_UPS => 'X'
        ];
        return $codes[$method] ?? 'P';
    }

    /**
     * Get information for order item ID generation
     */
    public static function getOrderItemSequenceInfo()
    {
        $useStringIds = false;
        try {
            $idCol = Database::queryAll("SHOW COLUMNS FROM order_items LIKE 'id'");
            if ($idCol && strpos(strtolower($idCol[0]['Type']), 'char') !== false) {
                $useStringIds = true;
            }
            // @reason: Schema introspection failure is non-critical - defaults to integer IDs
        } catch (Exception $e) {
        }

        $nextSequence = 1;
        if ($useStringIds) {
            $maxRow = Database::queryOne("SELECT id FROM order_items WHERE id REGEXP '^OI[0-9]+$' ORDER BY CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC LIMIT 1");
            if ($maxRow) {
                $nextSequence = (int) substr($maxRow['id'], 2) + 1;
            }
        }

        return ['useStringIds' => $useStringIds, 'nextSequence' => $nextSequence];
    }
}
