<?php
// includes/area_mappings/helpers/AreaMappingUploadHelper.php

class AreaMappingUploadHelper
{
    /**
     * Handle content image upload
     */
    public static function handleUpload()
    {
        $maxBytes = 10 * 1024 * 1024; // 10MB
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid method', 405);
        }

        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            throw new Exception('No image uploaded', 400);
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed (code ' . $file['error'] . ')', 400);
        }
        if ($file['size'] > $maxBytes) {
            throw new Exception('File too large (max 10MB)', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Unsupported file type', 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $destDir = $projectRoot . '/images/signs/';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new Exception('Failed to prepare upload directory', 500);
        }

        $slug = 'sign-' . date('Ymd-His') . '-' . substr(md5(random_bytes(8)), 0, 6);
        $absOriginal = $destDir . $slug . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $absOriginal)) {
            throw new Exception('Failed to save uploaded file', 500);
        }
        @chmod($absOriginal, 0644);

        $finalWebp = null;
        if (class_exists('AIImageProcessor')) {
            $processor = new AIImageProcessor();
            try {
                // Use processBackgroundImage which handles dual format (webp+png)
                $result = $processor->processBackgroundImage($absOriginal, [
                    'createDualFormat' => true,
                    'webp_quality' => 90,
                    'png_compression' => 1,
                    'preserve_transparency' => true,
                    'resizeDimensions' => ['width' => 500, 'height' => 500],
                    'resizeMode' => 'contain'
                ]);
                if ($result && !empty($result['webp_path'])) {
                    $finalWebp = $result['webp_path'];
                } else {
                    $finalWebp = $absOriginal;
                }
                if (!empty($result['png_path']) && file_exists($result['png_path'])) {
                    @chmod($result['png_path'], 0644);
                }
            } catch (Throwable $e) {
                error_log("Sign upload processor error: " . $e->getMessage());
                $finalWebp = $absOriginal;
            }
        } else {
            $finalWebp = $absOriginal;
        }

        // Normalize to site-relative URL
        return '/' . ltrim(str_replace($projectRoot, '', $finalWebp), '/');
    }
}
