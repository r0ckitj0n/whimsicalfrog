<?php
/**
 * Room Helper Functions
 *
 * Dynamic functions to get room data from database instead of hardcoded values
 */

require_once __DIR__ . '/config.php';

/**
 * Get all active product rooms (excludes A and B)
 * @return array Array of room numbers
 */
function getActiveProductRooms()
{
    // Product rooms are numeric >= 1 (exclude 0 main room and any letter-coded pages)
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            WHERE is_active = 1
              AND room_number REGEXP '^[0-9]+$'
              AND CAST(room_number AS UNSIGNED) >= 1
            ORDER BY CAST(room_number AS UNSIGNED)
        ");
        return array_map(function ($r) { return array_values($r)[0]; }, $rows);
    } catch (Exception $e) {
        error_log("Error getting active product rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all valid room numbers (A, B, plus all product rooms)
 * @return array Array of valid room numbers
 */
function getAllValidRooms()
{
    // Valid navigable room numbers are numeric (including 0 main room). Letter-coded pages are excluded here.
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            WHERE is_active = 1
              AND room_number REGEXP '^[0-9]+$'
            ORDER BY CAST(room_number AS UNSIGNED)
        ");
        return array_map(function ($r) { return array_values($r)[0]; }, $rows);
    } catch (Exception $e) {
        error_log("Error getting valid rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get core rooms that cannot be deleted
 * @return array Array of core room numbers
 */
function getCoreRooms()
{
    try {
        $rows = Database::queryAll("
            SELECT room_number 
            FROM room_settings 
            WHERE is_active = 1 
            ORDER BY display_order, room_number
        ");
        return array_map(function ($r) { return array_values($r)[0]; }, $rows);
    } catch (Exception $e) {
        error_log("Error getting core rooms: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

/**
 * Get room data for main room doors
 * @return array Array of room data with room_number, room_name, door_label, etc.
 */
function getRoomDoorsData()
{
    try {
        return Database::queryAll("
            SELECT room_number, room_name, door_label, description, display_order
            FROM room_settings
            WHERE is_active = 1
              AND room_number REGEXP '^[0-9]+$'
              AND CAST(room_number AS UNSIGNED) >= 1
            ORDER BY CAST(room_number AS UNSIGNED)
        ");
    } catch (Exception $e) {
        error_log("Error getting room doors data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get room type mappings for backgrounds
 * @return array Associative array of room_number => room_type
 */
function getRoomTypeMapping()
{
    // Map numeric rooms: 0 -> room_main, n>=1 -> room{n}
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            WHERE is_active = 1
              AND room_number REGEXP '^[0-9]+$'
            ORDER BY CAST(room_number AS UNSIGNED)
        ");
        $mapping = [];
        foreach ($rows as $row) {
            $num = $row['room_number'];
            $n = (int)$num;
            $mapping[$num] = ($n === 0) ? 'room_main' : ('room' . $n);
        }
        return $mapping;
    } catch (Exception $e) {
        error_log("Error getting room type mapping: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a room number is valid
 * @param string $roomNumber Room number to validate
 * @return bool True if valid, false otherwise
 */
function isValidRoom($roomNumber)
{
    $validRooms = getAllValidRooms();
    // If no valid rooms available, reject validation
    if (empty($validRooms)) {
        return false;
    }
    return in_array($roomNumber, $validRooms);
}

/**
 * Check if a room is a product room (not A or B)
 * @param string $roomNumber Room number to check
 * @return bool True if product room, false otherwise
 */
function isProductRoom($roomNumber)
{
    return !in_array($roomNumber, ['A', 'B']) && isValidRoom($roomNumber);
}

/**
 * Get room data as JSON for JavaScript
 * @return string JSON string of room data
 */
function getRoomDataAsJson()
{
    $roomData = [
        'validRooms' => getAllValidRooms(),
        'productRooms' => getActiveProductRooms(),
        'coreRooms' => getCoreRooms(),
        'roomDoors' => getRoomDoorsData(),
        'roomTypeMapping' => getRoomTypeMapping()
    ];
    return json_encode($roomData);
}
/**
 * Get room display type mapping for backgrounds
 * @return array Associative array of room_number => display type ('fullscreen' or 'modal')
 */
function getRoomDisplayTypeMapping()
{
    try {
        $rows = Database::queryAll(
            "SELECT room_number, background_display_type FROM room_settings WHERE is_active = 1"
        );
        $mapping = [];
        foreach ($rows as $row) {
            $mapping[$row['room_number']] = $row['background_display_type'];
        }
        return $mapping;
    } catch (Exception $e) {
        error_log("Error getting room display type mapping: " . $e->getMessage());
        return [];
    }
}

?> 