<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Check if admin token is provided
if (!isset($_POST['admin_token']) || $_POST['admin_token'] !== 'whimsical_admin_2024') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    // Create database connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
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
    $success_count = count(array_filter($results, function($result) {
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