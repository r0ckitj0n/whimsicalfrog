<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once 'config.php';

// Check if user is logged in
session_start();

// Debug session for troubleshooting
$debug = [
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'session_id' => session_id(),
    'has_user' => isset($_SESSION['user']),
    'user_data' => $_SESSION['user'] ?? null,
    'has_role' => isset($_SESSION['user']['role']) ?? false,
    'role_value' => $_SESSION['user']['role'] ?? null
];

// Log debug info for troubleshooting
error_log("Email history auth debug: " . json_encode($debug));

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || 
    ($_SESSION['user']['role'] !== 'Admin' && $_SESSION['user']['role'] !== 'admin')) {
    ob_clean();
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Access denied',
        'debug' => $debug // Include debug info temporarily
    ]);
    exit;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create email_logs table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            from_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            content TEXT NOT NULL,
            email_type ENUM('order_confirmation', 'admin_notification', 'test_email', 'manual_resend') NOT NULL,
            status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
            error_message TEXT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            order_id VARCHAR(50) NULL,
            created_by VARCHAR(50) NULL,
            INDEX idx_sent_at (sent_at),
            INDEX idx_email_type (email_type),
            INDEX idx_status (status),
            INDEX idx_to_email (to_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    
    // Get filter parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    $dateFilter = $_GET['date_filter'] ?? 'all';
    $typeFilter = $_GET['type_filter'] ?? 'all';
    $statusFilter = $_GET['status_filter'] ?? 'all';
    
    // Build WHERE clause
    $whereClauses = [];
    $params = [];
    
    // Date filter
    switch ($dateFilter) {
        case 'today':
            $whereClauses[] = "DATE(sent_at) = CURDATE()";
            break;
        case 'week':
            $whereClauses[] = "sent_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $whereClauses[] = "sent_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
    
    // Type filter
    if ($typeFilter !== 'all') {
        $whereClauses[] = "email_type = :type_filter";
        $params[':type_filter'] = $typeFilter;
    }
    
    // Status filter
    if ($statusFilter !== 'all') {
        $whereClauses[] = "status = :status_filter";
        $params[':status_filter'] = $statusFilter;
    }
    
    $whereSQL = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    
    // Get total count
    $countSQL = "SELECT COUNT(*) FROM email_logs $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn();
    
    // Get emails
    $sql = "SELECT id, to_email, from_email, subject, email_type, status, sent_at, order_id 
            FROM email_logs 
            $whereSQL 
            ORDER BY sent_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind filter parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination info
    $totalPages = ceil($totalCount / $limit);
    $start = $offset + 1;
    $end = min($offset + $limit, $totalCount);
    
    $pagination = [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_count' => $totalCount,
        'limit' => $limit,
        'start' => $start,
        'end' => $end,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'emails' => $emails,
        'pagination' => $pagination
    ]);
    
} catch (Exception $e) {
    error_log("Email history error: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load email history: ' . $e->getMessage()
    ]);
}
?> 