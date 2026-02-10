<?php
// includes/database/helpers/DatabaseImportHelper.php

class DatabaseImportHelper
{
    private static function isValidIdentifier($value)
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    private static function tableExists(PDO $pdo, $tableName)
    {
        if (!self::isValidIdentifier($tableName)) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $stmt->execute([$tableName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ((int)($row['c'] ?? 0)) > 0;
    }

    private static function getTableColumns(PDO $pdo, $tableName)
    {
        if (!self::isValidIdentifier($tableName)) {
            throw new Exception('Invalid table name');
        }
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $validColumns = [];
        foreach ($columns as $column) {
            $columnName = (string)$column;
            if (self::isValidIdentifier($columnName)) {
                $validColumns[] = $columnName;
            }
        }
        return $validColumns;
    }

    /**
     * Import SQL content
     */
    public static function importSQL($sqlContent)
    {
        if (empty($sqlContent)) {
            throw new Exception('No SQL content provided');
        }
        if (!is_string($sqlContent) || strlen($sqlContent) > 5_000_000) {
            throw new Exception('SQL payload is too large');
        }
        $forbiddenPatterns = [
            '/\bINTO\s+OUTFILE\b/i',
            '/\bLOAD_FILE\s*\(/i',
            '/\bLOAD\s+DATA\b/i',
            '/\bINFILE\b/i',
            '/\bGRANT\b/i',
            '/\bREVOKE\b/i',
            '/\bCREATE\s+USER\b/i',
            '/\bDROP\s+USER\b/i',
            '/\bSET\s+GLOBAL\b/i',
            '/\bINSTALL\s+PLUGIN\b/i',
            '/\bUNINSTALL\s+PLUGIN\b/i'
        ];
        foreach ($forbiddenPatterns as $pattern) {
            if (preg_match($pattern, $sqlContent) === 1) {
                throw new Exception('SQL contains forbidden operations');
            }
        }

        $pdo = Database::getInstance();
        $statements = explode(';', $sqlContent);
        $statementsExecuted = 0;
        $totalRowsAffected = 0;
        $warnings = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || str_starts_with($statement, '--') || str_starts_with($statement, '/*')) {
                continue;
            }

            try {
                $stmt = $pdo->prepare($statement);
                $stmt->execute();
                $statementsExecuted++;
                $totalRowsAffected += $stmt->rowCount();
            } catch (Exception $e) {
                $warnings[] = "Statement failed: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'statements_executed' => $statementsExecuted,
            'rows_affected' => $totalRowsAffected,
            'warnings' => !empty($warnings) ? implode('; ', $warnings) : null
        ];
    }

    /**
     * Import CSV content into a table
     */
    public static function importCSV($input)
    {
        $tableName = $input['table_name'] ?? '';
        $csvContent = $input['csv_content'] ?? '';
        $hasHeaders = $input['has_headers'] ?? true;
        $replaceData = $input['replace_data'] ?? false;

        if (empty($tableName) || empty($csvContent)) {
            throw new Exception('Table name and CSV content are required');
        }
        if (!self::isValidIdentifier($tableName)) {
            throw new Exception('Invalid table name');
        }
        if (!is_string($csvContent) || strlen($csvContent) > 5_000_000) {
            throw new Exception('CSV payload is too large');
        }

        $pdo = Database::getInstance();
        if (!self::tableExists($pdo, $tableName)) {
            throw new Exception("Table '$tableName' does not exist");
        }

        // Get table columns
        $columns = self::getTableColumns($pdo, $tableName);

        $lines = str_getcsv($csvContent, "\n");
        $rowsImported = 0;
        $skippedRows = 0;

        $headers = null;
        if ($hasHeaders && count($lines) > 0) {
            $headers = str_getcsv(array_shift($lines));
        } else {
            $headers = $columns;
        }

        $columnMapping = [];
        foreach ($headers as $i => $header) {
            $columnName = trim((string)$header);
            if (self::isValidIdentifier($columnName) && in_array($columnName, $columns, true)) {
                $columnMapping[$i] = $columnName;
            }
        }

        if (empty($columnMapping)) {
            throw new Exception('No matching columns found between CSV and table');
        }

        if ($replaceData) {
            $pdo->exec("DELETE FROM `$tableName`");
        }

        $mappedColumns = array_values(array_unique($columnMapping));
        $placeholders = str_repeat('?,', count($mappedColumns) - 1) . '?';
        $quotedColumns = array_map(static fn($c) => "`$c`", $mappedColumns);
        $insertSQL = "INSERT INTO `$tableName` (" . implode(',', $quotedColumns) . ") VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($insertSQL);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $values = [];
            foreach ($columnMapping as $csvIndex => $dbColumn) {
                $values[] = $row[$csvIndex] ?? null;
            }

            try {
                $insertStmt->execute($values);
                $rowsImported++;
            } catch (Exception $e) {
                $skippedRows++;
            }
        }

        return [
            'success' => true,
            'rows_imported' => $rowsImported,
            'columns_mapped' => count($columnMapping),
            'skipped_rows' => $skippedRows > 0 ? $skippedRows : null
        ];
    }

