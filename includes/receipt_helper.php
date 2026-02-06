<?php
/**
 * Helper functions for the receipt page.
 */

function getSalesVerbiage()
{
    try {
        $rows = Database::queryAll("
            SELECT setting_key, setting_value 
            FROM business_settings 
            WHERE category = 'sales' AND setting_key LIKE 'receipt_%'
            ORDER BY display_order
        ");
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    } catch (Exception $e) {
        error_log("Error getting sales verbiage: " . $e->getMessage());
        return [];
    }
}

function getReceiptMessage($order, $orderItems)
{
    // Default message
    $defaultMessage = [
        'title' => 'Payment Received',
        'content' => 'Your order is being processed with care. You\'ll receive updates as your custom items are prepared and shipped.'
    ];

    try {
        // Ensure receipt_settings table exists
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS receipt_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_type ENUM('shipping_method', 'item_count', 'item_category', 'default') NOT NULL,
                condition_key VARCHAR(100) NOT NULL,
                condition_value VARCHAR(255) NOT NULL,
                message_title VARCHAR(255) NOT NULL,
                message_content TEXT NOT NULL,
                ai_generated BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_condition (setting_type, condition_key, condition_value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        Database::execute($createTableSQL);

        // Priority order: shipping method > item category > item count > default

        // 1. Check for shipping method specific message
        if (!empty($order['shipping_method'])) {
            $result = Database::queryOne("
                SELECT message_title, message_content 
                FROM receipt_settings 
                WHERE setting_type = 'shipping_method' 
                AND condition_key = 'method' 
                AND condition_value = ?
                LIMIT 1
            ", [$order['shipping_method']]);

            if ($result) {
                return [
                    'title' => $result['message_title'],
                    'content' => $result['message_content']
                ];
            }
        }

        // 2. Check for item category specific message (if single category dominates)
        if (!empty($orderItems)) {
            $categories = array_column($orderItems, 'category');
            $categories = array_filter($categories); // Remove nulls

            if (!empty($categories)) {
                $categoryCounts = array_count_values($categories);
                $dominantCategory = array_keys($categoryCounts, max($categoryCounts))[0];

                $result = Database::queryOne("
                    SELECT message_title, message_content 
                    FROM receipt_settings 
                    WHERE setting_type = 'item_category' 
                    AND condition_key = 'category' 
                    AND condition_value = ?
                    LIMIT 1
                ", [$dominantCategory]);

                if ($result) {
                    return [
                        'title' => $result['message_title'],
                        'content' => $result['message_content']
                    ];
                }
            }
        }

        // 3. Check for item count specific message
        $item_count = count($orderItems);
        $countCondition = $item_count === 1 ? '1' : 'multiple';

        $result = Database::queryOne("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'item_count' 
            AND condition_key = 'count' 
            AND condition_value = ?
            LIMIT 1
        ", [$countCondition]);

        if ($result) {
            return [
                'title' => $result['message_title'],
                'content' => $result['message_content']
            ];
        }

        // 4. Check for default message
        $result = Database::queryOne("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'default' 
            LIMIT 1
        ");

        if ($result) {
            return [
                'title' => $result['message_title'],
                'content' => $result['message_content']
            ];
        }

    } catch (Exception $e) {
        error_log("Error getting receipt message: " . $e->getMessage());
    }

    return $defaultMessage;
}
