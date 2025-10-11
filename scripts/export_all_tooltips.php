<?php
require_once __DIR__ . '/../api/config.php';
$contexts = ["settings","inventory","orders","customers","marketing","reports","admin","common","dashboard","pos"];
$placeholders = implode(',', array_fill(0, count($contexts), '?'));
$q = "SELECT element_id, page_context, COALESCE(title,'') AS title FROM help_tooltips WHERE is_active=1 AND page_context IN ($placeholders) ORDER BY page_context, element_id";
$rows = Database::queryAll($q, $contexts);
foreach ($rows as $r) {
  $line = $r['page_context'] . ':::' . $r['element_id'] . ':::' . str_replace(["\r","\n"], [' ', ' '], $r['title']);
  echo $line . "\n";
}
