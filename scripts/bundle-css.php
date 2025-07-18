<?php

// scripts/bundle-css.php

// Define the base path relative to this script's location
$basePath = dirname(__DIR__); // This will be /Users/jongraves/Documents/Websites/WhimsicalFrog

// Define priority files that must be loaded first.
$priorityFiles = [
    // Core and foundational styles
    // Core and foundational styles
    'css/core/variables.css',
    'css/header-styles.css', // Load header styles early to ensure they apply
    'css/generated-tailwind-utilities.css',
    'css/core/utilities.css',
    'css/styles.css', // Main or base styles
    'css/main.css',

    // General component styles
    'css/components/buttons.css',
    'css/button-styles.css',
    'css/card-styles.css',
    'css/form-styles.css',
    'css/form-errors.css',
    'css/image-placeholder-styles.css',
    'css/notification-system.css',
    'css/footer-styles.css',

    // Modal and popup styles
    'css/global-modals.css',
    'css/search-modal.css',
    'css/room-modal.css',
    'css/pos-modal-styles.css',
    'css/room-popups.css',

    // Page-specific styles
    'css/landing.css',
    'css/admin-styles.css',
    'css/room-main.css',
    'css/room-styles.css',
    'css/room-iframe.css',

    // Miscellaneous and overrides
    'css/backgrounds.css',
    'css/email-styles.css',
    'css/generated_css_live.css',

    // Responsive styles should come last
    'css/responsive/mobile.css'
];

// The directory to scan for CSS files.
$cssDir = $basePath . '/css';

// Find all .css files recursively.
$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    if ($fileInfo->isFile() && $fileInfo->getExtension() === 'css' && $fileInfo->getFilename() !== 'bundle.css') {
        $relativePath = 'css' . str_replace(realpath($cssDir), '', $fileInfo->getRealPath());
        $allFiles[] = str_replace('\\', '/', $relativePath);
    }
}

// Ensure priority files are unique and at the beginning.
$otherFiles = array_diff($allFiles, $priorityFiles);
$filesToBundle = array_merge($priorityFiles, $otherFiles);

// The output file for the bundled CSS
$outputFile = $basePath . '/css/bundle.css';

// Variable to hold all the CSS content
$bundledCss = '';



foreach ($filesToBundle as $file) {
    $filePath = $basePath . '/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {

        // Add a comment to indicate the start of a file's content
        $bundledCss .= "/* --- Start of " . $file . " --- */\n\n";
        $bundledCss .= file_get_contents($filePath) . "\n\n";
        // Add a comment to indicate the end of a file's content
        $bundledCss .= "/* --- End of " . $file . " --- */\n\n";
    } else {

    }
}

// Write the bundled content to the output file
file_put_contents($outputFile, $bundledCss);
