<?php
echo "<h2>Testing get_product_images API for TS001</h2>";

// Test the API directly
$url = 'https://whimsicalfrog.us/api/get_product_images.php?productId=TS001';
$response = file_get_contents($url);

echo "<h3>API Response:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
if ($data) {
    echo "<h3>Parsed Data:</h3>";
    echo "<p>Success: " . ($data['success'] ? 'Yes' : 'No') . "</p>";
    echo "<p>Product ID: " . htmlspecialchars($data['productId'] ?? 'N/A') . "</p>";
    echo "<p>Total Images: " . ($data['totalImages'] ?? 0) . "</p>";
    
    if (!empty($data['images'])) {
        echo "<h4>Images:</h4>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Path</th><th>Primary</th><th>Sort Order</th><th>File Exists</th></tr>";
        foreach ($data['images'] as $img) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($img['id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($img['image_path'] ?? 'N/A') . "</td>";
            echo "<td>" . ($img['is_primary'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($img['sort_order'] ?? 'N/A') . "</td>";
            echo "<td>" . ($img['file_exists'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p>Failed to parse JSON response</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
}
?> 