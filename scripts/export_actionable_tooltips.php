<?php
require_once __DIR__ . '/../api/config.php';
$q = "SELECT element_id, page_context, COALESCE(title,'') AS title, COALESCE(content,'') AS content
      FROM help_tooltips
      WHERE is_active = 1
        AND page_context IN ('settings','inventory','orders','customers','marketing','reports','admin','common','dashboard','pos')
        AND (
          element_id REGEXP '(btn|button|link|tab|^action:|open|view|print|refund|ship|save|delete|import|export|duplicate|bulk|sync|test|toggle|apply|confirm)'
        )
      ORDER BY page_context, element_id";
$rows = Database::queryAll($q);
foreach ($rows as $r) {
  $line = $r['page_context'] . ":::" . $r['element_id'] . ":::" . str_replace(["\r","\n"],[' ',' '], $r['title']);
  echo $line . "\n";
}
?>
