<?php
// api/update_price_factor.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $data = Response::getJsonInput();
    $id = $data['id'] ?? null;
    $amount = $data['amount'] ?? null;

    if (!$id || $amount === null) {
        Response::error('Missing required fields (id, amount)');
    }

    // Validate amount is numeric
    if (!is_numeric($amount)) {
        Response::error('Amount must be numeric');
    }

    $result = Database::execute(
        "UPDATE price_factors SET amount = ? WHERE id = ?",
        [(float) $amount, (int) $id]
    );

    if ($result === false) {
        Response::error('Failed to update price factor');
    }

    Response::success(null, 'Price factor updated');

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
