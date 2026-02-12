<?php

// Include the configuration file
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

/**
 * Normalize and validate a repository-local image path.
 */
function wf_resolve_local_image_path(string $rawPath): ?string
{
    $rawPath = trim($rawPath);
    if ($rawPath === '') {
        return null;
    }

    $relative = ltrim($rawPath, '/');
    if ($relative === '' || strpos($relative, '..') !== false) {
        return null;
    }

    $abs = realpath(__DIR__ . '/../' . $relative);
    if ($abs === false) {
        return null;
    }

    $root = realpath(__DIR__ . '/..');
    if ($root === false || strpos($abs, $root) !== 0) {
        return null;
    }

    return $abs;
}

/**
 * Generate potential image path variants (e.g., webp/png pairs).
 */
function wf_expand_image_variants(array $paths): array
{
    $expanded = [];

    foreach ($paths as $path) {
        $path = trim((string) $path);
        if ($path === '') {
            continue;
        }

        $expanded[$path] = true;
        $lower = strtolower($path);
        if (str_ends_with($lower, '.webp')) {
            $expanded[substr($path, 0, -5) . '.png'] = true;
            $expanded[substr($path, 0, -5) . '.jpg'] = true;
            $expanded[substr($path, 0, -5) . '.jpeg'] = true;
            continue;
        }
        if (str_ends_with($lower, '.png') || str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg')) {
            $extLen = str_ends_with($lower, '.jpeg') ? 5 : 4;
            $expanded[substr($path, 0, -$extLen) . '.webp'] = true;
        }
    }

    return array_keys($expanded);
}

try {
    $pdo = null;
    // Get POST data
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON', null, 400);
    }

    // Validate SKU field
    if (!isset($data['sku']) || empty($data['sku'])) {
        Response::error('Item SKU is required', null, 400);
    }

    // Extract SKU
    $sku = trim((string)$data['sku']);
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        Response::error('Invalid SKU format', null, 422);
    }

    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if item exists and gather direct image_url reference.
    $itemRow = Database::queryOne('SELECT sku, image_url FROM items WHERE sku = ? LIMIT 1', [$sku]);
    if (!$itemRow) {
        Response::notFound('Item not found');
    }

    // Gather all image paths tied to the SKU before deleting DB rows.
    $imageRows = Database::queryAll(
        'SELECT image_path, original_path FROM item_images WHERE sku = ?',
        [$sku]
    );
    $candidatePaths = [];
    foreach ($imageRows as $imageRow) {
        $candidatePaths[] = (string) ($imageRow['image_path'] ?? '');
        $candidatePaths[] = (string) ($imageRow['original_path'] ?? '');
    }
    $candidatePaths[] = (string) ($itemRow['image_url'] ?? '');
    $candidatePaths = wf_expand_image_variants($candidatePaths);

    // Delete database rows in a transaction (metadata first, then item).
    Database::beginTransaction();
    Database::execute('DELETE FROM item_images WHERE sku = ?', [$sku]);
    $affected = Database::execute('DELETE FROM items WHERE sku = ?', [$sku]);

    if ($affected === false || $affected < 1) {
        Database::rollBack();
        throw new Exception('Failed to delete item');
    }
    Database::commit();

    // Best-effort file cleanup after commit so DB state remains authoritative.
    $deletedFiles = [];
    $missingFiles = [];
    $failedFiles = [];

    foreach ($candidatePaths as $candidatePath) {
        $absPath = wf_resolve_local_image_path($candidatePath);
        if ($absPath === null) {
            continue;
        }
        if (!is_file($absPath)) {
            $missingFiles[] = $candidatePath;
            continue;
        }
        if (@unlink($absPath)) {
            $deletedFiles[] = $candidatePath;
            continue;
        }
        $failedFiles[] = $candidatePath;
    }

    Response::success([
        'message' => 'Item deleted successfully',
        'sku' => $sku,
        'deleted_image_files' => array_values(array_unique($deletedFiles)),
        'missing_image_files' => array_values(array_unique($missingFiles)),
        'failed_image_files' => array_values(array_unique($failedFiles))
    ]);

} catch (PDOException $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Handle database errors
    Response::serverError('Database connection failed', $e->getMessage());
} catch (Exception $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Handle general errors
    Response::serverError('An unexpected error occurred', $e->getMessage());
}
