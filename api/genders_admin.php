<?php
// Admin Genders Management API (catalog-wide)
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Enforce admin auth using centralized helper
AuthHelper::requireAdmin();

try {
    Database::getInstance();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'list_distinct':
            $rows = Database::queryAll("SELECT DISTINCT gender FROM item_genders WHERE gender <> '' ORDER BY gender ASC");
            $genders = array_values(array_filter(array_map(fn($r) => trim((string)($r['gender'] ?? '')), $rows), fn($v) => $v !== ''));
            echo json_encode(['success' => true, 'genders' => $genders]);
            break;

        case 'rename':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $old = trim((string)($data['old'] ?? ''));
            $new = trim((string)($data['new'] ?? ''));
            if ($old === '' || $new === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Both old and new names are required']); break; }
            if (strcasecmp($old, $new) === 0) { echo json_encode(['success' => true, 'updated' => 0]); break; }
            $updated = Database::execute("UPDATE item_genders SET gender = ? WHERE gender = ?", [$new, $old]);
            echo json_encode(['success' => true, 'updated' => (int)$updated]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Gender name required']); break; }
            $deleted = Database::execute("DELETE FROM item_genders WHERE gender = ?", [$name]);
            echo json_encode(['success' => true, 'deleted' => (int)$deleted]);
            break;

        case 'create':
            // Create a new gender value in reference table if available; otherwise attempt to seed via item_genders
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $name = trim((string)($data['name'] ?? ''));
            $display = trim((string)($data['display_name'] ?? $name));
            if ($name === '') { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Gender name required']); break; }
            // Try genders reference first
            $createdRef = 0;
            try {
                // Check if table genders exists
                $exists = Database::queryOne("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'genders'");
                if ($exists && (int)($exists['c'] ?? 0) > 0) {
                    $createdRef = Database::execute("INSERT IGNORE INTO genders (gender, display_name, is_active) VALUES (?, ?, 1)", [$name, $display]);
                }
            } catch (Throwable $e) { /* ignore */ }
            // Also ensure at least one presence in item_genders for visibility in older UIs
            try {
                // Use a special placeholder SKU to avoid impacting real items
                Database::execute("INSERT INTO item_genders (item_sku, gender, is_active, created_at) VALUES ('__GLOBAL__', ?, 1, NOW()) ON DUPLICATE KEY UPDATE gender = VALUES(gender)", [$name]);
            } catch (Throwable $e) { /* ignore */ }
            echo json_encode(['success' => true, 'created' => (int)$createdRef]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
