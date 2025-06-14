<?php
// Fix file permissions on the server
header('Content-Type: text/plain');

echo "🔧 Fixing file permissions on server...\n";

// Get the document root
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
echo "Document root: $documentRoot\n";

$results = [];

// Fix directory permissions
$directories = [
    $documentRoot . '/images',
    $documentRoot . '/images/products',
    $documentRoot . '/css',
    $documentRoot . '/js',
    $documentRoot . '/api',
    $documentRoot . '/sections'
];

echo "\n📁 Setting directory permissions to 755...\n";
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $result = chmod($dir, 0755);
        echo ($result ? "✅" : "❌") . " $dir\n";
        $results[] = [$dir, $result ? 'OK' : 'FAILED'];
    } else {
        echo "⚠️  Directory not found: $dir\n";
    }
}

// Fix file permissions for images
$imageExtensions = ['webp', 'png', 'jpg', 'jpeg'];
$imageDirs = [
    $documentRoot . '/images',
    $documentRoot . '/images/products'
];

echo "\n🖼️  Setting image file permissions to 644...\n";
foreach ($imageDirs as $dir) {
    if (is_dir($dir)) {
        foreach ($imageExtensions as $ext) {
            $files = glob($dir . '/*.' . $ext);
            foreach ($files as $file) {
                $result = chmod($file, 0644);
                echo ($result ? "✅" : "❌") . " " . basename($file) . "\n";
                $results[] = [basename($file), $result ? 'OK' : 'FAILED'];
            }
        }
    }
}

// Fix PHP file permissions
$phpDirs = [
    $documentRoot,
    $documentRoot . '/api',
    $documentRoot . '/sections'
];

echo "\n🔧 Setting PHP file permissions to 644...\n";
foreach ($phpDirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            $result = chmod($file, 0644);
            echo ($result ? "✅" : "❌") . " " . basename($file) . "\n";
            $results[] = [basename($file), $result ? 'OK' : 'FAILED'];
        }
    }
}

// Fix CSS and JS file permissions
$assetDirs = [
    [$documentRoot . '/css', '*.css'],
    [$documentRoot . '/js', '*.js']
];

echo "\n🎨 Setting CSS/JS file permissions to 644...\n";
foreach ($assetDirs as [$dir, $pattern]) {
    if (is_dir($dir)) {
        $files = glob($dir . '/' . $pattern);
        foreach ($files as $file) {
            $result = chmod($file, 0644);
            echo ($result ? "✅" : "❌") . " " . basename($file) . "\n";
            $results[] = [basename($file), $result ? 'OK' : 'FAILED'];
        }
    }
}

echo "\n📊 SUMMARY:\n";
$successful = 0;
$failed = 0;
foreach ($results as [$item, $status]) {
    if ($status === 'OK') {
        $successful++;
    } else {
        $failed++;
        echo "❌ FAILED: $item\n";
    }
}

echo "\n✅ Successful: $successful\n";
echo "❌ Failed: $failed\n";

if ($failed === 0) {
    echo "\n🎉 All permissions fixed successfully!\n";
} else {
    echo "\n⚠️  Some permissions could not be fixed. Check server logs.\n";
}

echo "\n🔍 Testing sample image access...\n";
$testImages = [
    '/images/products/TS002A.webp',
    '/images/products/AW001A.png'
];

foreach ($testImages as $testPath) {
    $fullPath = $documentRoot . $testPath;
    if (file_exists($fullPath)) {
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        echo "📄 $testPath - Permissions: $perms - " . (is_readable($fullPath) ? "✅ Readable" : "❌ Not readable") . "\n";
    } else {
        echo "❌ File not found: $testPath\n";
    }
}

echo "\n🏁 Permission fix complete!\n";
?> 