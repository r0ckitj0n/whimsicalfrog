<?php
// Simple endpoint to return the next SKU for a given category.
// Usage: /api/next_sku.php?cat=Tumblers

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$category = $_GET['cat'] ?? '';
$category = trim($category);
if ($category === '') {
    echo json_encode(['success' => false, 'error' => 'Missing cat parameter']);
    exit;
}

function category_code(string $cat): string {
    $map = [
        'T-Shirts'      => 'TS',
        'Tumblers'      => 'TU',
        'Artwork'       => 'AR',
        'Sublimation'   => 'SU',
        'WindowWraps'   => 'WW',
    ];
    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 2));
}

$code = category_code($category);
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->prepare("SELECT sku FROM items WHERE sku LIKE :pat ORDER BY sku DESC LIMIT 1");
    $like = 'WF-' . $code . '-%';
    $stmt->execute([':pat' => $like]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextNum = 1;
    if ($row && preg_match('/WF-' . $code . '-(\d{3})$/', $row['sku'], $m)) {
        $nextNum = intval($m[1]) + 1;
    }
    $nextSku = 'WF-' . $code . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    echo json_encode(['success' => true, 'sku' => $nextSku]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
} 