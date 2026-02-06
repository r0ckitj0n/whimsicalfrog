<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once 'email_templates_handlers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Admin check for modification actions
    if (in_array($action, ['create', 'update', 'delete', 'set_default'])) {
        AuthHelper::requireAdmin();
    }

    switch ($action) {
        case 'get_all':
            handleGetAllTemplates($pdo);
            break;

        case 'get_template':
            handleGetTemplate($pdo);
            break;

        case 'create':
            handleCreateTemplate($pdo);
            break;

        case 'update':
            handleUpdateTemplate($pdo);
            break;

        case 'delete':
            handleDeleteTemplate($pdo);
            break;

        case 'get_types':
            handleGetTemplateTypes($pdo);
            break;

        case 'get_assignments':
            handleGetTemplateAssignments($pdo);
            break;

        case 'set_assignment':
            handleSetTemplateAssignment($pdo);
            break;

        case 'preview':
            handlePreviewTemplate($pdo);
            break;

        case 'send_test':
            handleSendTestEmail($pdo);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>