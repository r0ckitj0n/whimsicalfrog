<?php
// scripts/dev/apply-room-numbering-migration.php
// DRY-RUN by default. Set APPLY=1 in query string or env to execute changes.
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$APPLY = isset($_GET['APPLY']) ? ($_GET['APPLY'] === '1') : (getenv('APPLY') === '1');

function now_ts() { return date('Ymd_His'); }
function write_backup($name, $data) {
    $dir = __DIR__ . '/../../backups/sql_migrations';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = $dir . '/' . $name . '_' . now_ts() . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    return $file;
}

try {
    require_once __DIR__ . '/../../api/config.php';
    $pdo = Database::getInstance();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read current state
    $roomRows = $pdo->query("SELECT * FROM room_settings ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $bgRows = $pdo->query("SELECT * FROM backgrounds ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $mapRows = $pdo->query("SELECT * FROM room_maps ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    // Build lookup by room_name for mapping
    $byName = [];
    foreach ($roomRows as $r) { $byName[$r['room_name']] = $r; }

    // Desired numbering per spec
    $desired = [
        ['name' => 'Main Room', 'number' => '0'],
        ['name' => 'T-Shirts & Apparel', 'number' => '1'],
        ['name' => 'Window Wraps', 'number' => '2'],
        ['name' => 'Custom Artwork', 'number' => '3'],
        ['name' => 'Tumblers & Drinkware', 'number' => '4'],
        ['name' => 'Sublimation Items', 'number' => '5'],
    ];

    $roomUpdates = [];
    foreach ($desired as $spec) {
        if (!isset($byName[$spec['name']])) continue; // skip if missing
        $row = $byName[$spec['name']];
        if ((string)$row['room_number'] !== $spec['number']) {
            $roomUpdates[] = [
                'id' => (int)$row['id'],
                'from' => (string)$row['room_number'],
                'to' => $spec['number'],
                'sql' => sprintf("UPDATE room_settings SET room_number = %s WHERE id = %d;",
                    $pdo->quote($spec['number']), (int)$row['id'])
            ];
        }
    }

    // Move Landing Page out of numeric range to free '0' (schema may be INT). Also mark inactive.
    $landingUpdate = null;
    if (isset($byName['Landing Page'])) {
        $landing = $byName['Landing Page'];
        // Set to 100 and deactivate to avoid conflicts
        if ((string)$landing['room_number'] !== '100' || (int)$landing['is_active'] !== 0) {
            $landingUpdate = [
                'id' => (int)$landing['id'],
                'from' => (string)$landing['room_number'],
                'to' => '100',
                'sql' => sprintf("UPDATE room_settings SET room_number = 100, is_active = 0 WHERE id = %d;", (int)$landing['id'])
            ];
        }
    }

    // Backgrounds filename corrections and room_type remap per numbering
    $bgUpdates = [];
    // helper to canonical filename by room_type
    $canon = function($rt, $ext) {
        if ($rt === 'landing') return "background_home.$ext";
        if ($rt === 'room_main') return "background_room_main.$ext";
        if (preg_match('/^room(\d+)$/', $rt, $m)) return "background_room{$m[1]}.$ext";
        return null;
    };
    // Build mapping for names -> new numeric -> room_type target
    $nameToNewNum = [
        'Main Room' => '0',
        'T-Shirts & Apparel' => '1',
        'Window Wraps' => '2',
        'Custom Artwork' => '3',
        'Tumblers & Drinkware' => '4',
        'Sublimation Items' => '5',
    ];

    // Project final types for all backgrounds and collect update SQLs
    $projected = [];
    foreach ($bgRows as $bg) {
        $updates = [];
        // Determine final desired room_type first
        $finalType = $bg['room_type'];
        if (preg_match('/^room(\d+)$/', $bg['room_type'], $m)) {
            $oldN = (int)$m[1];
            if ($oldN >= 2 && $oldN <= 6) {
                $newN = $oldN - 1;
                $finalType = ($newN === 0) ? 'room_main' : ('room' . $newN);
            }
        }
        if ($finalType !== $bg['room_type']) {
            $updates['room_type'] = $finalType;
        }
        // Now compute canonical filenames from finalType
        $wantPng = $canon($finalType, 'png');
        $wantWebp = $canon($finalType, 'webp');
        if ($wantPng && $bg['image_filename'] !== $wantPng) $updates['image_filename'] = $wantPng;
        if ($wantWebp && $bg['webp_filename'] !== $wantWebp) $updates['webp_filename'] = $wantWebp;

        if (!empty($updates)) {
            $set = [];
            foreach ($updates as $col => $val) { $set[] = $col . ' = ' . $pdo->quote($val); }
            $bgUpdates[] = [
                'id' => (int)$bg['id'],
                'changes' => $updates,
                'sql' => 'UPDATE backgrounds SET ' . implode(', ', $set) . ' WHERE id = ' . (int)$bg['id'] . ';'
            ];
        }

        // Track projection
        $projected[] = [
            'id' => (int)$bg['id'],
            'current_type' => $bg['room_type'],
            'final_type' => $finalType,
            'is_active' => (int)$bg['is_active'],
            'background_name' => $bg['background_name'],
        ];
    }

    // Ensure safe updates: proactively deactivate any active rows that will move to a different final_type
    $bgDeactivateMovingActiveSQL = [];
    foreach ($projected as $p) {
        if ($p['is_active'] === 1 && $p['current_type'] !== $p['final_type']) {
            $bgDeactivateMovingActiveSQL[] = 'UPDATE backgrounds SET is_active = 0 WHERE id = ' . (int)$p['id'] . ';';
        }
    }

    // Pre-rename duplicates: if multiple rows in the same FINAL type share the same background_name, rename all but one
    $bgPreRenameSQL = [];
    $byFinalAndName = [];
    foreach ($projected as $p) {
        $k = $p['final_type'] . '||' . $p['background_name'];
        if (!isset($byFinalAndName[$k])) $byFinalAndName[$k] = [];
        $byFinalAndName[$k][] = $p;
    }
    foreach ($byFinalAndName as $k => $rows) {
        if (count($rows) <= 1) continue;
        // prefer the one already in place (current==final), else lowest id
        usort($rows, function($a, $b) {
            $ap = ($a['current_type'] === $a['final_type']) ? 0 : 1;
            $bp = ($b['current_type'] === $b['final_type']) ? 0 : 1;
            if ($ap !== $bp) return $ap - $bp;
            return $a['id'] <=> $b['id'];
        });
        $keep = array_shift($rows);
        foreach ($rows as $r) {
            $newName = $r['background_name'] . ' (copy ' . (int)$r['id'] . ')';
            $bgPreRenameSQL[] = 'UPDATE backgrounds SET background_name = ' . $pdo->quote($newName) . ' WHERE id = ' . (int)$r['id'] . ';';
        }
    }

    // Ensure only one active background per FINAL room_type remains active (prefer 'Original')
    $bgPreDeactivateSQL = [];
    $byFinal = [];
    foreach ($projected as $p) {
        if (!isset($byFinal[$p['final_type']])) $byFinal[$p['final_type']] = [];
        $byFinal[$p['final_type']][] = $p;
    }
    foreach ($byFinal as $type => $rows) {
        $actives = array_values(array_filter($rows, fn($r) => (int)$r['is_active'] === 1));
        if (count($actives) <= 1) continue;
        // Prefer a row already in the final type
        $already = array_values(array_filter($actives, fn($r) => $r['current_type'] === $r['final_type']));
        $preferred = null;
        if (!empty($already)) {
            foreach ($already as $r) { if (strcasecmp($r['background_name'], 'Original') === 0) { $preferred = $r; break; } }
            if (!$preferred) { $preferred = $already[0]; }
        } else {
            foreach ($actives as $r) { if (strcasecmp($r['background_name'], 'Original') === 0) { $preferred = $r; break; } }
            if (!$preferred) { $preferred = $actives[0]; }
        }
        foreach ($actives as $r) {
            if ((int)$r['id'] === (int)$preferred['id']) continue;
            $bgPreDeactivateSQL[] = 'UPDATE backgrounds SET is_active = 0 WHERE id = ' . (int)$r['id'] . ';';
        }
    }

    // room_maps remap similar to backgrounds
    $mapUpdates = [];
    foreach ($mapRows as $map) {
        $updates = [];
        if (preg_match('/^room(\d+)$/', $map['room_type'], $m)) {
            $oldN = (int)$m[1];
            if ($oldN >= 2 && $oldN <= 6) {
                $newN = $oldN - 1;
                $newType = ($newN === 0) ? 'room_main' : ('room' . $newN);
                if ($map['room_type'] !== $newType) $updates['room_type'] = $newType;
            }
        }
        if (!empty($updates)) {
            $set = [];
            foreach ($updates as $col => $val) { $set[] = $col . ' = ' . $pdo->quote($val); }
            $mapUpdates[] = [
                'id' => (int)$map['id'],
                'changes' => $updates,
                'sql' => 'UPDATE room_maps SET ' . implode(', ', $set) . ' WHERE id = ' . (int)$map['id'] . ';'
            ];
        }
    }

    // Collect ids to be updated in room_settings for temp bumping to avoid unique collisions
    $roomUpdateIds = array_map(fn($u) => (int)$u['id'], $roomUpdates);

    // Compute target room_types affected by background updates
    $targetTypes = [];
    foreach ($bgUpdates as $u) {
        $changes = $u['changes'];
        $type = $changes['room_type'] ?? null;
        if ($type) { $targetTypes[$type] = true; }
    }
    // Compute ALL final types present to ensure name normalization covers landing/room_main as well
    $allTypes = [];
    foreach ($projected as $p) { $allTypes[$p['final_type']] = true; }

    // Global temporary rename to avoid unique collisions during transition
    // This guarantees uniqueness regardless of (room_type, is_active, background_name) constraints
    $bgTempRenameSQL = "UPDATE backgrounds SET background_name = CONCAT(background_name, ' [tmp-', id, ']')";

    $plan = [
        'apply' => $APPLY,
        'room_settings_updates' => $roomUpdates,
        'landing_update' => $landingUpdate,
        'room_settings_temp_bump_ids' => $roomUpdateIds,
        'background_updates' => $bgUpdates,
        'background_temp_rename' => $bgTempRenameSQL,
        'background_pre_rename' => $bgPreRenameSQL,
        'background_pre_deactivate' => $bgPreDeactivateSQL,
        'background_deactivate_movers' => $bgDeactivateMovingActiveSQL,
        'background_target_types' => array_keys($targetTypes),
        'background_normalize_types' => array_keys($allTypes),
        'room_map_updates' => $mapUpdates,
    ];

    if (!$APPLY) {
        echo json_encode(['ok' => true, 'dry_run' => true, 'plan' => $plan], JSON_PRETTY_PRINT);
        exit;
    }

    // APPLY
    $pdo->beginTransaction();
    try {
        $b1 = write_backup('room_settings_backup', $roomRows);
        $b2 = write_backup('backgrounds_backup', $bgRows);
        $b3 = write_backup('room_maps_backup', $mapRows);

        // Apply landing first (move off 0) to free up '0'
        if ($landingUpdate) { $pdo->exec($landingUpdate['sql']); }
        if (!empty($roomUpdateIds)) {
            $pdo->exec('UPDATE room_settings SET room_number = room_number + 1000 WHERE id IN (' . implode(',', $roomUpdateIds) . ');');
        }
        foreach ($roomUpdates as $u) { $pdo->exec($u['sql']); }
        // Global tmp rename to make all names unique during transition
        $pdo->exec($bgTempRenameSQL);
        // Pre-rename duplicates, then deactivate moving actives and pre-deactivate to avoid unique collisions on update
        foreach ($bgPreRenameSQL as $sql) { $pdo->exec($sql); }
        foreach ($bgDeactivateMovingActiveSQL as $sql) { $pdo->exec($sql); }
        foreach ($bgPreDeactivateSQL as $sql) { $pdo->exec($sql); }
        if (!empty($targetTypes)) {
            $in = '\'' . implode('\',\'', array_keys($targetTypes)) . '\'';
            $pdo->exec('UPDATE backgrounds SET is_active = 0 WHERE is_active = 1 AND room_type IN (' . $in . ');');
        }
        foreach ($bgUpdates as $u) { $pdo->exec($u['sql']); }
        // Reactivate one background per target type (prefer Original)
        foreach (array_keys($targetTypes) as $type) {
            $stmt = $pdo->query(
                "SELECT id, background_name FROM backgrounds WHERE room_type = " . $pdo->quote($type) .
                " ORDER BY (background_name = 'Original') DESC, id ASC LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['id'])) {
                $pdo->exec('UPDATE backgrounds SET is_active = 1 WHERE id = ' . (int)$row['id'] . ';');
            }
        }
        // Normalize names per type for ALL final types: set active to 'Original', others to 'Variant N'
        foreach (array_keys($allTypes) as $type) {
            // Set active to 'Original'
            $stmt = $pdo->query(
                "SELECT id FROM backgrounds WHERE room_type = " . $pdo->quote($type) . " AND is_active = 1 LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['id'])) {
                $pdo->exec('UPDATE backgrounds SET background_name = \'Original\' WHERE id = ' . (int)$row['id'] . ';');
            }
            // Rename others deterministically
            $stmt2 = $pdo->query(
                "SELECT id FROM backgrounds WHERE room_type = " . $pdo->quote($type) . " AND id <> " . ((int)($row['id'] ?? 0)) . " ORDER BY id ASC"
            );
            $n = 2;
            while ($r = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $new = 'Variant ' . $n;
                $pdo->exec('UPDATE backgrounds SET background_name = ' . $pdo->quote($new) . ' WHERE id = ' . (int)$r['id'] . ';');
                $n++;
            }
        }
        foreach ($mapUpdates as $u) { $pdo->exec($u['sql']); }

        $pdo->commit();
        echo json_encode(['ok' => true, 'dry_run' => false, 'backups' => [$b1, $b2, $b3], 'applied' => $plan], JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'plan' => $plan]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
