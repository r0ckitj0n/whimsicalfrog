<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once 'config.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || 
    require_once __DIR__ . '/../includes/auth.php'; !isAdminWithToken()) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Get email ID from query parameter
    $emailId = intval($_GET['id'] ?? 0);
    
    if ($emailId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid email ID']);
        exit;
    }
    
    // Get email details
    $sql = "SELECT id, to_email, from_email, subject, content, email_type, status, 
                   error_message, sent_at, order_id, created_by 
            FROM email_logs 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $emailId, PDO::PARAM_INT);
    $stmt->execute();
    
    $email = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$email) {
        echo json_encode(['success' => false, 'error' => 'Email not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'email' => $email
    ]);
    
} catch (Exception $e) {
    error_log("Email details error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load email details: ' . $e->getMessage()
    ]);
}
?> 