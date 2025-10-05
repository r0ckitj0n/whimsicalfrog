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

// Ensure table exists (helper is idempotent)
try { @require_once $root . '/api/email_logger.php'; if (function_exists('initializeEmailLogsTable')) { initializeEmailLogsTable(); } } catch (Throwable $____) {}

$where = [];
$params = [];
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
if ($type !== '') { $where[] = 'email_type = ?'; $params[] = $type; }
if ($q !== '') {
  $where[] = '(to_email LIKE ? OR from_email LIKE ? OR subject LIKE ? OR order_id LIKE ?)';
  $like = "%" . $q . "%";
  array_push($params, $like, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$sql = "SELECT id, to_email, from_email, subject, email_type, status, error_message, sent_at, order_id, created_by
        FROM email_logs
        $whereSql
        ORDER BY sent_at DESC
        LIMIT $limit";

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

<div class="container mx-auto p-4" style="background:white">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Email History</h1>
    <form method="get" class="flex items-center gap-2 text-sm">
      <?php if ($inModal): ?><input type="hidden" name="modal" value="1"><?php endif; ?>
      <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" class="form-input" placeholder="Search recipient, sender, subject, order ID" />
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
      <button type="submit" class="btn btn-secondary">Filter</button>
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

  <div class="text-xs text-gray-600 mt-3">
    Showing up to <?= (int)$limit ?> results.
  </div>
</div>

<?php if (!$inModal): ?>
  </div>
</div>
<?php endif; ?>
