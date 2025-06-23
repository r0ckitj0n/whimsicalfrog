<?php
// Help Tooltips API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_GET['action'] ?? 'get';
    
    switch ($action) {
        case 'get':
            // Get tooltips for a specific page or all tooltips
            $pageContext = $_GET['page_context'] ?? null;
            $elementId = $_GET['element_id'] ?? null;
            
            $sql = "SELECT * FROM help_tooltips WHERE is_active = 1";
            $params = [];
            
            if ($pageContext) {
                $sql .= " AND (page_context = ? OR page_context = 'common')";
                $params[] = $pageContext;
            }
            
            if ($elementId) {
                $sql .= " AND element_id = ?";
                $params[] = $elementId;
            }
            
            $sql .= " ORDER BY page_context, element_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tooltips = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'tooltips' => $tooltips
            ]);
            break;
            
        case 'update':
            // Update a tooltip
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['element_id']) || !isset($data['title']) || !isset($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE help_tooltips 
                SET title = ?, content = ?, position = ?, updated_at = CURRENT_TIMESTAMP
                WHERE element_id = ?
            ");
            
            $result = $stmt->execute([
                $data['title'],
                $data['content'],
                $data['position'] ?? 'top',
                $data['element_id']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update tooltip']);
            }
            break;
            
        case 'create':
            // Create a new tooltip
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['element_id']) || !isset($data['page_context']) || 
                !isset($data['title']) || !isset($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO help_tooltips (element_id, page_context, title, content, position)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['element_id'],
                $data['page_context'],
                $data['title'],
                $data['content'],
                $data['position'] ?? 'top'
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create tooltip']);
            }
            break;
            
        case 'delete':
            // Delete a tooltip (soft delete by setting is_active = 0)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $elementId = $_POST['element_id'] ?? null;
            if (!$elementId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Element ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE help_tooltips SET is_active = 0 WHERE element_id = ?");
            $result = $stmt->execute([$elementId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip deactivated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to deactivate tooltip']);
            }
            break;
            
        case 'toggle':
            // Toggle tooltip active status
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'POST method required']);
                exit;
            }
            
            $elementId = $_POST['element_id'] ?? null;
            if (!$elementId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Element ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE help_tooltips SET is_active = NOT is_active WHERE element_id = ?");
            $result = $stmt->execute([$elementId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Tooltip status toggled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to toggle tooltip status']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 