<?php

/**
 * Process a single image with AI cropping and optional WebP conversion
 * Expected POST JSON:
 * { action: 'process_image', imagePath: 'images/items/abc.webp', sku?: 'SKU', options?: {...} }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
    $imagePath = $input['imagePath'] ?? '';
    $options = $input['options'] ?? [];

    if ($action !== 'process_image') {
        Response::json(['success' => false, 'error' => 'Invalid action']);
    }

    if (!$imagePath) {
        Response::json(['success' => false, 'error' => 'imagePath is required']);
    }

    $absPath = realpath(__DIR__ . '/../' . ltrim($imagePath, '/'));
    if (!$absPath || !file_exists($absPath)) {
        Response::json(['success' => false, 'error' => 'Image file not found']);
    }

    require_once __DIR__ . '/ai_image_processor.php';
    $processor = new AIImageProcessor();

    // Provide safe defaults
    $defaults = [
        'convertToWebP' => true,
        'quality' => 90,
        'preserveTransparency' => true,
        'useAI' => true,
        'fallbackTrimPercent' => 0.05
    ];
    $opts = array_merge($defaults, is_array($options) ? $options : []);

    $result = $processor->processImage($absPath, $opts);

    // Normalize processed path to relative if present
    if (!empty($result['processed_path'])) {
        $processedAbs = $result['processed_path'];
        // If processor returned absolute path, make it relative to web root
        if (strpos($processedAbs, __DIR__ . '/../') === 0) {
            $result['processed_path'] = substr($processedAbs, strlen(__DIR__ . '/../'));
        }
    }

    Response::json($result);
} catch (Throwable $e) {
    error_log('process_image_ai error: ' . $e->getMessage());
    Response::serverError('Server error');
}
