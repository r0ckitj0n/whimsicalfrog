<?php
/**
 * Modal Styling Update Script
 * Updates all modals throughout the site to use unified CSS classes
 */

// Define modal replacements
$modalReplacements = [
    // Standard modal overlay pattern
    'fixed inset-0 bg-black bg-opacity-50 hidden z-50' => 'modal-overlay hidden',
    'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden' => 'modal-overlay hidden',
    'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden' => 'modal-overlay hidden',
    'fixed inset-0 bg-black bg-opacity-50 z-[70] hidden flex items-center justify-center' => 'modal-overlay hidden modal-z-high',
    
    // Admin modal patterns
    'fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center' => 'admin-modal-overlay hidden',
    
    // Content containers
    'bg-white rounded-lg shadow-xl max-w-md w-full' => 'modal-content',
    'bg-white rounded-lg shadow-xl max-w-lg w-full' => 'modal-content',
    'bg-white rounded-lg shadow-xl max-w-xl w-full' => 'modal-content',
    'bg-white rounded-lg shadow-xl max-w-2xl w-full' => 'modal-content',
    'bg-white rounded-lg shadow-xl max-w-3xl w-full' => 'modal-content',
    'bg-white rounded-lg shadow-xl max-w-4xl w-full' => 'admin-modal-content',
    'bg-white rounded-lg shadow-xl max-w-5xl w-full' => 'admin-modal-content',
    'bg-white rounded-lg shadow-xl max-w-6xl w-full' => 'admin-modal-content',
    
    // Headers
    'flex justify-between items-center mb-4' => 'modal-header',
    'px-6 py-4 border-b border-gray-200' => 'modal-header',
    
    // Buttons
    'text-gray-400 hover:text-gray-600 text-2xl' => 'modal-close',
    'bg-gray-300 hover:bg-gray-400 text-gray-800' => 'modal-button btn-secondary',
    'bg-green-500 hover:bg-green-600 text-white' => 'modal-button btn-primary',
    'bg-red-500 hover:bg-red-600 text-white' => 'modal-button btn-danger',
    
    // Form elements
    'px-3 py-2 border border-gray-300 rounded text-sm' => 'modal-select',
    'w-20 text-center border border-gray-300 rounded-md py-2' => 'qty-input',
    'bg-gray-200 hover:bg-gray-300 text-gray-800 w-10 h-10 rounded-full' => 'qty-btn',
];

// Files to update
$filesToUpdate = [
    'components/ai_processing_modal.php',
    'sections/shop.php',
    'sections/room3.php',
    'sections/admin_settings.php',
    'sections/admin_inventory.php'
];

function updateModalStyling($filePath, $replacements) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $changesCount = 0;
    
    foreach ($replacements as $search => $replace) {
        $newContent = str_replace($search, $replace, $content);
        if ($newContent !== $content) {
            $changesCount += substr_count($content, $search);
            $content = $newContent;
        }
    }
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        echo "Updated $filePath - $changesCount changes made\n";
        return true;
    } else {
        echo "No changes needed for $filePath\n";
        return false;
    }
}

echo "Starting modal styling update...\n\n";

foreach ($filesToUpdate as $file) {
    updateModalStyling($file, $modalReplacements);
}

echo "\nModal styling update complete!\n";
echo "\nNext steps:\n";
echo "1. Test all modals to ensure they work correctly\n";
echo "2. Check that all modal types use consistent styling\n";
echo "3. Verify responsive behavior on mobile devices\n";
echo "4. Update any custom modal JavaScript if needed\n";
?> 