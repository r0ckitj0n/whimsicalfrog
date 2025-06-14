<?php
// Emergency permission fix - all images returning 403
header('Content-Type: text/plain');

echo "🚨 EMERGENCY: Fixing all image permissions...\n\n";

$documentRoot = $_SERVER['DOCUMENT_ROOT'];
echo "Document root: $documentRoot\n";

// Force set all directory permissions
$result1 = chmod($documentRoot . '/images', 0755);
$result2 = chmod($documentRoot . '/images/products', 0755);
echo "Images directory: " . ($result1 ? "✅ Fixed" : "❌ Failed") . "\n";
echo "Products directory: " . ($result2 ? "✅ Fixed" : "❌ Failed") . "\n\n";

// Get all image files and fix permissions
$extensions = ['png', 'webp', 'jpg', 'jpeg', 'gif'];
$fixed = 0;
$failed = 0;

foreach ($extensions as $ext) {
    // Products directory
    $files = glob($documentRoot . '/images/products/*.' . $ext);
    foreach ($files as $file) {
        if (chmod($file, 0644)) {
            $fixed++;
            echo "✅ Fixed: " . basename($file) . "\n";
        } else {
            $failed++;
            echo "❌ Failed: " . basename($file) . "\n";
        }
    }
    
    // Root images directory  
    $files = glob($documentRoot . '/images/*.' . $ext);
    foreach ($files as $file) {
        if (chmod($file, 0644)) {
            $fixed++;
            echo "✅ Fixed: images/" . basename($file) . "\n";
        } else {
            $failed++;
            echo "❌ Failed: images/" . basename($file) . "\n";
        }
    }
}

echo "\n📊 SUMMARY:\n";
echo "Fixed: $fixed files\n";
echo "Failed: $failed files\n";

// Test critical images
echo "\n🧪 Testing critical images:\n";
$testImages = [
    'placeholder.png',
    'TS001A.png', 
    'TU001A.png',
    'AW001A.png'
];

foreach ($testImages as $img) {
    $url = "https://whimsicalfrog.us/images/products/$img";
    $headers = @get_headers($url);
    $status = $headers ? $headers[0] : 'No response';
    echo "$img: $status\n";
}

echo "\n🚨 EMERGENCY FIX COMPLETE!\n";
?> 