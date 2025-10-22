<?php

// Background upload endpoint (multipart/form-data)
// Saves original image to images/backgrounds and generates WebP.

require_once __DIR__ . '/config.php'; // sets headers/CORS and DB env, loads Database class
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/ai_image_processor.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    Response::json(['success' => true]);
    exit;
}

function resizeFillToPng($srcPath, $destPath, $targetW = 1280, $targetH = 896)
{
    $info = getimagesize($srcPath);
    if (!$info) throw new Exception('Invalid image file.');
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($srcPath); break;
        case IMAGETYPE_PNG: $src = imagecreatefrompng($srcPath); imagealphablending($src, false); imagesavealpha($src, true); break;
        case IMAGETYPE_WEBP:
            if (!function_exists('imagecreatefromwebp')) {
                throw new Exception('Server does not support WEBP decoding');
            }
            $src = imagecreatefromwebp($srcPath); imagealphablending($src, false); imagesavealpha($src, true); break;
        default: throw new Exception('Unsupported image type');
    }
    if (!$src) throw new Exception('Failed to create image resource');
    $sw = imagesx($src); $sh = imagesy($src);
    $srcRatio = $sw / $sh; $tgtRatio = $targetW / $targetH;
    if ($srcRatio > $tgtRatio) { $srcH = $sh; $srcW = (int)($sh * $tgtRatio); $sx = (int)(($sw - $srcW) / 2); $sy = 0; }
    else { $srcW = $sw; $srcH = (int)($sw / $tgtRatio); $sx = 0; $sy = (int)(($sh - $srcH) / 2); }
    $dst = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127); imagefill($dst, 0, 0, $transparent);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $targetW, $targetH, $srcW, $srcH);
    imagepng($dst, $destPath, 1);
    imagedestroy($src); imagedestroy($dst);
    chmod($destPath, 0644);
    return $destPath;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

