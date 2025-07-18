<?php
// Get room categories API - returns categories for a specific room number
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    // Fallback response if database connection fails
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'primary_category' => null,
        'all_categories' => []
    ]);
    exit;
}

$roomNumber = $_GET['room_number'] ?? null;

if ($roomNumber === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Room number is required']);
    exit;
}

try {
    // Get all categories for the room, ordered by primary first
    $stmt = $pdo->prepare("
        SELECT rca.*, c.name as category_name, c.description as category_description
        FROM room_category_assignments rca 
        JOIN categories c ON rca.category_id = c.id 
        WHERE rca.room_number = ? 
        ORDER BY rca.is_primary DESC, rca.display_order ASC, c.name ASC
    ");
    $stmt->execute([$roomNumber]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $primaryCategory = null;
    $allCategories = [];
    $roomName = '';

    foreach ($assignments as $assignment) {
        $allCategories[] = [
            'id' => $assignment['category_id'],
            'name' => $assignment['category_name'],
            'description' => $assignment['category_description'],
            'is_primary' => (bool)$assignment['is_primary'],
            'display_order' => $assignment['display_order']
        ];

        if ($assignment['is_primary']) {
            $primaryCategory = [
                'id' => $assignment['category_id'],
                'name' => $assignment['category_name'],
                'description' => $assignment['category_description']
            ];
        }

        if (empty($roomName)) {
            $roomName = $assignment['room_name'];
        }
    }

    echo json_encode([
        'success' => true,
        'room_number' => (int)$roomNumber,
        'room_name' => $roomName,
        'primary_category' => $primaryCategory,
        'all_categories' => $allCategories,
        'total_categories' => count($allCategories)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'primary_category' => null,
        'all_categories' => []
    ]);
}
?> 