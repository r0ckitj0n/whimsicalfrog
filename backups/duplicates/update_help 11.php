<?php

$file = __DIR__ . '/help.php';
$content = file_get_contents($file);

$content = str_replace(
    "'inventory': '<h2>📦 Inventory</h2><p>Manage products, stock levels, and pricing.</p>',",
    "'inventory': `<h2>📦 Inventory</h2><p><strong>Route:</strong> /admin/?section=inventory</p><h3>Features:</h3><ul><li>Product management with variants</li><li>Stock tracking and alerts</li><li>AI pricing suggestions</li><li>Categories management</li><li>Image optimization</li></ul>`,",
    $content
);

file_put_contents($file, $content);
echo "Updated!";
