<?php
// includes/database/helpers/DatabaseImportHelper.php

class DatabaseImportHelper
{
    /**
     * Import SQL content
     */
    public static function importSQL($sqlContent)
    {
        if (empty($sqlContent)) {
            throw new Exception('No SQL content provided');
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

        $pdo = Database::getInstance();
        
        // Validate table exists
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
        if ($stmt->rowCount() == 0) {
            throw new Exception("Table '$tableName' does not exist");
        }

        // Get table columns
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
            if (in_array($header, $columns)) {
                $columnMapping[$i] = $header;
            }
        }

        if (empty($columnMapping)) {
            throw new Exception('No matching columns found between CSV and table');
        }

        if ($replaceData) {
            $pdo->exec("DELETE FROM `$tableName`");
        }

        $mappedColumns = array_values($columnMapping);
        $placeholders = str_repeat('?,', count($mappedColumns) - 1) . '?';
        $insertSQL = "INSERT INTO `$tableName` (" . implode(',', $mappedColumns) . ") VALUES ($placeholders)";
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

        $pdo = Database::getInstance();
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tableName));
        if ($stmt->rowCount() == 0) {
            throw new Exception("Table '$tableName' does not exist");
        }

        $stmt = $pdo->query("DESCRIBE `$tableName`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
                if (in_array($field, $columns)) {
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

        $tableList = explode(',', $tables);
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
            $table = trim($table);
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
