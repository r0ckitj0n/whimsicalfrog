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

    // Counts
    $tables = ['categories','items','item_images','room_settings','backgrounds'];
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

    // Sample items with category linkage by category_id if present
    try {
        $rows = $pdo->query("SELECT i.sku, i.name, i.category_id FROM items i LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out['sample']['items'] = $rows;
    } catch (Throwable $e) {
        $out['sample']['items_error'] = $e->getMessage();
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
