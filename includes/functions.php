<?php
/**
 * Common functions for the Whimsical Frog website
 * 
 * This file contains shared functions used across multiple PHP files
 * to ensure consistent functionality throughout the site.
 */

/**
 * Generates an HTML <img> tag with WebP support and fallback to the original image format.
 *
 * @param string $originalPath The path to the original image (e.g., 'images/my_image.png').
 * @param string $altText The alt text for the image.
 * @param string $class Optional CSS classes for the image tag.
 * @param string $style Optional inline styles for the image tag.
 * @return string The HTML <img> tag.
 */
function getImageTag($originalPath, $altText, $class = '', $style = '') {
    if (empty($originalPath)) {
        $originalPath = 'images/products/placeholder.png'; // Default placeholder if path is empty
    }
    // Corrected WebP path generation - assumes WebP is in the same directory as original but with .webp extension
    $pathInfo = pathinfo($originalPath);
    $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    
    $classAttr = !empty($class) ? " class='" . htmlspecialchars($class) . "'" : '';
    $styleAttr = !empty($style) ? " style='" . htmlspecialchars($style) . "'" : '';

    return "<img src='" . htmlspecialchars($webpPath) . "' alt='" . htmlspecialchars($altText) . "'" . $classAttr . $styleAttr . " onerror=\"this.onerror=null; this.src='" . htmlspecialchars($originalPath) . "';\">"; 
}
?>
