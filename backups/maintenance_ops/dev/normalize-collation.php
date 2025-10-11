<?php

// scripts/dev/normalize-collation.php
// Normalize the entire database and all tables/columns to a single charset/collation.
// Default target: utf8mb4 / utf8mb4_0900_ai_ci (MySQL 8+). Fallback to utf8mb4_unicode_ci if 0900 not supported.
// Safety: supports dry-run via ?dry_run=1. Requires admin access.

require_once __DIR__ . '/../../api/config.php';

header('Content-Type: text/plain');

$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
$diagnostics = isset($_GET['diagnostics']) && $_GET['diagnostics'] == '1';
$rebuildFk = isset($_GET['rebuild_fk']) && $_GET['rebuild_fk'] == '1';
$targetCharset = 'utf8mb4';
$preferredCollations = ['utf8mb4_0900_ai_ci', 'utf8mb4_unicode_ci'];

try {
    // Determine usable collation (prefer 0900_ai_ci, else unicode_ci)
    Database::getInstance();
    $usableCollation = null;
    foreach ($preferredCollations as $coll) {
        try {
            Database::execute("SET NAMES {$targetCharset} COLLATE {$coll}");
            Database::execute("SET collation_connection = {$coll}");
            $usableCollation = $coll;
            break;
        } catch (Throwable $e) {
            // try next
        }
    }
    if (!$usableCollation) {
        throw new RuntimeException('No suitable utf8mb4 collation available.');
    }

    // Read current DB name from DSN globals set by api/config.php
    global $db; // database name
    if (empty($db)) {
        throw new RuntimeException('Database name ($db) is not set. Ensure api/config.php ran.');
    }

    echo "Target charset: {$targetCharset}\n";
    echo "Target collation: {$usableCollation}\n";
    echo $dryRun ? "Mode: DRY-RUN (no changes)\n" : "Mode: APPLY (will modify schema)\n";
    echo $diagnostics ? "Diagnostics: ON\n" : "Diagnostics: OFF\n";
    echo $rebuildFk ? "Rebuild FK plan output: ON (print-only)\n\n" : "\n";

    // Helper to fetch table/column metadata
    $getColumns = function (string $schema) {
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH AS LEN, COLLATION_NAME, CHARACTER_SET_NAME AS CHARSET
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = ?
                ORDER BY TABLE_NAME, ORDINAL_POSITION";
        $rows = Database::queryAll($sql, [$schema]);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['TABLE_NAME']][$r['COLUMN_NAME']] = $r;
        }
        return $map;
    };

    $getForeignKeys = function (string $schema) {
        $sql = "SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                  ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                WHERE rc.CONSTRAINT_SCHEMA = ?
                ORDER BY rc.TABLE_NAME, rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
        $rows = Database::queryAll($sql, [$schema]);
        $byConstraint = [];
        foreach ($rows as $r) {
            $byConstraint[$r['CONSTRAINT_NAME']][] = $r;
        }
        return $byConstraint;
    };

    // Diagnostics pass: print columns not matching target and FK mismatches
    if ($diagnostics) {
        echo "== Diagnostics ==\n";
        $cols = $getColumns($db);
        $fks  = $getForeignKeys($db);
        // Columns not matching target collation (text columns only)
        echo "\n-- Columns not using target charset/collation --\n";
        $textTypes = ['char','varchar','tinytext','text','mediumtext','longtext','enum','set'];
        $diffCount = 0;
        foreach ($cols as $table => $cmap) {
            foreach ($cmap as $name => $def) {
                $dt = strtolower((string)$def['DATA_TYPE']);
                if (!in_array($dt, $textTypes, true)) {
                    continue;
                }
                $col = (string)($def['COLLATION_NAME'] ?? '');
                $cs  = (string)($def['CHARSET'] ?? '');
                if ($cs !== $targetCharset || $col !== $usableCollation) {
                    $diffCount++;
                    echo sprintf("%s.%s: type=%s len=%s charset=%s coll=%s\n", $table, $name, $dt, $def['LEN'] ?? 'null', $cs ?: 'NULL', $col ?: 'NULL');
                }
            }
        }
        if ($diffCount === 0) {
            echo "All text columns match target.\n";
        }

        // Foreign key definition mismatches
        echo "\n-- Foreign key parent/child definition mismatches (text columns) --\n";
        $fkDiff = 0;
        foreach ($fks as $fkName => $parts) {
            foreach ($parts as $p) {
                $t  = $p['TABLE_NAME'];
                $tc = $p['COLUMN_NAME'];
                $rt = $p['REFERENCED_TABLE_NAME'];
                $rc = $p['REFERENCED_COLUMN_NAME'];
                $child = $cols[$t][$tc] ?? null;
                $parent = $cols[$rt][$rc] ?? null;
                if (!$child || !$parent) {
                    continue;
                }
                $dtChild = strtolower((string)$child['DATA_TYPE']);
                $dtParent = strtolower((string)$parent['DATA_TYPE']);
                if (!in_array($dtChild, $textTypes, true) || !in_array($dtParent, $textTypes, true)) {
                    continue;
                }
                $cMismatch = ($child['CHARSET'] ?? '') !== ($parent['CHARSET'] ?? '')
                             || ($child['COLLATION_NAME'] ?? '') !== ($parent['COLLATION_NAME'] ?? '')
                             || (string)($child['LEN'] ?? '') !== (string)($parent['LEN'] ?? '');
                if ($cMismatch) {
                    $fkDiff++;
                    echo sprintf(
                        "FK %s: %s.%s(%s/%s len=%s) -> %s.%s(%s/%s len=%s)\n",
                        $fkName,
                        $t,
                        $tc,
                        $child['CHARSET'] ?? 'NULL',
                        $child['COLLATION_NAME'] ?? 'NULL',
                        $child['LEN'] ?? 'NULL',
                        $rt,
                        $rc,
                        $parent['CHARSET'] ?? 'NULL',
                        $parent['COLLATION_NAME'] ?? 'NULL',
                        $parent['LEN'] ?? 'NULL'
                    );
                    // Print suggested ALTER for child to match parent
                    $len = $parent['LEN'] ? '(' . $parent['LEN'] . ')' : '';
                    echo "  SUGGEST: ALTER TABLE `{$t}` MODIFY `{$tc}` {$parent['DATA_TYPE']}{$len} CHARACTER SET {$parent['CHARSET']} COLLATE {$parent['COLLATION_NAME']}\n";
                }
            }
        }
        if ($fkDiff === 0) {
            echo "No FK text-column definition mismatches found.\n";
        }

        echo "\n-- End diagnostics --\n\n";
    }

    // 1) Alter database default charset/collation
    $sqlAlterDb = "ALTER DATABASE `{$db}` CHARACTER SET {$targetCharset} COLLATE {$usableCollation}";
    echo "> DB: {$sqlAlterDb}\n";
    if (!$dryRun) {
        Database::execute($sqlAlterDb);
    }

    // 2) Convert every table
    $tables = [];
    $rows = Database::queryAll('SHOW TABLES');
    foreach ($rows as $row) {
        $tables[] = array_values($row)[0];
    }

    if (!$dryRun) {
        // Temporarily disable FK checks to avoid 3780 incompatibility errors during conversion
        Database::execute('SET FOREIGN_KEY_CHECKS = 0');
    }

    foreach ($tables as $table) {
        // Convert text columns and set default table charset/collation
        $sqlConvert = "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET {$targetCharset} COLLATE {$usableCollation}";
        $sqlDefault = "ALTER TABLE `{$table}` DEFAULT CHARACTER SET {$targetCharset} COLLATE {$usableCollation}";
        echo "> Table `{$table}`: {$sqlConvert}\n";
        echo "> Table `{$table}`: {$sqlDefault}\n";
        if (!$dryRun) {
            try {
                Database::execute($sqlConvert);
            } catch (Throwable $te) {
                echo "! WARN: {$table} convert failed: " . $te->getMessage() . "\n";
            }
            try {
                Database::execute($sqlDefault);
            } catch (Throwable $te) {
                echo "! WARN: {$table} default failed: " . $te->getMessage() . "\n";
            }
        }
    }

    if (!$dryRun) {
        // Re-enable FK checks after all tables are converted
        Database::execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    echo "\nNormalization complete." . ($dryRun ? " (dry-run)" : "") . "\n";
    echo "\nTip: Re-test /admin/?section=orders after this operation.";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}
