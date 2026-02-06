<?php
/**
 * includes/helpers/SalesHelper.php
 * Helper class for sales management logic
 */

require_once __DIR__ . '/../../Constants.php';

class SalesHelper {
    public static function getSaleStatus($isActive, $startDate, $endDate): string {
        if ($isActive != 1) return WF_Constants::SALES_STATUS_INACTIVE;
        $now = date('Y-m-d H:i:s');
        if ($now >= $startDate && $now <= $endDate) return WF_Constants::SALES_STATUS_ACTIVE;
        if ($now < $startDate) return WF_Constants::SALES_STATUS_SCHEDULED;
        return WF_Constants::SALES_STATUS_EXPIRED;
    }

    public static function getSaleItemsSkuCol(): string {
        try {
            $row = Database::queryOne("SHOW COLUMNS FROM sale_items LIKE 'sku'");
            return $row ? 'sku' : 'item_sku';
        } catch (Throwable $e) {
            return 'sku';
        }
    }

    public static function getSalesList($skuCol): array {
        return Database::queryAll(
            "SELECT s.*, 
                   COUNT(si.`$skuCol`) as item_count,
                   CASE 
                       WHEN s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date THEN ?
                       WHEN s.is_active = 1 AND NOW() < s.start_date THEN ?
                       WHEN s.is_active = 1 AND NOW() > s.end_date THEN ?
                       ELSE ?
                   END as status
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            GROUP BY s.id
            ORDER BY s.created_at DESC",
            [WF_Constants::SALES_STATUS_ACTIVE, WF_Constants::SALES_STATUS_SCHEDULED, WF_Constants::SALES_STATUS_EXPIRED, WF_Constants::SALES_STATUS_INACTIVE]
        );
    }

    public static function createSale($data, $skuCol): int {
        Database::execute(
            "INSERT INTO sales (name, description, discount_percentage, start_date, end_date, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$data['name'], $data['description'], $data['discount_percentage'], $data['start_date'], $data['end_date'], $data['is_active']]
        );
        $saleId = Database::lastInsertId();
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item_sku) {
                Database::execute("INSERT INTO sale_items (sale_id, `$skuCol`) VALUES (?, ?)", [$saleId, $item_sku]);
            }
        }
        return $saleId;
    }
}
