<?php

// scripts/dev/set-landing-background.php
// Upsert and activate the correct landing (room_number=0) background.
// Usage: php scripts/dev/set-landing-background.php [--name=Original] [--png=background-home.png] [--webp=background-home.webp]

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../../api/config.php';

function parseArgs(array $argv): array
{
    $out = [
        'name' => 'Original',
        'png' => 'background-home.png',
        'webp' => 'background-home.webp',
    ];
    foreach ($argv as $arg) {
        if (preg_match('/^--name=(.+)$/', $arg, $m)) {
            $out['name'] = $m[1];
        }
        if (preg_match('/^--png=(.+)$/', $arg, $m)) {
            $out['png'] = $m[1];
        }
        if (preg_match('/^--webp=(.+)$/', $arg, $m)) {
            $out['webp'] = $m[1];
        }
    }
    return $out;
}

$args = parseArgs($argv);
$roomNumber = 0; // landing/main logical bucket
$png = trim($args['png']);
$webp = trim($args['webp']);
$name = trim($args['name']);

$pngFs = dirname(__DIR__, 2) . '/images/backgrounds/' . $png;
$webpFs = dirname(__DIR__, 2) . '/images/backgrounds/' . $webp;

if (!is_file($webpFs) && !is_file($pngFs)) {
    fwrite(STDERR, "Error: Neither $webp nor $png was found under images/backgrounds/.\n");
    exit(2);
}

try {
    $pdo = Database::getInstance();
    Database::beginTransaction();

    // Deactivate all existing backgrounds for room 0
    Database::execute('UPDATE backgrounds SET is_active = 0 WHERE room_number = ?', [$roomNumber]);

    // Try to find an existing matching row
    $row = Database::queryOne('SELECT id FROM backgrounds WHERE room_number = ? AND (image_filename = ? OR webp_filename = ?) LIMIT 1', [$roomNumber, $png, $webp]);
    $id = $row['id'] ?? null;

    if ($id) {
        Database::execute('UPDATE backgrounds SET background_name = ?, image_filename = ?, webp_filename = ?, is_active = 1 WHERE id = ?', [$name, $png, $webp, (int)$id]);
    } else {
        Database::execute('INSERT INTO backgrounds (room_number, background_name, image_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, 1)', [$roomNumber, $name, $png, $webp]);
        $id = (int) Database::lastInsertId();
    }

    Database::commit();

    $result = Database::queryOne('SELECT * FROM backgrounds WHERE id = ?', [$id]);
    echo json_encode([
        'success' => true,
        'message' => 'Landing background set and activated',
        'data' => $result,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    try {
        if (Database::inTransaction()) {
            Database::rollBack();
        }
    } catch (Throwable $t) {
    }
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
