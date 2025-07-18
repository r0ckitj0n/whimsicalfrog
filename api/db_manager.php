<?php
// Secure Database Manager for WhimsicalFrog Live Server
// This allows direct database operations without going through multiple deployment steps

ob_start();
ob_clean();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start session first to access session data
session_start();

// Include database configuration first
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication functions
require_once __DIR__ . '/../includes/auth.php';

// Parse JSON input for admin token fallback
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Check authentication using centralized functions
if (!isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? $input['action'] ?? 'status';
    $result = ['success' => false, 'message' => '', 'data' => null];

    switch ($action) {
        case 'status':
            // Get database status and basic info
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $result = [
                'success' => true,
                'message' => 'Database connection successful',
                'data' => [
                    'database_name' => $database,
                    'host' => $host,
                    'tables' => $tables,
                    'table_count' => count($tables),
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ];
            break;

        case 'query':
            // Execute a custom SQL query
            $sql = $_POST['sql'] ?? $input['sql'] ?? '';
            if (empty($sql)) {
                $result = ['success' => false, 'error' => 'No SQL query provided'];
                break;
            }

            // Security: Only allow SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP
            $allowedOperations = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'SHOW', 'DESCRIBE'];
            $firstWord = strtoupper(trim(explode(' ', trim($sql))[0]));

            if (!in_array($firstWord, $allowedOperations)) {
                $result = ['success' => false, 'error' => 'SQL operation not allowed: ' . $firstWord];
                break;
            }

            try {
                if (in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE'])) {
                    // Read operations
                    $stmt = $pdo->query($sql);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $result = [
                        'success' => true,
                        'message' => 'Query executed successfully',
                        'data' => $data,
                        'row_count' => count($data)
                    ];
                } else {
                    // Write operations
                    $stmt = $pdo->prepare($sql);
                    $executed = $stmt->execute();
                    $result = [
                        'success' => $executed,
                        'message' => $executed ? 'Query executed successfully' : 'Query execution failed',
                        'affected_rows' => $stmt->rowCount()
                    ];
                }
            } catch (PDOException $e) {
                $result = ['success' => false, 'error' => 'SQL Error: ' . $e->getMessage()];
            }
            break;

        case 'email_logs':
            // Get email logs with optional filters
            $limit = $_POST['limit'] ?? 10;
            $offset = $_POST['offset'] ?? 0;
            $type_filter = $_POST['type_filter'] ?? '';

            $sql = "SELECT * FROM email_logs";
            $params = [];

            if (!empty($type_filter)) {
                $sql .= " WHERE email_type = :type";
                $params[':type'] = $type_filter;
            }

            $sql .= " ORDER BY sent_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [
                'success' => true,
                'message' => 'Email logs retrieved',
                'data' => $emails,
                'count' => count($emails)
            ];
            break;

        case 'fix_sample_email':
            // Direct sample email fix without separate script
            $sampleEmailContent = '
            <div class="order-email-body">
                <div class="order-email-header">
                    <h1 class="order-email-logo">WhimsicalFrog</h1>
                    <p class="order-email-tagline">Custom Products & Design</p>
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

                $stmt = $pdo->prepare("
                    UPDATE email_logs 
                    SET to_email = 'john.doe@example.com',
                        subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                        content = :content,
                        email_type = 'order_confirmation',
                        order_id = '01F14P23'
                    WHERE subject LIKE :pattern LIMIT 1
                ");

                $executed = $stmt->execute([':content' => $sampleEmailContent, ':pattern' => $pattern]);
                if ($executed && $stmt->rowCount() > 0) {
                    $updated = true;
                    $updateMessage = "Updated email by pattern: $pattern";
                    break;
                }
            }

            // Strategy 2: Update by created_by = 'system'
            if (!$updated) {
                $stmt = $pdo->prepare("
                    UPDATE email_logs 
                    SET to_email = 'john.doe@example.com',
                        subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                        content = :content,
                        email_type = 'order_confirmation',
                        order_id = '01F14P23'
                    WHERE created_by = 'system' LIMIT 1
                ");

                $executed = $stmt->execute([':content' => $sampleEmailContent]);
                if ($executed && $stmt->rowCount() > 0) {
                    $updated = true;
                    $updateMessage = "Updated email by created_by = 'system'";
                }
            }

            // Strategy 3: Create new if nothing to update
            if (!$updated) {
                $stmt = $pdo->prepare("
                    INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, order_id, created_by) 
                    VALUES ('john.doe@example.com', 'orders@whimsicalfrog.us', 'Order Confirmation #01F14P23 - WhimsicalFrog', 
                            :content, 'order_confirmation', 'sent', NOW(), '01F14P23', 'system')
                ");

                $executed = $stmt->execute([':content' => $sampleEmailContent]);
                if ($executed) {
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
?> 