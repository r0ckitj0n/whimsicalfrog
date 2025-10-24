<?php
// sections/tools/email_history.php — Focused Email History tool
// Supports modal context via ?modal=1 to render without header/footer for iframe embedding.

$root = dirname(__DIR__, 2);
$inModal = (isset($_GET['modal']) && $_GET['modal'] == '1');

require_once $root . '/api/config.php';
require_once $root . '/includes/functions.php';

// Simple filters
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(10, min(200, (int)$_GET['limit'])) : 50;
$from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$all = isset($_GET['all']) && (($_GET['all'] === '1') || ($_GET['all'] === 'true'));
$advancedOpen = ($from !== '' || $to !== '' || $status !== '' || $type !== '' || ($limit && $limit !== 50));

// Ensure table exists (helper is idempotent)
try { @require_once $root . '/api/email_logger.php'; if (function_exists('initializeEmailLogsTable')) { initializeEmailLogsTable(); } } catch (Throwable $____) {}

// Detect available columns so this view works across schema variants
$__eh_columns = [];
try {
  $cols = Database::queryAll("SHOW COLUMNS FROM email_logs");
  foreach ($cols as $col) { $__eh_columns[$col['Field']] = true; }
} catch (Throwable $__) {}
$__has = function($name) use ($__eh_columns) { return isset($__eh_columns[$name]); };

$where = [];
$params = [];
if ($from !== '') {
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from .= ' 00:00:00'; }
  $where[] = ($__has('sent_at') ? 'sent_at' : ($__has('created_at') ? 'created_at' : 'NOW()')) . ' >= ?';
  $params[] = $from;
}
if ($to !== '') {
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to .= ' 23:59:59'; }
  $where[] = ($__has('sent_at') ? 'sent_at' : ($__has('created_at') ? 'created_at' : 'NOW()')) . ' <= ?';
  $params[] = $to;
}
if ($status !== '' && $__has('status')) { $where[] = 'status = ?'; $params[] = $status; }
if ($type !== '' && $__has('email_type')) { $where[] = 'email_type = ?'; $params[] = $type; }
if ($q !== '') {
  $searchCols = [];
  foreach (['to_email','from_email','subject','email_subject','order_id','content','created_by','error_message'] as $c) {
    if ($__has($c)) $searchCols[] = "$c LIKE ?";
  }
  if ($searchCols) {
    $where[] = '(' . implode(' OR ', $searchCols) . ')';
    $like = "%" . $q . "%";
    $params = array_merge($params, array_fill(0, count($searchCols), $like));
  }
}
if ($from === '' && $to === '' && !$all && $q === '') { $where[] = ($__has('sent_at') ? 'sent_at' : ($__has('created_at') ? 'created_at' : 'NOW()')) . ' >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
// Build SELECT dynamically, aliasing fields for consistent rendering
$sel = [];
$sel[] = $__has('id') ? 'id' : 'NULL AS id';
$sel[] = $__has('to_email') ? 'to_email' : "'' AS to_email";
$sel[] = $__has('from_email') ? 'from_email' : "'' AS from_email";
if ($__has('subject')) { $sel[] = 'subject'; }
elseif ($__has('email_subject')) { $sel[] = 'email_subject AS subject'; }
else { $sel[] = "'' AS subject"; }
$sel[] = $__has('email_type') ? 'email_type' : "'' AS email_type";
$sel[] = $__has('status') ? 'status' : "'' AS status";
$sel[] = $__has('error_message') ? 'error_message' : 'NULL AS error_message';
if ($__has('sent_at')) { $sel[] = 'sent_at'; }
elseif ($__has('created_at')) { $sel[] = 'created_at AS sent_at'; }
else { $sel[] = 'NOW() AS sent_at'; }
$sel[] = $__has('order_id') ? 'order_id' : 'NULL AS order_id';
$sel[] = $__has('created_by') ? 'created_by' : 'NULL AS created_by';
$selectSql = implode(', ', $sel);

$orderCol = $__has('sent_at') ? 'sent_at' : ($__has('created_at') ? 'created_at' : 'id');

// Total count for pagination
$total = 0; $totalPages = 1; $offset = 0;
try {
  $countRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM email_logs $whereSql", $params);
  $total = (int)($countRow['cnt'] ?? 0);
  $totalPages = max(1, (int)ceil($total / $limit));
  if ($page > $totalPages) { $page = $totalPages; }
  $offset = ($page - 1) * $limit;
} catch (Throwable $__c) { $total = 0; $totalPages = 1; $offset = 0; }

$sql = "SELECT $selectSql FROM email_logs $whereSql ORDER BY $orderCol DESC LIMIT $limit OFFSET $offset";

$logs = [];
try { $logs = Database::queryAll($sql, $params); } catch (Throwable $e) { $logs = []; }

if (!$inModal) {
  // Full layout with admin header/nav
  if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    $page = 'admin';
    include $root . '/partials/header.php';
    if (!function_exists('__wf_email_history_footer_shutdown')) {
      function __wf_email_history_footer_shutdown() { @include __DIR__ . '/../../partials/footer.php'; }
    }
    register_shutdown_function('__wf_email_history_footer_shutdown');
  }
  $section = 'settings';
  include_once $root . '/components/admin_nav_tabs.php';
}
?>
<?php if (!$inModal): ?>
<div class="admin-dashboard page-content">
  <div id="admin-section-content">
