<?php
// Debug script to see current email_logs table contents
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start session to verify admin access
session_start();

// Check if user is admin
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "<h2>📧 Email Logs Table Debug</h2>";
    
    // Get all email logs
    $stmt = $pdo->query("SELECT * FROM email_logs ORDER BY sent_at DESC");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Total emails in database: " . count($emails) . "</h3>";
    
    if (empty($emails)) {
        echo "<p>❌ No emails found in the database.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>ID</th><th>To</th><th>From</th><th>Subject</th><th>Type</th><th>Status</th><th>Order ID</th><th>Created By</th><th>Sent At</th><th>Content Preview</th>";
        echo "</tr>";
        
        foreach ($emails as $email) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($email['id']) . "</td>";
            echo "<td>" . htmlspecialchars($email['to_email']) . "</td>";
            echo "<td>" . htmlspecialchars($email['from_email']) . "</td>";
            echo "<td>" . htmlspecialchars($email['subject']) . "</td>";
            echo "<td>" . htmlspecialchars($email['email_type']) . "</td>";
            echo "<td>" . htmlspecialchars($email['status']) . "</td>";
            echo "<td>" . htmlspecialchars($email['order_id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($email['created_by'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($email['sent_at']) . "</td>";
            echo "<td>" . htmlspecialchars(substr(strip_tags($email['content']), 0, 100)) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show full content of first email for debugging
        if (!empty($emails)) {
            echo "<h3>Full Content of First Email (ID: " . $emails[0]['id'] . "):</h3>";
            echo "<div style='border: 1px solid #ccc; padding: 10px; background-color: #f9f9f9;'>";
            echo "<strong>Subject:</strong> " . htmlspecialchars($emails[0]['subject']) . "<br>";
            echo "<strong>Content:</strong><br>";
            echo "<pre>" . htmlspecialchars($emails[0]['content']) . "</pre>";
            echo "</div>";
        }
    }
    
    // Test the UPDATE query conditions
    echo "<h3>🔍 Testing Update Conditions</h3>";
    
    $testSQL = "
    SELECT id, subject, created_by 
    FROM email_logs 
    WHERE 
        subject LIKE '%Email System Initialized%' 
        OR subject LIKE '%Email Logging System%'
        OR created_by = 'system'";
    
    $stmt = $pdo->query($testSQL);
    $matchingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Emails matching update conditions: " . count($matchingEmails) . "</p>";
    
    if (!empty($matchingEmails)) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Subject</th><th>Created By</th></tr>";
        foreach ($matchingEmails as $email) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($email['id']) . "</td>";
            echo "<td>" . htmlspecialchars($email['subject']) . "</td>";
            echo "<td>" . htmlspecialchars($email['created_by'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>❌ No emails match the update conditions. This explains why the update didn't work.</p>";
    }
    
    echo '<p><a href="../index.php?page=admin&section=settings" style="color: #87ac3a;">← Back to Admin Settings</a></p>';
    
} catch (Exception $e) {
    echo "<h2>❌ Error Debugging Email Logs</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo '<p><a href="../index.php?page=admin&section=settings" style="color: #87ac3a;">← Back to Admin Settings</a></p>';
}
?> 