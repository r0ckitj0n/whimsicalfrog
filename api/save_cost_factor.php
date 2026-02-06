<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $data = Response::getJsonInput();
    $sku = $data['sku'] ?? '';
    $category = $data['category'] ?? '';
    $cost = $data['cost'] ?? 0;
    $label = $data['label'] ?? '';
    $id = $data['id'] ?? null;
    $originalLabel = $data['originalLabel'] ?? null;
    $createdBy = $data['created_by'] ?? null;

    if (!$sku || !$category)
        Response::error('Missing required fields');

    // Validate category
    $validCategories = ['materials', 'labor', 'energy', 'equipment'];
    if (!in_array(strtolower($category), $validCategories))
        Response::error('Invalid category: ' . $category);

    $category = strtolower($category);

    if ($id) {
        // Update by ID (preferred method)
        Database::execute(
            "UPDATE cost_factors SET cost = ?, label = ?, updated_at = NOW() WHERE id = ? AND sku = ?",
            [$cost, $label ?: '', $id, $sku]
        );
    } elseif ($originalLabel !== null) {
        // Update by original label (legacy compatibility)
        Database::execute(
            "UPDATE cost_factors SET cost = ?, label = ?, updated_at = NOW() WHERE sku = ? AND category = ? AND label = ?",
            [$cost, $label ?: $originalLabel, $sku, $category, $originalLabel]
        );
    } else {
        // Insert new factor
        Database::execute(
            "INSERT INTO cost_factors (sku, category, label, cost, source, created_by, created_at, updated_at) 
             VALUES (?, ?, ?, ?, 'manual', ?, NOW(), NOW())",
            [$sku, $category, $label, $cost, $createdBy]
        );
    }

    Response::success(null, 'Saved factor');

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
