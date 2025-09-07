<?php
/**
 * Multi-Image Upload Processor
 *
 * Handles multiple image uploads per item with:
 * - Images named after item SKU (WF-TS-001A.jpg, WF-TS-001B.jpg, WF-TS-001C.jpg, etc.)
 * - Primary image designation
 * - Overwrite existing images option
 * - Support for multiple formats
 */

require_once __DIR__ . '/api/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $sku = $_POST['sku'] ?? '';
    $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true';
    $altText = $_POST['altText'] ?? '';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
    $useAIProcessing = isset($_POST['useAIProcessing']) && $_POST['useAIProcessing'] === 'true';

    if (empty($sku)) {
        echo json_encode(['success' => false, 'error' => 'SKU is required']);
        exit;
    }

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        echo json_encode(['success' => false, 'error' => 'No images uploaded']);
        exit;
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $uploadedImages = [];
    $errors = [];

    // Ensure items directory exists
    $itemsDir = __DIR__ . '/images/items/';
    if (!is_dir($itemsDir)) {
        mkdir($itemsDir, 0755, true);
    }

    // If this is marked as primary, unset any existing primary images for this item
    if ($isPrimary) {
        Database::execute("UPDATE item_images SET is_primary = 0 WHERE sku = ?", [$sku]);
    }

    // Get existing image paths to determine what letter suffixes are already used
    $rows = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ?", [$sku]);
    $existingPaths = array_map(function($r){ return $r['image_path']; }, $rows);

    // Extract used letter suffixes
    $usedSuffixes = [];
    foreach ($existingPaths as $path) {
        if (preg_match('/\/' . preg_quote($sku) . '([A-Z])\./', $path, $matches)) {
            $usedSuffixes[] = $matches[1];
        }
    }

    // Process each uploaded file
    $fileCount = count($_FILES['images']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for file " . ($i + 1);
            continue;
        }

        $originalName = $_FILES['images']['name'][$i];
        $tmpPath = $_FILES['images']['tmp_name'][$i];
        $fileSize = $_FILES['images']['size'][$i];

        // Validate file extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $errors[] = "Unsupported file type: $originalName";
            continue;
        }

        // Validate file size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "File too large: $originalName (max 10MB allowed)";
            continue;
        }

        // Find next available letter suffix
        $suffix = null;
        for ($letterIndex = 0; $letterIndex < 26; $letterIndex++) {
            $testSuffix = chr(65 + $letterIndex); // 65 is ASCII for 'A'
            if (!in_array($testSuffix, $usedSuffixes)) {
                $suffix = $testSuffix;
                $usedSuffixes[] = $suffix; // Mark this suffix as used for subsequent files in this batch
                break;
            }
        }

        if ($suffix === null) {
            $errors[] = "Too many images for item $sku (max 26)";
            continue;
        }

        $filename = $sku . $suffix . '.' . $ext;

        $relPath = 'images/items/' . $filename;
        $absPath = $itemsDir . $filename;

        // If overwriting, remove existing file
        if ($overwrite && file_exists($absPath)) {
            unlink($absPath);
        }

        // Move uploaded file
        if (move_uploaded_file($tmpPath, $absPath)) {
            chmod($absPath, 0644);

            $finalPath = $relPath;
            $aiProcessed = false;

            // Apply AI processing and dual format conversion if requested
            if ($useAIProcessing) {
                try {
                    require_once __DIR__ . '/api/ai_image_processor.php';
                    $processor = new AIImageProcessor();

                    // First apply AI processing with edge detection
                    $processingOptions = [
                        'convertToWebP' => false, // Don't convert yet, we'll do dual format next
                        'quality' => 90,
                        'preserveTransparency' => true,
                        'useAI' => true,
                        'fallbackTrimPercent' => 0.05
                    ];

                    $aiResult = $processor->processImage($absPath, $processingOptions);
                    $processedImagePath = $aiResult['success'] ? $aiResult['processed_path'] : $absPath;

                    // Now create dual format (PNG + WebP) for browser compatibility
                    $dualFormatOptions = [
                        'webp_quality' => 90,
                        'png_compression' => 1,
                        'preserve_transparency' => true,
                        'force_png' => true
                    ];

                    $formatResult = $processor->convertToDualFormat($processedImagePath, $dualFormatOptions);

                    if ($formatResult['success']) {
                        // Use WebP as primary, but ensure PNG exists for fallback
                        $finalPath = str_replace(__DIR__ . '/', '', $formatResult['webp_path']);
                        $aiProcessed = true;

                        // Create PNG filename for fallback
                        $pngFilename = $sku . $suffix . '.png';
                        $pngPath = $itemsDir . $pngFilename;

                        if ($formatResult['png_path'] && file_exists($formatResult['png_path'])) {
                            copy($formatResult['png_path'], $pngPath);
                            chmod($pngPath, 0644);
                        }

                        // Clean up temporary files
                        if ($processedImagePath !== $absPath && file_exists($processedImagePath)) {
                            unlink($processedImagePath);
                        }
                    } else {
                        error_log("Dual format conversion failed for {$filename}");
                    }

                } catch (Exception $e) {
                    error_log("AI processing failed for {$filename}: " . $e->getMessage());
                    // Continue with original image if AI processing fails
                }
            } else {
                // Even without AI processing, create dual format for browser compatibility
                try {
                    require_once __DIR__ . '/api/ai_image_processor.php';
                    $processor = new AIImageProcessor();

                    $dualFormatOptions = [
                        'webp_quality' => 90,
                        'png_compression' => 1,
                        'preserve_transparency' => true,
                        'force_png' => true
                    ];

                    $formatResult = $processor->convertToDualFormat($absPath, $dualFormatOptions);

                    if ($formatResult['success']) {
                        $finalPath = str_replace(__DIR__ . '/', '', $formatResult['webp_path']);

                        // Create PNG filename for fallback
                        $pngFilename = $sku . $suffix . '.png';
                        $pngPath = $itemsDir . $pngFilename;

                        if ($formatResult['png_path'] && file_exists($formatResult['png_path'])) {
                            copy($formatResult['png_path'], $pngPath);
                            chmod($pngPath, 0644);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Dual format conversion failed for {$filename}: " . $e->getMessage());
                    // Continue with original image
                }
            }

            // Determine if this should be primary
            $isThisPrimary = ($isPrimary && $i === 0) ? 1 : 0; // Only first image can be primary if multiple uploaded

            // Insert into database (restored complete parameter list)
            Database::execute(
                "INSERT INTO item_images (sku, image_path, is_primary, alt_text, sort_order, processed_with_ai, original_path, processing_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $sku,
                    $finalPath,
                    $isThisPrimary,
                    $altText ?: $originalName,
                    $sortOrder,
                    $aiProcessed ? 1 : 0,
                    $aiProcessed ? $relPath : null,
                    $aiProcessed ? date('Y-m-d H:i:s') : null
                ]
            );

            $uploadedImages[] = [
                'filename' => $filename,
                'path' => $finalPath,
                'isPrimary' => $isThisPrimary == 1,
                'sortOrder' => $sortOrder,
                'aiProcessed' => $aiProcessed
            ];

            // Update items table with primary image
            if ($isThisPrimary) {
                Database::execute("UPDATE items SET imageUrl = ? WHERE sku = ?", [$finalPath, $sku]);
            }

        } else {
            $errors[] = "Failed to save file: $originalName";
        }
    }

    // If no primary image exists for this item, make the first uploaded image primary
    if (!empty($uploadedImages)) {
        $rowCnt = Database::queryOne("SELECT COUNT(*) AS c FROM item_images WHERE sku = ? AND is_primary = 1", [$sku]);
        $hasPrimary = $rowCnt ? ((int)$rowCnt['c'] > 0) : false;

        if (!$hasPrimary && !empty($uploadedImages)) {
            $firstImage = $uploadedImages[0];
            Database::execute("UPDATE item_images SET is_primary = 1 WHERE sku = ? AND image_path = ?", [$sku, $firstImage['path']]);

            // Update items table
            Database::execute("UPDATE items SET imageUrl = ? WHERE sku = ?", [$firstImage['path'], $sku]);

            $uploadedImages[0]['isPrimary'] = true;
        }
    }

    $response = [
        'success' => true,
        'message' => count($uploadedImages) . ' image(s) uploaded successfully',
        'uploadedImages' => $uploadedImages
    ];

    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Database error in multi-image upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in multi-image upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
}
?> 