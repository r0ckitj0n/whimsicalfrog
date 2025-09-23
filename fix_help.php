<?php

$file = __DIR__ . '/help.php';
$content = file_get_contents($file);

$content = str_replace(
    "'getting-started': '<h2>🚀 Getting Started</h2><p>Welcome! Set up business info, configure payments, add products.</p>',",
    "'getting-started': `<h2>🚀 Getting Started</h2><p>Welcome! This platform manages your complete online store.</p><h3>First Steps:</h3><ol><li>Set up business info in Settings → Business Information</li><li>Configure payments in Settings → Configure Square</li><li>Add products in Admin → Inventory</li><li>Set up categories in Admin → Categories</li></ol>`,",
    $content
);

file_put_contents($file, $content);
echo "Help content updated!";
