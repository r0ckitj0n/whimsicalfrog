<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    requireAdmin(true);

    $data = Response::getJsonInput();
    $sku = trim((string)($data['sku'] ?? ''));

    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku))
        Response::error('Missing SKU');

    $tables = ['inventory_materials', 'inventory_labors', 'inventory_energies', 'inventory_equipments'];

    foreach ($tables as $table) {
        Database::execute("DELETE FROM `$table` WHERE sku = ?", [$sku]);
    }

    Response::success(null, 'Cleared all factors for ' . $sku);

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
