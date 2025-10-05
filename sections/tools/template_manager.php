<?php
// sections/tools/template_manager.php — Tailored Template Manager (read-only)
// Supports modal context via ?modal=1 for clean iframe embedding.

$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

$baseDir = realpath($root . '/templates');
$sub = isset($_GET['dir']) ? trim((string)$_GET['dir']) : '';
$relDir = $sub !== '' ? $sub : '';

// Prevent path traversal
$requestedPath = realpath($baseDir . '/' . $relDir);
if ($requestedPath === false || strpos($requestedPath, $baseDir) !== 0) {
  $requestedPath = $baseDir;
  $relDir = '';
}

function listDirectory($path) {
  $items = [];
  if (!is_dir($path) || !is_readable($path)) return $items;
  $dh = opendir($path);
  if (!$dh) return $items;
  while (($file = readdir($dh)) !== false) {
    if ($file === '.' || $file === '..') continue;
    $full = $path . '/' . $file;
    $isDir = is_dir($full);
    $items[] = [
      'name' => $file,
      'isDir' => $isDir,
      'size' => $isDir ? 0 : @filesize($full),
      'mtime' => @filemtime($full),
      'path' => $full,
    ];
  }
  closedir($dh);
  usort($items, function($a,$b){
    if ($a['isDir'] && !$b['isDir']) return -1;
    if (!$a['isDir'] && $b['isDir']) return 1;
    return strcasecmp($a['name'],$b['name']);
  });
  return $items;
}

$items = listDirectory($requestedPath);
$crumbs = [];
if ($relDir !== '') {
  $parts = explode('/', $relDir);
  $build = '';
  foreach ($parts as $p) {
    $build = ltrim($build . '/' . $p, '/');
    $crumbs[] = $build;
  }
}

if (!$inModal) {
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_template_manager_footer_shutdown')) {
      function __wf_template_manager_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_template_manager_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="container mx-auto p-4" style="background:white">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Template Manager</h1>
    <div class="text-sm text-gray-600">Read-only viewer for files under <code>/templates/</code></div>
  </div>

  <nav class="text-sm mb-3">
    <a class="text-blue-600 hover:text-blue-800" href="?<?php echo $inModal ? 'modal=1' : ''; ?>">/templates</a>
    <?php $accum=''; foreach ($crumbs as $i=>$c): $accum=$c; ?>
      <span class="text-gray-400"> / </span>
      <a class="text-blue-600 hover:text-blue-800" href="?<?php echo ($inModal?'modal=1&':''); ?>dir=<?php echo urlencode($accum); ?>"><?php echo htmlspecialchars(basename($c)); ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="border rounded">
    <table class="w-full text-xs">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="text-left p-2 w-6">Type</th>
          <th class="text-left p-2">Name</th>
          <th class="text-left p-2">Size</th>
          <th class="text-left p-2">Modified</th>
          <th class="text-left p-2">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if ($requestedPath !== $baseDir): ?>
          <tr>
            <td class="p-2">⬆️</td>
            <td class="p-2" colspan="4">
              <?php 
                $up = dirname($relDir);
                $up = ($up === '.' ? '' : $up);
              ?>
              <a class="text-blue-600 hover:text-blue-800" href="?<?php echo ($inModal?'modal=1&':''); ?>dir=<?php echo urlencode($up); ?>">Parent directory</a>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($items as $it): ?>
          <tr class="hover:bg-gray-50">
            <td class="p-2"><?php echo $it['isDir'] ? '📁' : '📄'; ?></td>
            <td class="p-2">
              <?php if ($it['isDir']): ?>
                <a class="text-blue-600 hover:text-blue-800" href="?<?php echo ($inModal?'modal=1&':''); ?>dir=<?php echo urlencode(ltrim(($relDir?($relDir.'/'):'').$it['name'],'/')); ?>"><?php echo htmlspecialchars($it['name']); ?></a>
              <?php else: ?>
                <code><?php echo htmlspecialchars($it['name']); ?></code>
              <?php endif; ?>
            </td>
            <td class="p-2 whitespace-nowrap"><?php echo $it['isDir'] ? '-' : number_format((int)$it['size']) . ' B'; ?></td>
            <td class="p-2 whitespace-nowrap"><?php echo $it['mtime'] ? date('Y-m-d H:i', $it['mtime']) : '-'; ?></td>
            <td class="p-2">
              <?php if (!$it['isDir']): ?>
                <?php 
                  $relFile = ltrim(($relDir?($relDir.'/'):'').$it['name'],'/');
                  $proxy = '/api/admin_file_proxy.php?path=' . rawurlencode('templates/' . $relFile);
                ?>
                <a class="btn btn-secondary btn-xs" href="<?php echo $proxy; ?>" target="_blank" rel="noopener">View</a>
              <?php else: ?>
                <span class="text-gray-400">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>
