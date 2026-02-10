<?php

/**
 * Batch AI Image Analysis Endpoint
 *
 * GET /api/run_image_analysis.php?sku=WF-TS-001
 * - Processes all images for a SKU that have not yet been AI processed
 * - Applies AI edge detection + smart cropping
 * - Converts to dual format (WebP primary + PNG fallback)
 * - Updates database records accordingly
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/ai_image_processor.php';
require_once __DIR__ . '/../includes/helpers/MultiImageUploadHelper.php';

function wf_abs_path_from_rel(string $rootDir, string $relPath): ?string
{
    $relPath = ltrim(trim($relPath), '/');
    if ($relPath === '') {
        return null;
    }
    return $rootDir . '/' . $relPath;
}

function wf_image_has_transparency(string $absPath): bool
{
    if (!file_exists($absPath)) {
        return false;
    }

    $info = @getimagesize($absPath);
    if (!$info || !isset($info[2])) {
        return false;
    }

    $type = (int)$info[2];
    if ($type === IMAGETYPE_JPEG) {
        return false;
    }

    $img = null;
    if ($type === IMAGETYPE_PNG) {
        $img = @imagecreatefrompng($absPath);
    } elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($absPath);
    } elseif ($type === IMAGETYPE_GIF) {
        $img = @imagecreatefromgif($absPath);
        if ($img && imagecolortransparent($img) >= 0) {
            return true;
        }
    }

    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) {
        return false;
    }

    $stepX = max(1, (int) floor($w / 180));
    $stepY = max(1, (int) floor($h / 180));

    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = 0; $x < $w; $x += $stepX) {
            $color = imagecolorat($img, $x, $y);
            $alpha = ($color >> 24) & 0x7F;
            if ($alpha > 0) {
                return true;
            }
        }
    }

    return false;
}

function wf_resolve_best_processing_source(string $rootDir, string $currentRelPath, string $originalRelPath): array
{
    $candidates = [];
    $seen = [];

    $addCandidate = static function (string $reason, ?string $relPath) use (&$candidates, &$seen, $rootDir): void {
        $relPath = ltrim(trim((string) $relPath), '/');
        if ($relPath === '' || isset($seen[$relPath])) {
            return;
        }
        $seen[$relPath] = true;
        $absPath = wf_abs_path_from_rel($rootDir, $relPath);
        if ($absPath && file_exists($absPath)) {
            $candidates[] = [
                'reason' => $reason,
                'rel' => $relPath,
                'abs' => $absPath
            ];
        }
    };

    $addCandidate('original_path', $originalRelPath);
    $addCandidate('current_path', $currentRelPath);

    $pathsForVariants = [$originalRelPath, $currentRelPath];
    foreach ($pathsForVariants as $relPath) {
        $relPath = ltrim(trim((string) $relPath), '/');
        if ($relPath === '') {
            continue;
        }
        $pathInfo = pathinfo($relPath);
        $dir = trim((string)($pathInfo['dirname'] ?? ''), '.');
        $base = (string)($pathInfo['filename'] ?? '');
        if ($base === '') {
            continue;
        }
        $prefix = $dir !== '' ? ($dir . '/') : '';
        $addCandidate('png_variant', $prefix . $base . '.png');
        $addCandidate('webp_variant', $prefix . $base . '.webp');
    }

    // If direct candidates are opaque, try sibling letter variants (e.g. ...A.png vs ...B.webp).
    foreach ($pathsForVariants as $relPath) {
        $relPath = ltrim(trim((string) $relPath), '/');
        if ($relPath === '') {
            continue;
        }
        $pathInfo = pathinfo($relPath);
        $dir = trim((string)($pathInfo['dirname'] ?? ''), '.');
        $base = (string)($pathInfo['filename'] ?? '');
        if (!preg_match('/^(.*)[A-Z]$/', $base, $m)) {
            continue;
        }

        $stem = (string)($m[1] ?? '');
        if ($stem === '') {
            continue;
        }

        $prefix = $dir !== '' ? ($dir . '/') : '';
        $absPattern = $rootDir . '/' . $prefix . $stem . '*';
        $matches = glob($absPattern);
        if (!$matches) {
            continue;
        }

        foreach ($matches as $absMatch) {
            if (!is_file($absMatch)) {
                continue;
            }
            $ext = strtolower(pathinfo($absMatch, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'webp', 'gif', 'jpg', 'jpeg'], true)) {
                continue;
            }
            $rootPrefix = rtrim($rootDir, '/') . '/';
            if (strpos($absMatch, $rootPrefix) !== 0) {
                continue;
            }
            $relMatch = substr($absMatch, strlen($rootPrefix));
            $addCandidate('sibling_variant', $relMatch);
        }
    }

    foreach ($candidates as $candidate) {
        if (wf_image_has_transparency($candidate['abs'])) {
            $candidate['has_transparency'] = true;
            return $candidate;
        }
    }

    if (!empty($candidates)) {
        $candidates[0]['has_transparency'] = false;
        return $candidates[0];
    }

    return [
        'reason' => 'not_found',
        'rel' => '',
        'abs' => '',
        'has_transparency' => false
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::methodNotAllowed();
    }

    $sku = isset($_GET['sku']) ? trim($_GET['sku']) : '';
    $force = isset($_GET['force']) ? ($_GET['force'] === '1' || strtolower($_GET['force']) === 'true') : false;

    if ($sku === '') {
        Response::json(['success' => false, 'error' => 'SKU is required']);
    }

    Database::getInstance();

    // Fetch images for SKU
    $images = Database::queryAll(
        "SELECT id, sku, image_path, is_primary, processed_with_ai, original_path 
         FROM item_images WHERE sku = ? ORDER BY sort_order ASC, id ASC",
        [$sku]
    );

    if (!$images) {
        Response::json(['success' => false, 'error' => 'No images found for SKU']);
    }

    $rootDir = dirname(__DIR__);
    $results = [];
    $processedCount = 0;
    $skippedCount = 0;
    $errors = [];

    foreach ($images as $img) {
        $id = (int)$img['id'];
        $relPath = $img['image_path'];
        $isPrimary = (int)$img['is_primary'] === 1;
        $alreadyProcessed = (int)$img['processed_with_ai'] === 1;

        if ($alreadyProcessed && !$force) {
            $skippedCount++;
            $results[] = [
                'id' => $id,
                'path' => $relPath,
                'status' => 'skipped',
                'reason' => 'already_processed'
            ];
            continue;
        }

        $originalRelPath = (string)($img['original_path'] ?? '');
        $source = wf_resolve_best_processing_source($rootDir, $relPath, $originalRelPath);
        $sourceAbsPath = (string)($source['abs'] ?? '');
        $sourceRelPath = (string)($source['rel'] ?? '');

        if ($sourceAbsPath === '' || !file_exists($sourceAbsPath)) {
            $errors[] = "File not found: {$relPath}";
            $results[] = [
                'id' => $id,
                'path' => $relPath,
                'status' => 'error',
                'error' => 'file_not_found'
            ];
            continue;
        }

        try {
            // Reuse the same crop/compress function used by upload processing.
            $formatResult = MultiImageUploadHelper::processImageAtPathForDualFormat($sourceAbsPath, $rootDir, true);
            if (!$formatResult['success']) {
                throw new Exception('Dual format conversion failed');
            }

            $webpAbs = $formatResult['webp_path'] ?? null;
            $pngAbs = $formatResult['png_path'] ?? null;
            if (!$webpAbs) {
                throw new Exception('WebP output path missing');
            }

            // Ensure permissions and relative paths
            if ($webpAbs && file_exists($webpAbs)) {
                chmod($webpAbs, 0644);
            }
            if ($pngAbs && file_exists($pngAbs)) {
                chmod($pngAbs, 0644);
            }

            $relWebp = str_replace($rootDir . '/', '', $webpAbs);
            $relPng = $pngAbs ? str_replace($rootDir . '/', '', $pngAbs) : null;

            // 3) Update DB: item_images
            Database::execute(
                "UPDATE item_images
                 SET image_path = ?, processed_with_ai = 1, original_path = COALESCE(original_path, ?), processing_date = NOW()
                 WHERE id = ?",
                [$relWebp, $sourceRelPath !== '' ? $sourceRelPath : $relPath, $id]
            );

            // 4) If primary, sync items.image_url
            if ($isPrimary) {
                Database::execute("UPDATE items SET image_url = ? WHERE sku = ?", [$relWebp, $sku]);
            }

            $processedCount++;
            $results[] = [
                'id' => $id,
                'old_path' => $relPath,
                'new_webp' => $relWebp,
                'new_png' => $relPng,
                'source_path' => $sourceRelPath,
                'source_reason' => $source['reason'] ?? null,
                'source_transparency' => (bool)($source['has_transparency'] ?? false),
                'status' => 'processed',
                'ai' => null,
                'steps' => ['Processed via shared upload/image pipeline']
            ];
        } catch (Throwable $e) {
            error_log("AI batch processing failed for {$relPath}: " . $e->getMessage());
            $errors[] = $e->getMessage();
            $results[] = [
                'id' => $id,
                'path' => $relPath,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    $response = [
        'success' => true,
        'sku' => $sku,
        'processed' => $processedCount,
        'skipped' => $skippedCount,
        'errors' => $errors,
        'results' => $results
    ];

    Response::json($response);
} catch (PDOException $e) {
    error_log('Database error in run_image_analysis: ' . $e->getMessage());
    Response::serverError('Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    error_log('Error in run_image_analysis: ' . $e->getMessage());
    Response::serverError($e->getMessage());
}
