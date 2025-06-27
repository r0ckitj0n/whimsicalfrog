<?php
// Simple endpoint to return the next SKU for a given category.
// Usage: /api/next_sku.php?cat=Tumblers

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Use centralized authentication
requireAdmin();

try {
    // Validate required parameter
    $category = $_GET['cat'] ?? '';
    if (empty($category)) {
        Response::error('Category parameter is required');
    }
    
    // Log the request for audit trail
    Logger::userAction('generate_sku', ['category' => $category]);
    
    $newSku = generateSkuForCategory($category);
    
    // Log successful generation
    Logger::info('SKU generated successfully', [
        'category' => $category,
        'sku' => $newSku
    ]);
    
    Response::success([
        'sku' => $newSku,
        'category' => $category
    ]);
    
} catch (Exception $e) {
    Logger::exception($e, 'Failed to generate SKU');
    Response::serverError('Failed to generate SKU: ' . $e->getMessage());
}

function generateSkuForCategory($category) {
    // Get category code - first 2 letters of category, uppercase
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category), 0, 2));
    if (strlen($categoryCode) < 2) {
        $categoryCode = 'GN'; // General fallback
    }
    
    // Find the highest existing number for this category using centralized database
    $lastSku = Database::queryRow(
        "SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1",
        ["WF-{$categoryCode}-%"]
    );
    
    $nextNum = 1;
    if ($lastSku && $lastSku['sku']) {
        $parts = explode('-', $lastSku['sku']);
        if (count($parts) >= 3 && is_numeric($parts[2])) {
            $nextNum = intval($parts[2]) + 1;
        }
    }
    
    return sprintf('WF-%s-%03d', $categoryCode, $nextNum);
} 