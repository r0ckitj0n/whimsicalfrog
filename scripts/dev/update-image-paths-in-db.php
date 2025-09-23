<?php

/**
 * Update image filenames/paths in the database from underscore to dash naming.
 * - Backgrounds, signs, logos.
 * - Leaves SKU item images unchanged (policy allows uppercase, but still no underscores).
 *
 * Usage:
 *   php scripts/dev/update-image-paths-in-db.php [--apply] [--verbose]
 *
 * Default mode is dry-run: prints what would change without modifying the DB.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../api/config.php';

$APPLY   = in_array('--apply', $argv, true);
$VERBOSE = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);

function logln(string $msg): void
{
    echo $msg, "\n";
}
function v(string $msg): void
{
    global $VERBOSE;
    if ($VERBOSE) {
        logln($msg);
    }
}

try {
    $pdo = Database::getInstance();
    $dbNameRow = Database::queryOne('SELECT DATABASE() AS db');
    $dbName = $dbNameRow ? ($dbNameRow['db'] ?? '') : '';
    if ($dbName === '') {
        throw new RuntimeException('Unable to determine current database name');
    }

    logln("Image path migration (" . ($APPLY ? 'APPLY' : 'DRY-RUN') . ") on database: {$dbName}");

    $totalPlanned = 0;
    $totalUpdated = 0;

    // Step 1: Update backgrounds table filenames (basename values)
    $bgTableExists = false;
    try {
        $bgTableExists = (bool) Database::queryOne("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'backgrounds'", [$dbName]);
    } catch (Throwable $e) {
        $bgTableExists = false;
    }

    if ($bgTableExists) {
        // Determine how many rows would change
        $bgCount = Database::queryOne(
            "SELECT COUNT(*) AS c FROM backgrounds 
             WHERE (image_filename REGEXP '(^|.*)(background_|room_)' OR webp_filename REGEXP '(^|.*)(background_|room_)')"
        );
        $plan = (int)($bgCount['c'] ?? 0);
        if ($plan > 0) {
            $totalPlanned += $plan;
            logln("backgrounds: {$plan} row(s) contain legacy segments in filename columns");
            if ($APPLY) {
                // Run two-phase replace: background_ -> background-; room_ -> room-
                $sql1 = "UPDATE backgrounds 
                         SET image_filename = REPLACE(image_filename, 'background_', 'background-'),
                             webp_filename  = REPLACE(webp_filename,  'background_', 'background-')
                         WHERE image_filename LIKE '%background_%' OR webp_filename LIKE '%background_%'";
                $sql2 = "UPDATE backgrounds 
                         SET image_filename = REPLACE(image_filename, 'room_', 'room-'),
                             webp_filename  = REPLACE(webp_filename,  'room_', 'room-')
                         WHERE image_filename LIKE '%room_%' OR webp_filename LIKE '%room_%'";
                Database::beginTransaction();
                try {
                    $a1 = Database::execute($sql1);
                    $a2 = Database::execute($sql2);
                    Database::commit();
                    $totalUpdated += (int)$a1 + (int)$a2; // affected rows (may double-count rows updated twice)
                    v("backgrounds: affected by background_->background-: {$a1}");
                    v("backgrounds: affected by room_->room-: {$a2}");
                } catch (Throwable $e) {
                    Database::rollBack();
                    throw $e;
                }
            }
        } else {
            v('backgrounds: no legacy segments detected');
        }
    } else {
        v('backgrounds: table not present, skipping');
    }

    // Step 2: Generic path-based replacements across all text/varchar columns
    // We only target columns that contain images/ paths.
    $cols = Database::queryAll(
        "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE 
         FROM information_schema.columns 
         WHERE TABLE_SCHEMA = ? 
           AND DATA_TYPE IN ('varchar','text','tinytext','mediumtext','longtext')",
        [$dbName]
    );

    $replacements = [
        // backgrounds
        ["needle" => 'images/backgrounds/background_', "repl" => 'images/backgrounds/background-'],
        ["needle" => 'background-room_',               "repl" => 'background-room-'],
        // signs
        ["needle" => 'images/signs/sign_door_room',    "repl" => 'images/signs/sign-door-room'],
        ["needle" => 'images/signs/sign_welcome',      "repl" => 'images/signs/sign-welcome'],
        ["needle" => 'images/signs/sign_main',         "repl" => 'images/signs/sign-main'],
        ["needle" => 'images/signs/sign_whimsicalfrog',"repl" => 'images/signs/sign-whimsicalfrog'],
        // logos
        ["needle" => 'images/logos/logo_whimsicalfrog',"repl" => 'images/logos/logo-whimsicalfrog'],
    ];

    foreach ($cols as $c) {
        $table = $c['TABLE_NAME'];
        $col   = $c['COLUMN_NAME'];
        // Skip backgrounds.filename columns here (already covered), but still allow path columns
        if ($table === 'backgrounds' && in_array($col, ['image_filename', 'webp_filename'], true)) {
            continue;
        }

        // Only consider columns that appear to contain image paths
        $likeCheck = Database::queryOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$col}` LIKE '%images/%'");
        $cnt = (int)($likeCheck['c'] ?? 0);
        if ($cnt <= 0) {
            continue;
        }

        $plannedForThisCol = 0;
        foreach ($replacements as $r) {
            $needle = $r['needle'];
            $repl   = $r['repl'];
            $would = Database::queryOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE `{$col}` LIKE ?", ["%{$needle}%"]);
            $candidates = (int)($would['c'] ?? 0);
            if ($candidates > 0) {
                $plannedForThisCol += $candidates;
                if ($APPLY) {
                    $sql = "UPDATE `{$table}` SET `{$col}` = REPLACE(`{$col}`, ?, ?) WHERE `{$col}` LIKE ?";
                    $affected = Database::execute($sql, [$needle, $repl, "%{$needle}%"]);
                    $totalUpdated += (int)$affected;
                    v("{$table}.{$col}: replaced '{$needle}' -> '{$repl}' in {$affected} row(s)");
                }
            }
        }
        if ($plannedForThisCol > 0) {
            $totalPlanned += $plannedForThisCol;
            logln("{$table}.{$col}: {$plannedForThisCol} potential replacement(s)");
        }
    }

    logln("");
    if ($APPLY) {
        logln("Completed DB image path migration. Affected row events (approx): {$totalUpdated}");
        logln("Note: affected count may exceed distinct rows if multiple patterns matched the same row in separate updates.");
    } else {
        logln("Dry-run complete. Planned replacement events (approx): {$totalPlanned}");
        logln("Run with --apply to execute, optionally add --verbose for details.");
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
