<?php
// Categories Management API
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch (Throwable $____) {}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit(0); }

AuthHelper::requireAdmin();

function ensure_categories_table(PDO $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS categories (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  try { $db->exec($sql); } catch (Throwable $____) {}
  // Ensure sort_order exists for older tables
  try {
    $db->exec("ALTER TABLE categories ADD COLUMN sort_order INT NOT NULL DEFAULT 0");
  } catch (Throwable $____) { /* likely already exists */ }
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list';

  switch ($action) {
    case 'list': {
      $db = Database::getInstance();
      ensure_categories_table($db);
      $rows = $db->query('SELECT id, name, sort_order FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
      // Include any categories present on items that aren't in categories table yet
      try {
        $itemCats = $db->query('SELECT DISTINCT category as name FROM items WHERE category IS NOT NULL AND category <> "" ORDER BY category ASC')->fetchAll();
      } catch (Throwable $e) { $itemCats = []; }
      $byName = [];
      foreach ($rows as $r) { $byName[strtolower($r['name'])] = ['id' => (int)$r['id'], 'name' => $r['name']]; }
      foreach ($itemCats as $r) {
        $n = (string)($r['name'] ?? ''); if ($n === '') continue; $k = strtolower($n);
        if (!isset($byName[$k])) { $byName[$k] = ['id' => null, 'name' => $n]; }
      }
      $list = array_values($byName);
      // counts
      $counts = [];
      try {
        $cnt = $db->query('SELECT category as name, COUNT(*) as cnt FROM items WHERE category IS NOT NULL AND category <> "" GROUP BY category')->fetchAll();
        foreach ($cnt as $c) { $counts[$c['name']] = (int)$c['cnt']; }
      } catch (Throwable $e) {}
      foreach ($list as &$it) { $it['item_count'] = $counts[$it['name']] ?? 0; }
      Response::success(['categories' => $list]);
      break; }

    case 'add': {
      $name = trim((string)($input['name'] ?? $_POST['name'] ?? ''));
      if ($name === '') { Response::validationError('Name required'); }
      $db = Database::getInstance();
      ensure_categories_table($db);
      // Insert or ignore duplicate
      $stmt = $db->prepare('INSERT INTO categories (name) VALUES (?)');
      try { $stmt->execute([$name]); } catch (Throwable $e) {}
      Response::success(['name' => $name], 'Category added');
      break; }

    case 'rename': {
      $old = trim((string)($input['old_name'] ?? ''));
      $new = trim((string)($input['new_name'] ?? ''));
      $touchItems = (bool)($input['update_items'] ?? true);
      if ($old === '' || $new === '') { Response::validationError('old_name and new_name required'); }
      $db = Database::getInstance();
      ensure_categories_table($db);
      Database::execute('UPDATE categories SET name = ? WHERE name = ?', [$new, $old]);
      if ($touchItems) {
        Database::execute('UPDATE items SET category = ? WHERE category = ?', [$new, $old]);
      }
      Response::success(['old' => $old, 'new' => $new], 'Category renamed');
      break; }

    case 'delete': {
      $name = trim((string)($input['name'] ?? ''));
      $reassign = isset($input['reassign_to']) ? trim((string)$input['reassign_to']) : null;
      if ($name === '') { Response::validationError('name required'); }
      $db = Database::getInstance();
      ensure_categories_table($db);
      // Check items
      $row = Database::queryOne('SELECT COUNT(*) AS c FROM items WHERE category = ?', [$name]);
      $count = (int)($row['c'] ?? 0);
      if ($count > 0 && ($reassign === null || $reassign === '')) {
        Response::error('Category in use; provide reassign_to');
      }
      if ($count > 0 && $reassign !== null) {
        Database::execute('UPDATE items SET category = ? WHERE category = ?', [$reassign, $name]);
      }
      Database::execute('DELETE FROM categories WHERE name = ?', [$name]);
      Response::success(['name' => $name, 'reassigned_to' => $reassign]);
      break; }

    case 'reorder': {
      $db = Database::getInstance();
      ensure_categories_table($db);
      $inputNames = $input['names'] ?? [];
      if (!is_array($inputNames)) {
        Response::validationError('names must be an array');
      }
      // Normalize to unique names, preserve order
      $seen = [];
      $ordered = [];
      foreach ($inputNames as $n) {
        $name = trim((string)$n);
        if ($name === '') continue;
        $key = mb_strtolower($name);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $ordered[] = $name;
      }
      // Apply sort_order sequence
      $stmt = $db->prepare('UPDATE categories SET sort_order = ? WHERE name = ?');
      foreach ($ordered as $i => $name) {
        try { $stmt->execute([$i, $name]); } catch (Throwable $____) {}
      }
      Response::success(['count' => count($ordered)], 'Category order saved');
      break; }

    default:
      Response::error('Invalid action');
  }
} catch (Throwable $e) {
  try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch (Throwable $____) {}
  Response::serverError('Categories API error', $e->getMessage());
}
exit;
