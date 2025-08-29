<?php
// Help Tooltips API - TEST VERSION (bypasses authentication)
<<<<<<< HEAD
header('Content-Type: application/json');
=======
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
<<<<<<< HEAD
=======
require_once __DIR__ . '/../includes/functions.php';
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)

// BYPASS AUTHENTICATION FOR TESTING
// session_start();
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
<<<<<<< HEAD
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Admin access required']);
//     exit;
// }

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
=======
//     Response::forbidden('Admin access required');
// }

try {
    $pdo = Database::getInstance();
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    
    $action = $_GET['action'] ?? 'get';
    
    switch ($action) {
        case 'get_stats':
            // Return that tooltips are globally enabled
<<<<<<< HEAD
            echo json_encode([
                'success' => true,
=======
            Response::success([
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                'global_enabled' => true,
                'total_tooltips' => 14,
                'active_tooltips' => 14
            ]);
            break;
            
        case 'get':
        case 'get_tooltips':
            // Get tooltips for a specific page context
            $pageContext = $_GET['page_context'] ?? $_GET['page'] ?? 'admin';
            
            $stmt = $pdo->prepare("
                SELECT id, element_id, page_context, title, content, position, is_active, created_at, updated_at
                FROM help_tooltips 
                WHERE page_context = ? AND is_active = 1 
                ORDER BY element_id
            ");
            $stmt->execute([$pageContext]);
            $tooltips = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
<<<<<<< HEAD
            echo json_encode([
                'success' => true,
=======
            Response::success([
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                'tooltips' => $tooltips,
                'count' => count($tooltips),
                'page_context' => $pageContext
            ]);
            break;
            
        case 'create':
            // Create new tooltip
<<<<<<< HEAD
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['element_id'], $data['title'], $data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
=======
            $data = Response::getJsonInput();
            
            if (!$data || !isset($data['element_id'], $data['title'], $data['content'])) {
                Response::error('Missing required fields');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $result = $stmt->execute([
                $data['element_id'],
                $data['page_context'] ?? 'admin',
                $data['title'],
                $data['content'],
                $data['position'] ?? 'top'
            ]);
            
            if ($result) {
<<<<<<< HEAD
                echo json_encode([
                    'success' => true,
                    'message' => 'Tooltip created successfully',
                    'id' => $pdo->lastInsertId()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create tooltip']);
=======
                Response::success([
                    'id' => $pdo->lastInsertId()
                ], 'Tooltip created successfully');
            } else {
                Response::serverError('Failed to create tooltip');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            break;
            
        case 'update':
            // Update existing tooltip
<<<<<<< HEAD
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$id || !$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing ID or data']);
                exit;
=======
            $data = Response::getJsonInput();
            $id = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$id || !$data) {
                Response::error('Missing ID or data');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            
            $fields = [];
            $values = [];
            
            foreach (['element_id', 'page_context', 'title', 'content', 'position', 'is_active'] as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
<<<<<<< HEAD
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
=======
                Response::error('No fields to update');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE help_tooltips SET " . implode(', ', $fields) . " WHERE id = ?");
            
            if ($stmt->execute($values)) {
<<<<<<< HEAD
                echo json_encode(['success' => true, 'message' => 'Tooltip updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update tooltip']);
=======
                Response::success(null, 'Tooltip updated successfully');
            } else {
                Response::serverError('Failed to update tooltip');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            break;
            
        case 'delete':
            // Delete tooltip
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
<<<<<<< HEAD
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing tooltip ID']);
                exit;
=======
                Response::error('Missing tooltip ID');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            
            $stmt = $pdo->prepare("DELETE FROM help_tooltips WHERE id = ?");
            
            if ($stmt->execute([$id])) {
<<<<<<< HEAD
                echo json_encode(['success' => true, 'message' => 'Tooltip deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete tooltip']);
=======
                Response::success(null, 'Tooltip deleted successfully');
            } else {
                Response::serverError('Failed to delete tooltip');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }
            break;
            
        default:
<<<<<<< HEAD
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
=======
            Response::error('Invalid action');
    }
    
} catch (Exception $e) {
    Logger::exception($e, 'Help tooltips API error');
    Response::serverError('Server error occurred');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
}
?> 