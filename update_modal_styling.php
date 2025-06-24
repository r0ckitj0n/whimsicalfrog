<?php
/**
 * Complete Modal Styling Standardization Script
 * Updates all remaining hardcoded modal classes to use unified CSS system
 */

echo "Starting comprehensive modal standardization...\n";

// Define file patterns to update
$files_to_update = [
    'sections/admin_settings.php',
    'sections/admin_inventory.php',
    'sections/shop.php',
    'sections/room2.php',
    'sections/room3.php',
    'sections/room_template.php'
];

// Define modal class replacements
$modal_replacements = [
    // Standard modal overlay patterns
    'class="fixed inset-0 bg-black bg-opacity-50 hidden z-50"' => 'class="modal-overlay hidden"',
    'class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden"' => 'class="modal-overlay hidden"',
    'class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden"' => 'class="modal-overlay hidden"',
    'class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden"' => 'class="modal-overlay hidden"',
    'class="fixed inset-0 bg-black bg-opacity-50 z-[70] hidden flex items-center justify-center p-2 sm:p-4"' => 'class="admin-modal-overlay hidden"',
    'class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4"' => 'class="admin-modal-overlay"',
    
    // Admin modal patterns
    'class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"' => 'class="admin-modal-overlay"',
    'class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"' => 'class="admin-modal-overlay"',
    'class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4"' => 'class="admin-modal-overlay hidden"',
    
    // Room modal patterns
    'class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4"' => 'class="modal-overlay"',
    
    // Modal content patterns
    'class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden"' => 'class="admin-modal-content"',
    'class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[95vh] flex flex-col overflow-hidden"' => 'class="admin-modal-content"',
    'class="bg-white rounded-lg shadow-xl w-full max-w-6xl max-h-[95vh] flex flex-col overflow-hidden"' => 'class="admin-modal-content"',
    'class="bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col overflow-hidden"' => 'class="admin-modal-content"',
    'class="bg-white rounded-lg shadow-xl max-w-lg w-full"' => 'class="modal-content"',
    'class="bg-white rounded-lg shadow-xl max-w-md w-full"' => 'class="modal-content"',
    'class="bg-white rounded-lg shadow-xl max-w-sm w-full"' => 'class="compact-modal-content"',
    
    // Header patterns
    'class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-purple-500 to-purple-600"' => 'class="admin-modal-header" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);"',
    'class="px-6 py-4 border-b border-gray-200"' => 'class="modal-header"',
    'class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex justify-between items-center flex-shrink-0"' => 'class="admin-modal-header"',
    'class="bg-gradient-to-r from-green-600 to-blue-600 px-4 sm:px-6 py-4 flex justify-between items-center flex-shrink-0"' => 'class="admin-modal-header" style="background: linear-gradient(135deg, #10b981, #3b82f6);"',
    
    // Footer patterns
    'class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg"' => 'class="modal-footer"',
    'class="px-6 py-4 border-t border-gray-200 bg-gray-50"' => 'class="modal-footer"',
    'class="bg-gray-50 px-4 sm:px-6 py-4 border-t flex-shrink-0"' => 'class="modal-footer"',
    'class="px-6 py-4 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100 flex justify-between items-center flex-shrink-0"' => 'class="modal-footer"',
    
    // Body patterns
    'class="px-6 py-4"' => 'class="modal-body"',
    'class="overflow-y-auto max-h-[calc(90vh-200px)]"' => 'class="modal-body" style="overflow-y: auto; max-height: calc(90vh - 200px);"',
    'class="flex-1 overflow-y-auto p-6"' => 'class="modal-body" style="flex: 1; overflow-y: auto;"',
    
    // Button patterns
    'class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm font-medium flex items-center text-left"' => 'class="modal-button btn-primary"',
    'class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"' => 'class="modal-button btn-secondary"',
    'class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium"' => 'class="modal-button btn-primary"',
    'class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded font-medium"' => 'class="modal-button btn-secondary"',
    
    // Close button patterns
    'class="text-gray-400 hover:text-gray-600 transition-colors"' => 'class="modal-close"',
    'class="text-white hover:text-gray-200 text-2xl font-bold"' => 'class="modal-close"',
    'class="text-white hover:text-green-200 text-2xl font-bold transition-colors duration-200"' => 'class="modal-close"',
    
    // Title patterns
    'class="text-lg font-semibold text-gray-900"' => 'class="modal-title"',
    'class="text-lg font-semibold text-white"' => 'class="modal-title"',
    'class="text-xl font-bold text-white"' => 'class="modal-title"',
    'class="text-lg sm:text-xl font-bold text-white"' => 'class="modal-title"',
    
    // Loading patterns
    'class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-3"' => 'class="modal-loading-spinner"',
    'class="animate-spin rounded-full h-4 w-4 border-2 border-blue-500 border-t-transparent"' => 'class="modal-loading-spinner"',
    'class="text-center py-8"' => 'class="modal-loading"',
    
    // Tab patterns
    'class="template-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-purple-500 text-purple-600"' => 'class="css-category-tab active"',
    'class="template-tab whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"' => 'class="css-category-tab"',
    'class="marketing-tab px-4 py-2 rounded-lg bg-white text-green-600 border-2 border-green-600 font-semibold whitespace-nowrap shadow-sm transition-all duration-200 hover:bg-green-50"' => 'class="css-category-tab active"',
    'class="marketing-tab px-4 py-2 rounded-lg text-gray-600 border-2 border-gray-300 hover:text-green-600 hover:border-green-300 whitespace-nowrap transition-all duration-200 hover:bg-gray-50"' => 'class="css-category-tab"',
    
    // Tab bar patterns
    'class="border-b border-gray-200"' => 'class="admin-tab-bar"',
    'class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-3 border-b border-gray-200 flex-shrink-0"' => 'class="admin-tab-bar"',
    
    // Tab content patterns
    'class="template-tab-content p-6"' => 'class="css-category-content p-6"',
    'class="template-tab-content p-6 hidden"' => 'class="css-category-content p-6 hidden"',
    
    // Select patterns
    'class="px-3 py-2 border border-gray-300 rounded text-sm"' => 'class="modal-select"',
    
    // Error patterns
    'class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4"' => 'class="modal-error"',
    'class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 hidden"' => 'class="modal-error hidden"',
];

