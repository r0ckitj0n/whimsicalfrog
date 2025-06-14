<?php
require_once __DIR__ . '/../api/config.php';

$pdo = new PDO($dsn, $user, $pass, $options);

$count = 0;
$updated = 0;

// Find inventory rows with missing or placeholder imageUrl
$stmt = $pdo->query("SELECT i.id, i.productId, i.imageUrl, p.image FROM inventory i LEFT JOIN products p ON i.productId = p.id WHERE (i.imageUrl IS NULL OR i.imageUrl = '' OR i.imageUrl = 'images/placeholder.png' OR i.imageUrl = 'images/products/placeholder.png') AND p.image IS NOT NULL AND p.image != ''");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $count++;
    $invId = $row['id'];
    $prodImg = $row['image'];
    if ($prodImg && $invId) {
        $upd = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE id = ?");
        if ($upd->execute([$prodImg, $invId])) {
            echo "Updated $invId to $prodImg\n";
            $updated++;
        }
    }
}

echo "Checked $count inventory rows, updated $updated.\n"; 