<?php
// api/update_price_factors_bulk.php

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
    $sku = trim((string)($data['sku'] ?? ''));
    $updates = $data['updates'] ?? null;

    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        Response::error('Invalid SKU format', null, 422);
    }
    if (!is_array($updates) || count($updates) < 1) {
        Response::error('Missing required fields (updates)', null, 422);
    }

    // Start transaction.
    Database::beginTransaction();

    foreach ($updates as $u) {
        if (!is_array($u)) {
            throw new Exception('Invalid update payload');
        }
        $id = $u['id'] ?? null;
        $amount = $u['amount'] ?? null;

        if (!is_numeric($id) || (int)$id <= 0) {
            throw new Exception('Invalid factor id');
        }
        if ($amount === null || !is_numeric($amount) || (float)$amount < 0) {
            throw new Exception('Invalid amount');
        }

        Database::execute(
            "UPDATE price_factors SET amount = ? WHERE id = ? AND sku = ?",
            [(float)$amount, (int)$id, $sku]
        );
    }

    // Keep items.retail_price consistent with the breakdown.
    wf_sync_item_retail_price_from_factors($sku);

    Database::commit();
    Response::success(null, 'Price factors updated');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
