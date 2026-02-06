<?php
/**
 * api/upload_background.php
 * Background upload endpoint (multipart/form-data)
 */

// Increase upload limits at runtime (for dev server that may not read .user.ini)
@ini_set('upload_max_filesize', '20M');
@ini_set('post_max_size', '25M');
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '120');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers/ImageUploadHelper.php';
require_once __DIR__ . '/ai_image_processor.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    // Validate inputs
    // Note: Use strict empty check - PHP treats "0" as falsy, but "0" is valid (Main Room)
    $roomParam = $_POST['room'] ?? $_POST['room_type'] ?? '';
    if ($roomParam === '' || !preg_match('/^[0-9a-zA-Z]+$/', $roomParam)) {
        Response::error('room is required (alphanumeric)', null, 400);
    }

    $roomType = str_starts_with(strtolower($roomParam), 'room') ? 'room' . substr($roomParam, 4) : 'room' . $roomParam;
    // Preserve case for multi-character room IDs (e.g., 'about', 'contact'), uppercase single-char IDs (e.g., 'A', '0')
    $rawRoomNumber = preg_replace('/^room/i', '', $roomType);
    $room_number = strlen($rawRoomNumber) === 1 ? strtoupper($rawRoomNumber) : $rawRoomNumber;
    $backgroundName = $_POST['name'] ?? '';

    if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
        Response::error('background_image upload failed', ['error' => $_FILES['background_image']['error'] ?? 'missing'], 400);
    }

    $file = $_FILES['background_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : null;
    if (!$mime && ($finfo = @finfo_open(FILEINFO_MIME_TYPE))) {
        $mime = @finfo_file($finfo, $file['tmp_name']);
        @finfo_close($finfo);
    }

    if (!isset($allowed[$mime]))
        Response::error('Unsupported file type. Allowed: JPG, PNG, WEBP', null, 400);

    $safeName = ImageUploadHelper::slugify($backgroundName ?: pathinfo($file['name'], PATHINFO_FILENAME));
    $safeRoom = ImageUploadHelper::slugify($roomType);
    $ext = $allowed[$mime];

    $imagesRoot = realpath(__DIR__ . '/../images') ?: (__DIR__ . '/../images');
    $destDir = $imagesRoot . '/backgrounds';
    ImageUploadHelper::ensureDir($destDir);

    $unique = $safeName . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);
    $destOriginalAbs = $destDir . '/' . $unique . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], $destOriginalAbs))
        throw new Exception('Failed to move upload');

    $destPngRel = 'backgrounds/' . $unique . '.png';
    $destWebpRel = 'backgrounds/' . $unique . '.webp';
    $hasWebpSupport = function_exists('imagewebp') && function_exists('imagecreatefromwebp');
    $processed = false;

    if ($hasWebpSupport) {
        try {
            $proc = (new AIImageProcessor())->processBackgroundImage($destOriginalAbs, [
                'createDualFormat' => true,
                'webp_quality' => 90,
                'png_compression' => 1,
                'preserve_transparency' => true,
                'useAI' => false,
                'resizeDimensions' => ['width' => 1280, 'height' => 896],
                'resizeMode' => 'fill'
            ]);
            if (!empty($proc['png_path']))
                @rename($proc['png_path'], $imagesRoot . '/' . $destPngRel);
            if (!empty($proc['webp_path']))
                @rename($proc['webp_path'], $imagesRoot . '/' . $destWebpRel);
            $processed = true;
        } catch (Throwable $e) {
            $processed = false;
        }
    }

    if (!$processed) {
        ImageUploadHelper::resizeFillToPng($destOriginalAbs, $imagesRoot . '/' . $destPngRel);
        if ($hasWebpSupport) {
            try {
                ImageUploadHelper::convertToWebP($imagesRoot . '/' . $destPngRel, $imagesRoot . '/' . $destWebpRel, 92);
            } catch (Exception $e) {
            }
        }
    }

    if (file_exists($destOriginalAbs))
        @unlink($destOriginalAbs);

    Database::getInstance();
    $finalName = $backgroundName ?: ucwords(str_replace('-', ' ', $safeName));
    if (Database::queryOne('SELECT id FROM backgrounds WHERE room_number = ? AND name = ? LIMIT 1', [$room_number, $finalName])) {
        $finalName .= ' ' . date('Ymd-His');
    }

    Database::execute('INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, ?, 0)', [$room_number, $finalName, $destPngRel, $destPngRel, $destWebpRel]);

    Response::success([
        'id' => Database::lastInsertId(),
        'room_type' => $roomType,
        'name' => $finalName,
        'image_url' => '/images/' . $destPngRel,
        'webp_url' => $destWebpRel ? '/images/' . $destWebpRel : null
    ]);

} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