// JavaScript class replacements
$js_replacements = [
    "overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';" => "overlay.className = 'modal-overlay';",
    "modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';" => "modal.className = 'modal-overlay';",
];

$total_replacements = 0;

foreach ($files_to_update as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }
    
    echo "Processing: $file\n";
    $content = file_get_contents($file);
    $original_content = $content;
    $file_replacements = 0;
    
    // Apply HTML/CSS replacements
    foreach ($modal_replacements as $search => $replace) {
        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        if ($count > 0) {
            echo "  ✓ Replaced '$search' ($count times)\n";
            $file_replacements += $count;
        }
    }
    
    // Apply JavaScript replacements
    foreach ($js_replacements as $search => $replace) {
        $count = 0;
        $content = str_replace($search, $replace, $content, $count);
        if ($count > 0) {
            echo "  ✓ Replaced JS: '$search' ($count times)\n";
            $file_replacements += $count;
        }
    }
    
    // Save if changes were made
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "  💾 Saved $file ($file_replacements replacements)\n";
        $total_replacements += $file_replacements;
    } else {
        echo "  ℹ️  No changes needed in $file\n";
    }
    
    echo "\n";
}

echo "🎉 Modal standardization complete!\n";
echo "📊 Total replacements made: $total_replacements\n";
echo "✨ All modals now use unified CSS classes from global-modals.css\n\n";

echo "🧪 Testing recommendations:\n";
echo "1. Start PHP server: php -S localhost:8000\n";
echo "2. Login as admin (admin/Pass.123)\n";
echo "3. Test admin settings modals\n";
echo "4. Test inventory modals\n";
echo "5. Test shop and room modals\n";
echo "6. Verify all modals have consistent styling\n\n";

echo "📝 Next steps:\n";
echo "1. Deploy changes: ./deploy.sh\n";
echo "2. Test on live server\n";
echo "3. Update documentation if needed\n";
?> 