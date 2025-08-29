<?php
// scripts/remove-tailwind-classes.php
// Remove all Tailwind utility classes that have been migrated to utilities.css

$cssFile = __DIR__ . '/../css/generated-tailwind-utilities.css';
if (!file_exists($cssFile)) {
    echo "Generated utilities file not found: $cssFile\n";
    exit(1);
}

// Extract class names from generated CSS
$css = file_get_contents($cssFile);
preg_match_all('/\.([a-z0-9\\:\-]+)/i', $css, $matches);
$classes = array_unique($matches[1]);
if (empty($classes)) {
    echo "No classes to remove.\n";
    exit;
}

// Files to process
$dir = dirname(__DIR__);
$files = array_merge(
    glob($dir . '/sections/*.php'),
    glob($dir . '/*.php')
);

foreach ($files as $file) {
    $contents = file_get_contents($file);
    // Remove each class from class="..."
    $new = preg_replace_callback(
        '/class="([^"]*)"/i',
        function ($m) use ($classes) {
            $names = preg_split('/\s+/', $m[1], -1, PREG_SPLIT_NO_EMPTY);
            $filtered = array_filter($names, function($n) use ($classes) {
                return !in_array($n, $classes, true);
            });
            if (empty($filtered)) {
                return 'class=""';
            }
            return 'class="' . implode(' ', $filtered) . '"';
        },
        $contents
    );
    file_put_contents($file, $new);
    echo "Processed: $file\n";
}
echo "Tailwind classes removed from markup.\n";
