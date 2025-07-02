<?php
/**
 * Image Conversion Script
 * Converts all images in images/ and images/items/ folders to both PNG and WebP formats
 * Maintains transparency, dimensions, and quality
 */

// Check if GD extension is available
if (!extension_loaded('gd')) {
    die("Error: GD extension is required for image processing\n");
}

// Check if WebP support is available
if (!function_exists('imagewebp')) {
    die("Error: WebP support is not available in GD extension\n");
}

echo "🖼️  Starting comprehensive image conversion process...\n\n";

// Define directories to process
$directories = [
    'images',
    'images/items'
];

// Track statistics
$stats = [
    'processed' => 0,
    'skipped' => 0,
    'errors' => 0,
    'png_created' => 0,
    'webp_created' => 0
];

// Supported image formats
$supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "⚠️  Directory '$dir' not found, skipping...\n";
        continue;
    }
    
    echo "📁 Processing directory: $dir\n";
    
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.DS_Store') {
            continue;
        }
        
        $filePath = $dir . '/' . $file;
        
        // Check if it's a file
        if (!is_file($filePath)) {
            continue;
        }
        
        // Get file extension
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        // Skip if not a supported image format
        if (!in_array($extension, $supportedFormats)) {
            echo "  ⏭️  Skipping non-image file: $file\n";
            $stats['skipped']++;
            continue;
        }
        
        // Get filename without extension
        $filename = pathinfo($file, PATHINFO_FILENAME);
        
        echo "  🔄 Processing: $file\n";
        
        try {
            // Load the image
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                throw new Exception("Could not get image info");
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $type = $imageInfo[2];
            
            // Create image resource based on type
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filePath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filePath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filePath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($filePath);
                    break;
                case IMAGETYPE_BMP:
                    $source = imagecreatefromwbmp($filePath);
                    break;
                default:
                    throw new Exception("Unsupported image type");
            }
            
            if (!$source) {
                throw new Exception("Could not create image resource");
            }
            
            // Preserve transparency
            imagealphablending($source, false);
            imagesavealpha($source, true);
            
            // Create PNG version if it doesn't exist or if source is not PNG
            $pngPath = $dir . '/' . $filename . '.png';
            if (!file_exists($pngPath) || $extension !== 'png') {
                echo "    📄 Creating PNG: " . basename($pngPath) . "\n";
                
                // Create a new image with the same dimensions
                $pngImage = imagecreatetruecolor($width, $height);
                
                // Preserve transparency
                imagealphablending($pngImage, false);
                imagesavealpha($pngImage, true);
                $transparent = imagecolorallocatealpha($pngImage, 0, 0, 0, 127);
                imagefill($pngImage, 0, 0, $transparent);
                
                // Copy the source image
                imagecopy($pngImage, $source, 0, 0, 0, 0, $width, $height);
                
                // Save PNG with maximum quality
                if (imagepng($pngImage, $pngPath, 9)) {
                    $stats['png_created']++;
                    echo "      ✅ PNG created successfully\n";
                } else {
                    echo "      ❌ Failed to create PNG\n";
                    $stats['errors']++;
                }
                
                imagedestroy($pngImage);
            } else {
                echo "    ⏭️  PNG already exists: " . basename($pngPath) . "\n";
            }
            
            // Create WebP version if it doesn't exist or if source is not WebP
            $webpPath = $dir . '/' . $filename . '.webp';
            if (!file_exists($webpPath) || $extension !== 'webp') {
                echo "    🌐 Creating WebP: " . basename($webpPath) . "\n";
                
                // Create a new image with the same dimensions
                $webpImage = imagecreatetruecolor($width, $height);
                
                // Preserve transparency
                imagealphablending($webpImage, false);
                imagesavealpha($webpImage, true);
                $transparent = imagecolorallocatealpha($webpImage, 0, 0, 0, 127);
                imagefill($webpImage, 0, 0, $transparent);
                
                // Copy the source image
                imagecopy($webpImage, $source, 0, 0, 0, 0, $width, $height);
                
                // Save WebP with maximum quality
                if (imagewebp($webpImage, $webpPath, 100)) {
                    $stats['webp_created']++;
                    echo "      ✅ WebP created successfully\n";
                } else {
                    echo "      ❌ Failed to create WebP\n";
                    $stats['errors']++;
                }
                
                imagedestroy($webpImage);
            } else {
                echo "    ⏭️  WebP already exists: " . basename($webpPath) . "\n";
            }
            
            // Clean up source image
            imagedestroy($source);
            
            $stats['processed']++;
            
        } catch (Exception $e) {
            echo "    ❌ Error processing $file: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    echo "\n";
}

// Print summary
echo "📊 Conversion Summary:\n";
echo "=====================\n";
echo "✅ Files processed: " . $stats['processed'] . "\n";
echo "⏭️  Files skipped: " . $stats['skipped'] . "\n";
echo "📄 PNG files created: " . $stats['png_created'] . "\n";
echo "🌐 WebP files created: " . $stats['webp_created'] . "\n";
echo "❌ Errors: " . $stats['errors'] . "\n";

if ($stats['errors'] === 0) {
    echo "\n🎉 All images processed successfully!\n";
    echo "💡 Both PNG and WebP versions are now available for all images.\n";
    echo "🔍 Check the images/ and images/items/ folders for the converted files.\n";
} else {
    echo "\n⚠️  Some errors occurred during conversion. Check the output above for details.\n";
}

echo "\n";
?> 