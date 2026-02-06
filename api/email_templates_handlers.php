<?php
/**
 * Email Templates Handlers for WhimsicalFrog
 * Extracted handler functions for template management and previews.
 */

require_once __DIR__ . '/../includes/auth_helper.php';
require_once 'email_templates_utils.php';

function handleGetAllTemplates($pdo)
{
    $query = "
        SELECT 
            et.*,
            (SELECT COUNT(*) FROM email_template_assignments eta WHERE eta.template_id = et.id) as assignment_count
        FROM email_templates et 
        ORDER BY et.template_type, et.template_name
    ";

    $templates = Database::queryAll($query);

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

    $template = Database::queryOne("SELECT * FROM email_templates WHERE id = ?", [$templateId]);

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

    Database::execute("
        INSERT INTO email_templates 
        (template_name, template_type, subject, html_content, text_content, description, variables, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ", [
        $templateName,
        $templateType,
        $subject,
        $htmlContent,
        $textContent,
        $description,
        json_encode($variables)
    ]);

    $templateId = Database::lastInsertId();

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

    Database::execute("
        UPDATE email_templates 
        SET template_name = ?, template_type = ?, subject = ?, html_content = ?, 
            text_content = ?, description = ?, variables = ?, is_active = ?, updated_at = NOW()
        WHERE id = ?
    ", [
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
    $row = Database::queryOne("SELECT COUNT(*) AS c FROM email_template_assignments WHERE template_id = ?", [$templateId]);
    $assignmentCount = $row ? (int) $row['c'] : 0;

    if ($assignmentCount > 0) {
        throw new Exception('Cannot delete template that is currently assigned to email types. Please reassign first.');
    }

    Database::execute("DELETE FROM email_templates WHERE id = ?", [$templateId]);

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
            eta.email_type,
            eta.template_id
        FROM email_template_assignments eta
        ORDER BY eta.email_type
    ";

    $rows = Database::queryAll($query);

    // Convert to keyed object format: {email_type: template_id}
    $assignments = [];
    foreach ($rows as $row) {
        $assignments[$row['email_type']] = (int) $row['template_id'];
    }

    echo json_encode([
        'success' => true,
        'assignments' => $assignments
    ]);
}

function handleSetTemplateAssignment($pdo)
{
    AuthHelper::requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);

    $emailType = trim($input['email_type'] ?? '');
    $templateId = $input['template_id'] ?? null;

    if (empty($emailType)) {
        throw new Exception('Email type is required');
    }

    // Check if assignment exists
    $existingAssignment = Database::queryOne("SELECT id FROM email_template_assignments WHERE email_type = ?", [$emailType]);

    // Handle unassign (null or 0 template_id)
    if (empty($templateId) || $templateId === 0) {
        if ($existingAssignment) {
            Database::execute("DELETE FROM email_template_assignments WHERE email_type = ?", [$emailType]);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Template assignment removed'
        ]);
        return;
    }

    if ($existingAssignment) {
        // Update existing assignment
        Database::execute("UPDATE email_template_assignments SET template_id = ?, updated_at = NOW() WHERE email_type = ?", [$templateId, $emailType]);
    } else {
        // Create new assignment
        Database::execute("INSERT INTO email_template_assignments (email_type, template_id, created_at) VALUES (?, ?, NOW())", [$emailType, $templateId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template assignment updated successfully'
    ]);
}
