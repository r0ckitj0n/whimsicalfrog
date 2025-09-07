<?php

// Handle AJAX requests for category create/delete/update
require_once __DIR__ . '/api/config.php';

header('Content-Type: text/plain');

// Admin authentication with fallback to admin token
$isAdmin = false;

// Check session authentication first
if (isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin') {
    $isAdmin = true;
}

// Fallback to admin token authentication for development/API usage
if (!$isAdmin && isset($input['admin_token']) && $input['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    echo 'Unauthorized';
    exit;
}

// Handle both JSON and form-encoded data
$input = [];
if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    $input = $_POST;
}

$action = $input['action'] ?? '';
$category = trim($input['category'] ?? $input['categoryName'] ?? '');
$newCategory = trim($input['newCategory'] ?? $input['newName'] ?? '');
$oldName = trim($input['oldName'] ?? '');

if (!$category && $action !== 'update') {
    echo 'Category required';
    exit;
}

if ($action === 'rename' && !$newCategory) {
    echo 'New category name required';
    exit;
}

if ($action === 'update' && (!$oldName || !$newCategory)) {
    echo 'Old name and new name required for update';
    exit;
}

// Function to generate category code for naming scheme
function cat_code($cat)
{
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
function updateNamingScheme($pdo)
{
    try {
        // Get all current categories
        $rows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
        $categories = array_map(function($r){ return array_values($r)[0]; }, $rows);

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
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    if ($action === 'delete') {
        // Set category fields to NULL/empty for items
        $affectedRows = Database::execute('UPDATE items SET category = NULL WHERE category = ?', [$category]);

        // Update naming scheme after deletion
        $updatedMappings = updateNamingScheme($pdo);

        echo 'Category deleted successfully';

    } elseif ($action === 'add' || $action === 'create') {
        // For creation, we don't need to insert anything since categories are implicit
        // But we should validate the category name and update the naming scheme

        // Generate the category code for this new category
        $categoryCode = cat_code($category);

        // Update naming scheme after creation
        $updatedMappings = updateNamingScheme($pdo);

        echo "Category '{$category}' created successfully";

    } elseif ($action === 'update') {
        // Use oldName and newName for the update action
        $oldCategory = $oldName;
        $newCategoryName = $newCategory;

        // Check if new category name already exists (and is different from old name)
        if ($oldCategory !== $newCategoryName) {
            $row = Database::queryOne('SELECT COUNT(*) AS c FROM items WHERE category = ?', [$newCategoryName]);
            if ((int)($row['c'] ?? 0) > 0) {
                echo "Category '{$newCategoryName}' already exists";
                exit;
            }
        }

        // Update all items with the old category to use the new category name
        $affectedRows = Database::execute('UPDATE items SET category = ? WHERE category = ?', [$newCategoryName, $oldCategory]);

        // Update naming scheme after rename
        $updatedMappings = updateNamingScheme($pdo);

        echo "Category renamed from '{$oldCategory}' to '{$newCategoryName}' successfully";

    } elseif ($action === 'rename') {
        // Check if new category name already exists
        $row = Database::queryOne('SELECT COUNT(*) AS c FROM items WHERE category = ?', [$newCategory]);
        if ((int)($row['c'] ?? 0) > 0) {
            echo "Category '{$newCategory}' already exists";
            exit;
        }

        // Update all items with the old category to use the new category name
        $affectedRows = Database::execute('UPDATE items SET category = ? WHERE category = ?', [$newCategory, $category]);

        // Generate the category code for the new category name
        $newCategoryCode = cat_code($newCategory);

        // Update naming scheme after rename
        $updatedMappings = updateNamingScheme($pdo);

        echo "Category renamed from '{$category}' to '{$newCategory}' successfully";

    } else {
        echo 'Invalid action';
    }
} catch (PDOException $e) {
    error_log('Category action error: ' . $e->getMessage());
    echo 'Database error';
}
