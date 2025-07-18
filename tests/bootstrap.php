<?php
// PHPUnit bootstrap for WhimsicalFrog

// Composer autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Core helper functions needed by tests
$functionsPath = __DIR__ . '/../includes/functions.php';
if (file_exists($functionsPath)) {
    require_once $functionsPath;
}

// Load shared background helpers
$bgHelperPath = __DIR__ . '/../includes/background_helpers.php';
if (file_exists($bgHelperPath)) {
    require_once $bgHelperPath;
}
