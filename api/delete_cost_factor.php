<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $data = Response::getJsonInput();
    $sku = $data['sku'] ?? '';
    $category = $data['category'] ?? '';
    $id = $data['id'] ?? null;
    $label = $data['label'] ?? null;

    if (!$sku)
        Response::error('Missing SKU');

    if ($id) {
        // Delete by ID (preferred method)
        Database::execute("DELETE FROM cost_factors WHERE id = ? AND sku = ?", [$id, $sku]);
    } elseif ($label !== null && $category) {
        // Delete by label (legacy compatibility)
        Database::execute(
            "DELETE FROM cost_factors WHERE sku = ? AND category = ? AND label = ?",
            [$sku, strtolower($category), $label]
        );
    } else {
        Response::error('Missing id or label to identify factor');
    }

    Response::success(null, 'Deleted factor');

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
