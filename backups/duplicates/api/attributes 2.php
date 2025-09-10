<?php
// Attributes Management API (Gender, Size, Color)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);
try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch (Throwable $____) {}

// Helpers to detect legacy tables and columns
function table_exists(PDO $db, string $name): bool {
  try { $q = $db->query("SHOW TABLES LIKE '" . addslashes($name) . "'"); return $q && $q->fetch() ? true : false; } catch (Throwable $____) { return false; }
}
function has_column(PDO $db, string $table, string $col): bool {
  try { $q = $db->query("SHOW COLUMNS FROM `" . str_replace('`','',$table) . "` LIKE '" . addslashes($col) . "'"); return $q && $q->fetch() ? true : false; } catch (Throwable $____) { return false; }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { exit(0); }

AuthHelper::requireAdmin();

function ensure_attributes_table(PDO $db): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS attribute_values (
  id INT NOT NULL AUTO_INCREMENT,
  type VARCHAR(32) NOT NULL,
  value VARCHAR(128) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_type_value (type, value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
  try { $db->exec($sql); } catch (Throwable $____) {}
}

try {
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list';

  $validTypes = ['gender','size','color'];

  switch ($action) {
    case 'list': {
      $db = Database::getInstance();
      $data = ['gender'=>[], 'size'=>[], 'color'=>[]];
      // Prefer legacy canonical tables when available
      if (table_exists($db, 'global_genders')) {
        $order = has_column($db, 'global_genders', 'display_order') ? 'display_order ASC, gender_name ASC' : 'gender_name ASC';
        $where = has_column($db, 'global_genders', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT gender_name FROM global_genders $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r) { $data['gender'][] = ['value' => (string)$r['gender_name']]; }
      }
      if (table_exists($db, 'global_sizes')) {
        $order = has_column($db, 'global_sizes', 'display_order') ? 'display_order ASC, size_name ASC' : 'size_name ASC';
        $where = has_column($db, 'global_sizes', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT size_name, size_code FROM global_sizes $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r) { $val = (string)($r['size_code'] ?: $r['size_name']); $data['size'][] = ['value' => $val]; }
      }
      if (table_exists($db, 'global_colors')) {
        $order = has_column($db, 'global_colors', 'display_order') ? 'display_order ASC, color_name ASC' : 'color_name ASC';
        $where = has_column($db, 'global_colors', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT color_name FROM global_colors $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r) { $data['color'][] = ['value' => (string)$r['color_name']]; }
      }
      // Fallback to attribute_values table if legacy absent or empty
      if (empty($data['gender']) && empty($data['size']) && empty($data['color'])) {
        ensure_attributes_table($db);
        $rows = $db->query('SELECT type, value, sort_order FROM attribute_values ORDER BY type ASC, sort_order ASC, value ASC')->fetchAll();
        foreach ($rows as $r) {
          $t = strtolower((string)($r['type'] ?? ''));
          if (!isset($data[$t])) continue;
          $data[$t][] = ['value' => (string)$r['value'], 'sort_order' => (int)($r['sort_order'] ?? 0)];
        }
      }
      Response::success(['attributes' => $data]);
      break; }

    case 'add': {
      $type = strtolower(trim((string)($input['type'] ?? '')));
      $value = trim((string)($input['value'] ?? ''));
      if (!in_array($type, $validTypes, true)) Response::validationError('Invalid type');
      if ($value === '') Response::validationError('Value required');
      $db = Database::getInstance();
      if ($type === 'gender' && table_exists($db, 'global_genders')) {
        $hasOrder = has_column($db, 'global_genders', 'display_order');
        $hasActive = has_column($db, 'global_genders', 'is_active');
        $next = 0;
        if ($hasOrder) { $row = Database::queryOne('SELECT COALESCE(MAX(display_order), -1) + 1 AS n FROM global_genders'); $next = (int)($row['n'] ?? 0); }
        $sql = 'INSERT INTO global_genders (gender_name' . ($hasOrder?', display_order':'') . ($hasActive?', is_active':'') . ') VALUES (?' . ($hasOrder?', ?':'') . ($hasActive?', 1':'') . ')';
        $params = [$value]; if ($hasOrder) $params[] = $next; Database::execute($sql, $params);
      } elseif ($type === 'size' && table_exists($db, 'global_sizes')) {
        $hasOrder = has_column($db, 'global_sizes', 'display_order');
        $hasActive = has_column($db, 'global_sizes', 'is_active');
        $next = 0;
        if ($hasOrder) { $row = Database::queryOne('SELECT COALESCE(MAX(display_order), -1) + 1 AS n FROM global_sizes'); $next = (int)($row['n'] ?? 0); }
        $code = (strlen($value) <= 4 && ctype_alpha(str_replace(' ', '', $value))) ? strtoupper(str_replace(' ', '', $value)) : strtoupper(substr(preg_replace('/[^A-Za-z]/','',$value),0,4));
        $sql = 'INSERT INTO global_sizes (size_name, size_code' . ($hasOrder?', display_order':'') . ($hasActive?', is_active':'') . ') VALUES (?, ?' . ($hasOrder?', ?':'') . ($hasActive?', 1':'') . ')';
        $params = [$value, $code]; if ($hasOrder) $params[] = $next; Database::execute($sql, $params);
      } elseif ($type === 'color' && table_exists($db, 'global_colors')) {
        $hasOrder = has_column($db, 'global_colors', 'display_order');
        $hasActive = has_column($db, 'global_colors', 'is_active');
        $next = 0;
        if ($hasOrder) { $row = Database::queryOne('SELECT COALESCE(MAX(display_order), -1) + 1 AS n FROM global_colors'); $next = (int)($row['n'] ?? 0); }
        $sql = 'INSERT INTO global_colors (color_name' . ($hasOrder?', display_order':'') . ($hasActive?', is_active':'') . ') VALUES (?' . ($hasOrder?', ?':'') . ($hasActive?', 1':'') . ')';
        $params = [$value]; if ($hasOrder) $params[] = $next; Database::execute($sql, $params);
      } else {
        ensure_attributes_table($db);
        $row = Database::queryOne('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM attribute_values WHERE type = ?', [$type]);
        $next = (int)($row['next_sort'] ?? 0);
        $stmt = $db->prepare('INSERT INTO attribute_values (type, value, sort_order) VALUES (?, ?, ?)');
        try { $stmt->execute([$type, $value, $next]); } catch (Throwable $e) {}
      }
      Response::success(['type' => $type, 'value' => $value], 'Attribute added');
      break; }

    case 'rename': {
      $type = strtolower(trim((string)($input['type'] ?? '')));
      $old = trim((string)($input['old_value'] ?? ''));
      $new = trim((string)($input['new_value'] ?? ''));
      if (!in_array($type, $validTypes, true)) Response::validationError('Invalid type');
      if ($old === '' || $new === '') Response::validationError('Both old_value and new_value required');
      $db = Database::getInstance();
      if ($type === 'gender' && table_exists($db, 'global_genders')) {
        Database::execute('UPDATE global_genders SET gender_name = ? WHERE gender_name = ?', [$new, $old]);
      } elseif ($type === 'size' && table_exists($db, 'global_sizes')) {
        // Derive a reasonable size_code from the new value
        $newCode = (strlen($new) <= 4 && ctype_alpha(str_replace(' ', '', $new)))
          ? strtoupper(str_replace(' ', '', $new))
          : strtoupper(substr(preg_replace('/[^A-Za-z]/','',$new),0,4));
        // Update whichever column matched (name or code), and keep both in sync
        if (has_column($db, 'global_sizes', 'size_code')) {
          Database::execute('UPDATE global_sizes SET size_name = ?, size_code = ? WHERE size_name = ? OR size_code = ?', [$new, $newCode, $old, $old]);
        } else {
          Database::execute('UPDATE global_sizes SET size_name = ? WHERE size_name = ?', [$new, $old]);
        }
      } elseif ($type === 'color' && table_exists($db, 'global_colors')) {
        Database::execute('UPDATE global_colors SET color_name = ? WHERE color_name = ?', [$new, $old]);
      } else {
        ensure_attributes_table($db);
        Database::execute('UPDATE attribute_values SET value = ? WHERE type = ? AND value = ?', [$new, $type, $old]);
      }
      Response::success(['type' => $type, 'old' => $old, 'new' => $new], 'Attribute renamed');
      break; }

    case 'delete': {
      $type = strtolower(trim((string)($input['type'] ?? '')));
      $value = trim((string)($input['value'] ?? ''));
      if (!in_array($type, $validTypes, true)) Response::validationError('Invalid type');
      if ($value === '') Response::validationError('Value required');
      $db = Database::getInstance();
      if ($type === 'gender' && table_exists($db, 'global_genders')) {
        if (has_column($db, 'global_genders', 'is_active')) Database::execute('UPDATE global_genders SET is_active = 0 WHERE gender_name = ?', [$value]);
        else Database::execute('DELETE FROM global_genders WHERE gender_name = ?', [$value]);
      } elseif ($type === 'size' && table_exists($db, 'global_sizes')) {
        if (has_column($db, 'global_sizes', 'is_active')) {
          Database::execute('UPDATE global_sizes SET is_active = 0 WHERE size_name = ? OR size_code = ?', [$value, $value]);
        } else {
          Database::execute('DELETE FROM global_sizes WHERE size_name = ? OR size_code = ?', [$value, $value]);
        }
      } elseif ($type === 'color' && table_exists($db, 'global_colors')) {
        if (has_column($db, 'global_colors', 'is_active')) Database::execute('UPDATE global_colors SET is_active = 0 WHERE color_name = ?', [$value]);
        else Database::execute('DELETE FROM global_colors WHERE color_name = ?', [$value]);
      } else {
        ensure_attributes_table($db);
        Database::execute('DELETE FROM attribute_values WHERE type = ? AND value = ?', [$type, $value]);
      }
      Response::success(['type' => $type, 'value' => $value], 'Attribute deleted');
      break; }

    case 'reorder': {
      $type = strtolower(trim((string)($input['type'] ?? '')));
      $values = $input['values'] ?? [];
      if (!in_array($type, $validTypes, true)) Response::validationError('Invalid type');
      if (!is_array($values)) Response::validationError('values must be an array');
      $db = Database::getInstance();
      if ($type === 'gender' && table_exists($db, 'global_genders') && has_column($db, 'global_genders', 'display_order')) {
        $stmt = $db->prepare('UPDATE global_genders SET display_order = ? WHERE gender_name = ?');
        foreach (array_values($values) as $i => $v) { $stmt->execute([$i, (string)$v]); }
      } elseif ($type === 'size' && table_exists($db, 'global_sizes') && has_column($db, 'global_sizes', 'display_order')) {
        $stmt = $db->prepare('UPDATE global_sizes SET display_order = ? WHERE size_name = ? OR size_code = ?');
        foreach (array_values($values) as $i => $v) { $stmt->execute([$i, (string)$v, (string)$v]); }
      } elseif ($type === 'color' && table_exists($db, 'global_colors') && has_column($db, 'global_colors', 'display_order')) {
        $stmt = $db->prepare('UPDATE global_colors SET display_order = ? WHERE color_name = ?');
        foreach (array_values($values) as $i => $v) { $stmt->execute([$i, (string)$v]); }
      } else {
        ensure_attributes_table($db);
        $stmt = $db->prepare('UPDATE attribute_values SET sort_order = ? WHERE type = ? AND value = ?');
        foreach (array_values($values) as $i => $v) { $stmt->execute([$i, $type, (string)$v]); }
      }
      Response::success(['type' => $type, 'values' => array_values($values)], 'Order saved');
      break; }

    default:
      Response::error('Invalid action');
  }
} catch (Throwable $e) {
  try { while (ob_get_level() > 0) { @ob_end_clean(); } } catch (Throwable $____) {}
  Response::serverError('Attributes API error', $e->getMessage());
}
exit;
