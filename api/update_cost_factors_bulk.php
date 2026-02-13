<?php
// api/update_cost_factors_bulk.php

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

    Database::beginTransaction();

    foreach ($updates as $u) {
        if (!is_array($u)) {
            throw new Exception('Invalid update payload');
        }
        $id = $u['id'] ?? null;
        $cost = $u['cost'] ?? null;
        $label = isset($u['label']) ? trim((string)$u['label']) : null;

        if (!is_numeric($id) || (int)$id <= 0) {
            throw new Exception('Invalid factor id');
        }
        if ($cost === null || !is_numeric($cost) || (float)$cost < 0) {
            throw new Exception('Invalid cost');
        }
        if ($label !== null && strlen($label) > 255) {
            throw new Exception('Label too long');
        }

        if ($label !== null) {
            Database::execute(
                "UPDATE cost_factors SET cost = ?, label = ?, updated_at = NOW() WHERE id = ? AND sku = ?",
                [(float)$cost, $label, (int)$id, $sku]
            );
        } else {
            Database::execute(
                "UPDATE cost_factors SET cost = ?, updated_at = NOW() WHERE id = ? AND sku = ?",
                [(float)$cost, (int)$id, $sku]
            );
        }
    }

    // Keep items.cost_price consistent with the breakdown.
    wf_sync_item_cost_price_from_factors($sku);

    Database::commit();
    Response::success(null, 'Cost factors updated');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
