<?php

// scripts/bundle-css.php

// Define the base path relative to this script's location
$basePath = dirname(__DIR__); // This will be /Users/jongraves/Documents/Websites/WhimsicalFrog

// Define priority files that must be loaded first.
$priorityFiles = [
    // Core variables & utilities
    'css/core/variables.css',
    'css/generated-tailwind-utilities.css',
    'css/core/utilities.css',

    // Components
    'css/components.css',

    // Pages
    'css/pages.css',
    // Room iframe backgrounds
    'css/room-iframe.css',

    // Overrides
    'css/generated_css_live.css',

    // Responsive/mobile
    'css/responsive/mobile.css',
];

// The directory to scan for CSS files.
$cssDir = $basePath . '/css';

// --- Generate consolidated modals.css from global-modals chunk files ---
function generate_modals_css(string $basePath): void {
    $chunksDir = $basePath . '/css/global-modals';
    $outputFile = $basePath . '/css/modals.css';
    if (!is_dir($chunksDir)) {
        return; // nothing to do
    }
    $chunks = glob($chunksDir . '/chunk-*.css');
    if (!$chunks) {
        return;
    }
    // natural sort so chunk-2.css < chunk-10.css
    natsort($chunks);
    $combined = '';
    foreach ($chunks as $chunk) {
        $combined .= "/* --- chunk: " . basename($chunk) . " --- */\n";
        $combined .= file_get_contents($chunk) . "\n";
    }
    file_put_contents($outputFile, $combined);
}

// ensure modals.css is freshly built each time bundler runs
generate_modals_css($basePath);

// Find all .css files recursively.
$allFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cssDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fileInfo) {
    // Skip original modal chunk files now that they are consolidated
    $relativePathTest = 'css' . str_replace(realpath($cssDir), '', $fileInfo->getRealPath());
    $relativePathTest = str_replace('\\', '/', $relativePathTest);
    if (substr($relativePathTest, 0, 25) === 'css/global-modals/chunk-') {
        continue;
    }
    if ($fileInfo->isFile() && $fileInfo->getExtension() === 'css' && $fileInfo->getFilename() !== 'bundle.css') {
        $relativePath = 'css' . str_replace(realpath($cssDir), '', $fileInfo->getRealPath());
        $allFiles[] = str_replace('\\', '/', $relativePath);
    }
}

// Ensure priority files are unique and at the beginning.
$otherFiles = array_diff($allFiles, $priorityFiles);
$filesToBundle = array_merge($priorityFiles, $otherFiles);

// The output file for the bundled CSS
$outputFile = $basePath . '/css/bundle.css';

// Variable to hold all the CSS content
$bundledCss = '';

// Polyfill str_starts_with for PHP < 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}


/**
 * Recursively inline @import statements.
 */
function inlineCssImports(string $css, string $currentDir, string $basePath): string {
    return preg_replace_callback(
        '/@import\s+(?:url\()?\s*["\']([^"\']+)["\']\s*\)?\s*;\s*/i',
        function ($matches) use ($currentDir, $basePath) {
            $importPath = $matches[1];
            // Skip external URLs
            if (preg_match('/^https?:/i', $importPath)) {
                return '';
            }
            $resolved = realpath($currentDir . '/' . ltrim($importPath, './'));
            if (!$resolved || !str_starts_with($resolved, $basePath) || !file_exists($resolved)) {
                // Could not resolve; drop the import to avoid 404s.
                return '';
            }
            $importCss = file_get_contents($resolved);
            $importDir = dirname($resolved);
            return inlineCssImports($importCss, $importDir, $basePath);
        },
        $css
    );
}




foreach ($filesToBundle as $file) {
    $filePath = $basePath . '/' . $file;
    if (file_exists($filePath) && is_file($filePath)) {

        // Add a comment to indicate the start of a file's content
        $bundledCss .= "/* --- Start of " . $file . " --- */\n\n";
        $cssContent = file_get_contents($filePath);
        $cssContent = inlineCssImports($cssContent, dirname($filePath), $basePath);
        // Remove header/footer comments like /* --- Start of ... --- */ that break Vite
        $cssContent = preg_replace('/\/\* --- (Start|End) of .*? --- \*\//', '', $cssContent);
        $bundledCss .= $cssContent . "\n\n";
        // Add a comment to indicate the end of a file's content
        $bundledCss .= "/* --- End of " . $file . " --- */\n\n";
    } else {

    }
}

// Write the bundled content to the output file
file_put_contents($outputFile, $bundledCss);
