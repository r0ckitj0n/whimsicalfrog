<?php
// api/shop_probe.php
// Admin-token protected probe to check shop data availability on the current environment.
// Usage: /api/shop_probe.php?admin_token=whimsical_admin_2024
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$token = $_GET['admin_token'] ?? null;
if ($token !== AuthHelper::ADMIN_TOKEN) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$out = [ 'ok' => true ];
try {
    $pdo = Database::getInstance();
    $meta = $pdo->query("SELECT DATABASE() AS dbname, VERSION() AS version")->fetch(PDO::FETCH_ASSOC) ?: [];
    $out['db'] = $meta;

    // List tables in current schema
    try {
        $tbls = $pdo->query("SELECT TABLE_NAME AS t FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY t ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['tables'] = array_map(function($r){ return $r['t']; }, $tbls);
    } catch (Throwable $e) {
        $out['tables_error'] = $e->getMessage();
    }

    // Counts
    $tables = ['categories','items','products','sale_items','item_images','room_settings','backgrounds'];
    foreach ($tables as $t) {
        try {
            $row = $pdo->query("SELECT COUNT(*) AS c FROM `{$t}`")->fetch(PDO::FETCH_ASSOC) ?: ['c'=>null];
            $out['counts'][$t] = (int)$row['c'];
        } catch (Throwable $e) {
            $out['counts'][$t] = null;
            $out['errors'][$t] = $e->getMessage();
        }
    }

    // Sample categories
    try {
        $rows = $pdo->query("SELECT id, name, COALESCE(slug, LOWER(REPLACE(TRIM(name), ' ', '-'))) AS slug FROM categories ORDER BY name ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['categories'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['categories_error'] = $e->getMessage();
    }

    // Sample items/products/sale_items
    // Items
    try {
        $rows = $pdo->query("SELECT i.sku, i.name, i.category_id FROM items i LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['items'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['items_error'] = $e->getMessage();
    }
    // Products
    try {
        $rows = $pdo->query("SELECT p.sku, p.name, p.category_id, p.price FROM products p LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['products'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['products_error'] = $e->getMessage();
    }

    // Sale Items (legacy / fallback)
    try {
        // Unknown schema on live: fetch all columns but limit rows to avoid error on missing specific column names
        $rows = $pdo->query("SELECT * FROM sale_items LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['sale_items'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['sale_items_error'] = $e->getMessage();
    }

    // Backgrounds under room_number 0
    try {
        $rows = $pdo->query("SELECT id, room_number, background_name, is_active, image_filename, webp_filename FROM backgrounds WHERE room_number = 0 ORDER BY is_active DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['backgrounds_room0'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['backgrounds_error'] = $e->getMessage();
    }

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
