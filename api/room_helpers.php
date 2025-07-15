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
function getActiveProductRooms() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number 
            FROM room_settings 
            WHERE is_active = 1 
            AND room_number NOT IN ('A', 'B')
            ORDER BY display_order, room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting active product rooms: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all valid room numbers (A, B, plus all product rooms)
 * @return array Array of valid room numbers
 */
function getAllValidRooms() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number 
            FROM room_settings 
            WHERE is_active = 1 
            ORDER BY 
                CASE 
                    WHEN room_number = 'A' THEN 0
                    WHEN room_number = 'B' THEN 1
                    ELSE CAST(room_number AS UNSIGNED) + 1
                END
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting valid rooms: " . $e->getMessage());
        return []; // Return empty array - let UI handle graceful degradation
    }
}

/**
 * Get core rooms that cannot be deleted
 * @return array Array of core room numbers
 */
function getCoreRooms() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number 
            FROM room_settings 
            WHERE is_active = 1 
            ORDER BY display_order, room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Error getting core rooms: " . $e->getMessage());
        return []; // Return empty array on error
    }
}

/**
 * Get room data for main room doors
 * @return array Array of room data with room_number, room_name, door_label, etc.
 */
function getRoomDoorsData() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number, room_name, door_label, description, display_order
            FROM room_settings 
            WHERE is_active = 1 
            AND room_number NOT IN ('A', 'B')
            ORDER BY display_order, room_number
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting room doors data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get room type mappings for backgrounds
 * @return array Associative array of room_number => room_type
 */
function getRoomTypeMapping() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("
            SELECT room_number, CONCAT('room', room_number) as room_type
            FROM room_settings 
            WHERE is_active = 1 
            AND room_number NOT IN ('A', 'B')
            ORDER BY display_order, room_number
        ");
        $stmt->execute();
        $mapping = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapping[$row['room_number']] = $row['room_type'];
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
function isValidRoom($roomNumber) {
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
function isProductRoom($roomNumber) {
    return !in_array($roomNumber, ['A', 'B']) && isValidRoom($roomNumber);
}

/**
 * Get room data as JSON for JavaScript
 * @return string JSON string of room data
 */
function getRoomDataAsJson() {
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
function getRoomDisplayTypeMapping() {
    try {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT room_number, background_display_type
            FROM room_settings
            WHERE is_active = 1"
        );
        $stmt->execute();
        $mapping = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $mapping[$row['room_number']] = $row['background_display_type'];
        }
        return $mapping;
    } catch (Exception $e) {
        error_log("Error getting room display type mapping: " . $e->getMessage());
        return [];
    }
}

?> 