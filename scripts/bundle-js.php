<?php
// scripts/bundle-js.php

// Define the base path relative to this script's location
$basePath = dirname(__DIR__); // This will be /Users/jongraves/Documents/Websites/WhimsicalFrog

// Define the exact order of files to be bundled.
// This prevents issues with dependency loading and ensures a consistent build.
$filesToBundle = [
    // --- Core Systems & Utilities ---
    'js/whimsical-frog-core.js',
    'js/utils.js',
    'js/central-functions.js',
    // 'js/wf-unified.js', // Excluded from production bundle to prevent duplicate loader

    // --- Global UI Components ---
    'js/ui-manager.js',
    'js/image-viewer.js',
    'js/global-notifications.js',
    'js/notification-messages.js',
    'js/global-popup.js',
    'js/global-modals.js',
    'js/modal-functions.js',
    'js/modal-close-positioning.js',

    // --- Feature Modules & Managers ---
    'js/analytics.js',

    'js/sales-checker.js',
    'js/search.js',

    // --- Room & Item Systems ---
    'js/room-css-manager.js',
    'js/room-coordinate-manager.js',
    'js/room-event-manager.js',
    'js/room-functions.js',
    'js/room-helper.js',
    'js/global-item-modal.js',
    'js/detailed-item-modal.js',
    'js/room-modal-manager.js', // The main modal manager

    // --- Main Application Logic & Cart ---
    'js/modules/cart-system.js',
    'js/main.js', // The main application that ties everything together
];

// The output file for the bundled JS
$outputFile = $basePath . '/js/bundle.js';

// Variable to hold all the JS content
$bundledJs = '';



foreach ($filesToBundle as $file) {
    $filePath = $basePath . '/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {
        
        // Add a comment to indicate the start of a file's content
        $bundledJs .= "// --- Start of " . $file . " --- \n\n";
        $bundledJs .= file_get_contents($filePath) . "\n\n";
        // Add a comment to indicate the end of a file's content
        $bundledJs .= "// --- End of " . $file . " --- \n\n";
    } else {
        
    }
}

// Write the bundled content to the output file
file_put_contents($outputFile, $bundledJs);

?>
