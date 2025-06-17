<?php
/**
 * Script to fix image permissions and create proper .htaccess for images directory
 */

echo "Fixing image permissions and access...\n";

$itemsDir = __DIR__ . '/../images/items/';
$imagesDir = __DIR__ . '/../images/';

// Create .htaccess file for images directory to allow access
$htaccessContent = "# Allow access to image files
<Files ~ \"\\.(png|jpe?g|gif|webp|svg)$\">
    Order allow,deny
    Allow from all
</Files>

# Enable content type headers
<IfModule mod_mime.c>
    AddType image/jpeg .jpg .jpeg
    AddType image/png .png
    AddType image/gif .gif
    AddType image/webp .webp
    AddType image/svg+xml .svg
</IfModule>

# Set proper cache headers for images
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType image/jpg \"access plus 1 month\"
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
    ExpiresByType image/webp \"access plus 1 month\"
</IfModule>

# Prevent directory browsing
Options -Indexes

# Allow direct access to files
Options +FollowSymLinks
";

$htaccessPath = $imagesDir . '.htaccess';
if (file_put_contents($htaccessPath, $htaccessContent)) {
    echo "Created .htaccess file at: $htaccessPath\n";
    chmod($htaccessPath, 0644);
} else {
    echo "Failed to create .htaccess file\n";
}

// Set directory permissions
if (is_dir($imagesDir)) {
    chmod($imagesDir, 0755);
    echo "Set permissions 755 on images directory\n";
}

if (is_dir($itemsDir)) {
    chmod($itemsDir, 0755);
    echo "Set permissions 755 on items directory\n";
    
    // Set file permissions for all files in items directory
    $files = glob($itemsDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            chmod($file, 0644);
            echo "Set permissions 644 on: " . basename($file) . "\n";
        }
    }
} else {
    echo "Items directory does not exist: $itemsDir\n";
}

// Create a test file to verify access
$testFile = $itemsDir . 'test_access.txt';
if (file_put_contents($testFile, 'Access test - ' . date('Y-m-d H:i:s'))) {
    chmod($testFile, 0644);
    echo "Created test file: test_access.txt\n";
} else {
    echo "Failed to create test file\n";
}

// List all files in items directory with their permissions
echo "\nFiles in items directory:\n";
if (is_dir($itemsDir)) {
    $files = scandir($itemsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $fullPath = $itemsDir . $file;
            $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
            echo "  $file - $perms\n";
        }
    }
}

echo "\nPermissions fix completed!\n";
echo "Test URLs:\n";
echo "- Test file: https://whimsicalfrog.us/images/items/test_access.txt\n";
echo "- Sample image: https://whimsicalfrog.us/images/items/WF-TS-002A.webp\n";
?> 