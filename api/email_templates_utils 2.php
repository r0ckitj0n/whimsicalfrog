<?php
/**
 * Email Templates Utilities for WhimsicalFrog
 * Handles template previews, test emails, and string conversions.
 */

function handlePreviewTemplate($pdo)
{
    $templateId = $_GET['template_id'] ?? '';

    if (empty($templateId)) {
        throw new Exception('Template ID is required');
    }

    $template = Database::queryOne("SELECT * FROM email_templates WHERE id = ?", [$templateId]);

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
    $textContent = $template['text_content'] ?? '';
    $subject = $template['subject'];

    // Replace variables in content
    foreach ($sampleVars as $var => $value) {
        $htmlContent = str_replace('{' . $var . '}', $value, $htmlContent);
        if (!empty($textContent)) {
            $textContent = str_replace('{' . $var . '}', $value, $textContent);
        }
        $subject = str_replace('{' . $var . '}', $value, $subject);
    }

    // Derive plain text if not provided
    if (empty($textContent)) {
        $textContent = html_to_text_basic($htmlContent);
    }

    echo json_encode([
        'success' => true,
        'preview' => [
            'subject' => $subject,
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'variables_used' => $sampleVars
        ]
    ]);
}

/**
 * Very basic HTML to text converter for preview purposes.
 */
function html_to_text_basic($html)
{
    $text = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n", $html);
    $text = preg_replace('/<(br|br\/)\s*>/i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function handleSendTestEmail($pdo)
{
    AuthHelper::requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true);

    $templateId = $input['template_id'] ?? '';
    $testEmail = trim($input['test_email'] ?? '');

    if (empty($templateId) || empty($testEmail)) {
        throw new Exception('Template ID and test email address are required');
    }

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Load email notifications for sendTemplatedEmail
    require_once __DIR__ . '/email_notifications.php';

    // Resolve non-numeric identifiers (allow passing an email type like "order_confirmation")
    $resolvedTemplateId = null;
    if (ctype_digit((string) $templateId)) {
        $resolvedTemplateId = (int) $templateId;
    } else {
        // Treat provided value as email_type; find assigned template
        $emailType = trim((string) $templateId);
        if ($emailType !== '') {
            $rowA = Database::queryOne("SELECT template_id FROM email_template_assignments WHERE email_type = ?", [$emailType]);
            $assignedId = $rowA ? $rowA['template_id'] : null;
            if ($assignedId) {
                $resolvedTemplateId = (int) $assignedId;
            } else {
                // Fallback: first active template matching this type
                $rowB = Database::queryOne("SELECT id FROM email_templates WHERE template_type = ? AND is_active = 1 ORDER BY updated_at DESC, created_at DESC LIMIT 1", [$emailType]);
                $fallbackId = $rowB ? $rowB['id'] : null;
                if ($fallbackId) {
                    $resolvedTemplateId = (int) $fallbackId;
                }
            }
        }
    }

    if (!$resolvedTemplateId) {
        throw new Exception('Template not found for the provided identifier');
    }

    // Get template and send test email
    $template = Database::queryOne("SELECT * FROM email_templates WHERE id = ? AND is_active = 1", [$resolvedTemplateId]);
    if (!$template) {
        throw new Exception('Template not found or inactive');
    }

    // Sample variables for testing
    $testVariables = [
        'customer_name' => 'John Doe',
        'customer_email' => $testEmail,
        'order_id' => 'TEST-001',
        'order.created_at' => date('F j, Y g:i A'),
        'order_total' => '$45.99',
        'items' => '<li>Sample T-Shirt - $25.00</li><li>Custom Tumbler - $20.99</li>',
        'items_text' => "- Sample T-Shirt - $25.00\n- Custom Tumbler - $20.99",
        'shipping_address' => '123 Test Street, Test City, TS 12345',
        'payment_method' => 'Credit Card',
        'shipping_method' => 'Standard Shipping',
        'status' => WF_Constants::ORDER_STATUS_PROCESSING,
        'payment_status' => WF_Constants::PAYMENT_STATUS_PAID,
        'user_name' => 'John Doe',
        'reset_url' => 'https://whimsicalfrog.us/reset-password?token=test',
        'activation_url' => 'https://whimsicalfrog.us/activate?token=test'
    ];

    $success = sendTemplatedEmail($template, $testEmail, $testVariables, WF_Constants::EMAIL_TYPE_TEST_EMAIL);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $testEmail
        ]);
    } else {
        throw new Exception('Failed to send test email. Check server logs for details.');
    }
}
