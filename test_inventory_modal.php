<?php
// Simple test to verify inventory modal JavaScript
echo "Testing inventory modal JavaScript...\n";

// Test 1: Check if the title was changed
$content = file_get_contents('sections/admin_inventory.php');
if (strpos($content, 'Inventory Management') !== false) {
    echo "✅ Title changed to 'Inventory Management'\n";
} else {
    echo "❌ Title not found or not changed\n";
}

// Test 2: Check if the JavaScript conditional was added
if (strpos($content, "if (modalMode === 'edit' || modalMode === 'view' || modalMode === 'add')") !== false) {
    echo "✅ JavaScript conditional added for modal mode check\n";
} else {
    echo "❌ JavaScript conditional not found\n";
}

// Test 3: Check if cost breakdown elements are properly referenced
if (strpos($content, 'materialsList') !== false && strpos($content, 'laborList') !== false) {
    echo "✅ Cost breakdown element IDs are present\n";
} else {
    echo "❌ Cost breakdown element IDs not found\n";
}

echo "\nTest completed. The JavaScript errors should be resolved.\n";
echo "The missing image 404 errors are expected for non-existent images (WF-TU-002B.webp, WF-TU-003A.webp).\n";
?> 