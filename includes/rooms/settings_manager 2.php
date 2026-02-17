<?php
/**
 * Room Settings Manager Logic
 */

function wf_room_is_protected($room_number)
{
    return in_array((string) $room_number, ['0', 'A', 'B', 'S', 'X'], true);
}

function wf_normalize_icon_panel_color($value)
{
    if ($value === null)
        return null;
    $raw = trim((string) $value);
    if ($raw === '' || strcasecmp($raw, 'transparent') === 0 || $raw === 'none')
        return 'transparent';

    // Support var(--brand-...) and hex colors
    if (strpos($raw, 'var(') === 0 || preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $raw)) {
        if (strpos($raw, '#') === 0 && strlen($raw) === 4) {
            $raw = '#' . $raw[1] . $raw[1] . $raw[2] . $raw[2] . $raw[3] . $raw[3];
        }
        return $raw; // Keep original case for CSS variables, uppercase for hex
    }
    return null;
}

function setRoomActiveState($room_number, $isActive)
{
    if ($room_number === null || $room_number === '') {
        throw new Exception('Room number required');
    }

    $isActive = (int) !!$isActive;

    if ($isActive === 0 && wf_room_is_protected($room_number)) {
        throw new Exception('Protected room cannot be deactivated');
    }

    $res = Database::execute("UPDATE room_settings SET is_active = ? WHERE room_number = ?", [$isActive, (string) $room_number]);

    // Cascade to items via category assignments
    $categoryRows = Database::queryAll("SELECT category_id FROM room_category_assignments WHERE room_number = ?", [(string) $room_number]);
    $failedItems = [];
    $updatedCount = 0;

    foreach ($categoryRows as $row) {
        $catId = $row['category_id'];
        $newStatus = $isActive ? 'live' : 'draft';

        if ($isActive === 0) {
            // Deactivation: Only hide items if the category isn't in any OTHER active rooms
            $otherActive = Database::queryOne(
                "SELECT 1 FROM room_category_assignments rca 
                 JOIN room_settings rs ON rca.room_number = rs.room_number 
                 WHERE rca.category_id = ? AND rca.room_number != ? AND rs.is_active = 1 LIMIT 1",
                [$catId, $room_number]
            );
            if ($otherActive) {
                continue; // Skip - category is in another active room
            }
        }

        // Get items in this category to track individual failures
        $items = Database::queryAll("SELECT sku, name FROM items WHERE category_id = ?", [$catId]);
        foreach ($items as $item) {
            try {
                Database::execute("UPDATE items SET status = ? WHERE sku = ?", [$newStatus, $item['sku']]);
                $updatedCount++;
            } catch (Exception $e) {
                $failedItems[] = [
                    'sku' => $item['sku'],
                    'name' => $item['name'] ?? $item['sku'],
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    return [
        'success' => empty($failedItems),
        'room_updated' => $res > 0,
        'items_updated' => $updatedCount,
        'failed_items' => $failedItems
    ];
}

function updateRoomFlags($room_number, $input)
{
    $fields = [];
    $params = [];
    if (array_key_exists('has_icons_white_background', $input)) {
        $fields[] = 'has_icons_white_background = ?';
        $params[] = (int) !!$input['has_icons_white_background'];
    }
    if (array_key_exists('icon_panel_color', $input)) {
        $color = wf_normalize_icon_panel_color($input['icon_panel_color']);
        if ($color !== null) {
            $fields[] = 'icon_panel_color = ?';
            $params[] = $color;
            if (!array_key_exists('has_icons_white_background', $input)) {
                $fields[] = 'has_icons_white_background = ?';
                $params[] = $color === 'transparent' ? 0 : 1;
            }
        }
    }
    if (empty($fields))
        return 0;
    $params[] = (string) $room_number;
    return Database::execute('UPDATE room_settings SET ' . implode(', ', $fields) . ' WHERE room_number = ?', $params);
}
