<?php
header('Content-Type: application/json; charset=utf-8');

$result = [
  'status' => 'ok',
  'backgrounds' => [ 'checked' => 0, 'missing' => [], 'present' => 0 ],
  'items' => [ 'checked' => 0, 'missing' => [], 'present' => 0, 'columns_used' => [], 'remote_checked' => 0, 'remote_ok' => 0, 'remote_errors' => 0 ],
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

function wf_head_remote($url) {
  $out = ['ok' => false, 'status' => null, 'error' => null];
  if (!preg_match('#^https?://#i', $url)) return $out;
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_RETURNTRANSFER => true,
  ]);
  $ok = curl_exec($ch);
  if ($ok === false) {
    $out['error'] = curl_error($ch);
  }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code) {
    $out['status'] = $code;
    $out['ok'] = ($code >= 200 && $code < 400);
  }
  return $out;
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
  $idCols  = [];
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
      // gather potential identifier columns in priority order
      if (in_array($name, ['id','sku','item_id','product_id','uuid'])) {
        $idCols[] = $c['Field'];
      }
    }
  } catch (Throwable $e) {
    $result['notes'][] = 'items column discovery error: ' . $e->getMessage();
  }
  $imgCols = array_values(array_unique($imgCols));
  $result['items']['columns_used'] = $imgCols;

  // ---------- Scan items image columns ----------
  if (!empty($imgCols)) {
    // Build a safe SELECT column list: include any discovered id-like cols if present
    $selectCols = [];
    // add up to 2 identifier columns
    foreach (['id','sku'] as $pref) {
      foreach ($idCols as $c) {
        if (strtolower($c) === $pref && !in_array('`'.$c.'`', $selectCols, true)) {
          $selectCols[] = '`'.$c.'`';
          break;
        }
      }
    }
    // If none found, include the first column from DESCRIBE as a fallback identifier
    if (empty($selectCols) && !empty($cols)) {
      $first = $cols[0]['Field'] ?? null;
      if ($first) { $selectCols[] = '`'.$first.'`'; }
    }
    // Always include image columns
    foreach ($imgCols as $c) { $selectCols[] = '`'.$c.'`'; }
    $colList = implode(', ', array_unique($selectCols));
    try {
      $q = $pdo->query("SELECT $colList FROM items LIMIT 1000");
      while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        foreach ($imgCols as $c) {
          $val = trim((string)($row[$c] ?? ''));
          if ($val === '') continue;
          $pathsToTry = [];
          $isRemote = preg_match('#^https?://#i', $val);
          if ($isRemote) {
            // no local paths, remote check only
          } else if (strpos($val, 'images/') === 0) {
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
            $miss = [
              'column' => $c,
              'value' => $val,
              'tried' => $pathsToTry,
            ];
            // Remote HEAD check
            if ($isRemote) {
              $result['items']['remote_checked']++;
              $head = wf_head_remote($val);
              $miss['remote_status'] = $head['status'];
              $miss['remote_ok'] = $head['ok'];
              if ($head['ok']) $result['items']['remote_ok']++; else $result['items']['remote_errors']++;
              if (!$head['ok'] && $head['error']) { $miss['remote_error'] = $head['error']; }
            }
            // attach any identifier values we selected
            foreach ($row as $k=>$v) {
              $lk = strtolower($k);
              if (in_array($lk, ['id','sku','item_id','product_id','uuid'])) {
                $miss[$lk] = $v;
              }
            }
            $result['items']['missing'][] = $miss;
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
