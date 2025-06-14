<?php
require_once __DIR__ . '/api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $productId = 'TS001';
    
    echo "<h2>Debug: Images for Product $productId</h2>";
    
    // Check product_images table
    $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>product_images table:</h3>";
    if (empty($images)) {
        echo "<p>No images found in product_images table</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Product ID</th><th>Image Path</th><th>Is Primary</th><th>Sort Order</th></tr>";
        foreach ($images as $img) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($img['id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['product_id']) . "</td>";
            echo "<td>" . htmlspecialchars($img['image_path']) . "</td>";
            echo "<td>" . ($img['is_primary'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($img['sort_order']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check inventory table
    $stmt = $pdo->prepare("SELECT productId, imageUrl FROM inventory WHERE productId = ?");
    $stmt->execute([$productId]);
    $invItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>inventory table:</h3>";
    if ($invItem) {
        echo "<p>Product ID: " . htmlspecialchars($invItem['productId']) . "</p>";
        echo "<p>Image URL: " . htmlspecialchars($invItem['imageUrl']) . "</p>";
    } else {
        echo "<p>No inventory item found</p>";
    }
    
    // Test the regex pattern
    echo "<h3>Regex Test:</h3>";
    $existingPaths = [];
    foreach ($images as $img) {
        $existingPaths[] = $img['image_path'];
    }
    
    $usedSuffixes = [];
    foreach ($existingPaths as $path) {
        echo "<p>Testing path: " . htmlspecialchars($path) . "</p>";
        if (preg_match('/\/' . preg_quote($productId) . '([A-Z])\./', $path, $matches)) {
            echo "<p>  - Found suffix: " . $matches[1] . "</p>";
            $usedSuffixes[] = $matches[1];
        } else {
            echo "<p>  - No suffix match</p>";
        }
    }
    
    echo "<p>Used suffixes: " . json_encode($usedSuffixes) . "</p>";
    
    // Find next available
    $nextSuffix = null;
    for ($letterIndex = 0; $letterIndex < 26; $letterIndex++) {
        $testSuffix = chr(65 + $letterIndex);
        if (!in_array($testSuffix, $usedSuffixes)) {
            $nextSuffix = $testSuffix;
            break;
        }
    }
    echo "<p>Next available suffix: " . ($nextSuffix ?: 'None') . "</p>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 