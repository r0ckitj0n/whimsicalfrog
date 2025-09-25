<?php
header('Content-Type: application/json; charset=utf-8');

$result = [
  'status' => 'ok',
  'backgrounds' => [ 'checked' => 0, 'missing' => [], 'present' => 0 ],
  'items' => [ 'checked' => 0, 'missing' => [], 'present' => 0, 'columns_used' => [] ],
  'notes' => [],
];

$baseDir = realpath(__DIR__ . '/..');
if (!$baseDir) { $baseDir = dirname(__DIR__); }

try {
  require_once __DIR__ . '/config.php';
  require_once __DIR__ . '/../includes/database.php';
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','error'=>'bootstrap','message'=>$e->getMessage()]);
  exit;
}

function wf_abs(string $rel) {
  global $baseDir;
  $rel = ltrim($rel, '/');
  return $baseDir . '/' . $rel;
}

function wf_check_file($path) {
  if (!$path) return false;
  $abs = wf_abs($path);
  return is_file($abs);
}

try {
  $pdo = Database::getInstance();

  // ---------- Scan backgrounds ----------
  try {
    $stmt = $pdo->query("SELECT image_filename, webp_filename FROM backgrounds");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      foreach (['image_filename','webp_filename'] as $k) {
        $fn = trim((string)($row[$k] ?? ''));
        if ($fn === '') continue;
        // Normalize to images/backgrounds/
        if (strpos($fn, 'images/') !== 0) {
          // likely stored as just a filename
          $candidate = 'images/backgrounds/' . ltrim($fn, '/');
        } else {
          $candidate = $fn;
        }
        $exists = wf_check_file($candidate);
        $result['backgrounds']['checked']++;
        if ($exists) $result['backgrounds']['present']++; else $result['backgrounds']['missing'][] = $candidate;
      }
    }
  } catch (Throwable $e) {
    $result['notes'][] = 'backgrounds scan error: ' . $e->getMessage();
  }

  // ---------- Discover image-related columns in items ----------
  $imgCols = [];
  try {
    $cols = $pdo->query('SHOW COLUMNS FROM items')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
      $name = strtolower($c['Field'] ?? '');
      if ($name === '') continue;
      if (
        strpos($name, 'image') !== false ||
        strpos($name, 'img') !== false ||
        strpos($name, 'thumb') !== false ||
        strpos($name, 'picture') !== false
      ) {
        $imgCols[] = $c['Field'];
      }
    }
  } catch (Throwable $e) {
    $result['notes'][] = 'items column discovery error: ' . $e->getMessage();
  }
  $imgCols = array_values(array_unique($imgCols));
  $result['items']['columns_used'] = $imgCols;

  // ---------- Scan items image columns ----------
  if (!empty($imgCols)) {
    $colList = '`id`, `sku`, ' . implode(', ', array_map(function($c){ return '`' . $c . '`'; }, $imgCols));
    try {
      $q = $pdo->query("SELECT $colList FROM items LIMIT 1000");
      while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        foreach ($imgCols as $c) {
          $val = trim((string)($row[$c] ?? ''));
          if ($val === '') continue;
          $pathsToTry = [];
          if (strpos($val, 'images/') === 0) {
            $pathsToTry[] = $val;
          } else {
            // try common bases
            $pathsToTry[] = 'images/items/' . ltrim($val, '/');
            $pathsToTry[] = 'images/' . ltrim($val, '/');
          }
          $found = false;
          foreach ($pathsToTry as $p) {
            if (wf_check_file($p)) { $found = $p; break; }
          }
          $result['items']['checked']++;
          if ($found) {
            $result['items']['present']++;
          } else {
            $result['items']['missing'][] = [
              'id' => $row['id'] ?? null,
              'sku' => $row['sku'] ?? null,
              'column' => $c,
              'value' => $val,
              'tried' => $pathsToTry,
            ];
          }
        }
      }
    } catch (Throwable $e) {
      $result['notes'][] = 'items scan query error: ' . $e->getMessage();
    }
  } else {
    $result['notes'][] = 'No image-related columns discovered in items table.';
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','error'=>'scan','message'=>$e->getMessage()]);
  exit;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
