<?php
/**
 * Asset Synchronization Helper
 * --------------------------------
 * Scans the /images directory (all subfolders) and compares filenames to
 * the values stored in database tables that reference images. Reports:
 *   • DB rows whose file is missing on disk
 *   • Files on disk that are not referenced in the DB (optional cleanup)
 *   • Candidate UPDATE statements for simple name mismatches (e.g. prefix changes)
 *
 * Usage (CLI):
 *   php scripts/sync_assets.php [--write-sql]
 *
 * If --write-sql is provided, the script will output a file sync_assets_updates.sql
 * containing UPDATE statements you can review / run manually.
 */

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../api/config.php';

$pdo = Database::getInstance();

// 1. Gather actual filenames on disk -------------------------------------------------
$baseDir = realpath(__DIR__ . '/../images');
if (!$baseDir) {
    echo "Images directory not found.\n";
    exit(1);
}

$diskFiles = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $diskFiles[strtolower($file->getFilename())] = $file->getPathname();
}

echo "Found " . count($diskFiles) . " image files on disk.\n";

// 2. Define tables/columns to scan ---------------------------------------------------
$tables = [
    // table => [columns]
    'backgrounds' => ['image_filename', 'webp_filename'],
    'items'       => ['image_filename', 'thumbnail_filename', 'webp_filename'], // will ignore missing cols
    'signs'       => ['image_filename', 'webp_filename'], // if exists
];

$missing = [];
$unused  = [];
$sqlUpdates = [];

foreach ($tables as $table => $columns) {
    // Check if table exists
    try {
        $exists = $pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
        if (!$exists) {
            echo "Table $table not found – skipping.\n";
            continue;
        }
    } catch (Exception $e) {
        echo "Error checking table $table: " . $e->getMessage() . "\n";
        continue;
    }

    // Build column list intersection with actual table definition
    $colResult = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    $colsToCheck = array_intersect($columns, $colResult);
    if (empty($colsToCheck)) continue;

    // Fetch rows
    $selectCols = 'id,' . implode(',', $colsToCheck);
    $rows = $pdo->query("SELECT $selectCols FROM $table")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $id = $row['id'];
        foreach ($colsToCheck as $col) {
            $filename = trim($row[$col]);
            if ($filename === '') continue;

            $key = strtolower($filename);
            if (!isset($diskFiles[$key])) {
                $missing[] = [
                    'table' => $table,
                    'id'    => $id,
                    'col'   => $col,
                    'filename' => $filename,
                ];

                // Try heuristics to find likely match
                $candidates = [];
                // Case 1: strip prefix
                $candidates[] = str_replace('background_', '', $key);
                // Case 2: add prefix
                $candidates[] = 'background_' . $key;
                foreach ($candidates as $cand) {
                    if (isset($diskFiles[$cand])) {
                        $newName = basename($diskFiles[$cand]);
                        $sqlUpdates[] = "UPDATE $table SET $col = '" . addslashes($newName) . "' WHERE id = $id;";
                        break;
                    }
                }
            } else {
                // Mark file as used
                $unused[$key] = true;
            }
        }
    }
}

// 3. Output report -------------------------------------------------------------------

echo "\n=== Missing files referenced in DB ===\n";
if (empty($missing)) {
    echo "None – good job!\n";
} else {
    foreach ($missing as $m) {
        echo "{$m['table']}.{$m['col']} (id {$m['id']}): {$m['filename']} – NOT FOUND\n";
    }
}

// Files on disk not referenced anywhere
$unusedFiles = array_diff_key($diskFiles, $unused);

echo "\n=== Files on disk not referenced in DB ===\n";
if (empty($unusedFiles)) {
    echo "None.\n";
} else {
    foreach ($unusedFiles as $name => $path) {
        echo "$name\n";
    }
}

// 4. Optionally write SQL ------------------------------------------------------------
if (in_array('--write-sql', $argv, true) && !empty($sqlUpdates)) {
    $sqlPath = __DIR__ . '/sync_assets_updates.sql';
    file_put_contents($sqlPath, implode("\n", $sqlUpdates) . "\n");
    echo "\nCandidate UPDATE statements written to $sqlPath\n";
}

echo "\nAudit complete.\n";
