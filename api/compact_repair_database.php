<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication with fallback token support like cleanup_system.php
if (session_status() === PHP_SESSION_NONE) {
    
}

// Check if user is admin or has valid admin token
if (!isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    // Create database connection using centralized Database class
    $pdo = Database::getInstance();

    // Get list of all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];
    $processed_count = 0;
    $errors = [];

    foreach ($tables as $table) {
        try {
            // Run OPTIMIZE TABLE
            $stmt = $pdo->query("OPTIMIZE TABLE `$table`");
            $optimize_result = $stmt->fetch();

            // Run REPAIR TABLE
            $stmt = $pdo->query("REPAIR TABLE `$table`");
            $repair_result = $stmt->fetch();

            $results[$table] = [
                'optimize' => $optimize_result,
                'repair' => $repair_result,
                'status' => 'success'
            ];

            $processed_count++;

        } catch (Exception $e) {
            $results[$table] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
            $errors[] = "Table $table: " . $e->getMessage();
        }
    }

    // Calculate statistics
    $success_count = count(array_filter($results, function ($result) {
        return $result['status'] === 'success';
    }));

    $error_count = count($errors);

    echo json_encode([
        'success' => true,
        'tables_processed' => $processed_count,
        'tables_total' => count($tables),
        'success_count' => $success_count,
        'error_count' => $error_count,
        'results' => $results,
        'errors' => $errors,
        'message' => "Database optimization complete. $success_count tables optimized successfully" .
                    ($error_count > 0 ? ", $error_count errors encountered" : "")
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
}
?> 