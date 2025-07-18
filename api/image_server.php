<?php
/**
 * Smart Image Server - Dual Format Support
 *
 * Automatically serves WebP to modern browsers and PNG to older browsers
 * for maximum compatibility while maintaining optimal performance.
 */

// Get the requested image path
$imagePath = $_GET['image'] ?? '';

if (empty($imagePath)) {
    http_response_code(400);
    echo 'No image specified';
    exit;
}

// Security: Prevent directory traversal
$imagePath = str_replace(['../', '../', '..\\', '..\\\\'], '', $imagePath);

// Determine if browser supports WebP
function supportsWebP()
{
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    return strpos($acceptHeader, 'image/webp') !== false;
}

// Get base path without extension
$pathInfo = pathinfo($imagePath);
$basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
$directory = __DIR__ . '/../' . $pathInfo['dirname'] . '/';

// Define possible image paths
$webpPath = $directory . $basePath . '.webp';
$pngPath = $directory . $basePath . '.png';
$originalPath = __DIR__ . '/../' . $imagePath;

// Smart format selection
$serveWebP = supportsWebP();
$imagePath = null;
$mimeType = null;

if ($serveWebP && file_exists($webpPath)) {
    // Serve WebP for modern browsers
    $imagePath = $webpPath;
    $mimeType = 'image/webp';
} elseif (file_exists($pngPath)) {
    // Serve PNG for compatibility
    $imagePath = $pngPath;
    $mimeType = 'image/png';
} elseif (file_exists($originalPath)) {
    // Fallback to original image
    $imagePath = $originalPath;
    $imageInfo = getimagesize($originalPath);
    $mimeType = $imageInfo ? $imageInfo['mime'] : 'image/png';
} else {
    // No image found
    http_response_code(404);
    echo 'Image not found';
    exit;
}

// Set appropriate headers
header('Content-Type: ' . $mimeType);
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Add headers for debugging (can be removed in production)
header('X-Image-Format: ' . ($serveWebP ? 'webp' : 'fallback'));
header('X-Browser-Supports-WebP: ' . ($serveWebP ? 'yes' : 'no'));

// Serve the image
readfile($imagePath);
exit;
?> 