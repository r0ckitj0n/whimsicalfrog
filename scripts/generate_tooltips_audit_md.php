<?php
require_once __DIR__ . '/../api/config.php';

$contexts = ["settings","inventory","orders","customers","marketing","reports","admin","common","dashboard","pos"];
$placeholders = implode(',', array_fill(0, count($contexts), '?'));
$q = "SELECT page_context, element_id, COALESCE(title,'') AS title, COALESCE(content,'') AS content
      FROM help_tooltips
      WHERE is_active = 1 AND page_context IN ($placeholders)
      ORDER BY page_context, element_id";
$rows = Database::queryAll($q, $contexts);

$byCtx = [];
foreach ($rows as $r) {
  $byCtx[$r['page_context']][] = $r;
}

$now = date('Y-m-d H:i:s');
$total = count($rows);

echo "# Admin Tooltips Audit (v8 FULL)\n\n";
echo "Generated: $now\n\n";
echo "Total active tooltips: $total\n\n";
echo "Format per entry:\n";
echo "- [context:::element_id]\n";
echo "  - Title: original title from DB (if any)\n";
echo "  - Tooltip: final content in DB\n\n";

ksort($byCtx);
foreach ($byCtx as $ctx => $items) {
  echo "## ".$ctx."\n";
  foreach ($items as $r) {
    $title = trim((string)$r['title']);
    $content = trim((string)$r['content']);
    $titleOne = str_replace(["\r","\n"], [' ', ' '], $title);
    $contentOne = str_replace(["\r"], [' '], $content);
    echo "- [".$ctx.":::".$r['element_id']."]\n";
    echo "  - Title: ".($titleOne !== '' ? $titleOne : '(none)')."\n";
    echo "  - Tooltip: ".$contentOne."\n";
  }
  echo "\n";
}
