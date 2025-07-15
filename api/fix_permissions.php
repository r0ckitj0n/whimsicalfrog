<?php
// Automatic permission fix for images after deployment
// Returns minimal output for automated calls

$documentRoot = $_SERVER['DOCUMENT_ROOT'];

// Fix directory permissions
chmod($documentRoot . '/images', 0755);
chmod($documentRoot . '/images/products', 0755);

// Fix file permissions for all image types
$extensions = ['png', 'webp', 'jpg', 'jpeg', 'gif'];
$fixed = 0;

foreach ($extensions as $ext) {
    // Products directory
    $files = glob($documentRoot . '/images/products/*.' . $ext);
    foreach ($files as $file) {
        if (chmod($file, 0644)) {
            $fixed++;
        }
    }
    
    // Root images directory
    $files = glob($documentRoot . '/images/*.' . $ext);
    foreach ($files as $file) {
        if (chmod($file, 0644)) {
            $fixed++;
        }
    }
}

// Minimal response for automated calls
echo "Fixed $fixed files";
?> 