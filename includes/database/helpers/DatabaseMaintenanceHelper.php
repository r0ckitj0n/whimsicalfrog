<?php
// includes/database/helpers/DatabaseMaintenanceHelper.php

class DatabaseMaintenanceHelper
{
    /**
     * Optimize all tables in the database
     */
    public static function optimizeTables()
    {
        try {
            $pdo = Database::getInstance();
            global $db;

            $tables = array_column(Database::queryAll("SHOW TABLES FROM " . $pdo->quote($db)), 0);
            $optimizedCount = 0;
            $details = [];

            foreach ($tables as $table) {
                $result = Database::queryOne("OPTIMIZE TABLE `$table`") ?? [];
                $msg = $result['Msg_text'] ?? ($result['Message_text'] ?? 'OK');
                $details[] = "$table: " . $msg;
                $optimizedCount++;
            }

            return [
                'success' => true,
                'tables_optimized' => $optimizedCount,
                'details' => implode('; ', $details)
            ];
        } catch (Exception $e) {
            throw new Exception('Table optimization failed: ' . $e->getMessage());
        }
    }

    /**
     * Analyze indexes and check for missing indexes
     */
    public static function analyzeIndexes()
    {
        try {
            $pdo = Database::getInstance();
            global $db;
            $quotedDb = $pdo->quote($db);

            $indexes = Database::queryAll("
                SELECT table_name, index_name, cardinality, index_type
                FROM information_schema.statistics 
                WHERE table_schema = $quotedDb
                ORDER BY table_name, index_name
            ");

            $rowsNoIdx = Database::queryAll("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = $quotedDb
                AND table_name NOT IN (
                    SELECT DISTINCT table_name 
                    FROM information_schema.statistics 
                    WHERE table_schema = $quotedDb
                )
            ");
            
            $noIndexTables = array_column($rowsNoIdx, 'table_name');
            $recommendations = !empty($noIndexTables) ? ["Tables without indexes: " . implode(', ', $noIndexTables)] : [];

            return [
                'success' => true,
                'indexes_analyzed' => count($indexes),
                'recommendations' => implode('; ', $recommendations) ?: 'All tables have appropriate indexes'
            ];
        } catch (Exception $e) {
            throw new Exception('Index analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup orphaned records from various tables
     */
    public static function cleanupDatabase()
    {
        try {
            $orphanedRecords = 0;

            // Remove orphaned order items
            $affected = Database::execute("
                DELETE oi FROM order_items oi 
                LEFT JOIN orders o ON oi.order_id = o.id 
                WHERE o.id IS NULL
            ");
            $orphanedRecords += ($affected > 0 ? $affected : 0);

            // Remove orphaned item images
            $affected2 = Database::execute("
                DELETE ii FROM item_images ii 
                LEFT JOIN items i ON ii.sku = i.sku 
                WHERE i.sku IS NULL
            ");
            $orphanedRecords += ($affected2 > 0 ? $affected2 : 0);

            return [
                'success' => true,
                'orphaned_records' => $orphanedRecords,
                'temp_files' => 0
            ];
        } catch (Exception $e) {
            throw new Exception('Database cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Repair all tables in the database
     */
    public static function repairTables()
    {
        try {
            $pdo = Database::getInstance();
            global $db;

            $tables = array_column(Database::queryAll("SHOW TABLES FROM " . $pdo->quote($db)), 0);
            $repairedCount = 0;
            $details = [];

            foreach ($tables as $table) {
                $result = Database::queryOne("REPAIR TABLE `$table`") ?? [];
                $msg = $result['Msg_text'] ?? ($result['Message_text'] ?? 'OK');
                $details[] = "$table: " . $msg;
                $repairedCount++;
            }

            return [
                'success' => true,
                'tables_repaired' => $repairedCount,
                'details' => implode('; ', $details)
            ];
        } catch (Exception $e) {
            throw new Exception('Table repair failed: ' . $e->getMessage());
        }
    }

    /**
     * Check foreign key integrity across the database
     */
    public static function checkForeignKeys()
    {
        try {
            $pdo = Database::getInstance();
            global $db;
            $quotedDb = $pdo->quote($db);

            $foreignKeys = Database::queryAll("
                SELECT table_name, constraint_name, column_name, referenced_table_name, referenced_column_name
                FROM information_schema.key_column_usage 
                WHERE table_schema = $quotedDb
                AND referenced_table_name IS NOT NULL
            ");

            $keysChecked = count($foreignKeys);
            $issuesFound = 0;

            foreach ($foreignKeys as $fk) {
                if (!isset($fk['table_name']) || !isset($fk['referenced_table_name']) ||
                    !isset($fk['column_name']) || !isset($fk['referenced_column_name'])) {
                    continue;
                }

                try {
                    $sql = "
                        SELECT COUNT(*) as orphaned_count
                        FROM `{$fk['table_name']}` t1
                        LEFT JOIN `{$fk['referenced_table_name']}` t2 
                        ON t1.`{$fk['column_name']}` = t2.`{$fk['referenced_column_name']}`
                        WHERE t1.`{$fk['column_name']}` IS NOT NULL 
                        AND t2.`{$fk['referenced_column_name']}` IS NULL
                    ";

                    $result = Database::queryOne($sql);
                    if ($result && isset($result['orphaned_count']) && $result['orphaned_count'] > 0) {
                        $issuesFound += $result['orphaned_count'];
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            return [
                'success' => true,
                'keys_checked' => $keysChecked,
                'issues_found' => $issuesFound
            ];
        } catch (Exception $e) {
            throw new Exception('Foreign key check failed: ' . $e->getMessage());
        }
    }
}
