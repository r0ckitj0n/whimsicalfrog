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

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    if ($action === 'delete') {
        // Set category fields to NULL/empty
        $stmt = $pdo->prepare('UPDATE products SET productType = NULL WHERE productType = ?');
        $stmt->execute([$category]);

        $stmt = $pdo->prepare('UPDATE inventory SET category = NULL WHERE category = ?');
        $stmt->execute([$category]);

        echo json_encode(['success' => true]);
    } elseif ($action === 'create') {
        // No central categories table; creation is implicit. We'll just respond success.
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log('Category action error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
} 