<?php
/**
 * Attributes Manager Logic
 */

function table_exists(PDO $db, string $name): bool
{
    try {
        $q = $db->query("SHOW TABLES LIKE '" . addslashes($name) . "'");
        return $q && $q->fetch() ? true : false;
        // @reason: Table existence check should not fail critically - return false gracefully
    } catch (Throwable $____) {
        return false;
    }
}

function has_column(PDO $db, string $table, string $col): bool
{
    try {
        $q = $db->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE '" . addslashes($col) . "'");
        return $q && $q->fetch() ? true : false;
        // @reason: Column existence check should not fail critically - return false gracefully
    } catch (Throwable $____) {
        return false;
    }
}

function ensure_attributes_table(PDO $db): void
{
    $sql = "CREATE TABLE IF NOT EXISTS attribute_values (
      id INT NOT NULL AUTO_INCREMENT,
      type VARCHAR(32) NOT NULL,
      value VARCHAR(128) NOT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_type_value (type, value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    // @reason: Idempotent DDL - table may already exist
    try {
        $db->exec($sql);
    } catch (Throwable $____) {
    }
}

function listAttributes($db)
{
    $data = ['gender' => [], 'size' => [], 'color' => []];
    if (table_exists($db, 'global_genders')) {
        $order = has_column($db, 'global_genders', 'display_order') ? 'display_order ASC, gender_name ASC' : 'gender_name ASC';
        $where = has_column($db, 'global_genders', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT gender_name FROM global_genders $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r)
            $data['gender'][] = ['value' => (string) $r['gender_name']];
    }
    if (table_exists($db, 'global_sizes')) {
        $order = has_column($db, 'global_sizes', 'display_order') ? 'display_order ASC, size_name ASC' : 'size_name ASC';
        $where = has_column($db, 'global_sizes', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT size_name, size_code FROM global_sizes $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r)
            $data['size'][] = ['value' => (string) ($r['size_code'] ?: $r['size_name'])];
    }
    if (table_exists($db, 'global_colors')) {
        $order = has_column($db, 'global_colors', 'display_order') ? 'display_order ASC, color_name ASC' : 'color_name ASC';
        $where = has_column($db, 'global_colors', 'is_active') ? 'WHERE is_active = 1' : '';
        $rows = $db->query("SELECT color_name FROM global_colors $where ORDER BY $order")->fetchAll();
        foreach ($rows as $r)
            $data['color'][] = ['value' => (string) $r['color_name']];
    }
    if (empty($data['gender']) && empty($data['size']) && empty($data['color'])) {
        ensure_attributes_table($db);
        $rows = $db->query('SELECT type, value, sort_order FROM attribute_values ORDER BY type ASC, sort_order ASC, value ASC')->fetchAll();
        foreach ($rows as $r) {
            $t = strtolower((string) ($r['type'] ?? ''));
            if (isset($data[$t]))
                $data[$t][] = ['value' => (string) $r['value'], 'sort_order' => (int) ($r['sort_order'] ?? 0)];
        }
    }
    return $data;
}
