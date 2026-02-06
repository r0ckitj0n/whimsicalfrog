<?php
/**
 * Dashboard Sections Table Initializer
 */

function wf_ensure_dashboard_sections_table(PDO $db): void
{
    $sql = "CREATE TABLE IF NOT EXISTS dashboard_sections (
      section_key varchar(64) NOT NULL,
      display_order int NOT NULL DEFAULT 0,
      is_active tinyint(1) NOT NULL DEFAULT 1,
      show_title tinyint(1) NOT NULL DEFAULT 1,
      show_description tinyint(1) NOT NULL DEFAULT 1,
      custom_title varchar(255) DEFAULT NULL,
      custom_description text DEFAULT NULL,
      width_class varchar(64) NOT NULL DEFAULT 'half-width',
      PRIMARY KEY (section_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // @reason: Idempotent DDL - table may already exist
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
    }
}

function wf_get_available_sections(): array
{
    return [
        'metrics' => ['title' => '📊 Quick Metrics', 'description' => 'Key performance indicators', 'category' => 'Analytics'],
        'recent_orders' => ['title' => '📋 Recent Orders', 'description' => 'Latest customer orders', 'category' => 'Orders'],
        'low_stock' => ['title' => '⚠️ Low Stock Alerts', 'description' => 'Items running low', 'category' => 'Inventory'],
        'inventory_summary' => ['title' => '📦 Inventory Summary', 'description' => 'Inventory management overview', 'category' => 'Inventory'],
        'customer_summary' => ['title' => '👥 Customer Overview', 'description' => 'Recent customers and activity', 'category' => 'Customers'],
        'marketing_tools' => ['title' => '📈 Marketing Tools', 'description' => 'Quick access to marketing', 'category' => 'Marketing'],
        'order_fulfillment' => ['title' => '🚚 Order Fulfillment', 'description' => 'Fulfillment interface', 'category' => 'Orders'],
        'reports_summary' => ['title' => '📊 Reports Summary', 'description' => 'Key business reports', 'category' => 'Analytics']
    ];
}
