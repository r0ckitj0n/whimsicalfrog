<?php
/**
 * Room Helper Functions
 *
 * Dynamic functions to get room data from database instead of hardcoded values
 */

require_once __DIR__ . '/config.php';

/**
 * Get all active item rooms (excludes A and B)
 * @return array Array of room numbers
 */
function getActiveItemRooms()
{
    // Item rooms are numeric >= 1 (exclude 0 main room and letter-coded pages)
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
        error_log("Error getting active item rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all valid room numbers (A, B, plus all item rooms)
 * @return array Array of valid room numbers
 */
function getAllValidRooms()
{
    // Valid navigable rooms: any alphanumeric identifier including '0'
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            WHERE is_active = 1
            ORDER BY display_order, room_number
        ");
        return array_map(function ($r) { return array_values($r)[0]; }, $rows);
    } catch (Exception $e) {
        error_log("Error getting valid rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all room numbers including inactive ones (for admin contexts)
 * @return array Array of all room numbers
 */
function getAllRoomsIncludingInactive()
{
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            ORDER BY display_order, room_number
        ");
        return array_map(function ($r) { return array_values($r)[0]; }, $rows);
    } catch (Exception $e) {
        error_log("Error getting all rooms: " . $e->getMessage());
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
              AND room_number <> '0'
            ORDER BY display_order, room_number
        ");
    } catch (Exception $e) {
        error_log("Error getting room doors data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all active rooms for admin tooling (includes Landing/Main)
 * @return array
 */
function getAllActiveRooms()
{
    try {
        return Database::queryAll("
            SELECT room_number, room_name, door_label, description, display_order
            FROM room_settings
            WHERE is_active = 1
            ORDER BY display_order, room_number
        ");
    } catch (Exception $e) {
        error_log("Error getting active rooms list: " . $e->getMessage());
        return [];
    }
}

/**
 * Get room type mappings for backgrounds
 * @return array Associative array of room_number => room_type
 */
function getRoomTypeMapping()
{
    // Map rooms: '0' -> room_main, others -> room{token}
    try {
        $rows = Database::queryAll("
            SELECT room_number
            FROM room_settings
            WHERE is_active = 1
            ORDER BY display_order, room_number
        ");
        $mapping = [];
        foreach ($rows as $row) {
            $num = $row['room_number'];
            $mapping[$num] = ($num === '0') ? 'room_main' : ('room' . $num);
        }
        return $mapping;
    } catch (Exception $e) {
        error_log("Error getting room type mapping: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a room number is valid
 * @param string $room_number Room number to validate
 * @return bool True if valid, false otherwise
 */
function isValidRoom($room_number, $includeInactive = false)
{
    $validRooms = $includeInactive ? getAllRoomsIncludingInactive() : getAllValidRooms();
    // If no valid rooms available, reject validation
    if (empty($validRooms)) {
        return false;
    }
    return in_array($room_number, $validRooms);
}

/**
 * Check if a room is an item room (not A or B)
 * @param string $room_number Room number to check
 * @return bool True if item room, false otherwise
 */
function isItemRoom($room_number)
{
    // Treat numeric rooms >=1 as item rooms; exclude letter-coded and '0'
    return preg_match('/^[0-9]+$/', (string)$room_number) && (int)$room_number >= 1 && isValidRoom($room_number);
}

/**
 * Get room data as JSON for JavaScript
 * @return string JSON string of room data
 */
function getRoomDataAsJson()
{
    $roomData = [
        'validRooms' => getAllValidRooms(),
        'itemRooms' => getActiveItemRooms(),
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
