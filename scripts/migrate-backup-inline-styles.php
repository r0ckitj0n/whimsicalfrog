<?php

// scripts/migrate-backup-inline-styles.php
// Usage: php scripts/migrate-backup-inline-styles.php

$projectRoot = realpath(__DIR__ . '/..');
$backupDir   = $projectRoot . '/backups';

// Map backup path patterns to target CSS files
$map = [
    '#/backups/sections/admin_#'        => $projectRoot . '/css/admin-styles.css',
    '#/backups/api/email_#'            => $projectRoot . '/css/email-styles.css',
    '#/backups/api/(save_email_config|update_sample_email|email_config)\.php\.bak#' => $projectRoot . '/css/email-styles.css',
    // Add more patterns as needed for other modules
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($backupDir)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'bak') {
        continue;
    }
    $path = $file->getPathname();

    foreach ($map as $pattern => $targetCss) {
        if (preg_match($pattern, $path)) {
            $content = file_get_contents($path);
            if (preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $content, $matches)) {
                foreach ($matches[1] as $css) {
                    $css = trim($css);
                    // Skip empty or PHP-containing blocks
                    if ($css === '' || strpos($css, '<?') !== false) {
                        continue;
                    }
                    file_put_contents(
                        $targetCss,
                        "\n/* Extracted from {$file->getFilename()} (backup) */\n" . $css . "\n",
                        FILE_APPEND
                    );
                    echo "Appended styles from {$file->getFilename()} to " . basename($targetCss) . "\n";
                }
            }
        }
    }
}

echo "Migration complete.\n";
