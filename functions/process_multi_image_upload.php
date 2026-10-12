<?php
/**
 * Multi-Image Upload Processor
 */

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/ai_image_processor.php';
require_once dirname(__DIR__) . '/includes/helpers/MultiImageUploadHelper.php';
require_once dirname(__DIR__) . '/includes/helpers/HeicImageHelper.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';
require_once dirname(__DIR__) . '/includes/response.php';

@ini_set('display_errors', '0');
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

AuthHelper::requireAdmin(401, 'Admin authentication required to upload item images');

function wf_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds server upload size limit (' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file received',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary upload directory',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write uploaded file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension',
        default => 'Unknown upload error code ' . $code,
    };
}

try {
    Database::getInstance();
    $sku = trim((string) ($_POST['sku'] ?? ''));
    $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true';
    $altText = $_POST['altText'] ?? '';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
    $useAI = (($_POST['useAIProcessing'] ?? 'true') === 'true');

    if ($sku === '') {
        echo json_encode(['success' => false, 'error' => 'SKU required']);
        exit;
    }
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax = ini_get('post_max_size');
        $hint = $contentLen > 0
            ? " No files received (request was {$contentLen} bytes). Check post_max_size={$postMax}."
            : '';
        echo json_encode(['success' => false, 'error' => 'No images uploaded.' . $hint]);
        exit;
    }

    // Ensure add-mode image-first uploads work even before full item details are saved.
    $itemExists = Database::queryOne('SELECT sku FROM items WHERE sku = ? LIMIT 1', [$sku]);
    if (!$itemExists) {
        Database::execute(
            "INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status)
             VALUES (?, ?, ?, 0, 5, 0, 0, '', ?)",
            [$sku, $sku, 'General', 'draft']
        );
    }

    $projectRoot = dirname(__DIR__);
    $itemsDir = $projectRoot . '/images/items/';
    if (!is_dir($itemsDir) && !mkdir($itemsDir, 0755, true) && !is_dir($itemsDir)) {
        echo json_encode(['success' => false, 'error' => 'Cannot create images/items directory']);
        exit;
    }
    if (!is_writable($itemsDir)) {
        echo json_encode(['success' => false, 'error' => 'images/items directory is not writable']);
        exit;
    }

    if ($isPrimary) {
        Database::execute('UPDATE item_images SET is_primary = 0 WHERE sku = ?', [$sku]);
    }

    $existing = Database::queryAll('SELECT image_path FROM item_images WHERE sku = ?', [$sku]);
    $usedSuffixes = [];
    foreach ($existing as $r) {
        if (preg_match('/\/' . preg_quote($sku, '/') . '([A-Z])\./', $r['image_path'], $m)) {
            $usedSuffixes[] = $m[1];
        }
    }

    $sortOrder = (int) (Database::queryOne('SELECT MAX(sort_order) AS max_sort FROM item_images WHERE sku = ?', [$sku])['max_sort'] ?? -1) + 1;
    $uploadedImages = [];
    $errors = [];
    $fileCount = is_array($_FILES['images']['name'] ?? null) ? count($_FILES['images']['name']) : 0;

    for ($i = 0; $i < $fileCount; $i++) {
        $errCode = (int) ($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        $originalName = (string) ($_FILES['images']['name'][$i] ?? ('file' . ($i + 1)));
        if ($errCode !== UPLOAD_ERR_OK) {
            $errors[] = "{$originalName}: " . wf_upload_error_message($errCode);
            continue;
        }

        $tmpName = (string) ($_FILES['images']['tmp_name'][$i] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $errors[] = "{$originalName}: temporary upload missing";
            continue;
        }
        $tmpSize = (int) @filesize($tmpName);
        if ($tmpSize <= 0) {
            $errors[] = "{$originalName}: uploaded file was empty";
            continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($tmpName);
        }
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'];
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = "{$originalName}: unsupported type .{$ext} (use PNG/JPG/WebP/GIF/HEIC)";
            continue;
        }

        $suffix = MultiImageUploadHelper::getNextSuffix($sku, $usedSuffixes);
        if (!$suffix) {
            $errors[] = 'Max 26 images reached for this SKU';
            break;
        }

        // Normalize HEIC to JPEG before dual-format conversion.
        $uploadExt = $ext;
        $sourceTmp = $tmpName;
        $heicTemp = null;
        if (HeicImageHelper::isHeicUpload($originalName, $mime)) {
            $heicTemp = $itemsDir . $sku . $suffix . '_heic_tmp.jpg';
            $converted = HeicImageHelper::convertToJpeg($tmpName, $heicTemp);
            if ($converted === null) {
                $errors[] = "{$originalName}: HEIC conversion failed on server";
                continue;
            }
            $sourceTmp = $converted;
            $uploadExt = 'jpg';
        }

        $filename = $sku . $suffix . '.' . $uploadExt;
        $absPath = $itemsDir . $filename;
        if ($overwrite && file_exists($absPath)) {
            @unlink($absPath);
        }

        $moved = ($sourceTmp === $tmpName)
            ? move_uploaded_file($tmpName, $absPath)
            : @rename($sourceTmp, $absPath) || @copy($sourceTmp, $absPath);

        if ($heicTemp && is_file($heicTemp)) {
            @unlink($heicTemp);
        }

        if (!$moved || !is_file($absPath) || (int) filesize($absPath) <= 0) {
            @unlink($absPath);
            $errors[] = "{$originalName}: failed to save uploaded file to disk";
            continue;
        }
        @chmod($absPath, 0644);

        $res = MultiImageUploadHelper::processImageForDualFormat($absPath, $sku, $suffix, $itemsDir, $projectRoot, $useAI);
        if (!$res['success']) {
            @unlink($absPath);
            $errors[] = "{$originalName}: failed to convert to PNG/WebP (file may be corrupt or unsupported)";
            continue;
        }

        $finalPath = $res['path'];
        $finalAbs = $projectRoot . '/' . ltrim($finalPath, '/');
        if (!is_file($finalAbs) || (int) filesize($finalAbs) <= 0) {
            @unlink($absPath);
            @unlink($finalAbs);
            $errors[] = "{$originalName}: converted image was empty and was not saved";
            continue;
        }

        // Prefer WebP DB path; keep original only when it differs.
        if (is_file($absPath) && realpath($absPath) !== realpath($finalAbs)) {
            // Keep JPEG/PNG source for AI analysis when present; remove only if duplicate basename conflict.
            $keepSource = in_array(strtolower(pathinfo($absPath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
            if (!$keepSource) {
                @unlink($absPath);
            }
        }

        $isThisPrimary = ($isPrimary && $i === 0) ? 1 : 0;
        Database::execute(
            'INSERT INTO item_images (sku, image_path, is_primary, alt_text, sort_order, processed_with_ai, original_path, processing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $sku,
                $finalPath,
                $isThisPrimary,
                $altText ?: $originalName,
                $sortOrder++,
                0,
                null,
                null,
            ]
        );

        $uploadedImages[] = ['filename' => basename($finalPath), 'path' => $finalPath, 'isPrimary' => $isThisPrimary === 1];
        if ($isThisPrimary) {
            Database::execute('UPDATE items SET image_url = ? WHERE sku = ?', [$finalPath, $sku]);
        }
    }

    if (!empty($uploadedImages)) {
        if (!Database::queryOne('SELECT id FROM item_images WHERE sku = ? AND is_primary = 1 LIMIT 1', [$sku])) {
            Database::execute('UPDATE item_images SET is_primary = 1 WHERE sku = ? AND image_path = ?', [$sku, $uploadedImages[0]['path']]);
            Database::execute('UPDATE items SET image_url = ? WHERE sku = ?', [$uploadedImages[0]['path'], $sku]);
            $uploadedImages[0]['isPrimary'] = true;
        }
    }

    if (empty($uploadedImages)) {
        echo json_encode([
            'success' => false,
            'error' => !empty($errors) ? implode(' | ', $errors) : 'No images were saved',
            'uploadedImages' => [],
            'warnings' => $errors,
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'uploadedImages' => $uploadedImages,
            'warnings' => $errors,
            'message' => count($uploadedImages) . ' image(s) saved',
        ]);
    }
} catch (Throwable $e) {
    error_log('process_multi_image_upload: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
