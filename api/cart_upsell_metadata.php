<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/upsell_rules_helper.php';

try {
    Database::getInstance();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

Response::validateMethod(['GET']);

try {
    $data = wf_generate_cart_upsell_rules();
    $products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];

    $categories = [];
    foreach ($products as $sku => $meta) {
        $c = trim((string)($meta['category'] ?? ''));
        if ($c !== '' && !isset($categories[$c])) $categories[$c] = true;
    }
    // Also include categories present only in leader/secondary metadata
    $leaders = isset($metadata['category_leaders']) && is_array($metadata['category_leaders']) ? $metadata['category_leaders'] : [];
    $seconds = isset($metadata['category_secondaries']) && is_array($metadata['category_secondaries']) ? $metadata['category_secondaries'] : [];
    foreach (array_keys($leaders) as $c) { if ($c !== '' && !isset($categories[$c])) $categories[$c] = true; }
    foreach (array_keys($seconds) as $c) { if ($c !== '' && !isset($categories[$c])) $categories[$c] = true; }

    $categoryList = array_values(array_keys($categories));
    sort($categoryList, SORT_NATURAL | SORT_FLAG_CASE);

    Response::success([
        'categories' => $categoryList,
        'site_top' => $metadata['site_top'] ?? null,
        'site_second' => $metadata['site_second'] ?? null,
    ]);
} catch (Throwable $e) {
    Response::serverError('Failed to load upsell metadata', $e->getMessage());
}
