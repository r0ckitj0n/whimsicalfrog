<?php
// Compare all active tooltips vs keys present in apply_tooltips_v8_manual.php
$root = dirname(__DIR__);
$applyFile = $root.'/scripts/apply_tooltips_v8_manual.php';
$allList = $root.'/documentation/all_tooltips.list';

$applySrc = file_get_contents($applyFile);
$keys = [];
if (preg_match_all("/'([a-z0-9:_-]+:::[^']+)'\s*=>/i", $applySrc, $m)) {
  foreach ($m[1] as $k) { $keys[$k] = true; }
}

$all = [];
$lines = @file($allList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($lines as $ln) {
  // each line: ctx:::element_id:::title
  $parts = explode(':::', $ln, 3);
  if (count($parts) >= 2) {
    $k = $parts[0].':::'.$parts[1];
    $all[$k] = $parts[2] ?? '';
  }
}

$remaining = array_diff_key($all, $keys);
ksort($remaining);

echo "Total active: ".count($all)."\n";
echo "Covered in apply script: ".count($keys)."\n";
echo "Remaining (not yet authored/applied): ".count($remaining)."\n";

$show = 300; $i=0;
foreach ($remaining as $k => $title) {
  echo $k.":::".$title."\n";
  if (++$i >= $show) break;
}