<?php endif; ?>

<div class="container mx-auto p-4 bg-white">
  <div class="flex items-center justify-between mb-4">
    <?php if (!$inModal): ?>
    <h1 class="text-2xl font-bold">Email History</h1>
    <?php endif; ?>
    <form method="get" class="flex items-center gap-2 text-sm">
      <?php if ($inModal): ?><input type="hidden" name="modal" value="1"><?php endif; ?>
      <input type="hidden" name="page" value="1" />
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" class="form-input" placeholder="Search recipient, sender, subject, order ID" />
      <button type="submit" class="btn btn-secondary">Search</button>
      <details class="ml-2" <?= $advancedOpen ? 'open' : '' ?>>
        <summary class="cursor-pointer select-none text-xs text-gray-600">Advanced filters</summary>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <label class="text-xs text-gray-600">From
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-input" />
          </label>
          <label class="text-xs text-gray-600">To
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-input" />
          </label>
          <select name="type" class="form-input">
            <option value="">All Types</option>
            <?php foreach ([
              'order_confirmation' => 'Order Confirmation',
              'admin_notification' => 'Admin Notification',
              'test_email' => 'Test Email',
              'manual_resend' => 'Manual Resend',
            ] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <select name="status" class="form-input">
            <option value="">All Status</option>
            <?php foreach ([ 'sent' => 'Sent', 'failed' => 'Failed' ] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <select name="limit" class="form-input">
            <?php foreach ([25,50,100,150,200] as $opt): ?>
              <option value="<?= $opt ?>" <?= $limit===$opt ? 'selected' : '' ?>>Show <?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-xs text-gray-500">Default shows last 30 days. Add filters to narrow results.</div>
          <?php
            // Build quick preset URLs preserving current filters
            $base = [ 'q' => $q, 'type' => $type, 'status' => $status, 'limit' => $limit, 'page' => 1 ];
            if ($inModal) { $base['modal'] = '1'; }
            $mk = function($arr) { return '?' . http_build_query($arr); };
            $p7  = $base; $p7['from'] = date('Y-m-d', strtotime('-7 days'));  unset($p7['to'], $p7['all']);
            $p30 = $base; $p30['from'] = date('Y-m-d', strtotime('-30 days')); unset($p30['to'], $p30['all']);
            $p90 = $base; $p90['from'] = date('Y-m-d', strtotime('-90 days')); unset($p90['to'], $p90['all']);
            $pAll = $base; unset($pAll['from'], $pAll['to']); $pAll['all'] = 1;
          ?>
          <div class="flex items-center gap-1 text-xs">
            <span class="text-gray-500 mr-1">Quick ranges:</span>
            <a class="link" href="<?= $mk($p7) ?>">7d</a>
            <a class="link" href="<?= $mk($p30) ?>">30d</a>
            <a class="link" href="<?= $mk($p90) ?>">90d</a>
            <a class="link" href="<?= $mk($pAll) ?>">All</a>
          </div>
          <?php
            // CSV export URL (map q->search; include from/to or all)
            $exportParams = [
              'action' => 'export',
              'search' => $q,
              'from' => $all ? '1970-01-01' : $from,
              'to' => $to,
              'type' => $type,
              'status' => $status,
            ];
            $exportUrl = '/api/email_history.php?' . http_build_query($exportParams);
          ?>
          <a href="<?= htmlspecialchars($exportUrl) ?>" target="_blank" rel="noopener" class="btn btn-secondary">Download CSV</a>
        </div>
      </details>
    </form>
  </div>

  <div class="overflow-x-auto border rounded">
    <table class="w-full text-xs">
      <thead class="bg-gray-50 border-b">
        <tr>
          <th class="text-left p-2">Sent At</th>
          <th class="text-left p-2">To</th>
          <th class="text-left p-2">From</th>
          <th class="text-left p-2">Type</th>
          <th class="text-left p-2">Status</th>
          <th class="text-left p-2">Subject</th>
          <th class="text-left p-2">Order</th>
          <th class="text-left p-2">By</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if (empty($logs)): ?>
          <tr><td colspan="8" class="p-4 text-center text-gray-500">No emails found.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $row): ?>
            <tr class="hover:bg-gray-50">
              <td class="p-2 whitespace-nowrap"><?= htmlspecialchars($row['sent_at'] ?? '') ?></td>
              <td class="p-2 whitespace-nowrap"><span class="font-mono"><?= htmlspecialchars($row['to_email'] ?? '') ?></span></td>
              <td class="p-2 whitespace-nowrap"><span class="font-mono"><?= htmlspecialchars($row['from_email'] ?? '') ?></span></td>
              <td class="p-2 whitespace-nowrap"><span class="inline-block rounded bg-gray-100 px-2 py-1 text-[10px] uppercase tracking-wide"><?= htmlspecialchars($row['email_type'] ?? '') ?></span></td>
              <td class="p-2 whitespace-nowrap">
                <?php $ok = ($row['status'] ?? 'sent') === 'sent'; ?>
                <span class="inline-block rounded px-2 py-1 text-[10px] <?= $ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= $ok ? 'Sent' : 'Failed' ?></span>
              </td>
              <td class="p-2">
                <div class="truncate max-w-[420px]" title="<?= htmlspecialchars($row['subject'] ?? '') ?>"><?= htmlspecialchars($row['subject'] ?? '') ?></div>
              </td>
              <td class="p-2 whitespace-nowrap"><?= htmlspecialchars($row['order_id'] ?? '') ?></td>
              <td class="p-2 whitespace-nowrap"><?= htmlspecialchars($row['created_by'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    // Pagination controls
    $prevPage = max(1, $page - 1);
    $nextPage = min($totalPages, $page + 1);
    $qp = [
      'q' => $q, 'type' => $type, 'status' => $status, 'limit' => $limit,
      'from' => $from, 'to' => $to, 'page' => null, 'all' => $all ? 1 : null
    ];
    if ($inModal) { $qp['modal'] = '1'; }
    $mkp = function($pg) use ($qp) {
      $params = $qp; $params['page'] = $pg; return '?' . http_build_query($params);
    };
  ?>
  <div class="flex items-center justify-between mt-3 text-xs text-gray-700">
    <div>
      Page <?= (int)$page ?> of <?= (int)$totalPages ?> • <?= (int)$total ?> total
    </div>
    <div class="flex items-center gap-2">
      <a class="btn btn-secondary <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>" href="<?= $page <= 1 ? '#' : $mkp($prevPage) ?>">Prev</a>
      <a class="btn btn-secondary <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>" href="<?= $page >= $totalPages ? '#' : $mkp($nextPage) ?>">Next</a>
    </div>
  </div>

  <div class="text-xs text-gray-600 mt-3">
    <?php if ($q !== ''): ?>
      Found <?= (int)$total ?> result<?= $total===1?'':'s' ?> for "<?= htmlspecialchars($q) ?>"<?= ($from==='' && $to==='') ? ' (searched all time)' : '' ?>.
    <?php else: ?>
      Showing <?= (int)min($limit, max(0, $total - (($page-1)*$limit))) ?> of <?= (int)$total ?> from the <?= ($from!=='' || $to!=='') ? 'selected date range' : 'last 30 days' ?>.
    <?php endif; ?>
  </div>
</div>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>
