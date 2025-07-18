<?php
// Help Tooltips API - TEST VERSION (bypasses authentication)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once __DIR__ . '/../includes/functions.php';

// BYPASS AUTHENTICATION FOR TESTING
// session_start();
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     Response::forbidden('Admin access required');
// }

try {
    $pdo = Database::getInstance();

    $action = $_GET['action'] ?? 'get';

    switch ($action) {
        case 'get_stats':
            // Return that tooltips are globally enabled
            Response::success([
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

            Response::success([
                'tooltips' => $tooltips,
                'count' => count($tooltips),
                'page_context' => $pageContext
            ]);
            break;

        case 'create':
            // Create new tooltip
            $data = Response::getJsonInput();

            if (!$data || !isset($data['element_id'], $data['title'], $data['content'])) {
                Response::error('Missing required fields');
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
                Response::success([
                    'id' => $pdo->lastInsertId()
                ], 'Tooltip created successfully');
            } else {
                Response::serverError('Failed to create tooltip');
            }
            break;

        case 'update':
            // Update existing tooltip
            $data = Response::getJsonInput();
            $id = $_GET['id'] ?? $data['id'] ?? null;

            if (!$id || !$data) {
                Response::error('Missing ID or data');
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
                Response::error('No fields to update');
            }

            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE help_tooltips SET " . implode(', ', $fields) . " WHERE id = ?");

            if ($stmt->execute($values)) {
                Response::success(null, 'Tooltip updated successfully');
            } else {
                Response::serverError('Failed to update tooltip');
            }
            break;

        case 'delete':
            // Delete tooltip
            $id = $_GET['id'] ?? null;

            if (!$id) {
                Response::error('Missing tooltip ID');
            }

            $stmt = $pdo->prepare("DELETE FROM help_tooltips WHERE id = ?");

            if ($stmt->execute([$id])) {
                Response::success(null, 'Tooltip deleted successfully');
            } else {
                Response::serverError('Failed to delete tooltip');
            }
            break;

        default:
            Response::error('Invalid action');
    }

} catch (Exception $e) {
    Logger::exception($e, 'Help tooltips API error');
    Response::serverError('Server error occurred');
}
?> 