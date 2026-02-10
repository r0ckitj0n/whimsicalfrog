<?php
/**
 * Multi-Image Upload Processor
 */

require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/api/ai_image_processor.php';
require_once dirname(__DIR__) . '/includes/helpers/MultiImageUploadHelper.php';

@ini_set('display_errors', '0');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    Database::getInstance();
    $sku = $_POST['sku'] ?? '';
    $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true';
    $altText = $_POST['altText'] ?? '';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
    $useAI = (($_POST['useAIProcessing'] ?? 'true') === 'true');

    if (empty($sku)) { echo json_encode(['success' => false, 'error' => 'SKU required']); exit; }
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) { echo json_encode(['success' => false, 'error' => 'No images']); exit; }

    // Ensure add-mode image-first uploads work even before full item details are saved.
    $itemExists = Database::queryOne("SELECT sku FROM items WHERE sku = ? LIMIT 1", [$sku]);
    if (!$itemExists) {
        Database::execute(
            "INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, status)
             VALUES (?, ?, ?, 0, 5, 0, 0, '', ?)",
            [$sku, $sku, 'General', 'draft']
        );
    }

    $projectRoot = dirname(__DIR__);
    $itemsDir = $projectRoot . '/images/items/';
    if (!is_dir($itemsDir)) mkdir($itemsDir, 0755, true);

    if ($isPrimary) Database::execute("UPDATE item_images SET is_primary = 0 WHERE sku = ?", [$sku]);

    $existing = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ?", [$sku]);
    $usedSuffixes = [];
    foreach ($existing as $r) {
        if (preg_match('/\/' . preg_quote($sku) . '([A-Z])\./', $r['image_path'], $m)) $usedSuffixes[] = $m[1];
    }

    $sortOrder = (int)(Database::queryOne("SELECT MAX(sort_order) AS max_sort FROM item_images WHERE sku = ?", [$sku])['max_sort'] ?? -1) + 1;
    $uploadedImages = []; $errors = [];

    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for file " . ($i + 1); continue;
        }

        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
        $suffix = MultiImageUploadHelper::getNextSuffix($sku, $usedSuffixes);
        if (!$suffix) { $errors[] = "Max 26 images reached"; break; }

        $filename = $sku . $suffix . '.' . $ext;
        $absPath = $itemsDir . $filename;
        if ($overwrite && file_exists($absPath)) unlink($absPath);

        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $absPath)) {
            chmod($absPath, 0644);
            $finalPath = 'images/items/' . $filename;
            $aiProcessed = false;
            $isJpegSource = in_array($ext, ['jpg', 'jpeg', 'jfif'], true);

            if ($useAI) {
                $res = MultiImageUploadHelper::processImageWithAI($absPath, $sku, $suffix, $itemsDir, $projectRoot);
                if ($res['success']) {
                    $finalPath = $res['path'];
                    $aiProcessed = true;
                } else if ($isJpegSource) {
                    @unlink($absPath);
                    $errors[] = "Failed to convert JPEG source to PNG/WebP for file " . ($i + 1);
                    continue;
                }
            } else {
                $res = MultiImageUploadHelper::convertToDualFormatOnly($absPath, $sku, $suffix, $itemsDir, $projectRoot);
                if ($res['success']) {
                    $finalPath = $res['path'];
                } else if ($isJpegSource) {
                    @unlink($absPath);
                    $errors[] = "Failed to convert JPEG source to PNG/WebP for file " . ($i + 1);
                    continue;
                }
            }

            $isThisPrimary = ($isPrimary && $i === 0) ? 1 : 0;
            Database::execute("INSERT INTO item_images (sku, image_path, is_primary, alt_text, sort_order, processed_with_ai, original_path, processing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$sku, $finalPath, $isThisPrimary, $altText ?: $_FILES['images']['name'][$i], $sortOrder++, $aiProcessed ? 1 : 0, $aiProcessed ? 'images/items/'.$filename : null, $aiProcessed ? date('Y-m-d H:i:s') : null]);

            $uploadedImages[] = ['filename' => $filename, 'path' => $finalPath, 'isPrimary' => $isThisPrimary == 1];
            if ($isThisPrimary) Database::execute("UPDATE items SET image_url = ? WHERE sku = ?", [$finalPath, $sku]);
        }
    }

    if (!empty($uploadedImages)) {
        if (!Database::queryOne("SELECT id FROM item_images WHERE sku = ? AND is_primary = 1 LIMIT 1", [$sku])) {
            Database::execute("UPDATE item_images SET is_primary = 1 WHERE sku = ? AND image_path = ?", [$sku, $uploadedImages[0]['path']]);
            Database::execute("UPDATE items SET image_url = ? WHERE sku = ?", [$uploadedImages[0]['path'], $sku]);
            $uploadedImages[0]['isPrimary'] = true;
        }
    }

    echo json_encode(['success' => true, 'uploadedImages' => $uploadedImages, 'warnings' => $errors]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
