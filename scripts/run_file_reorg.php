<?php

/**
 * Script: run_file_reorg.php
 * ----------------------------------------
 * Reads a CSV file that maps current file paths to their proposed destinations
 * and moves the files accordingly (relative to the project root).
 *
 * Usage:
 *   php scripts/run_file_reorg.php [--dry-run] [csv_path]
 *
 *  - --dry-run (optional): If provided, no files will be moved. Instead, a
 *    summary of the operations will be printed.
 *  - csv_path   (optional): Path to CSV file. Defaults to
 *    documentation/file-structure-reorg.csv
 *
 * The script will create destination directories as needed. It skips any entry
 * where the source file does not exist or the destination already contains a
 * file of the same name. All actions are logged to the console.
 */

// ----------------------------------------
// Helper functions
// ----------------------------------------

/**
 * Prints a message to STDOUT with timestamp.
 */
function log_msg(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message" . PHP_EOL;
}

/**
 * Ensure destination directory exists.
 */
function ensure_directory(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }

    return mkdir($dir, 0775, true);
}

// ----------------------------------------
// Parse CLI args
// ----------------------------------------

$args = $argv;
array_shift($args); // remove script name

$dryRun    = false;
$csvPath   = 'documentation/file-structure-reorg.csv';

foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (!str_starts_with($arg, '--')) {
        $csvPath = $arg;
    }
}

$projectRoot = realpath(__DIR__ . '/..');

if ($projectRoot === false) {
    log_msg('ERROR: Unable to determine project root.');
    exit(1);
}

$csvFullPath = $projectRoot . DIRECTORY_SEPARATOR . $csvPath;

if (!file_exists($csvFullPath)) {
    log_msg("ERROR: CSV file not found at $csvFullPath");
    exit(1);
}

log_msg('Starting file reorganization' . ($dryRun ? ' (dry-run)' : ''));
log_msg("Using CSV: $csvFullPath");

// ----------------------------------------
// Process CSV
// ----------------------------------------

$fh = fopen($csvFullPath, 'r');
if ($fh === false) {
    log_msg('ERROR: Unable to open CSV file.');
    exit(1);
}

$lineNumber = 0;
$moveCount  = 0;
$skipCount  = 0;

while (($row = fgetcsv($fh)) !== false) {
    $lineNumber++;

    // Skip header
    if ($lineNumber === 1 && $row[0] === 'current_path') {
        continue;
    }

    if (count($row) < 2) {
        log_msg("WARNING: Malformed CSV line $lineNumber; expected 2 columns.");
        continue;
    }

    [$currentPath, $destDir] = $row;
    $source = $projectRoot . DIRECTORY_SEPARATOR . $currentPath;

    if (!file_exists($source)) {
        log_msg("SKIP: Source not found ($currentPath)");
        $skipCount++;
        continue;
    }

    // Normalise destination directory
    $destDir = rtrim($destDir, '/');
    if ($destDir === 'root' || $destDir === '.') {
        $destDir = '';
    }

    $destinationDir = $projectRoot . ($destDir ? DIRECTORY_SEPARATOR . $destDir : '');

    if (!ensure_directory($destinationDir)) {
        log_msg("ERROR: Unable to create directory $destinationDir");
        $skipCount++;
        continue;
    }

    $destination = $destinationDir . DIRECTORY_SEPARATOR . basename($currentPath);

    if (realpath($source) === realpath($destination)) {
        log_msg("SKIP: Source and destination are the same for $currentPath");
        $skipCount++;
        continue;
    }

    if (file_exists($destination)) {
        log_msg("SKIP: Destination already exists ($destination). File not moved.");
        $skipCount++;
        continue;
    }

    if ($dryRun) {
        log_msg("MOVE: $currentPath -> " . ($destDir ?: 'root'));
        $moveCount++;
        continue;
    }

    if (@rename($source, $destination)) {
        log_msg("MOVED: $currentPath -> " . ($destDir ?: 'root'));
        $moveCount++;
    } else {
        log_msg("ERROR: Failed to move $currentPath");
        $skipCount++;
    }
}

fclose($fh);

log_msg("Reorganization complete. Moved: $moveCount, Skipped: $skipCount");

if ($dryRun) {
    log_msg('Dry run finished. No files were moved. Rerun without --dry-run to apply changes.');
}
