<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance();

    // Ensure table exists
    $createTableQuery = "CREATE TABLE IF NOT EXISTS badge_content (
        id INT AUTO_INCREMENT PRIMARY KEY,
        badge_type VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        weight DECIMAL(3,2) DEFAULT 1.0,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($createTableQuery);

    switch ($action) {
        case 'get_all':
            $badgeType = $_GET['badge_type'] ?? '';
            $query = "SELECT * FROM badge_content";
            $params = [];

            if (!empty($badgeType)) {
                $query .= " WHERE badge_type = ?";
                $params[] = $badgeType;
            }

            $query .= " ORDER BY badge_type, weight DESC, content";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $badges = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'badges' => $badges,
                'count' => count($badges)
            ]);
            break;

        case 'get_types':
            $query = "SELECT DISTINCT badge_type FROM badge_content ORDER BY badge_type";
            $stmt = $db->query($query);
            $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'types' => $types
            ]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for create action');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $badgeType = $input['badge_type'] ?? '';
            $content = $input['content'] ?? '';
            $weight = floatval($input['weight'] ?? 1.0);
            $active = isset($input['active']) ? (bool)$input['active'] : true;

            if (empty($badgeType) || empty($content)) {
                throw new Exception('Badge type and content are required');
            }

            $query = "INSERT INTO badge_content (badge_type, content, weight, active) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$badgeType, $content, $weight, $active]);

            $newId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Badge content created successfully',
                'id' => $newId
            ]);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for update action');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);
            $badgeType = $input['badge_type'] ?? '';
            $content = $input['content'] ?? '';
            $weight = floatval($input['weight'] ?? 1.0);
            $active = isset($input['active']) ? (bool)$input['active'] : true;

            if ($id <= 0 || empty($badgeType) || empty($content)) {
                throw new Exception('ID, badge type, and content are required');
            }

            $query = "UPDATE badge_content SET badge_type = ?, content = ?, weight = ?, active = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$badgeType, $content, $weight, $active, $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Badge content updated successfully'
            ]);
            break;

        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for delete action');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Valid ID is required');
            }

            $query = "DELETE FROM badge_content WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Badge content deleted successfully'
            ]);
            break;

        case 'toggle_active':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for toggle action');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $id = intval($input['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Valid ID is required');
            }

            $query = "UPDATE badge_content SET active = NOT active WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Badge status toggled successfully'
            ]);
            break;

        case 'get_stats':
            $statsQuery = "SELECT 
                badge_type,
                COUNT(*) as total_count,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_count,
                AVG(weight) as avg_weight,
                MAX(weight) as max_weight,
                MIN(weight) as min_weight
                FROM badge_content 
                GROUP BY badge_type
                ORDER BY badge_type";

            $stmt = $db->query($statsQuery);
            $stats = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;

        case 'bulk_update_weights':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required for bulk update');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $updates = $input['updates'] ?? [];

            if (empty($updates)) {
                throw new Exception('Updates array is required');
            }

            $db->beginTransaction();

            try {
                $query = "UPDATE badge_content SET weight = ? WHERE id = ?";
                $stmt = $db->prepare($query);

                foreach ($updates as $update) {
                    $id = intval($update['id'] ?? 0);
                    $weight = floatval($update['weight'] ?? 1.0);

                    if ($id > 0) {
                        $stmt->execute([$weight, $id]);
                    }
                }

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Weights updated successfully',
                    'updated_count' => count($updates)
                ]);

            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 