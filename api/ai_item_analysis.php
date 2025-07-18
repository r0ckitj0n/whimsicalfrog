<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';
require_once 'ai_providers.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check session authentication first
require_once __DIR__ . '/../includes/auth.php';
if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

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
        throw new Exception('Only POST method allowed');
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
    $stmt = $pdo->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
    $existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

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

    echo json_encode([
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

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateSkuForCategory($pdo, $category)
{
    // Get category code
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General
    }

    // Find next number for this category
    $stmt = $pdo->prepare("SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1");
    $stmt->execute(["WF-{$categoryCode}-%"]);
    $lastSku = $stmt->fetchColumn();

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