<?php
// api/health_items.php
// Reports items without a primary image and missing image files

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try { Database::getInstance(); AuthHelper::requireAdmin(); } catch (Throwable $e) { Response::serverError('DB error', $e->getMessage()); }

function fileExistsRel(?string $rel): bool {
  if (!$rel) return false;
  $abs = __DIR__ . '/..' . '/' . ltrim($rel, '/');
  return is_file($abs);
}

try {
  // Items with no record in item_images marked primary
  $noPrimary = Database::queryAll(
    "SELECT i.sku, i.name
     FROM items i
     LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
     WHERE img.id IS NULL
     ORDER BY i.sku"
  );

  // Items with primary image but missing file
  $missingFiles = Database::queryAll(
    "SELECT i.sku, i.name, img.image_path
     FROM items i
     JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
     ORDER BY i.sku"
  );
  $missing = [];
  foreach ($missingFiles as $row) {
    $path = $row['image_path'] ?? '';
    if (!$path || !fileExistsRel($path)) {
      $missing[] = [ 'sku' => $row['sku'], 'name' => $row['name'], 'image_path' => $path ];
    }
  }

  Response::success([
    'noPrimary' => $noPrimary,
    'missingFiles' => $missing,
    'counts' => [ 'noPrimary' => count($noPrimary), 'missingFiles' => count($missing) ],
  ]);
} catch (Throwable $e) {
  Response::serverError('Health items failed', $e->getMessage());
}
