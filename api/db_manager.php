<?php
// Secure Database Manager for WhimsicalFrog Live Server
// This allows direct database operations without going through multiple deployment steps

ob_start();
ob_clean();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Security: Only allow access from admin session
session_start();

// Check authentication with multiple session structures
$isAdmin = false;
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
}

// Additional security: Check for specific admin token (you can set this)
$adminToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';
$validToken = 'whimsical_admin_2024'; // Change this to something secure

if (!$isAdmin && $adminToken !== $validToken) {
    ob_clean();
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Access denied - admin authentication required',
        'debug' => [
            'isAdmin' => $isAdmin,
            'adminToken' => $adminToken,
            'validToken' => $validToken,
            'session_keys' => array_keys($_SESSION ?? []),
            'session_user' => $_SESSION['user'] ?? 'not set',
            'session_role' => $_SESSION['role'] ?? 'not set',
            'session_user_role' => $_SESSION['user_role'] ?? 'not set',
            'post_data' => $_POST,
            'get_data' => $_GET
        ]
    ]);
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_POST['action'] ?? $_GET['action'] ?? 'status';
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
            $sql = $_POST['sql'] ?? '';
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
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #87ac3a; margin: 0;">WhimsicalFrog</h1>
                    <p style="color: #666; margin: 5px 0;">Custom Products & Design</p>
                </div>
                
                <h2 style="color: #333;">Order Confirmation #01F14P23</h2>
                
                <p>Dear John Doe,</p>
                
                <p>Thank you for your order! We have received your order and it is being processed.</p>
                
                <h3 style="color: #87ac3a;">Order Details:</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Item</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Quantity</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Price</th>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;">Custom T-Shirt Design</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">2</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$25.00</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;">Custom Tumbler</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">1</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$15.00</td>
                    </tr>
                    <tr style="background-color: #f5f5f5; font-weight: bold;">
                        <td style="padding: 10px; border: 1px solid #ddd;" colspan="2">Total:</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$40.00</td>
                    </tr>
                </table>
                
                <h3 style="color: #87ac3a;">Shipping Information:</h3>
                <p>John Doe<br>
                123 Main Street<br>
                Anytown, ST 12345</p>
                
                <p><strong>Delivery Method:</strong> Pickup</p>
                <p><strong>Expected Completion:</strong> 3-5 business days</p>
                
                <p>We will notify you when your order is ready for pickup!</p>
                
                <p>If you have any questions, please contact us at orders@whimsicalfrog.us</p>
                
                <p>Thank you for choosing WhimsicalFrog!</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
                    <p>This is an automated email. Please do not reply to this email address.</p>
                </div>
            </div>';
            
            // Try multiple strategies to update sample email
            $updated = false;
            $updateMessage = "";
            
            // Strategy 1: Update by subject patterns
            $patterns = ['%Email System Initialized%', '%Email Logging System%', '%initialized%'];
            foreach ($patterns as $pattern) {
                if ($updated) break;
                
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