<?php

/**
 * WhimsicalFrog Constructor and Initialization Management
 * Centralized PHP functions to eliminate duplication
 * Generated: 2025-07-01 23:42:24
 */

// Include core dependencies for constructors
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Initialize room system
 * @param string $room_number
 * @return array
 */
function initializeRoom($room_number = '2')
{
    $roomType = "room{$room_number}";

    // Initialize database connection
    $database = Database::getInstance();

    return [
        'room_number' => $room_number,
        'roomType' => $roomType,
        'database' => $database
    ];
}

/**
 * Configure system settings
 * @param array $config
 * @return void
 */
function configureSystem($config)
{
    if (!isset($GLOBALS['system_config'])) {
        $GLOBALS['system_config'] = [];
    }
    $GLOBALS['system_config'] = array_merge($GLOBALS['system_config'], $config);
}
