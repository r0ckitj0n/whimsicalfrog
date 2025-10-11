<?php

// Fix st(...) -> setStatus(...) only inside the Dashboard Config controller
$target = __DIR__ . '/../../sections/admin_settings.php';
if (!is_file($target)) {
    fwrite(STDERR, "Not found: $target\n");
    exit(1);
}

$src = file_get_contents($target);
if ($src === false) {
    fwrite(STDERR, "Read failed\n");
    exit(1);
}

$needle = "const MODAL = 'dashboardConfigModal'";
$pos = strpos($src, $needle);
if ($pos === false) {
    fwrite(STDOUT, "Controller not found (no changes).\n");
    exit(0);
}

// Find enclosing <script> ... </script> around the controller
$startScript = strrpos(substr($src, 0, $pos), '<script>');
$endScript   = strpos($src, '</script>', $pos);
if ($startScript === false || $endScript === false) {
    fwrite(STDOUT, "Script block not found (no changes).\n");
    exit(0);
}
$endScript += strlen('</script>');

$before = substr($src, 0, $startScript);
$block  = substr($src, $startScript, $endScript - $startScript);
$after  = substr($src, $endScript);

// Replace st(...) -> setStatus(...) only in this block
$patched = preg_replace('/\\bst\\s*\\(/', 'setStatus(', $block);

if ($patched === null) {
    fwrite(STDERR, "Regex error\n");
    exit(1);
}
if ($patched === $block) {
    fwrite(STDOUT, "No changes needed.\n");
    exit(0);
}

// Backup then write
$bak = $target . '.' . date('Ymd_His') . '.bak';
if (!file_put_contents($bak, $src)) {
    fwrite(STDERR, "Backup failed\n");
    exit(1);
}
if (!file_put_contents($target, $before . $patched . $after)) {
    fwrite(STDERR, "Write failed\n");
    exit(1);
}

fwrite(STDOUT, "Patched successfully. Backup: $bak\n");
