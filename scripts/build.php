<?php
// scripts/build.php

echo "Starting build process...\n\n";

// --- Bundle CSS ---
require_once 'bundle-css.php';

echo "\n"; // Add a newline for better separation

// --- Bundle JS ---
require_once 'bundle-js.php';

echo "\nBuild process completed!\n";

?>
