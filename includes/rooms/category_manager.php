<?php
/**
 * Room Category Assignment Manager
 */

function getAllAssignments()
{
    return Database::queryAll(
        "SELECT 
            rca.*, 
            COALESCE(c.name, CONCAT('Category #', rca.category_id)) as category_name, 
            c.description as category_description
         FROM room_category_assignments rca 
         LEFT JOIN categories c ON rca.category_id = c.id 
         ORDER BY rca.room_number, rca.display_order"
    );
}

function getSummary()
{
    return Database::queryAll(
        "SELECT 
            rca.room_number,
            rca.room_name,
            GROUP_CONCAT(COALESCE(c.name, CONCAT('Category #', rca.category_id)) ORDER BY rca.display_order SEPARATOR ', ') as categories,
            COUNT(*) as category_count,
            MAX(CASE WHEN rca.is_primary = 1 THEN COALESCE(c.name, CONCAT('Category #', rca.category_id)) END) as primary_category
         FROM room_category_assignments rca 
         LEFT JOIN categories c ON rca.category_id = c.id 
         GROUP BY rca.room_number, rca.room_name
         ORDER BY rca.room_number"
    );
}

function getRoomAssignments($room_number)
{
    if ($room_number === null) throw new Exception('Room number is required');
    return Database::queryAll(
        "SELECT 
            rca.*, 
            COALESCE(c.name, CONCAT('Category #', rca.category_id)) as category_name, 
            c.description as category_description
         FROM room_category_assignments rca 
         LEFT JOIN categories c ON rca.category_id = c.id 
         WHERE rca.room_number = ?
         ORDER BY rca.display_order",
        [$room_number]
    );
}

function addAssignment($input)
{
    $room_number = $input['room_number'] ?? null;
    $roomName = $input['room_name'] ?? '';
    $category_id = $input['category_id'] ?? null;
    $isPrimary = $input['is_primary'] ?? 0;

    if ($room_number === null || $category_id === null) throw new Exception('Room number and category ID required');

    $exists = Database::queryOne("SELECT id FROM room_category_assignments WHERE room_number = ? AND category_id = ?", [$room_number, $category_id]);
    if ($exists) throw new Exception('Assignment already exists');

    if ($isPrimary) {
        Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$room_number]);
    }

    $maxOrderRow = Database::queryOne("SELECT MAX(display_order) AS max_order FROM room_category_assignments WHERE room_number = ?", [$room_number]);
    $nextOrder = isset($maxOrderRow['max_order']) ? ((int)$maxOrderRow['max_order']) + 1 : 0;

    return Database::execute(
        "INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) VALUES (?, ?, ?, ?, ?)",
        [$room_number, $roomName, $category_id, $isPrimary, $nextOrder]
    );
}

function updateAssignment($input)
{
    $id = $input['id'] ?? null;
    if (!$id) throw new Exception('ID required');

    $existing = Database::queryOne("SELECT * FROM room_category_assignments WHERE id = ?", [$id]);
    if (!$existing) throw new Exception('Not found');

    $room_number = $input['room_number'] ?? $existing['room_number'];
    $roomName = $input['room_name'] ?? $existing['room_name'];
    $category_id = $input['category_id'] ?? $existing['category_id'];
    $isPrimary = isset($input['is_primary']) ? (int)$input['is_primary'] : (int)$existing['is_primary'];
    $displayOrder = isset($input['display_order']) ? (int)$input['display_order'] : (int)$existing['display_order'];

    Database::beginTransaction();
    try {
        if ($isPrimary === 1) {
            Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$room_number]);
        }
        Database::execute(
            "UPDATE room_category_assignments SET room_number = ?, room_name = ?, category_id = ?, is_primary = ?, display_order = ? WHERE id = ?",
            [$room_number, $roomName, $category_id, $isPrimary, $displayOrder, $id]
        );
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}

function setPrimary($input)
{
    $room_number = $input['room_number'] ?? null;
    $category_id = $input['category_id'] ?? null;
    if ($room_number === null || $category_id === null) throw new Exception('Room and Category required');

    Database::beginTransaction();
    try {
        Database::execute("UPDATE room_category_assignments SET is_primary = 0 WHERE room_number = ?", [$room_number]);
        Database::execute("UPDATE room_category_assignments SET is_primary = 1 WHERE room_number = ? AND category_id = ?", [$room_number, $category_id]);
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}
