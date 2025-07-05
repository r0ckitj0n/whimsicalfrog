<?php
// Popup Sales Lingo API
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

// Start session for authentication
session_start();

// Authentication check for admin actions
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Parse action from GET, POST, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // If no action found in GET/POST, try parsing from JSON body
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }
    
    switch ($action) {
        case 'get_random':
            $category = $_GET['category'] ?? 'general';
            $limit = (int)($_GET['limit'] ?? 1);
            $minPriority = (int)($_GET['min_priority'] ?? 1);
            
            $stmt = $pdo->prepare("
                SELECT id, category, message, priority
                FROM popup_sales_lingo 
                WHERE category = ? AND is_active = 1 AND priority >= ?
                ORDER BY RAND() 
                LIMIT ?
            ");
            $stmt->execute([$category, $minPriority, $limit]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_all_categories':
            $stmt = $pdo->prepare("
                SELECT DISTINCT category, COUNT(*) as message_count
                FROM popup_sales_lingo 
                WHERE is_active = 1
                GROUP BY category
                ORDER BY category
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;
            
        case 'get_by_category':
            $category = $_GET['category'] ?? 'general';
            
            $stmt = $pdo->prepare("
                SELECT id, category, message, priority, is_active
                FROM popup_sales_lingo 
                WHERE category = ?
                ORDER BY priority DESC, message ASC
            ");
            $stmt->execute([$category]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'get_all':
            // Admin only
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, category, message, priority, is_active, created_at, updated_at
                FROM popup_sales_lingo 
                ORDER BY category, priority DESC, message ASC
            ");
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
            break;
            
        case 'add':
            // Admin only
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $category = trim($data['category'] ?? 'general');
            $message = trim($data['message'] ?? '');
            $priority = (int)($data['priority'] ?? 1);
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO popup_sales_lingo (category, message, priority) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$category, $message, $priority]);
            
            $messageId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Sales message added successfully',
                'message_id' => $messageId
            ]);
            break;
            
        case 'update':
            // Admin only
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $messageId = (int)($data['message_id'] ?? 0);
            $category = trim($data['category'] ?? 'general');
            $message = trim($data['message'] ?? '');
            $priority = (int)($data['priority'] ?? 1);
            $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
            
            if (empty($message)) {
                throw new Exception('Message is required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE popup_sales_lingo 
                SET category = ?, message = ?, priority = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$category, $message, $priority, $isActive, $messageId]);
            
            echo json_encode(['success' => true, 'message' => 'Sales message updated successfully']);
            break;
            
        case 'delete':
            // Admin only
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $messageId = (int)($_GET['message_id'] ?? $_POST['message_id'] ?? 0);
            
            if ($messageId <= 0) {
                throw new Exception('Valid message ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM popup_sales_lingo WHERE id = ?");
            $stmt->execute([$messageId]);
            
            echo json_encode(['success' => true, 'message' => 'Sales message deleted successfully']);
            break;
            
        case 'toggle_active':
            // Admin only
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            $messageId = (int)($_GET['message_id'] ?? $_POST['message_id'] ?? 0);
            
            if ($messageId <= 0) {
                throw new Exception('Valid message ID is required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE popup_sales_lingo 
                SET is_active = 1 - is_active, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$messageId]);
            
            echo json_encode(['success' => true, 'message' => 'Message status updated successfully']);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 