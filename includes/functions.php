<?php

/**
 * Common functions for the Whimsical Frog website (Conductor)
 *
 * This file delegates functionality to specialized modular helper files.
 */

// Include Vite helper for asset management
require_once __DIR__ . '/vite_helper.php';
require_once __DIR__ . '/branding_tokens_helper.php';

// Include modular helper files
require_once __DIR__ . '/functions/url_helpers.php';
require_once __DIR__ . '/functions/room_helpers.php';
require_once __DIR__ . '/functions/image_helpers.php';
require_once __DIR__ . '/functions/formatting_helpers.php';
require_once __DIR__ . '/functions/system_helpers.php';

// TEMPORARILY DISABLED: Include required logging classes
// require_once __DIR__ . '/logging_config.php';
// require_once __DIR__ . '/logger.php';
// require_once __DIR__ . '/database_logger.php';
// require_once __DIR__ . '/error_logger.php';
// require_once __DIR__ . '/admin_logger.php';

// TEMPORARILY DISABLED: Initialize comprehensive logging system
/*
try {
    // Initialize logging configuration
    $loggingConfig = LoggingConfig::initializeLogging();
    $fileConfig = $loggingConfig['file_logging'];

    // Initialize Logger with application log file and comprehensive levels
    Logger::init($fileConfig['files']['application'], $fileConfig['levels']);

    // Initialize database logging (primary logging method)
    DatabaseLogger::getInstance();
    ErrorLogger::init();
    AdminLogger::init();

    // Log system initialization
    Logger::info('Comprehensive logging system initialized', [
        'file_logging' => $fileConfig['enabled'],
        'database_logging' => $loggingConfig['database_logging']['primary'],
        'admin_logging' => $loggingConfig['retail_admin_logging']['enabled'],
        'seo_logging' => $loggingConfig['seo_logging']['enabled'],
        'security_logging' => $loggingConfig['security_logging']['enabled'],
        'logs_directory' => $fileConfig['directory']
    ]);

} catch (Exception $e) {
    error_log("Failed to initialize logging system: " . $e->getMessage());
}
*/
