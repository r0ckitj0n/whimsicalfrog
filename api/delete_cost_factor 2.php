<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/item_price_sync.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    requireAdmin(true);

    $normalizeFactorId = static function ($rawId): ?int {
        if ($rawId === null || $rawId === '') {
            return null;
        }

        if (is_int($rawId)) {
            return $rawId > 0 ? $rawId : null;
        }

        if (is_string($rawId)) {
            $trimmed = trim($rawId);
            if ($trimmed === '') {
                return null;
            }

            if (ctype_digit($trimmed)) {
                $parsed = (int) $trimmed;
                return $parsed > 0 ? $parsed : null;
            }

            if (preg_match('/(\d+)$/', $trimmed, $matches) === 1) {
                $parsed = (int) $matches[1];
                return $parsed > 0 ? $parsed : null;
            }
        }

        return null;
    };

    $data = Response::getJsonInput();
    $sku = $data['sku'] ?? '';
    $category = $data['category'] ?? '';
    $id = $normalizeFactorId($data['id'] ?? null);
    $label = isset($data['label']) ? trim((string)$data['label']) : null;

    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', (string)$sku))
        Response::error('Missing SKU');

    if ($id === null && ($label === null || $label === '' || !$category)) {
        Response::error('Missing id or label to identify factor');
    }

    Database::beginTransaction();
    if ($id !== null) {
        // Delete by ID (preferred method)
        Database::execute("DELETE FROM cost_factors WHERE id = ? AND sku = ?", [$id, $sku]);
    } elseif ($label !== null && $category) {
        // Delete by label (legacy compatibility)
        Database::execute(
            "DELETE FROM cost_factors WHERE sku = ? AND category = ? AND label = ?",
            [$sku, strtolower($category), $label]
        );
    }

    // Keep items.cost_price consistent with the breakdown.
    wf_sync_item_cost_price_from_factors((string) $sku);
    Database::commit();

    Response::success(null, 'Deleted factor');

} catch (Exception $e) {
    try {
        Database::rollBack();
    } catch (Throwable $_ignored) {
        // ignore
    }
    Response::serverError($e->getMessage());
}
