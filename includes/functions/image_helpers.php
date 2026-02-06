<?php

/**
 * Image handling utility functions
 */

/**
 * Generates an HTML <img> tag with WebP support and fallback to the original image format.
 *
 * @param string $imagePath The path to the image.
 * @param string $altText The alt text for the image.
 * @param string $class Optional CSS classes.
 * @param string $style Ignored (inline styles disallowed).
 * @return string The HTML <img> tag or <picture> element.
 */
function getImageTag($imagePath, $altText = '', $class = '', $style = '')
{
    if (empty($imagePath)) {
        return '';
    }

    $pathInfo = pathinfo($imagePath);
    $extension = strtolower($pathInfo['extension'] ?? '');
    $basePath = ($pathInfo['dirname'] && $pathInfo['dirname'] !== '.')
        ? $pathInfo['dirname'] . '/' . $pathInfo['filename']
        : $pathInfo['filename'];

    $classAttr = !empty($class) ? ' class="' . htmlspecialchars($class) . '"' : '';

    if ($extension === 'webp') {
        return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . '>';
    }

    $webpPath = $basePath . '.webp';
    if (file_exists(__DIR__ . '/../../' . $webpPath)) {
        return '<picture>'
            . '<source srcset="' . htmlspecialchars($webpPath) . '" type="image/webp">'
            . '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . '>'
            . '</picture>';
    }

    return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . '>';
}

/**
 * Get the active background image for a specific room type.
 * Supports direct room_number identifiers: 'A', 'S', 'X', '0', 'about', 'contact', numeric rooms
 */
function get_active_background($roomType)
{
    try {
        $pdo = Database::getInstance();
        $roomTypeStr = (string) $roomType;

        $pickFile = function ($row) {
            $candidates = [];
            if (!empty($row['webp_filename']))
                $candidates[] = $row['webp_filename'];
            if (!empty($row['png_filename']))
                $candidates[] = $row['png_filename'];
            if (!empty($row['image_filename']))
                $candidates[] = $row['image_filename'];
            foreach ($candidates as $rel) {
                $rel = ltrim($rel, '/');
                $abs = (strpos($rel, 'images/') === 0)
                    ? __DIR__ . '/../../' . $rel
                    : __DIR__ . '/../../images/' . $rel;
                if (is_file($abs)) {
                    return (strpos($rel, 'images/') === 0) ? ('/' . $rel) : ('/images/' . $rel);
                }
            }
            return '';
        };

        // Direct lookup by room_number (handles 'about', 'contact', 'A', 'S', 'X', '0', etc.)
        $stmt = $pdo->prepare("SELECT image_filename, png_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$roomTypeStr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            $url = $pickFile($row);
            if ($url !== '')
                return $url;
        }

        // Legacy fallback for old 'landing', 'shop', 'settings', 'room_main' keywords
        $legacyMappings = [
            'landing' => 'A',
            'shop' => 'S',
            'settings' => 'X',
            'admin/settings' => 'X',
            'room_main' => '0'
        ];

        if (isset($legacyMappings[strtolower($roomTypeStr)])) {
            $mappedRoom = $legacyMappings[strtolower($roomTypeStr)];
            $stmt = $pdo->prepare("SELECT image_filename, png_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$mappedRoom]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $url = $pickFile($row);
                if ($url !== '')
                    return $url;
            }
        }

        // Numeric room fallback (e.g., 'room1' -> '1')
        if (preg_match('/^room(\d+)$/i', $roomTypeStr, $m)) {
            $stmt = $pdo->prepare("SELECT image_filename, png_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$m[1]]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $url = $pickFile($row);
                if ($url !== '')
                    return $url;
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching active background: ' . $e->getMessage());
    }
    return '';
}
