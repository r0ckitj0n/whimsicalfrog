<?php
// Simple endpoint to return the next SKU for a given category.
// Usage: /api/next_sku.php?cat=Tumblers

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure user is logged in and is an Admin
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = false;

if ($isLoggedIn) {
    $userData = $_SESSION['user'];
    // Handle both string and array formats
    if (is_string($userData)) {
        $userData = json_decode($userData, true);
    }
    if (is_array($userData)) {
        $isAdmin = isset($userData['role']) && strtolower($userData['role']) === 'admin';
    }
}

if (!$isLoggedIn || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $category = $_GET['cat'] ?? '';
    if (empty($category)) {
        throw new Exception('Category parameter is required');
    }
    
    $newSku = generateSkuForCategory($pdo, $category);
    
    echo json_encode([
        'success' => true,
        'sku' => $newSku,
        'category' => $category
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateSkuForCategory($pdo, $category) {
    // Get category code - first 2 letters of category, uppercase
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General fallback
    }
    
    // Find the highest existing number for this category
    $stmt = $pdo->prepare("SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1");
    $stmt->execute(["WF-{$categoryCode}-%"]);
    $lastSku = $stmt->fetchColumn();
    
    $nextNum = 1;
    if ($lastSku) {
        $parts = explode('-', $lastSku);
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $nextNum = intval($parts[2]) + 1;
        }
    }
    
    return sprintf('WF-%s-%03d', $categoryCode, $nextNum);
} 