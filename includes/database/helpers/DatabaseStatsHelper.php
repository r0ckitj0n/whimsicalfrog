<?php
// includes/database/helpers/DatabaseStatsHelper.php

class DatabaseStatsHelper
{
    /**
     * Get database connection statistics
     */
    public static function getConnectionStats()
    {
        try {
            Database::getInstance();
            global $db;

            $connections = Database::queryOne("SHOW STATUS LIKE 'Threads_connected'") ?? [];
            $maxConnections = Database::queryOne("SHOW VARIABLES LIKE 'max_connections'") ?? [];

            $sizeInfo = Database::queryOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS database_size_mb
                 FROM information_schema.tables 
                 WHERE table_schema = " . Database::getInstance()->quote($db)
            ) ?? [];

            $tableCount = Database::queryOne(
                "SELECT COUNT(*) as table_count 
                 FROM information_schema.tables 
                 WHERE table_schema = " . Database::getInstance()->quote($db)
            ) ?? [];

            return [
                'success' => true,
                'stats' => [
                    'current_connections' => $connections['Value'] ?? null,
                    'max_connections' => $maxConnections['Value'] ?? null,
                    'database_size_mb' => $sizeInfo['database_size_mb'] ?? null,
                    'table_count' => $tableCount['table_count'] ?? null,
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get connection stats: ' . $e->getMessage());
        }
    }

    /**
     * Analyze database size
     */
    public static function analyzeDatabaseSize()
    {
        try {
            Database::getInstance();
            global $db;
            $quotedDb = Database::getInstance()->quote($db);

            $sizeInfo = Database::queryOne("
                SELECT 
                    ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_size_mb,
                    ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_size_mb,
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb
                FROM information_schema.tables 
                WHERE table_schema = $quotedDb
            ");

            $largestTable = Database::queryOne("
                SELECT 
                    table_name,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = $quotedDb
                ORDER BY (data_length + index_length) DESC
                LIMIT 1
            ");

            return [
                'success' => true,
                'total_size' => ($sizeInfo['total_size_mb'] ?? 0) . ' MB',
                'data_size' => ($sizeInfo['data_size_mb'] ?? 0) . ' MB',
                'index_size' => ($sizeInfo['index_size_mb'] ?? 0) . ' MB',
                'largest_table' => ($largestTable['table_name'] ?? 'Unknown') . ' (' . ($largestTable['size_mb'] ?? 0) . ' MB)'
            ];
        } catch (Exception $e) {
            throw new Exception('Database size analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Monitor database performance
     */
    public static function performanceMonitor()
    {
        try {
            Database::getInstance();

            $connections = Database::queryOne("SHOW STATUS LIKE 'Threads_connected'") ?? [];
            $slowLogStatus = Database::queryOne("SHOW VARIABLES LIKE 'slow_query_log'") ?? [];
            $uptime = Database::queryOne("SHOW STATUS LIKE 'Uptime'") ?? [];
            $hits = Database::queryOne("SHOW STATUS LIKE 'Qcache_hits'") ?? [];
            $selects = Database::queryOne("SHOW STATUS LIKE 'Com_select'") ?? [];

            $hitRate = 'N/A';
            if ($hits && $selects && (intval($hits['Value'] ?? 0) + intval($selects['Value'] ?? 0)) > 0) {
                $hitRate = round((intval($hits['Value']) / (intval($hits['Value']) + intval($selects['Value']))) * 100, 2) . '%';
            }

            return [
                'success' => true,
                'connections' => $connections['Value'] ?? null,
                'slow_queries' => (($slowLogStatus['Value'] ?? 'OFF') === 'ON') ? 'Enabled' : 'Disabled',
                'cache_hit_rate' => $hitRate,
                'uptime_seconds' => $uptime['Value'] ?? null,
                'avg_query_time' => 'N/A'
            ];
        } catch (Exception $e) {
            throw new Exception('Performance monitoring failed: ' . $e->getMessage());
        }
    }
}
