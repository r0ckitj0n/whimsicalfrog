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

if (!$category) {
    echo json_encode(['error' => 'Category required']);
    exit;
}

<<<<<<< HEAD
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

=======
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    if ($action === 'delete') {
<<<<<<< HEAD
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
        
=======
        // Set category fields to NULL/empty
        $stmt = $pdo->prepare('UPDATE products SET productType = NULL WHERE productType = ?');
        $stmt->execute([$category]);

        $stmt = $pdo->prepare('UPDATE inventory SET category = NULL WHERE category = ?');
        $stmt->execute([$category]);

        echo json_encode(['success' => true]);
    } elseif ($action === 'create') {
        // No central categories table; creation is implicit. We'll just respond success.
        echo json_encode(['success' => true]);
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('Category action error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
} 