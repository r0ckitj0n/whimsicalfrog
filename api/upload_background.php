<?php
// Background upload endpoint (multipart/form-data)
// Saves original image to images/backgrounds and generates WebP.

require_once __DIR__ . '/config.php'; // sets headers/CORS and DB env, loads Database class

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Helpers
function slugify($text) {
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'bg';
}

function ensureDir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            throw new Exception('Failed to create directory: ' . $path);
        }
    }
}

function convertToWebP($srcPath, $destPath, $quality = 90)
{
    $info = getimagesize($srcPath);
    if (!$info) {
        throw new Exception('Invalid image file.');
    }
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($srcPath);
            // Preserve transparency for PNG
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/webp':
            // If already webp, we can just copy to destination
            if (!copy($srcPath, $destPath)) {
                throw new Exception('Failed to copy WEBP image.');
            }
            return $destPath;
        default:
            throw new Exception('Unsupported image type: ' . $mime);
    }

    if (!$image) {
        throw new Exception('Failed to create image resource.');
    }

    if (!imagewebp($image, $destPath, $quality)) {
        imagedestroy($image);
        throw new Exception('Failed to generate WebP image.');
    }

    imagedestroy($image);
    return $destPath;
}

try {
    // Validate inputs: prefer 'room' over legacy 'room_type'
    $roomParam = $_POST['room'] ?? null;
    $legacyRoomType = $_POST['room_type'] ?? null;
    if ($roomParam !== null && $roomParam !== '') {
        if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
            $roomType = 'room' . (int)$m[1];
        } else {
            $roomType = 'room' . (int)$roomParam;
        }
    } else {
        $roomType = $legacyRoomType ?? '';
    }
    $roomNumber = preg_match('/^room(\w+)$/i', (string)$roomType, $m) ? (string)$m[1] : '';
    $backgroundName = $_POST['background_name'] ?? '';

    if ($roomType === '' || !preg_match('/^room[1-5]$/', $roomType)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'room is required (use 1..5)']);
        exit;
    }

    if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $err = $_FILES['background_image']['error'] ?? 'missing';
        echo json_encode(['success' => false, 'message' => 'background_image upload failed', 'error' => $err]);
        exit;
    }

    $file = $_FILES['background_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unsupported file type. Allowed: JPG, PNG, WEBP']);
        exit;
    }

    // Build filenames
    $baseName = $backgroundName ?: pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = slugify($baseName);
    $safeRoom = slugify($roomType);
    $ext = $allowed[$mime];

    $imagesRoot = realpath(__DIR__ . '/../images');
    if ($imagesRoot === false) {
        throw new Exception('Images root not found');
    }
    $destDir = $imagesRoot . '/backgrounds';
    ensureDir($destDir);

    $unique = $safeName . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);

    $destOriginalRel = 'backgrounds/' . $unique . '.' . $ext; // stored in DB
    $destOriginalAbs = $imagesRoot . '/' . $destOriginalRel;

    if (!move_uploaded_file($file['tmp_name'], $destOriginalAbs)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Generate WebP alongside
    $destWebpRel = 'backgrounds/' . $unique . '.webp';
    $destWebpAbs = $imagesRoot . '/' . $destWebpRel;

    try {
        convertToWebP($destOriginalAbs, $destWebpAbs, 92);
    } catch (Exception $e) {
        // Non-fatal: allow original-only if conversion fails
        $destWebpRel = null;
        error_log('WebP conversion failed: ' . $e->getMessage());
    }

    // DB insert
    try {
        Database::getInstance();

        // If no background_name provided, build a nice name
        if (empty($backgroundName)) {
            $backgroundName = ucwords(str_replace('-', ' ', $safeName));
        }

        // Prevent duplicate names for room (prefer room_number)
        $row = Database::queryOne('SELECT id FROM backgrounds WHERE (room_number = ? OR room_type = ?) AND background_name = ? LIMIT 1', [$roomNumber, $roomType, $backgroundName]);
        if ($row) {
            // Append unique suffix rather than failing
            $backgroundName .= ' ' . date('Ymd-His');
        }

        Database::execute('INSERT INTO backgrounds (room_type, room_number, background_name, image_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, ?, 0)', [$roomType, $roomNumber, $backgroundName, $destOriginalRel, $destWebpRel]);
        $id = Database::lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Background uploaded successfully',
            'id' => $id,
            'room_type' => $roomType,
            'background_name' => $backgroundName,
            'image_filename' => $destOriginalRel,
            'webp_filename' => $destWebpRel,
            'image_url' => '/images/' . $destOriginalRel,
            'webp_url' => $destWebpRel ? '/images/' . $destWebpRel : null
        ]);
        exit;
    } catch (PDOException $e) {
        // Cleanup files on DB error
        if (file_exists($destOriginalAbs)) { @unlink($destOriginalAbs); }
        if (!empty($destWebpAbs) && file_exists($destWebpAbs)) { @unlink($destWebpAbs); }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
