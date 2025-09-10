<?php
// scripts/dev/normalize-room-maps-active.php
// Ensures only one active map per room_number, preferring 'Original' then most recently updated.

require_once __DIR__ . '/../../api/config.php';

try {
    Database::getInstance();
    echo "=== Normalize room_maps active flags ===\n";

    $rooms = Database::queryAll("SELECT DISTINCT room_number FROM room_maps");
    $totalDeactivated = 0;

    foreach ($rooms as $r) {
        $rn = (string)$r['room_number'];
        $rows = Database::queryAll(
            "SELECT id, map_name, is_active, updated_at FROM room_maps WHERE room_number = ? ORDER BY (map_name = 'Original') DESC, updated_at DESC, id DESC",
            [$rn]
        );
        if (!$rows) continue;

        $keepId = null;
        $toDeactivate = [];
        foreach ($rows as $row) {
            if ($keepId === null) {
                $keepId = (int)$row['id'];
            } else if ((int)$row['is_active'] === 1) {
                $toDeactivate[] = (int)$row['id'];
            }
        }
        if ($keepId !== null) {
            // Deactivate others
            if (!empty($toDeactivate)) {
                $place = implode(',', array_fill(0, count($toDeactivate), '?'));
                Database::execute("UPDATE room_maps SET is_active = 0 WHERE id IN ($place)", $toDeactivate);
                $totalDeactivated += count($toDeactivate);
            }
            // Ensure keepId is active
            Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$keepId]);
            echo "room $rn: keep=$keepId deactivated=" . count($toDeactivate) . "\n";
        }
    }

    echo "Done. Deactivated $totalDeactivated duplicate active maps.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
