<?php
// admin_landing_coords.php — Simple admin tool to manage landing (room 'A') welcome sign coordinates
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth_helper.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/vite_helper.php';

// Require admin
AuthHelper::requireAdmin(403, 'Admin access required for Landing Coordinates');

$pdo = Database::getInstance();
$notice = null; $error = null; $coords = [ 'top' => 411, 'left' => 601, 'width' => 125, 'height' => 77 ];

// Load current coordinates for room_number 'A' if present
try {
  $row = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_number = 'A' AND is_active = 1 ORDER BY updated_at DESC LIMIT 1", []);
  if (!$row) { $row = Database::queryOne("SELECT coordinates FROM room_maps WHERE room_number = 'A' ORDER BY updated_at DESC, created_at DESC LIMIT 1", []); }
  if ($row && !empty($row['coordinates'])) {
    $arr = json_decode($row['coordinates'], true);
    if (is_array($arr) && isset($arr[0]) && is_array($arr[0])) {
      $c0 = $arr[0];
      $coords['top'] = isset($c0['top']) ? (int)$c0['top'] : $coords['top'];
      $coords['left'] = isset($c0['left']) ? (int)$c0['left'] : $coords['left'];
      $coords['width'] = isset($c0['width']) ? (int)$c0['width'] : $coords['width'];
      $coords['height'] = isset($c0['height']) ? (int)$c0['height'] : $coords['height'];
    }
  }
} catch (Throwable $e) {
  $error = 'Failed to load current landing coordinates: ' . $e->getMessage();
}

// Handle POST to save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $top = isset($_POST['top']) ? (int)$_POST['top'] : $coords['top'];
    $left = isset($_POST['left']) ? (int)$_POST['left'] : $coords['left'];
    $width = isset($_POST['width']) ? (int)$_POST['width'] : $coords['width'];
    $height = isset($_POST['height']) ? (int)$_POST['height'] : $coords['height'];

    // Basic sanity bounds (original image 1280x896)
    $top = max(0, min(896, $top));
    $left = max(0, min(1280, $left));
    $width = max(10, min(1280, $width));
    $height = max(10, min(896, $height));

    $payload = json_encode([ [ 'selector' => '.area-1', 'top' => $top, 'left' => $left, 'width' => $width, 'height' => $height ] ], JSON_UNESCAPED_SLASHES);

    Database::beginTransaction();
    // Find an existing dedicated landing map for room 'A'
    $existing = Database::queryOne("SELECT id FROM room_maps WHERE room_number = 'A' AND map_name = 'Landing Admin' LIMIT 1", []);
    if ($existing) {
      $id = (int)$existing['id'];
      Database::execute("UPDATE room_maps SET coordinates = ?, updated_at = NOW() WHERE id = ?", [$payload, $id]);
      // Activate this map, deactivate others for 'A'
      Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = 'A' AND id <> ?", [$id]);
      Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$id]);
    } else {
      Database::execute("INSERT INTO room_maps (room_number, map_name, coordinates, is_active, created_at, updated_at) VALUES ('A', 'Landing Admin', ?, 1, NOW(), NOW())", [$payload]);
      $id = (int)Database::lastInsertId();
      Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = 'A' AND id <> ?", [$id]);
    }
    Database::commit();

    $coords = [ 'top' => $top, 'left' => $left, 'width' => $width, 'height' => $height ];
    $notice = 'Landing coordinates saved and set active for room \"A\".';
  } catch (Throwable $e) {
    try { Database::rollBack(); } catch (Throwable $__) {}
    $error = 'Failed to save landing coordinates: ' . $e->getMessage();
  }
}

// Prepare Vite assets and header
ob_start();
include __DIR__ . '/partials/header.php';
$headerOut = ob_get_clean();

echo $headerOut;
?>

<div class="container mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4">Landing Coordinates (room 'A')</h1>
  <?php if ($notice): ?>
    <div class="p-3 mb-4 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="p-3 mb-4 bg-red-100 text-red-800 rounded"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-xl">
    <label class="block">
      <span class="text-sm text-gray-700">Top (px)</span>
      <input type="number" name="top" value="<?= (int)$coords['top'] ?>" class="mt-1 w-full border rounded p-2" min="0" max="896" required>
    </label>
    <label class="block">
      <span class="text-sm text-gray-700">Left (px)</span>
      <input type="number" name="left" value="<?= (int)$coords['left'] ?>" class="mt-1 w-full border rounded p-2" min="0" max="1280" required>
    </label>
    <label class="block">
      <span class="text-sm text-gray-700">Width (px)</span>
      <input type="number" name="width" value="<?= (int)$coords['width'] ?>" class="mt-1 w-full border rounded p-2" min="10" max="1280" required>
    </label>
    <label class="block">
      <span class="text-sm text-gray-700">Height (px)</span>
      <input type="number" name="height" value="<?= (int)$coords['height'] ?>" class="mt-1 w-full border rounded p-2" min="10" max="896" required>
    </label>

    <div class="md:col-span-2 flex items-center gap-3 mt-2">
      <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save & Activate</button>
      <a class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200" href="/">Preview Landing</a>
    </div>
  </form>

  <p class="text-sm text-gray-600 mt-6">Note: This updates <code>room_maps</code> for <code>room_number='A'</code> and sets the "Landing Admin" map as active, deactivating other 'A' maps.</p>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