// Helpers
function slugify($text)
{
    $text = trim($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'bg';
}

function ensureDir($path)
{
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
        $raw = trim((string)$roomParam);
        if (preg_match('/^room([0-9a-zA-Z]+)$/i', $raw, $m)) {
            $roomType = 'room' . strtoupper($m[1]);
        } elseif (preg_match('/^[0-9]+$/', $raw)) {
            $roomType = 'room' . (int)$raw;
        } elseif (preg_match('/^[a-zA-Z]+$/', $raw)) {
            $roomType = 'room' . strtoupper($raw);
        } else {
            $roomType = '';
        }
    } else {
        $roomType = $legacyRoomType ?? '';
    }
    $roomNumber = preg_match('/^room(\w+)$/i', (string)$roomType, $m) ? strtoupper((string)$m[1]) : '';
    $backgroundName = $_POST['background_name'] ?? '';

    if ($roomType === '' || !preg_match('/^room(?:[0-5]|A|S|X)$/', $roomType)) {
        Response::error('room is required (use 0..5, A, S, or X)', null, 400);
    }

    if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['background_image']['error'] ?? 'missing';
        Response::error('background_image upload failed', ['error' => $err], 400);
    }

    $file = $_FILES['background_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = null;
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($file['tmp_name']);
    }
    if (!$mime) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $file['tmp_name']);
            @finfo_close($finfo);
        }
    }

    if (!isset($allowed[$mime])) {
        Response::error('Unsupported file type. Allowed: JPG, PNG, WEBP', null, 400);
    }

    // Build filenames
    $baseName = $backgroundName ?: pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = slugify($baseName);
    $safeRoom = slugify($roomType);
    $ext = $allowed[$mime];

    $imagesRoot = realpath(__DIR__ . '/../images');
    if ($imagesRoot === false) {
        $imagesRoot = __DIR__ . '/../images';
        ensureDir($imagesRoot);
    }
    $destDir = $imagesRoot . '/backgrounds';
    ensureDir($destDir);

    $unique = $safeName . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);

    $destOriginalRel = 'backgrounds/' . $unique . '.' . $ext; // stored in DB
    $destOriginalAbs = $imagesRoot . '/' . $destOriginalRel;

    if (!move_uploaded_file($file['tmp_name'], $destOriginalAbs)) {
        throw new Exception('Failed to move uploaded file');
    }

    $destPngRel = null; $destWebpRel = null;
    $hasWebpSupport = function_exists('imagewebp') && function_exists('imagecreatefromwebp');
    $didProcess = false;
    if ($hasWebpSupport) {
        try {
            $processor = new AIImageProcessor();
            $proc = $processor->processBackgroundImage($destOriginalAbs, [
                'createDualFormat' => true,
                'webp_quality' => 90,
                'png_compression' => 1,
                'preserve_transparency' => true,
                'useAI' => false,
                'resizeDimensions' => ['width' => 1280, 'height' => 896],
                'resizeMode' => 'fill'
            ]);
            if (!empty($proc['png_path'])) {
                $destPngRel = ltrim(str_replace($imagesRoot . '/', '', $proc['png_path']), '/');
            }
            if (!empty($proc['webp_path'])) {
                $destWebpRel = ltrim(str_replace($imagesRoot . '/', '', $proc['webp_path']), '/');
            }
            $didProcess = true;
        } catch (Throwable $e) {
            $didProcess = false;
        }
    }
    if (!$didProcess) {
        $pngAbs = $imagesRoot . '/backgrounds/' . $unique . '_1280x896.png';
        resizeFillToPng($destOriginalAbs, $pngAbs, 1280, 896);
        $destPngRel = 'backgrounds/' . basename($pngAbs);
        if ($hasWebpSupport) {
            $webpAbs = $imagesRoot . '/backgrounds/' . $unique . '_1280x896.webp';
            try { convertToWebP($pngAbs, $webpAbs, 92); $destWebpRel = 'backgrounds/' . basename($webpAbs); } catch (Exception $ignored) {}
        }
    }

    // Normalize names to omit '_resized' suffix and dimension postfix by using a clean base
    $cleanPngRel = 'backgrounds/' . $unique . '.png';
    $cleanWebpRel = 'backgrounds/' . $unique . '.webp';
    if (!empty($destPngRel) && $destPngRel !== $cleanPngRel) {
        $src = $imagesRoot . '/' . $destPngRel; $dst = $imagesRoot . '/' . $cleanPngRel;
        if (file_exists($src)) { @rename($src, $dst) || @copy($src, $dst); if (file_exists($src) && $src !== $dst) { @unlink($src); } }
        $destPngRel = $cleanPngRel;
    }
    if (!empty($destWebpRel) && $destWebpRel !== $cleanWebpRel) {
        $src = $imagesRoot . '/' . $destWebpRel; $dst = $imagesRoot . '/' . $cleanWebpRel;
        if (file_exists($src)) { @rename($src, $dst) || @copy($src, $dst); if (file_exists($src) && $src !== $dst) { @unlink($src); } }
        $destWebpRel = $cleanWebpRel;
    }

    // Purge the original upload to avoid clutter once processed versions exist
    if (file_exists($destOriginalAbs)) {
        @unlink($destOriginalAbs);
    }

    // DB insert
    try {
        Database::getInstance();

        // If no background_name provided, build a nice name
        if (empty($backgroundName)) {
            $backgroundName = ucwords(str_replace('-', ' ', $safeName));
        }

        // Prevent duplicate names for room using room_number
        $row = Database::queryOne('SELECT id FROM backgrounds WHERE room_number = ? AND background_name = ? LIMIT 1', [$roomNumber, $backgroundName]);
        if ($row) {
            // Append unique suffix rather than failing
            $backgroundName .= ' ' . date('Ymd-His');
        }

        $destPngRelStr = $destPngRel ?: '';
        $destWebpRelStr = $destWebpRel ?: '';
        // Store PNG as primary image_filename so consumers without webp/png fields can still load
        Database::execute('INSERT INTO backgrounds (room_number, background_name, image_filename, png_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, ?, 0)', [$roomNumber, $backgroundName, $destPngRelStr, $destPngRelStr, $destWebpRelStr]);
        $id = Database::lastInsertId();

        Response::success([
            'message' => 'Background uploaded successfully',
            'id' => $id,
            'room_type' => $roomType,
            'background_name' => $backgroundName,
            'image_filename' => $destPngRel,
            'png_filename' => $destPngRel,
            'webp_filename' => $destWebpRel,
            'image_url' => $destPngRel ? '/images/' . $destPngRel : null,
            'png_url' => $destPngRel ? '/images/' . $destPngRel : null,
            'webp_url' => $destWebpRel ? '/images/' . $destWebpRel : null
        ]);
    } catch (PDOException $e) {
        // Cleanup files on DB error
        if (file_exists($destOriginalAbs)) {
            @unlink($destOriginalAbs);
        }
        if (!empty($destPngRel)) {
            $p = $imagesRoot . '/' . $destPngRel; if (file_exists($p)) { @unlink($p); }
        }
        if (!empty($destWebpRel)) {
            $w = $imagesRoot . '/' . $destWebpRel; if (file_exists($w)) { @unlink($w); }
        }
        Response::serverError('Database error: ' . $e->getMessage());
    }

} catch (Throwable $e) {
    Response::serverError('Upload failed: ' . $e->getMessage());
}
