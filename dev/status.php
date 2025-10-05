<?php
// Dev status dashboard: backgrounds, item images, and room door data
header('Content-Type: text/html; charset=utf-8');

$ok = true;
$notes = [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_remote($v){ return (bool)preg_match('#^https?://#i', (string)$v); }
function abs_path($rel){
  $base = realpath(__DIR__ . '/..');
  if (!$base) $base = dirname(__DIR__);
  $rel = ltrim((string)$rel, '/');
  return $base . '/' . $rel;
}
function file_exists_rel($rel){ return is_file(abs_path($rel)); }
function head_remote($url){
  $out = ['ok'=>false,'status'=>null,'error'=>null];
  if (!is_remote($url)) return $out;
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
  if ($ok === false){ $out['error'] = curl_error($ch); }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code){ $out['status']=$code; $out['ok']=($code>=200 && $code<400); }
  return $out;
}

try {
  require_once __DIR__ . '/../api/config.php';
  require_once __DIR__ . '/../includes/database.php';
  require_once __DIR__ . '/../api/room_helpers.php';
} catch (Throwable $e) {
  $ok = false; $notes[] = 'Bootstrap error: ' . $e->getMessage();
}

$bgRows = [];
$itemRows = [];
$doors = [];

try {
  $pdo = Database::getInstance();
  // Backgrounds
  $bgRows = $pdo->query('SELECT id, room_number, background_name, image_filename, webp_filename FROM backgrounds ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
  // Items: discover image columns
  $cols = $pdo->query('SHOW COLUMNS FROM items')->fetchAll(PDO::FETCH_ASSOC);
  $imgCols = [];
  foreach ($cols as $c){ $n=strtolower($c['Field']??''); if($n==="") continue; if (strpos($n,'image')!==false || strpos($n,'img')!==false || strpos($n,'thumb')!==false || strpos($n,'picture')!==false){ $imgCols[] = $c['Field']; } }
  $imgCols = array_values(array_unique($imgCols));
  $sel = '`id`';
  foreach ($imgCols as $c){ $sel .= ', `'.$c.'`'; }
  $itemRows = $pdo->query("SELECT $sel FROM items LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
  // Doors
  $doors = getRoomDoorsData();
} catch (Throwable $e) {
  $ok = false; $notes[] = 'DB error: ' . $e->getMessage();
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WF Dev Status</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,'Helvetica Neue',Arial,'Noto Sans',sans-serif;line-height:1.4;padding:16px;background:#f8fafc;color:#0f172a}
    h1,h2{margin:0.2em 0}
    .ok{color:#15803d}
    .warn{color:#b45309}
    .err{color:#b91c1c}
    table{border-collapse:collapse;width:100%;margin:12px 0}
    th,td{border:1px solid #e5e7eb;padding:6px 8px;text-align:left;font-size:14px}
    th{background:#f1f5f9}
    code{background:#0ea5e914;padding:1px 4px;border-radius:4px}
    .grid{display:grid;grid-template-columns:1fr;gap:16px}
    @media(min-width:900px){.grid{grid-template-columns:1fr 1fr}}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px}
  </style>
</head>
<body>
  <h1>WhimsicalFrog Dev Status</h1>
  <div><?php echo 'PHP '.h(PHP_VERSION); ?></div>
  <?php if ($notes): ?>
    <div class="card err">
      <h2>Notes</h2>
      <ul>
        <?php foreach ($notes as $n): ?>
          <li><?php echo h($n); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="card">
      <h2>Backgrounds (latest 20)</h2>
      <table>
        <thead><tr><th>ID</th><th>Room #</th><th>Name</th><th>Image</th><th>Exists</th><th>WebP</th><th>Exists</th></tr></thead>
        <tbody>
          <?php foreach ($bgRows as $r):
            $img = (string)($r['image_filename'] ?? '');
            $webp = (string)($r['webp_filename'] ?? '');
            $imgPath = (strpos($img,'images/')===0)?$img:('images/backgrounds/'.ltrim($img,'/'));
            $webpPath = (strpos($webp,'images/')===0)?$webp:('images/backgrounds/'.ltrim($webp,'/'));
            $imgOk = $img ? file_exists_rel($imgPath) : false;
            $webpOk = $webp ? file_exists_rel($webpPath) : false;
          ?>
            <tr>
              <td><?php echo h($r['id']); ?></td>
              <td><?php echo h($r['room_number']); ?></td>
              <td><?php echo h($r['background_name']); ?></td>
              <td><code><?php echo h($imgPath); ?></code></td>
              <td class="<?php echo $imgOk?'ok':'err'; ?>"><?php echo $imgOk?'OK':'MISS'; ?></td>
              <td><code><?php echo h($webpPath); ?></code></td>
              <td class="<?php echo $webpOk?'ok':'warn'; ?>"><?php echo $webpOk?'OK':($webp?'MISS':'—'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Room Doors Data</h2>
      <div>Count: <strong><?php echo h(is_array($doors)?count($doors):0); ?></strong></div>
      <table>
        <thead><tr><th>#</th><th>room_number</th><th>room_name</th><th>door_label</th></tr></thead>
        <tbody>
        <?php $i=1; foreach ((array)$doors as $d): ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo h($d['room_number'] ?? ''); ?></td>
            <td><?php echo h($d['room_name'] ?? ''); ?></td>
            <td><?php echo h($d['door_label'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card" style="grid-column: 1 / -1;">
      <h2>Items (first 20, image columns)</h2>
      <?php if (empty($imgCols)): ?>
        <div class="warn">No image-like columns (image/img/thumb/picture) found in <code>items</code> table.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <?php foreach ($imgCols as $c): ?><th><?php echo h($c); ?></th><th>Status</th><?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($itemRows as $row): ?>
              <tr>
                <td><?php echo h($row['id'] ?? ''); ?></td>
                <?php foreach ($imgCols as $c): 
                  $val = trim((string)($row[$c] ?? ''));
                  $status = 'EMPTY'; $cls='warn';
                  if ($val !== ''){
                    if (is_remote($val)){
                      $head = head_remote($val);
                      $status = $head['ok'] ? ('HTTP '.$head['status']) : ('ERR '.($head['status']?:'') . ($head['error']?(' '. $head['error']):''));
                      $cls = $head['ok']?'ok':'err';
                    } else {
                      $p1 = (strpos($val,'images/')===0)?$val:('images/items/'.ltrim($val,'/'));
                      $exists = file_exists_rel($p1) || file_exists_rel('images/'.ltrim($val,'/'));
                      $status = $exists ? 'OK (local)' : 'MISS (local)';
                      $cls = $exists?'ok':'err';
                    }
                  }
                ?>
                  <td><code><?php echo h($val); ?></code></td>
                  <td class="<?php echo $cls; ?>"><?php echo h($status); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
