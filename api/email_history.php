<?php
// /api/email_history.php
// Provides list/get/export for email history

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_logger.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$action = $_GET['action'] ?? 'list';

function json_response($data, $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

try {
  initializeEmailLogsTable();
} catch (Throwable $e) {
  // continue, table may already exist
}

// Detect available columns in email_logs (supports multiple historical schemas)
$__eh_columns = [];
try {
  $cols = Database::queryAll("SHOW COLUMNS FROM email_logs");
  foreach ($cols as $col) { $__eh_columns[$col['Field']] = true; }
} catch (Throwable $__) {}

$_eh_has = function($name) use ($__eh_columns) { return isset($__eh_columns[$name]); };

try {
  if ($action === 'list') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(1, min(200, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $search = trim((string)($_GET['search'] ?? ''));
    $from   = trim((string)($_GET['from'] ?? ''));
    $to     = trim((string)($_GET['to'] ?? ''));
    $type   = trim((string)($_GET['type'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    $sort   = trim((string)($_GET['sort'] ?? 'sent_at_desc'));

    $where = [];
    $params = [];
    if ($from !== '') {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from .= ' 00:00:00'; }
      $where[] = 'sent_at >= ?';
      $params[] = $from;
    }
    if ($to !== '') {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to .= ' 23:59:59'; }
      $where[] = 'sent_at <= ?';
      $params[] = $to;
    }
    if ($status !== '' && $_eh_has('status')) { $where[] = 'status = ?'; $params[] = $status; }
    if ($type !== '' && $_eh_has('email_type')) { $where[] = 'email_type = ?'; $params[] = $type; }
    if ($search !== '') {
      $searchCols = [];
      foreach (['to_email','from_email','subject','email_subject','order_id','content','created_by','error_message'] as $c) {
        if ($_eh_has($c)) $searchCols[] = "$c LIKE ?";
      }
      if ($searchCols) {
        $where[] = '(' . implode(' OR ', $searchCols) . ')';
        $like = "%" . $search . "%";
        $params = array_merge($params, array_fill(0, count($searchCols), $like));
      }
    }
    if ($from === '' && $to === '') { $where[] = 'sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Determine columns and build SELECT
    $sel = [];
    $sel[] = $_eh_has('id') ? 'id' : 'NULL AS id';
    $sel[] = $_eh_has('to_email') ? 'to_email' : "'' AS to_email";
    $sel[] = $_eh_has('from_email') ? 'from_email' : "'' AS from_email";
    if ($_eh_has('subject')) {
      $sel[] = 'subject';
    } elseif ($_eh_has('email_subject')) {
      $sel[] = 'email_subject AS subject';
    } else {
      $sel[] = "'' AS subject";
    }
    $sel[] = $_eh_has('email_type') ? 'email_type' : "'' AS email_type";
    $sel[] = $_eh_has('status') ? 'status' : "'' AS status";
    $sel[] = $_eh_has('error_message') ? 'error_message' : 'NULL AS error_message';
    if ($_eh_has('sent_at')) {
      $sel[] = 'sent_at';
    } elseif ($_eh_has('created_at')) {
      $sel[] = 'created_at AS sent_at';
    } else {
      $sel[] = 'NOW() AS sent_at';
    }
    $sel[] = $_eh_has('order_id') ? 'order_id' : 'NULL AS order_id';
    $sel[] = $_eh_has('created_by') ? 'created_by' : 'NULL AS created_by';
    $selectSql = implode(", ", $sel);

    // Sorting
    switch ($sort) {
      case 'sent_at_asc': $orderBy = ($_eh_has('sent_at') ? 'sent_at' : ($_eh_has('created_at') ? 'created_at' : 'id')) . ' ASC'; break;
      case 'subject_asc': $orderBy = ( $_eh_has('subject') ? 'subject' : ( $_eh_has('email_subject') ? 'email_subject' : 'id') ) . ' ASC'; break;
      case 'subject_desc': $orderBy = ( $_eh_has('subject') ? 'subject' : ( $_eh_has('email_subject') ? 'email_subject' : 'id') ) . ' DESC'; break;
      case 'sent_at_desc':
      default: $orderBy = ($_eh_has('sent_at') ? 'sent_at' : ($_eh_has('created_at') ? 'created_at' : 'id')) . ' DESC'; break;
    }

    $sql = "SELECT SQL_CALC_FOUND_ROWS $selectSql
            FROM email_logs
            $whereSql
            ORDER BY $orderBy
            LIMIT $limit OFFSET $offset";
    $rows = Database::queryAll($sql, $params) ?: [];

    $countRow = Database::queryOne('SELECT FOUND_ROWS() AS cnt');
    $total = (int)($countRow['cnt'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $limit));

    $data = array_map(function($r) {
      return [
        'id' => (int)$r['id'],
        'to_email' => $r['to_email'] ?? '',
        'from_email' => $r['from_email'] ?? '',
        'subject' => $r['subject'] ?? '',
        'type' => $r['email_type'] ?? '',
        'status' => $r['status'] ?? 'sent',
        'error_message' => $r['error_message'] ?? null,
        'sent_at' => $r['sent_at'] ?? null,
        'order_id' => $r['order_id'] ?? null,
        'created_by' => $r['created_by'] ?? null,
      ];
    }, $rows);

    json_response([
      'success' => true,
      'data' => $data,
      'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
      ]
    ]);
  }

  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(['success' => false, 'error' => 'Missing id'], 400);
    $sel = [];
    $sel[] = $_eh_has('id') ? 'id' : 'NULL AS id';
    $sel[] = $_eh_has('to_email') ? 'to_email' : "'' AS to_email";
    $sel[] = $_eh_has('from_email') ? 'from_email' : "'' AS from_email";
    if ($_eh_has('subject')) {
      $sel[] = 'subject';
    } elseif ($_eh_has('email_subject')) {
      $sel[] = 'email_subject AS subject';
    } else {
      $sel[] = "'' AS subject";
    }
    $sel[] = $_eh_has('content') ? 'content' : "'' AS content";
    $sel[] = $_eh_has('email_type') ? 'email_type' : "'' AS email_type";
    $sel[] = $_eh_has('status') ? 'status' : "'' AS status";
    $sel[] = $_eh_has('error_message') ? 'error_message' : 'NULL AS error_message';
    if ($_eh_has('sent_at')) {
      $sel[] = 'sent_at';
    } elseif ($_eh_has('created_at')) {
      $sel[] = 'created_at AS sent_at';
    } else {
      $sel[] = 'NOW() AS sent_at';
    }
    $sel[] = $_eh_has('order_id') ? 'order_id' : 'NULL AS order_id';
    $sel[] = $_eh_has('created_by') ? 'created_by' : 'NULL AS created_by';
    $selectSql = implode(', ', $sel);

    $row = Database::queryOne("SELECT $selectSql FROM email_logs WHERE id = ?", [$id]);
    if (!$row) json_response(['success' => false, 'error' => 'Not found'], 404);

    json_response([
      'success' => true,
      'data' => [
        'id' => (int)$row['id'],
        'to_email' => $row['to_email'] ?? '',
        'from_email' => $row['from_email'] ?? '',
        'subject' => $row['subject'] ?? '',
        'content' => $row['content'] ?? '',
        'type' => $row['email_type'] ?? '',
        'status' => $row['status'] ?? 'sent',
        'error_message' => $row['error_message'] ?? null,
        'sent_at' => $row['sent_at'] ?? null,
        'order_id' => $row['order_id'] ?? null,
        'created_by' => $row['created_by'] ?? null,
        'bcc_email' => null, // not stored
        'headers' => null,   // not stored
      ]
    ]);
  }

  if ($action === 'export') {
    // Export CSV of filtered set (no pagination)
    $search = trim((string)($_GET['search'] ?? ''));
    $from   = trim((string)($_GET['from'] ?? ''));
    $to     = trim((string)($_GET['to'] ?? ''));
    $type   = trim((string)($_GET['type'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));

    $where = [];
    $params = [];
    if ($from !== '') {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from .= ' 00:00:00'; }
      $where[] = 'sent_at >= ?';
      $params[] = $from;
    }
    if ($to !== '') {
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to .= ' 23:59:59'; }
      $where[] = 'sent_at <= ?';
      $params[] = $to;
    }
    if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; }
    if ($type !== '') { $where[] = 'email_type = ?'; $params[] = $type; }
    if ($search !== '') {
      $where[] = '(
        to_email LIKE ? OR from_email LIKE ? OR subject LIKE ? OR order_id LIKE ? OR
        content LIKE ? OR created_by LIKE ? OR error_message LIKE ?
      )';
      $like = "%" . $search . "%";
      array_push($params, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($from === '' && $to === '') { $where[] = 'sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sel = [];
    $sel[] = $_eh_has('id') ? 'id' : 'NULL AS id';
    $sel[] = $_eh_has('sent_at') ? 'sent_at' : ($_eh_has('created_at') ? 'created_at AS sent_at' : 'NOW() AS sent_at');
    $sel[] = $_eh_has('to_email') ? 'to_email' : "'' AS to_email";
    $sel[] = $_eh_has('from_email') ? 'from_email' : "'' AS from_email";
    $sel[] = $_eh_has('email_type') ? 'email_type' : "'' AS email_type";
    $sel[] = $_eh_has('status') ? 'status' : "'' AS status";
    if ($_eh_has('subject')) { $sel[] = 'subject'; }
    elseif ($_eh_has('email_subject')) { $sel[] = 'email_subject AS subject'; }
    else { $sel[] = "'' AS subject"; }
    $sel[] = $_eh_has('order_id') ? 'order_id' : 'NULL AS order_id';
    $sel[] = $_eh_has('created_by') ? 'created_by' : 'NULL AS created_by';
    $selectSql = implode(', ', $sel);

    $orderCol = $_eh_has('sent_at') ? 'sent_at' : ($_eh_has('created_at') ? 'created_at' : 'id');
    $rows = Database::queryAll("SELECT $selectSql FROM email_logs $whereSql ORDER BY $orderCol DESC", $params) ?: [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="email-history-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Sent At','To','From','Type','Status','Subject','Order','By','Error']);
    foreach ($rows as $r) {
      fputcsv($out, [
        $r['id'], $r['sent_at'], $r['to_email'], $r['from_email'], $r['email_type'], $r['status'], $r['subject'], $r['order_id'], $r['created_by'], $r['error_message']
      ]);
    }
    fclose($out);
    exit;
  }

  json_response(['success' => false, 'error' => 'Unknown action'], 400);
} catch (Throwable $e) {
  json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
