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
    $cost = $data['cost'] ?? 0;
    $label = trim((string)($data['label'] ?? ''));
    $id = $normalizeFactorId($data['id'] ?? null);
    $originalLabel = $data['originalLabel'] ?? null;
    $createdBy = $data['created_by'] ?? null;

    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', (string)$sku) || !$category)
        Response::error('Missing required fields');
    if (!is_numeric($cost) || (float)$cost < 0) {
        Response::error('Invalid cost value', null, 422);
    }
    if (strlen($label) > 255) {
        Response::error('Label too long', null, 422);
    }

    // Validate category
    $validCategories = ['materials', 'labor', 'energy', 'equipment'];
    if (!in_array(strtolower($category), $validCategories))
        Response::error('Invalid category: ' . $category);

    $category = strtolower($category);

    if ($id !== null) {
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
