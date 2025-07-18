<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';

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
        requireAdmin(true);
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

function handleGetAllTemplates($pdo)
{
    $query = "
        SELECT 
            et.*,
            (SELECT COUNT(*) FROM email_template_assignments eta WHERE eta.template_id = et.id) as assignment_count
        FROM email_templates et 
        ORDER BY et.template_type, et.template_name
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

function handleGetTemplate($pdo)
{
    $templateId = $_GET['template_id'] ?? '';

    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found');
    }

    echo json_encode([
        'success' => true,
        'template' => $template
    ]);
}

function handleCreateTemplate($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $templateName = trim($input['template_name'] ?? '');
    $templateType = trim($input['template_type'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $htmlContent = trim($input['html_content'] ?? '');
    $textContent = trim($input['text_content'] ?? '');
    $description = trim($input['description'] ?? '');
    $variables = $input['variables'] ?? [];

    if (empty($templateName) || empty($templateType) || empty($subject) || empty($htmlContent)) {
        throw new Exception('Template name, type, subject, and HTML content are required');
    }

    // Validate template type
    $validTypes = ['order_confirmation', 'admin_notification', 'welcome', 'password_reset', 'custom'];
    if (!in_array($templateType, $validTypes)) {
        throw new Exception('Invalid template type');
    }

    $stmt = $pdo->prepare("
        INSERT INTO email_templates 
        (template_name, template_type, subject, html_content, text_content, description, variables, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");

    $stmt->execute([
        $templateName,
        $templateType,
        $subject,
        $htmlContent,
        $textContent,
        $description,
        json_encode($variables)
    ]);

    $templateId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Email template created successfully',
        'template_id' => $templateId
    ]);
}

function handleUpdateTemplate($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['template_id'] ?? '';
    $templateName = trim($input['template_name'] ?? '');
    $templateType = trim($input['template_type'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $htmlContent = trim($input['html_content'] ?? '');
    $textContent = trim($input['text_content'] ?? '');
    $description = trim($input['description'] ?? '');
    $variables = $input['variables'] ?? [];
    $isActive = $input['is_active'] ?? 1;

    if (empty($templateId) || empty($templateName) || empty($templateType) || empty($subject) || empty($htmlContent)) {
        throw new Exception('Template ID, name, type, subject, and HTML content are required');
    }

    $stmt = $pdo->prepare("
        UPDATE email_templates 
        SET template_name = ?, template_type = ?, subject = ?, html_content = ?, 
            text_content = ?, description = ?, variables = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $templateName,
        $templateType,
        $subject,
        $htmlContent,
        $textContent,
        $description,
        json_encode($variables),
        $isActive,
        $templateId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Email template updated successfully'
    ]);
}

function handleDeleteTemplate($pdo)
{
    $templateId = $_POST['template_id'] ?? $_GET['template_id'] ?? '';

    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }

    // Check if template is assigned
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_template_assignments WHERE template_id = ?");
    $stmt->execute([$templateId]);
    $assignmentCount = $stmt->fetchColumn();

    if ($assignmentCount > 0) {
        throw new Exception('Cannot delete template that is currently assigned to email types. Please reassign first.');
    }

    $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);

    echo json_encode([
        'success' => true,
        'message' => 'Email template deleted successfully'
    ]);
}

function handleGetTemplateTypes($pdo)
{
    $types = [
        'order_confirmation' => [
            'name' => 'Order Confirmation',
            'description' => 'Sent to customers when they place an order',
            'default_variables' => ['customer_name', 'order_id', 'order_date', 'order_total', 'items', 'shipping_address']
        ],
        'admin_notification' => [
            'name' => 'Admin Notification',
            'description' => 'Sent to admins when new orders are received',
            'default_variables' => ['customer_name', 'customer_email', 'order_id', 'order_date', 'order_total', 'items']
        ],
        'welcome' => [
            'name' => 'Welcome Email',
            'description' => 'Sent to new users when they register',
            'default_variables' => ['user_name', 'activation_url']
        ],
        'password_reset' => [
            'name' => 'Password Reset',
            'description' => 'Sent when users request password reset',
            'default_variables' => ['user_name', 'reset_url', 'reset_token']
        ],
        'custom' => [
            'name' => 'Custom Template',
            'description' => 'Custom email template for manual sending',
            'default_variables' => []
        ]
    ];

    echo json_encode([
        'success' => true,
        'types' => $types
    ]);
}

function handleGetTemplateAssignments($pdo)
{
    $query = "
        SELECT 
            eta.*,
            et.template_name,
            et.subject
        FROM email_template_assignments eta
        LEFT JOIN email_templates et ON eta.template_id = et.id
        ORDER BY eta.email_type
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);
}

function handleSetTemplateAssignment($pdo)
{
    requireAdmin(true);

    $input = json_decode(file_get_contents('php://input'), true);

    $emailType = trim($input['email_type'] ?? '');
    $templateId = $input['template_id'] ?? '';

    if (empty($emailType) || empty($templateId)) {
        throw new Exception('Email type and template ID are required');
    }

    // Check if assignment exists
    $stmt = $pdo->prepare("SELECT id FROM email_template_assignments WHERE email_type = ?");
    $stmt->execute([$emailType]);
    $existingAssignment = $stmt->fetch();

    if ($existingAssignment) {
        // Update existing assignment
        $stmt = $pdo->prepare("UPDATE email_template_assignments SET template_id = ?, updated_at = NOW() WHERE email_type = ?");
        $stmt->execute([$templateId, $emailType]);
    } else {
        // Create new assignment
        $stmt = $pdo->prepare("INSERT INTO email_template_assignments (email_type, template_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$emailType, $templateId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template assignment updated successfully'
    ]);
}

function handlePreviewTemplate($pdo)
{
    $templateId = $_GET['template_id'] ?? '';

    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found');
    }

    // Sample variables for preview
    $sampleVars = [
        'customer_name' => 'John Doe',
        'order_id' => 'WF-2024-001',
        'order_date' => date('F j, Y'),
        'order_total' => '$45.99',
        'customer_email' => 'john.doe@example.com',
        'user_name' => 'John Doe',
        'reset_url' => 'https://whimsicalfrog.us/reset-password?token=sample',
        'activation_url' => 'https://whimsicalfrog.us/activate?token=sample',
        'items' => '<li>Sample T-Shirt - $25.00</li><li>Custom Tumbler - $20.99</li>',
        'shipping_address' => '123 Main St, City, ST 12345'
    ];

    $htmlContent = $template['html_content'];
    $subject = $template['subject'];

    // Replace variables in content
    foreach ($sampleVars as $var => $value) {
        $htmlContent = str_replace('{' . $var . '}', $value, $htmlContent);
        $subject = str_replace('{' . $var . '}', $value, $subject);
    }

    echo json_encode([
        'success' => true,
        'preview' => [
            'subject' => $subject,
            'html_content' => $htmlContent,
            'variables_used' => $sampleVars
        ]
    ]);
}

function handleSendTestEmail($pdo)
{
    requireAdmin(true);

    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['template_id'] ?? '';
    $testEmail = trim($input['test_email'] ?? '');

    if (empty($templateId) || empty($testEmail)) {
        throw new Exception('Template ID and test email address are required');
    }

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Load email notification functions
    require_once __DIR__ . '/email_notifications.php';

    $success = sendTestEmail($templateId, $testEmail, $pdo);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $testEmail
        ]);
    } else {
        throw new Exception('Failed to send test email. Check server logs for details.');
    }
}
?> 