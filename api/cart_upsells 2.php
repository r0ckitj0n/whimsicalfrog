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

Response::validateMethod(['POST']);

$input = Response::getJsonInput();
if (!is_array($input)) {
    $input = [];
}

$skus = isset($input['skus']) && is_array($input['skus']) ? $input['skus'] : [];
$limit = isset($input['limit']) ? (int)$input['limit'] : 4;

try {
    $result = wf_resolve_cart_upsells($skus, $limit > 0 ? $limit : 4);
    Response::success(['upsells' => $result['upsells'], 'metadata' => $result['metadata']]);
} catch (Throwable $e) {
    Response::serverError('Failed to build upsell recommendations', $e->getMessage());
}
