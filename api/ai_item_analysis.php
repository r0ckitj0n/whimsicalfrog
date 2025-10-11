<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/config.php';
require_once 'ai_providers.php';

// Centralized admin check
AuthHelper::requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed();
    }

    // Check if image was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image uploaded or upload error occurred');
    }

    $uploadedFile = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    if (!in_array($uploadedFile['type'], $allowedTypes)) {
        throw new Exception('Invalid image type. Only JPG, PNG, and WebP are allowed.');
    }

    // Create temporary file path
    $tempPath = sys_get_temp_dir() . '/ai_analysis_' . uniqid() . '.' . pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);

    if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
        throw new Exception('Failed to save uploaded image');
    }

    // Get existing categories for context
    $rows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $existingCategories = array_map(function ($r) { return array_values($r)[0]; }, $rows);

    // Initialize AI providers
    $aiProviders = new AIProviders();

    // Analyze the image and generate item details
    $analysisResult = $aiProviders->analyzeItemImage($tempPath, $existingCategories);

    // Clean up temporary file
    unlink($tempPath);

    if (!$analysisResult) {
        throw new Exception('AI analysis failed to generate results');
    }

    // Generate SKU based on suggested category
    $suggestedCategory = $analysisResult['category'] ?? 'General';
    $newSku = generateSkuForCategory($pdo, $suggestedCategory);

    Response::json([
        'success' => true,
        'analysis' => [
            'category' => $analysisResult['category'] ?? 'General',
            'title' => $analysisResult['title'] ?? 'New Item',
            'description' => $analysisResult['description'] ?? '',
            'suggested_sku' => $newSku,
            'confidence' => $analysisResult['confidence'] ?? 'medium',
            'reasoning' => $analysisResult['reasoning'] ?? 'AI analysis completed'
        ]
    ]);

} catch (Exception $e) {
    // Clean up temp file if it exists
    if (isset($tempPath) && file_exists($tempPath)) {
        unlink($tempPath);
    }

    Response::serverError($e->getMessage());
}

function generateSkuForCategory($pdo, $category)
{
    // Get category code
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General
    }

    // Find next number for this category
    $row = Database::queryOne("SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1", ["WF-{$categoryCode}-%"]);
    $lastSku = $row ? $row['sku'] : null;

    $nextNum = 1;
    if ($lastSku) {
        $parts = explode('-', $lastSku);
        if (count($parts) >= 3) {
            $nextNum = intval($parts[2]) + 1;
        }
    }

    return sprintf('WF-%s-%03d', $categoryCode, $nextNum);
}
?> 