<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $data = Response::getJsonInput();
    $sku = $data['sku'] ?? '';

    if (!$sku)
        Response::error('Missing SKU');

    $tables = ['inventory_materials', 'inventory_labors', 'inventory_energies', 'inventory_equipments'];

    foreach ($tables as $table) {
        Database::execute("DELETE FROM $table WHERE sku = ?", [$sku]);
    }

    Response::success(null, 'Cleared all factors for ' . $sku);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
