<?php
require_once __DIR__ . '/../api/config.php';

$contexts = ["settings","inventory","orders","customers","marketing","reports","admin","common","dashboard","pos"];
$placeholders = implode(',', array_fill(0, count($contexts), '?'));
$q = "SELECT id, page_context, element_id, COALESCE(title,'') AS title, COALESCE(content,'') AS content
      FROM help_tooltips
      WHERE is_active = 1 AND page_context IN ($placeholders)
      ORDER BY page_context, element_id";
$rows = Database::queryAll($q, $contexts);

$minLen = 30; // soft floor for content length
$banned = [
  'click here', 'learn more', 'use this', 'this button', 'this link', 'n/a', 'tbd'
];

$total = count($rows);
$tooShort = [];
$repeatsTitle = [];
$containsBanned = [];
$empty = [];

foreach ($rows as $r) {
  $ctx = $r['page_context'];
  $el = $r['element_id'];
  $title = trim((string)$r['title']);
  $content = trim((string)$r['content']);

  if ($content === '') { $empty[] = $r; continue; }
  if (mb_strlen($content) < $minLen) { $tooShort[] = $r; }

  if ($title !== '') {
    $tl = mb_strtolower($title);
    $cl = mb_strtolower($content);
    // flag if content starts with the exact title or includes title as a standalone phrase
    if (strpos($cl, $tl) !== false) {
      // stricter: starts with title
      if (preg_match('/^\s*'.preg_quote($tl, '/').'\b/u', $cl)) {
        $repeatsTitle[] = $r;
      }
    }
  }

  $cl = mb_strtolower($content);
  foreach ($banned as $b) {
    if (strpos($cl, $b) !== false) { $containsBanned[] = $r; break; }
  }
}

function sampleRows(array $arr, int $max = 15): array {
  if (count($arr) <= $max) return $arr;
  $keys = array_rand($arr, $max);
  if (!is_array($keys)) $keys = [$keys];
  $out = [];
  foreach ($keys as $k) { $out[] = $arr[$k]; }
  return $out;
}

function printSection(string $title, array $rows): void {
  echo "\n## ".$title." (".count($rows).")\n";
  foreach (sampleRows($rows, 15) as $r) {
    $ctx = $r['page_context']; $el = $r['element_id'];
    $t = trim((string)$r['title']); $c = trim((string)$r['content']);
    echo "- [".$ctx.":::".$el."] title='".str_replace("\n", ' ', $t)."'\n  → ".mb_substr($c,0,160).(mb_strlen($c)>160?'…':'')."\n";
  }
}

echo "# Tooltip QA Report\n";
echo "Total active checked: ".$total."\n";
printSection('Empty content', $empty);
printSection('Too short (<'.$minLen.' chars)', $tooShort);
printSection('Content starts with title (potential repetition)', $repeatsTitle);
printSection('Contains banned phrases', $containsBanned);

$okCount = $total - (count($empty) + count($tooShort) + count($repeatsTitle) + count($containsBanned));
echo "\nOK (no flags intersecting above categories, approximate): ".$okCount."\n";
