<?php
// Admin Reports/Documentation Browser (migrated to sections/tools)
// Allows admins to browse files under documentation/ and reports/ via the admin file proxy.

require_once dirname(__DIR__, 2) . '/api/config.php';
require_once dirname(__DIR__, 2) . '/includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');

$allowedBases = [
    'documentation' => realpath(dirname(__DIR__, 2) . '/documentation'),
    'reports'       => realpath(dirname(__DIR__, 2) . '/reports'),
];
$baseKey = isset($_GET['base']) && isset($allowedBases[$_GET['base']]) ? $_GET['base'] : 'documentation';
$basePath = $allowedBases[$baseKey];

$dirRel = isset($_GET['dir']) ? (string)$_GET['dir'] : '';
$dirRel = str_replace('\\', '/', $dirRel);
$dirRel = trim($dirRel, '/');
$absDir = $basePath;
if ($dirRel !== '') {
    $absDir = realpath($basePath . '/' . $dirRel) ?: $basePath;
}
if (strpos($absDir, $basePath) !== 0) {
    $absDir = $basePath;
    $dirRel = '';
}

$entries = [];
if (is_dir($absDir)) {
    $handle = opendir($absDir);
    if ($handle) {
        while (($name = readdir($handle)) !== false) {
            if ($name === '.' || $name === '..' || $name === '.htaccess') {
                continue;
            }
            $full = $absDir . '/' . $name;
            $isDir = is_dir($full);
            $entries[] = [ 'name' => $name, 'isDir' => $isDir ];
        }
        closedir($handle);
    }
}
usort($entries, function ($a, $b) { if ($a['isDir'] !== $b['isDir']) { return $a['isDir'] ? -1 : 1; } return strcasecmp($a['name'], $b['name']); });

$crumbs = [];
$accum = '';
if ($dirRel !== '') {
    foreach (explode('/', $dirRel) as $seg) {
        $accum = $accum === '' ? $seg : ($accum . '/' . $seg);
        $crumbs[] = [ 'label' => $seg, 'dir' => $accum ];
    }
}

$__wf_included_layout = false;
if (!function_exists('__wf_admin_root_footer_shutdown')) {
    include dirname(__DIR__, 2) . '/partials/header.php';
    $__wf_included_layout = true;
}
?>
<section class="page-content" id="adminReportsBrowser">
  <div class="container mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">Admin File Browser</h1>

    <div class="mb-4 flex gap-2">
      <a class="btn-chip<?php echo $baseKey === 'documentation' ? ' active' : ''; ?>" href="?base=documentation">documentation/</a>
      <a class="btn-chip<?php echo $baseKey === 'reports' ? ' active' : ''; ?>" href="?base=reports">reports/</a>
    </div>

    <div class="mb-4 text-sm">
      <strong>Path:</strong>
      <a href="?base=<?php echo urlencode($baseKey); ?>">/<?php echo htmlspecialchars($baseKey); ?>/</a>
      <?php if (!empty($crumbs)): ?>
        <?php foreach ($crumbs as $i => $c): ?>
          &raquo; <a href="?base=<?php echo urlencode($baseKey); ?>&dir=<?php echo urlencode($c['dir']); ?>"><?php echo htmlspecialchars($c['label']); ?>/</a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($dirRel !== ''): ?>
      <div class="mb-4">
        <?php $parent = dirname($dirRel);
        $upDir = $parent === '.' ? '' : $parent; ?>
        <a class="btn-chip" href="?base=<?php echo urlencode($baseKey); ?>&dir=<?php echo urlencode($upDir); ?>">&larr; Up one level</a>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded shadow divide-y">
      <?php if (empty($entries)): ?>
        <div class="p-4 text-gray-600">This folder is empty.</div>
      <?php else: ?>
        <?php foreach ($entries as $e): ?>
          <?php $childRel = $dirRel === '' ? $e['name'] : ($dirRel . '/' . $e['name']);
            $href = $e['isDir']
                ? ('?base=' . rawurlencode($baseKey) . '&dir=' . rawurlencode($childRel))
                : ('/api/admin_file_proxy.php?path=' . rawurlencode($baseKey . '/' . $childRel)); ?>
          <div class="p-3 flex items-center justify-between">
            <div>
              <span class="mr-2"><?php echo $e['isDir'] ? '📁' : '📄'; ?></span>
              <a class="text-brand-primary hover:underline" href="<?php echo htmlspecialchars($href); ?>" target="<?php echo $e['isDir'] ? '_self' : '_blank'; ?>" rel="noopener noreferrer">
                <?php echo htmlspecialchars($e['name']); ?><?php echo $e['isDir'] ? '/' : ''; ?>
              </a>
            </div>
            <?php if (!$e['isDir']): ?>
              <div>
                <a class="btn-chip" href="<?php echo htmlspecialchars($href); ?>" download>Download</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php if ($__wf_included_layout) {
    include dirname(__DIR__, 2) . '/partials/footer.php';
} ?>
