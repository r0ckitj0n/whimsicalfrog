<?php
require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';
AuthHelper::requireAdmin(403, 'Admin access required');

$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
$baseDir = realpath(dirname(__DIR__, 2) . '/documentation');
$fileParam = isset($_GET['file']) ? (string)$_GET['file'] : '';
$fileParam = trim(str_replace('\\', '/', $fileParam), '/');

$absPath = $baseDir;
if ($fileParam !== '') {
  $candidate = realpath(dirname(__DIR__, 2) . '/' . $fileParam);
  if ($candidate && strpos($candidate, $baseDir) === 0 && is_file($candidate)) {
    $absPath = $candidate;
  } else {
    http_response_code(404);
    echo 'File not found or not permitted';
    exit;
  }
} else {
  http_response_code(400);
  echo 'Missing file parameter';
  exit;
}

$raw = @file_get_contents($absPath);
if ($raw === false) { http_response_code(500); echo 'Failed to read file'; exit; }

function wf_md_to_html($md) {
  $md = str_replace(["\r\n", "\r"], "\n", $md);
  $out = [];
  $lines = explode("\n", $md);
  $inCode = false; $codeLang = '';
  $para = '';
  $flushPara = function() use (&$out, &$para) {
    $t = trim($para);
    if ($t !== '') { $out[] = '<p>' . $t . '</p>'; }
    $para = '';
  };
  foreach ($lines as $line) {
    if (preg_match('/^```(\w+)?\s*$/', $line, $m)) {
      if ($inCode) {
        $out[] = '</code></pre>';
        $inCode = false; $codeLang = '';
      } else {
        if (trim($para) !== '') $flushPara();
        $inCode = true; $codeLang = isset($m[1]) ? htmlspecialchars($m[1]) : '';
        $cls = $codeLang ? ' class="language-' . $codeLang . '"' : '';
        $out[] = '<pre><code' . $cls . '>';
      }
      continue;
    }
    if ($inCode) {
      $out[] = htmlspecialchars($line);
      continue;
    }
    if (preg_match('/^(#{1,6})\s*(.+)$/', $line, $m)) {
      $flushPara();
      $lvl = strlen($m[1]);
      $txt = trim($m[2]);
      $out[] = '<h' . $lvl . '>' . htmlspecialchars($txt) . '</h' . $lvl . '>';
      continue;
    }
    if (trim($line) === '') { $flushPara(); continue; }
    $l = htmlspecialchars($line);
    $l = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $l);
    $l = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $l);
    $l = preg_replace('/`([^`]+)`/', '<code>$1</code>', $l);
    if ($para !== '') $para .= ' ';
    $para .= $l;
  }
  if ($inCode) { $out[] = '</code></pre>'; }
  if (trim($para) !== '') $out[] = '<p>' . $para . '</p>';
  return implode("\n", $out);
}

$title = basename($absPath);
$html = wf_md_to_html($raw);

if ($isModal) {
  include dirname(__DIR__, 2) . '/partials/modal_header.php';
} else {
  include dirname(__DIR__, 2) . '/partials/header.php';
}
?>
<section class="page-content" id="mdViewer">
  <div class="container mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4"><?php echo htmlspecialchars($title); ?></h1>
    <article class="prose max-w-none" style="line-height:1.55">
      <?php echo $html; ?>
    </article>
  </div>
</section>
<?php if (!$isModal) { include dirname(__DIR__, 2) . '/partials/footer.php'; } ?>
