<?php

$target = __DIR__ . '/../../sections/admin_settings.php';
$src = file_get_contents($target);
if ($src === false) {
    fwrite(STDERR, "read fail\n");
    exit(1);
}

$before = $src;

// draw(): optional chaining on d
$src = str_replace('d?.available_sections', '(d && d.available_sections)', $src);
$src = str_replace('Array.isArray(d?.sections)', '(d && Array.isArray(d.sections))', $src);

// payload(): replace three instances using ?. with guarded vars
$src = preg_replace_callback(
    '#const rows=\[\.\.\.document\.querySelectorAll\(\'\#\'\s*\+\s*TBODY\s*\+\s*\' tr\'\)\];return \{action:\'update_sections\',sections:rows\.map\(\(row,i\)=>\{(.*?)\}\)\.filter\(s=>s\.key\)\}#s',
    function ($m) {
        $block = $m[1];
        // key
        $block = preg_replace('#const key=row\.querySelector\(\'.dash-active\'\)\?\.(dataset\.key)#', 'const __elA=row.querySelector(\'.dash-active\'); const key=(__elA && __elA.dataset ? __elA.dataset.key : undefined)', $block);
        // width
        $block = preg_replace('#const width=row\.querySelector\(\'.dash-width\'\)\?\.(value)#', 'const __elW=row.querySelector(\'.dash-width\'); const width=(__elW ? __elW.value : \'half-width\')', $block);
        // active
        $block = preg_replace('#const active=row\.querySelector\(\'.dash-active\'\)\?\.(checked)\?1:0#', 'const active=(__elA && __elA.checked ? 1 : 0)', $block);
        return $block;
    },
    $src,
    1
);

// move-up/move-down: guarded dataset.key in findIndex
$src = preg_replace(
    '#querySelector\(\'.dash-active\'\)\?\.(dataset\.key)#',
    '(function(){var __el=row.querySelector(\'.dash-active\');return __el && __el.dataset ? __el.dataset.key : undefined;})()',
    $src
);

// If nothing changed, exit quietly
if ($src === $before) {
    echo "No changes needed.\n";
    exit(0);
}

// Backup and write
$bak = $target . '.' . date('Ymd_His') . '.bak';
if (file_put_contents($bak, $before) === false) {
    fwrite(STDERR, "backup failed\n");
    exit(1);
}
if (file_put_contents($target, $src) === false) {
    fwrite(STDERR, "write failed\n");
    exit(1);
}

echo "Patched successfully. Backup: $bak\n";
