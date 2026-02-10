<?php
// Secure Database Manager for WhimsicalFrog Live Server
// This allows direct database operations without going through multiple deployment steps

ob_start();
ob_clean();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start session first to access session data


// Include database configuration first
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication functions
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';

// Strict admin session check (avoid helper local bypass for this endpoint)
requireAdmin(true);

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $action = $_POST['action'] ?? $_GET['action'] ?? $input['action'] ?? WF_Constants::ACTION_STATUS;
    $allowedActions = [
        WF_Constants::ACTION_STATUS,
        WF_Constants::ACTION_QUERY,
        WF_Constants::ACTION_EMAIL_LOGS,
        WF_Constants::ACTION_FIX_SAMPLE_EMAIL
    ];
    if (!in_array($action, $allowedActions, true)) {
        Response::error('Invalid action', null, 400);
    }
    $result = ['success' => false, 'message' => '', 'data' => null];

    switch ($action) {
        case WF_Constants::ACTION_STATUS:
            // Get database status and basic info
            $tableRows = Database::queryAll("SHOW TABLES");
            $tables = array_map(function ($r) { return array_values($r)[0]; }, $tableRows);
            $result = [
                'success' => true,
                'message' => 'Database connection successful',
                'data' => [
                    'database_name' => (wf_get_db_config(WF_Constants::ENV_LOCAL)['db'] ?? null),
                    'host' => (wf_get_db_config(WF_Constants::ENV_LOCAL)['host'] ?? null),
                    'tables' => $tables,
                    'table_count' => count($tables),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            break;

        case WF_Constants::ACTION_QUERY:
            // Execute a custom SQL query
            $sql = $_POST['sql'] ?? $input['sql'] ?? '';
            if (empty($sql)) {
                $result = ['success' => false, 'error' => 'No SQL query provided'];
                break;
            }

            // Security: read-only query endpoint
            $allowedOperations = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
            $firstWord = strtoupper(trim(explode(' ', trim($sql))[0]));

            if (!in_array($firstWord, $allowedOperations, true)) {
                $result = ['success' => false, 'error' => 'SQL operation not allowed: ' . $firstWord];
                break;
            }
            if (strlen($sql) > 20000) {
                $result = ['success' => false, 'error' => 'SQL query too large'];
                break;
            }
            if (strpos($sql, ';') !== false) {
                $result = ['success' => false, 'error' => 'Multiple SQL statements are not allowed'];
                break;
            }

            try {
                $data = Database::queryAll($sql);
                $result = [
                    'success' => true,
                    'message' => 'Query executed successfully',
                    'data' => $data,
                    'row_count' => count($data)
                ];
            } catch (PDOException $e) {
                $result = ['success' => false, 'error' => 'SQL Error: ' . $e->getMessage()];
            }
            break;

        case WF_Constants::ACTION_EMAIL_LOGS:
            // Get email logs with optional filters
            $limit = $_POST['limit'] ?? 10;
            $offset = $_POST['offset'] ?? 0;
            $type_filter = $_POST['type_filter'] ?? '';

            $limit = (int)$limit;
            $offset = (int)$offset;
            $emails = [];
            if (!empty($type_filter)) {
                $emails = Database::queryAll(
                    "SELECT * FROM email_logs WHERE email_type = ? ORDER BY sent_at DESC LIMIT $limit OFFSET $offset",
                    [$type_filter]
                );
            } else {
                $emails = Database::queryAll(
                    "SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT $limit OFFSET $offset"
                );
            }

            $result = [
                'success' => true,
                'message' => 'Email logs retrieved',
                'data' => $emails,
                'count' => count($emails)
            ];
            break;

        case WF_Constants::ACTION_FIX_SAMPLE_EMAIL:
            // Direct sample email fix without separate script
            $sampleEmailContent = '
            <div class="order-email-body">
                <div class="order-email-header">
                    <h1 class="order-email-logo">WhimsicalFrog</h1>
                    <p class="order-email-tagline">Custom Items & Design</p>
                </div>
                
                <h2 class="order-email-title">Order Confirmation #01F14P23</h2>
                
                <p>Dear John Doe,</p>
                
                <p>Thank you for your order! We have received your order and it is being processed.</p>
                
                <h3 class="order-email-section-title">Order Details:</h3>
                <table class="order-email-table">
                    <tr class="order-email-table-header">
                        <th class="order-email-table-header">Item</th>
                        <th class="order-email-table-header">Quantity</th>
                        <th class="order-email-table-header">Price</th>
                    </tr>
                    <tr>
                        <td class="order-email-table-cell">Custom T-Shirt Design</td>
                        <td class="order-email-table-cell-right">2</td>
                        <td class="order-email-table-cell-right">$25.00</td>
                    </tr>
                    <tr>
                        <td class="order-email-table-cell">Custom Tumbler</td>
                        <td class="order-email-table-cell-right">1</td>
                        <td class="order-email-table-cell-right">$15.00</td>
                    </tr>
                    <tr class="order-email-table-total">
                        <td class="order-email-table-cell" colspan="2">Total:</td>
                        <td class="order-email-table-cell-right">$40.00</td>
                    </tr>
                </table>
                
                <h3 class="order-email-section-title">Shipping Information:</h3>
                <p>John Doe<br>
                123 Main Street<br>
                Anytown, ST 12345</p>
                
                <p><strong>Delivery Method:</strong> Pickup</p>
                <p><strong>Expected Completion:</strong> 3-5 business days</p>
                
                <p>We will notify you when your order is ready for pickup!</p>
                
                <p>If you have any questions, please contact us at orders@whimsicalfrog.us</p>
                
                <p>Thank you for choosing WhimsicalFrog!</p>
                
                <div class="order-email-shipping-info">
                    <p>This is an automated email. Please do not reply to this email address.</p>
                </div>
            </div>';

            // Try multiple strategies to update sample email
            $updated = false;
            $updateMessage = "";

            // Strategy 1: Update by subject patterns
            $patterns = ['%Email System Initialized%', '%Email Logging System%', '%initialized%'];
            foreach ($patterns as $pattern) {
                if ($updated) {
                    break;
                }

                $affected = Database::execute(
                    "UPDATE email_logs 
                     SET to_email = 'john.doe@example.com',
                         subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                         content = ?,
                         email_type = ?,
                         order_id = '01F14P23'
                     WHERE subject LIKE ? LIMIT 1",
                    [$sampleEmailContent, WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION, $pattern]
                );
                if ($affected && $affected > 0) {
                    $updated = true;
                    $updateMessage = "Updated email by pattern: $pattern";
                    break;
                }
            }

            // Strategy 2: Update by created_by = 'system'
            if (!$updated) {
                $affected = Database::execute(
                    "UPDATE email_logs 
                     SET to_email = 'john.doe@example.com',
                         subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                         content = ?,
                         email_type = ?,
                         order_id = '01F14P23'
                     WHERE created_by = ? LIMIT 1",
                    [$sampleEmailContent, WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION, WF_Constants::ROLE_SYSTEM]
                );
                if ($affected && $affected > 0) {
                    $updated = true;
                    $updateMessage = "Updated email by created_by = 'system'";
                }
            }

            // Strategy 3: Create new if nothing to update
            if (!$updated) {
                $affected = Database::execute(
                    "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, order_id, created_by) 
                     VALUES ('john.doe@example.com', 'orders@whimsicalfrog.us', 'Order Confirmation #01F14P23 - WhimsicalFrog', ?, ?, ?, NOW(), '01F14P23', ?)",
                    [$sampleEmailContent, WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION, WF_Constants::EMAIL_STATUS_SENT, WF_Constants::ROLE_SYSTEM]
                );
                if ($affected !== false) {
                    $updated = true;
                    $updateMessage = "Created new sample email";
                }
            }

            $result = [
                'success' => $updated,
                'message' => $updated ? "Sample email fixed: $updateMessage" : 'Failed to fix sample email',
                'data' => ['strategy_used' => $updateMessage]
            ];
            break;

        default:
            $result = ['success' => false, 'error' => 'Unknown action: ' . $action];
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
