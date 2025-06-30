<?php
// Help Tooltips API - TEST VERSION (bypasses authentication)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// BYPASS AUTHENTICATION FOR TESTING
// session_start();
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'message' => 'Admin access required']);
//     exit;
// }

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $action = $_GET['action'] ?? 'get';
    
    switch ($action) {
        case 'get_stats':
            // Return that tooltips are globally enabled
            echo json_encode([
                'success' => true,
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
            
            echo json_encode([
                'success' => true,
                'tooltips' => $tooltips,
                'count' => count($tooltips),
                'page_context' => $pageContext
            ]);
            break;
            
        case 'create':
            // Create new tooltip
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['element_id'], $data['title'], $data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
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
                echo json_encode([
                    'success' => true,
                    'message' => 'Tooltip created successfully',
                    'id' => $pdo->lastInsertId()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create tooltip']);
            }
            break;
            
        case 'update':
            // Update existing tooltip
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$id || !$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing ID or data']);
                exit;
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
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }
            
            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE help_tooltips SET " . implode(', ', $fields) . " WHERE id = ?");
            
            if ($stmt->execute($values)) {
                echo json_encode(['success' => true, 'message' => 'Tooltip updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update tooltip']);
            }
            break;
            
        case 'delete':
            // Delete tooltip
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing tooltip ID']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM help_tooltips WHERE id = ?");
            
            if ($stmt->execute([$id])) {
                echo json_encode(['success' => true, 'message' => 'Tooltip deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete tooltip']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 