    /**
     * Import JSON content into a table
     */
    public static function importJSON($input)
    {
        $tableName = $input['table_name'] ?? '';
        $jsonContent = $input['json_content'] ?? '';

        if (empty($tableName) || empty($jsonContent)) {
            throw new Exception('Table name and JSON content are required');
        }
        if (!self::isValidIdentifier($tableName)) {
            throw new Exception('Invalid table name');
        }
        if (!is_string($jsonContent) || strlen($jsonContent) > 5_000_000) {
            throw new Exception('JSON payload is too large');
        }

        $pdo = Database::getInstance();
        if (!self::tableExists($pdo, $tableName)) {
            throw new Exception("Table '$tableName' does not exist");
        }

        $columns = self::getTableColumns($pdo, $tableName);

        $jsonData = json_decode($jsonContent, true);
        if ($jsonData === null) throw new Exception('Invalid JSON format');
        if (!is_array($jsonData)) throw new Exception('JSON must be an array of objects');

        $recordsImported = 0;
        $validationErrors = 0;
        $fieldsMapping = [];

        foreach ($jsonData as $record) {
            if (!is_array($record)) {
                $validationErrors++;
                continue;
            }

            $mappedFields = [];
            $values = [];
            foreach ($record as $field => $value) {
                if (self::isValidIdentifier($field) && in_array($field, $columns, true)) {
                    $mappedFields[] = "`$field`";
                    $values[] = $value;
                    $fieldsMapping[$field] = true;
                }
            }

            if (empty($mappedFields)) {
                $validationErrors++;
                continue;
            }

            $placeholders = str_repeat('?,', count($mappedFields) - 1) . '?';
            $insertSQL = "INSERT INTO `$tableName` (" . implode(',', $mappedFields) . ") VALUES ($placeholders)";

            try {
                $stmt = $pdo->prepare($insertSQL);
                $stmt->execute($values);
                $recordsImported++;
            } catch (Exception $e) {
                $validationErrors++;
            }
        }

        return [
            'success' => true,
            'records_imported' => $recordsImported,
            'fields_mapped' => count($fieldsMapping),
            'validation_errors' => $validationErrors > 0 ? $validationErrors : null
        ];
    }

    /**
     * Export tables as SQL
     */
    public static function exportTables($tables)
    {
        if (empty($tables)) {
            throw new Exception('No tables specified for export');
        }

        $tableList = array_filter(array_map('trim', explode(',', (string)$tables)));
        if (count($tableList) === 0 || count($tableList) > 50) {
            throw new Exception('Invalid table selection');
        }
        $pdo = Database::getInstance();
        global $db;

        // Set headers for file download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="database_export_' . date('Y-m-d_H-i-s') . '.sql"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "-- Database Export\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Database: $db\n";
        echo "-- Tables: " . implode(', ', $tableList) . "\n\n";

        foreach ($tableList as $table) {
            if (!self::isValidIdentifier($table)) {
                throw new Exception('Invalid table name in export list');
            }
            if (!self::tableExists($pdo, $table)) {
                throw new Exception("Table '$table' does not exist");
            }
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

            echo "-- Table structure for `$table`\n";
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $createTable['Create Table'] . ";\n\n";

            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                echo "-- Data for table `$table`\n";
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                echo "INSERT INTO `$table` ($columnList) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escapedRow = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    $values[] = '(' . implode(', ', $escapedRow) . ')';
                }
                echo implode(",\n", $values) . ";\n\n";
            }
        }
        exit;
    }
}
