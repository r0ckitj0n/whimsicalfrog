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
require_once __DIR__ . '/ai_image_processor.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $sku = isset($_GET['sku']) ? trim($_GET['sku']) : '';
    $force = isset($_GET['force']) ? ($_GET['force'] === '1' || strtolower($_GET['force']) === 'true') : false;

    if ($sku === '') {
        echo json_encode(['success' => false, 'error' => 'SKU is required']);
        exit;
    }

    Database::getInstance();

    // Fetch images for SKU
    $images = Database::queryAll(
        "SELECT id, sku, image_path, is_primary, processed_with_ai, original_path 
         FROM item_images WHERE sku = ? ORDER BY sort_order ASC, id ASC",
        [$sku]
    );

    if (!$images) {
        echo json_encode(['success' => false, 'error' => 'No images found for SKU']);
        exit;
    }

    $rootDir = dirname(__DIR__);
    $itemsDir = $rootDir . '/images/items/';

    $processor = new AIImageProcessor();

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

        $absPath = $rootDir . '/' . ltrim($relPath, '/');
        if (!file_exists($absPath)) {
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
            // 1) AI analysis + smart cropping (overwrites original path)
            $processingOptions = [
                'convertToWebP' => false,
                'quality' => 90,
                'preserveTransparency' => true,
                'useAI' => true,
                'fallbackTrimPercent' => 0.05
            ];

            $aiResult = $processor->processImage($absPath, $processingOptions);
            $processedImagePath = $aiResult['success'] ? $aiResult['processed_path'] : $absPath;

            // 2) Dual format conversion (WebP + PNG)
            $dualFormatOptions = [
                'webp_quality' => 90,
                'png_compression' => 1,
                'preserve_transparency' => true,
                'force_png' => true
            ];

            $formatResult = $processor->convertToDualFormat($processedImagePath, $dualFormatOptions);
            if (!$formatResult['success']) {
                throw new Exception('Dual format conversion failed');
            }

            $webpAbs = $formatResult['webp_path'];
            $pngAbs = $formatResult['png_path'];

            // Ensure permissions and relative paths
            if ($webpAbs && file_exists($webpAbs)) chmod($webpAbs, 0644);
            if ($pngAbs && file_exists($pngAbs)) chmod($pngAbs, 0644);

            $relWebp = str_replace($rootDir . '/', '', $webpAbs);
            $relPng = $pngAbs ? str_replace($rootDir . '/', '', $pngAbs) : null;

            // Optional: ensure PNG sits in items dir with standardized name (already produced by converter)
            if ($pngAbs && strpos($pngAbs, $itemsDir) !== 0) {
                // Move PNG into items dir if converter placed elsewhere (unlikely)
                $pngInfo = pathinfo($pngAbs);
                $stdPngAbs = $itemsDir . $pngInfo['basename'];
                if (@copy($pngAbs, $stdPngAbs)) {
                    @chmod($stdPngAbs, 0644);
                    $relPng = str_replace($rootDir . '/', '', $stdPngAbs);
                }
            }

            // 3) Update DB: item_images
            Database::execute(
                "UPDATE item_images
                 SET image_path = ?, processed_with_ai = 1, original_path = COALESCE(original_path, ?), processing_date = NOW()
                 WHERE id = ?",
                [$relWebp, $relPath, $id]
            );

            // 4) If primary, sync items.imageUrl
            if ($isPrimary) {
                Database::execute("UPDATE items SET imageUrl = ? WHERE sku = ?", [$relWebp, $sku]);
            }

            $processedCount++;
            $results[] = [
                'id' => $id,
                'old_path' => $relPath,
                'new_webp' => $relWebp,
                'new_png' => $relPng,
                'status' => 'processed',
                'ai' => $aiResult['ai_analysis'] ?? null,
                'steps' => $aiResult['processing_steps'] ?? []
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

    echo json_encode($response);
} catch (PDOException $e) {
    error_log('Database error in run_image_analysis: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in run_image_analysis: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
