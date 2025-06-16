<?php
// Handle AJAX requests for category create/delete
require_once __DIR__ . '/api/config.php';
session_start();
header('Content-Type: application/json');

// Only admin allowed
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'Admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action   = $input['action']   ?? '';
$category = trim($input['category'] ?? '');
$newCategory = trim($input['newCategory'] ?? '');

if (!$category) {
    echo json_encode(['error' => 'Category required']);
    exit;
}

if ($action === 'rename' && !$newCategory) {
    echo json_encode(['error' => 'New category name required']);
    exit;
}

// Function to generate category code for naming scheme
function cat_code($cat) {
    $map = [
        'T-Shirts' => 'TS',
        'Tumblers' => 'TU', 
        'Artwork' => 'AR',
        'Sublimation' => 'SU',
        'WindowWraps' => 'WW'
    ];
    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 2));
}

// Function to update naming scheme documentation
function updateNamingScheme($pdo) {
    try {
        // Get all current categories
        $stmt = $pdo->query("SELECT DISTINCT productType FROM products WHERE productType IS NOT NULL ORDER BY productType");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Generate updated category mapping
        $categoryMappings = [];
        foreach ($categories as $cat) {
            $code = cat_code($cat);
            $categoryMappings[$cat] = $code;
        }
        
        // Log the update for debugging
        error_log('Naming scheme updated with categories: ' . json_encode($categoryMappings));
        
        return $categoryMappings;
    } catch (Exception $e) {
        error_log('Error updating naming scheme: ' . $e->getMessage());
        return false;
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    if ($action === 'delete') {
        // Set category fields to NULL/empty for products
        $stmt = $pdo->prepare('UPDATE products SET productType = NULL WHERE productType = ?');
        $stmt->execute([$category]);
        
        // Update naming scheme after deletion
        $updatedMappings = updateNamingScheme($pdo);
        
        echo json_encode([
            'success' => true, 
            'message' => "Category '{$category}' deleted successfully",
            'namingSchemeUpdated' => true,
            'categoryMappings' => $updatedMappings
        ]);
        
    } elseif ($action === 'create') {
        // For creation, we don't need to insert anything since categories are implicit
        // But we should validate the category name and update the naming scheme
        
        // Generate the category code for this new category
        $categoryCode = cat_code($category);
        
        // Update naming scheme after creation
        $updatedMappings = updateNamingScheme($pdo);
        
        echo json_encode([
            'success' => true, 
            'message' => "Category '{$category}' created successfully",
            'categoryCode' => $categoryCode,
            'namingSchemeUpdated' => true,
            'categoryMappings' => $updatedMappings
        ]);
        
    } elseif ($action === 'rename') {
        // Check if new category name already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE productType = ?');
        $stmt->execute([$newCategory]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => "Category '{$newCategory}' already exists"]);
            exit;
        }
        
        // Update all products with the old category to use the new category name
        $stmt = $pdo->prepare('UPDATE products SET productType = ? WHERE productType = ?');
        $stmt->execute([$newCategory, $category]);
        $affectedRows = $stmt->rowCount();
        
        // Generate the category code for the new category name
        $newCategoryCode = cat_code($newCategory);
        
        // Update naming scheme after rename
        $updatedMappings = updateNamingScheme($pdo);
        
        echo json_encode([
            'success' => true, 
            'message' => "Category renamed from '{$category}' to '{$newCategory}' successfully",
            'oldCategory' => $category,
            'newCategory' => $newCategory,
            'newCategoryCode' => $newCategoryCode,
            'affectedProducts' => $affectedRows,
            'namingSchemeUpdated' => true,
            'categoryMappings' => $updatedMappings
        ]);
        
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('Category action error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
} 