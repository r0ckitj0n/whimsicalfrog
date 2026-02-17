<?php
// api/update_price_factor.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/item_price_sync.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    requireAdmin(true);

    $data = Response::getJsonInput();
    $id = $data['id'] ?? null;
    $amount = $data['amount'] ?? null;

    if (!$id || !is_numeric($id) || $amount === null) {
        Response::error('Missing required fields (id, amount)');
    }

    // Validate amount is numeric
    if (!is_numeric($amount)) {
        Response::error('Amount must be numeric');
    }

    Database::beginTransaction();
    $skuRow = Database::queryOne('SELECT sku FROM price_factors WHERE id = ? LIMIT 1', [(int) $id]);
    if (!$skuRow || empty($skuRow['sku'])) {
        Database::rollBack();
        Response::error('Price factor not found', null, 404);
    }
    $sku = (string) $skuRow['sku'];

    $result = Database::execute(
        "UPDATE price_factors SET amount = ? WHERE id = ?",
        [(float) $amount, (int) $id]
    );

    if ($result === false) {
        Database::rollBack();
        Response::error('Failed to update price factor');
    }

    // Keep items.retail_price consistent with the breakdown.
    wf_sync_item_retail_price_from_factors($sku);
    Database::commit();

    Response::success(null, 'Price factor updated');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
