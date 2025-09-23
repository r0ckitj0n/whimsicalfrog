<?php

// Enhanced SKU endpoint that supports gender, size, and color attributes
// Usage: /api/next_sku.php?cat=Tumblers&gender=Male&size=L&color=Black

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check session authentication first
require_once __DIR__ . '/../includes/auth.php';
if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    // Validate required parameter
    $category = $_GET['cat'] ?? '';
    if (empty($category)) {
        Response::error('Category parameter is required');
    }

    // Optional enhanced parameters
    $gender = $_GET['gender'] ?? '';
    $size = $_GET['size'] ?? '';
    $color = $_GET['color'] ?? '';
    $enhanced = $_GET['enhanced'] ?? 'false'; // Set to 'true' for enhanced SKUs

    // Log the request for audit trail
    Logger::userAction('generate_sku', [
        'category' => $category,
        'gender' => $gender,
        'size' => $size,
        'color' => $color,
        'enhanced' => $enhanced
    ]);

    if ($enhanced === 'true' && (!empty($gender) || !empty($size) || !empty($color))) {
        $newSku = generateEnhancedSku($category, $gender, $size, $color);
    } else {
        $newSku = generateSkuForCategory($category);
    }

    // Log successful generation
    Logger::info('SKU generated successfully', [
        'category' => $category,
        'sku' => $newSku,
        'enhanced' => $enhanced === 'true'
    ]);

    Response::success([
        'sku' => $newSku,
        'category' => $category,
        'enhanced' => $enhanced === 'true',
        'attributes' => [
            'gender' => $gender,
            'size' => $size,
            'color' => $color
        ]
    ]);

} catch (Exception $e) {
    Logger::exception($e, 'Failed to generate SKU');
    Response::serverError('Failed to generate SKU: ' . $e->getMessage());
}

function generateSkuForCategory($category)
{
    // Get category code - first 2 letters of category, uppercase
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General fallback
    }

    // Find the highest existing number for this category using centralized database
    $lastSku = Database::queryOne(
        "SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1",
        ["WF-{$categoryCode}-%"]
    );

    $nextNum = 1;
    if ($lastSku && $lastSku['sku']) {
        $parts = explode('-', $lastSku['sku']);
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $nextNum = intval($parts[2]) + 1;
        }
    }

    return sprintf('WF-%s-%03d', $categoryCode, $nextNum);
}

function generateEnhancedSku($category, $gender = '', $size = '', $color = '')
{
    // Get category code - first 2 letters of category, uppercase
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General fallback
    }

    // Build attribute parts
    $attributeParts = [];

    // Add gender code
    if (!empty($gender)) {
        $genderCode = getGenderCode($gender);
        if ($genderCode) {
            $attributeParts[] = $genderCode;
        }
    }

    // Add size code
    if (!empty($size)) {
        $sizeCode = getSizeCode($size);
        if ($sizeCode) {
            $attributeParts[] = $sizeCode;
        }
    }

    // Add color code
    if (!empty($color)) {
        $colorCode = getColorCode($color);
        if ($colorCode) {
            $attributeParts[] = $colorCode;
        }
    }

    // Build the pattern for finding existing SKUs with attributes
    $attributeString = !empty($attributeParts) ? '-' . implode('-', $attributeParts) : '';
    $searchPattern = "WF-{$categoryCode}{$attributeString}-%";

    // Find the highest existing number for this category/attribute combination
    $lastSku = Database::queryOne(
        "SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1",
        [$searchPattern]
    );

    $nextNum = 1;
    if ($lastSku && $lastSku['sku']) {
        // Extract the number from the end of the SKU
        $parts = explode('-', $lastSku['sku']);
        $lastPart = end($parts);
        if (is_numeric($lastPart)) {
            $nextNum = intval($lastPart) + 1;
        }
    }

    // Build final SKU: WF-TS-M-L-BLK-001
    if (!empty($attributeParts)) {
        return sprintf('WF-%s-%s-%03d', $categoryCode, implode('-', $attributeParts), $nextNum);
    }

    // Fallback to basic SKU if no attributes
    return sprintf('WF-%s-%03d', $categoryCode, $nextNum);
}

function getGenderCode($gender)
{
    $genderMap = [
        'Male' => 'M',
        'Female' => 'F',
        'Unisex' => 'U',
        'Men' => 'M',
        'Women' => 'F',
        'Kids' => 'K',
        'Children' => 'K',
        'Boys' => 'B',
        'Girls' => 'G'
    ];

    return $genderMap[ucfirst(strtolower($gender))] ?? strtoupper(substr($gender, 0, 1));
}

function getSizeCode($size)
{
    // Handle standard size codes
    $sizeMap = [
        'Extra Small' => 'XS',
        'Small' => 'S',
        'Medium' => 'M',
        'Large' => 'L',
        'Extra Large' => 'XL',
        'Double XL' => 'XXL',
        'Triple XL' => 'XXXL'
    ];

    // Check if it's already a code
    if (strlen($size) <= 4 && ctype_alpha($size)) {
        return strtoupper($size);
    }

    // Try to find in map
    return $sizeMap[ucfirst($size)] ?? strtoupper(substr($size, 0, 2));
}

function getColorCode($color)
{
    $colorMap = [
        'Black' => 'BLK',
        'White' => 'WHT',
        'Red' => 'RED',
        'Blue' => 'BLU',
        'Green' => 'GRN',
        'Yellow' => 'YEL',
        'Orange' => 'ORG',
        'Purple' => 'PUR',
        'Pink' => 'PNK',
        'Brown' => 'BRN',
        'Gray' => 'GRY',
        'Grey' => 'GRY',
        'Silver' => 'SLV',
        'Gold' => 'GLD',
        'Navy' => 'NVY',
        'Navy Blue' => 'NVY',
        'Dark Blue' => 'DBL',
        'Light Blue' => 'LBL',
        'Dark Green' => 'DGR',
        'Light Green' => 'LGR',
        'Maroon' => 'MAR',
        'Burgundy' => 'BUR',
        'Teal' => 'TEL',
        'Turquoise' => 'TUR',
        'Magenta' => 'MAG',
        'Cyan' => 'CYN',
        'Lime' => 'LIM',
        'Olive' => 'OLV',
        'Beige' => 'BEG',
        'Tan' => 'TAN',
        'Cream' => 'CRM'
    ];

    // Check if it's already a 3-letter code
    if (strlen($color) === 3 && ctype_alpha($color)) {
        return strtoupper($color);
    }

    // Try to find in map
    $mapped = $colorMap[ucfirst($color)] ?? null;
    if ($mapped) {
        return $mapped;
    }

    // Generate 3-letter code from color name
    $clean = preg_replace('/[^a-zA-Z]/', '', $color);
    return strtoupper(substr($clean, 0, 3));
}
