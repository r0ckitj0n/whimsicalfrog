<?php
/**
 * Process a single image with AI cropping and optional WebP conversion
 * Expected POST JSON:
 * { action: 'process_image', imagePath: 'images/items/abc.webp', sku?: 'SKU', options?: {...} }
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
    $imagePath = $input['imagePath'] ?? '';
    $options = $input['options'] ?? [];

    if ($action !== 'process_image') {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }

    if (!$imagePath) {
        echo json_encode(['success' => false, 'error' => 'imagePath is required']);
        exit;
    }

    $absPath = realpath(__DIR__ . '/../' . ltrim($imagePath, '/'));
    if (!$absPath || !file_exists($absPath)) {
        echo json_encode(['success' => false, 'error' => 'Image file not found']);
        exit;
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

    echo json_encode($result);
} catch (Throwable $e) {
    error_log('process_image_ai error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
