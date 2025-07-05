<?php
// Update sample email with realistic content for testing
ob_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start session to verify admin access
session_start();

// Check if user is admin
require_once __DIR__ . '/../includes/auth.php';
if (!isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Realistic sample email content
    $sampleEmailContent = '
    <div class="email-container">
        <div class="email-header">
            <h1 class="email-title">WhimsicalFrog</h1>
            <p class="email-subtitle">Custom Products & Design</p>
        </div>
        
        <h2 style="color: #333;">Order Confirmation #01F14P23</h2>
        
        <p>Dear John Doe,</p>
        
        <p>Thank you for your order! We have received your order and it is being processed.</p>
        
        <h3 class="email-section-title">Order Details:</h3>
        <table class="email-table">
            <tr class="email-table-header">
                <th class="email-table-header">Item</th>
                <th class="email-table-header" style="text-align: right;">Quantity</th>
                <th class="email-table-header" style="text-align: right;">Price</th>
            </tr>
            <tr>
                <td class="email-table-cell">Custom T-Shirt Design</td>
                <td class="email-table-cell" style="text-align: right;">2</td>
                <td class="email-table-cell" style="text-align: right;">$25.00</td>
            </tr>
            <tr>
                <td class="email-table-cell">Custom Tumbler</td>
                <td class="email-table-cell" style="text-align: right;">1</td>
                <td class="email-table-cell" style="text-align: right;">$15.00</td>
            </tr>
            <tr class="email-table-header email-table-total">
                <td class="email-table-cell" colspan="2">Total:</td>
                <td class="email-table-cell" style="text-align: right;">$40.00</td>
            </tr>
        </table>
        
        <h3 class="email-section-title">Shipping Information:</h3>
        <p>John Doe<br>
        123 Main Street<br>
        Anytown, ST 12345</p>
        
        <p><strong>Delivery Method:</strong> Pickup</p>
        <p><strong>Expected Completion:</strong> 3-5 business days</p>
        
        <p>We will notify you when your order is ready for pickup!</p>
        
        <p>If you have any questions, please contact us at orders@whimsicalfrog.us</p>
        
        <p>Thank you for choosing WhimsicalFrog!</p>
        
        <div class="email-footer">
            <p>This is an automated email. Please do not reply to this email address.</p>
        </div>
    </div>';
    
    // First, let's see what emails exist
    $checkSQL = "SELECT id, subject, created_by, email_type FROM email_logs ORDER BY sent_at DESC";
    $stmt = $pdo->query($checkSQL);
    $existingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📧 Current Emails in Database:</h3>";
    if (empty($existingEmails)) {
        echo "<p>No emails found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>ID</th><th>Subject</th><th>Type</th><th>Created By</th></tr>";
        foreach ($existingEmails as $email) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($email['id']) . "</td>";
            echo "<td>" . htmlspecialchars($email['subject']) . "</td>";
            echo "<td>" . htmlspecialchars($email['email_type']) . "</td>";
            echo "<td>" . htmlspecialchars($email['created_by'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Try multiple update strategies
    $updated = false;
    $updateMessage = "";
    
    // Strategy 1: Update by subject patterns
    $updateSQL1 = "
    UPDATE email_logs 
    SET 
        to_email = 'john.doe@example.com',
        subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
        content = :sample_content,
        email_type = 'order_confirmation',
        order_id = '01F14P23'
    WHERE 
        (subject LIKE '%Email System Initialized%' 
        OR subject LIKE '%Email Logging System%'
        OR subject LIKE '%initialized%')
    LIMIT 1";
    
    $stmt = $pdo->prepare($updateSQL1);
    $result1 = $stmt->execute([':sample_content' => $sampleEmailContent]);
    
    if ($result1 && $stmt->rowCount() > 0) {
        $updated = true;
        $updateMessage = "Updated email by subject pattern match.";
    }
    
    // Strategy 2: Update by created_by = 'system'
    if (!$updated) {
        $updateSQL2 = "
        UPDATE email_logs 
        SET 
            to_email = 'john.doe@example.com',
            subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
            content = :sample_content,
            email_type = 'order_confirmation',
            order_id = '01F14P23'
        WHERE created_by = 'system'
        LIMIT 1";
        
        $stmt = $pdo->prepare($updateSQL2);
        $result2 = $stmt->execute([':sample_content' => $sampleEmailContent]);
        
        if ($result2 && $stmt->rowCount() > 0) {
            $updated = true;
            $updateMessage = "Updated email by created_by = 'system'.";
        }
    }
    
    // Strategy 3: Update the first email if it's a test_email type
    if (!$updated && !empty($existingEmails)) {
        $firstEmail = $existingEmails[0];
        if ($firstEmail['email_type'] === 'test_email') {
            $updateSQL3 = "
            UPDATE email_logs 
            SET 
                to_email = 'john.doe@example.com',
                subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                content = :sample_content,
                email_type = 'order_confirmation',
                order_id = '01F14P23'
            WHERE id = :email_id";
            
            $stmt = $pdo->prepare($updateSQL3);
            $result3 = $stmt->execute([
                ':sample_content' => $sampleEmailContent,
                ':email_id' => $firstEmail['id']
            ]);
            
            if ($result3 && $stmt->rowCount() > 0) {
                $updated = true;
                $updateMessage = "Updated the first test_email in the database.";
            }
        }
    }
    
    // Strategy 4: Just update the most recent email
    if (!$updated && !empty($existingEmails)) {
        $firstEmail = $existingEmails[0];
        $updateSQL4 = "
        UPDATE email_logs 
        SET 
            to_email = 'john.doe@example.com',
            subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
            content = :sample_content,
            email_type = 'order_confirmation',
            order_id = '01F14P23'
        WHERE id = :email_id";
        
        $stmt = $pdo->prepare($updateSQL4);
        $result4 = $stmt->execute([
            ':sample_content' => $sampleEmailContent,
            ':email_id' => $firstEmail['id']
        ]);
        
        if ($result4 && $stmt->rowCount() > 0) {
            $updated = true;
            $updateMessage = "Updated the most recent email in the database.";
        }
    }
    
    // Strategy 5: If no emails exist, create a new one
    if (!$updated) {
        $insertSQL = "
        INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, order_id, created_by) 
        VALUES 
        ('john.doe@example.com', 'orders@whimsicalfrog.us', 'Order Confirmation #01F14P23 - WhimsicalFrog', 
         :sample_content, 'order_confirmation', 'sent', NOW(), '01F14P23', 'system')";
        
        $stmt = $pdo->prepare($insertSQL);
        $result5 = $stmt->execute([':sample_content' => $sampleEmailContent]);
        
        if ($result5) {
            $updated = true;
            $updateMessage = "Created a new sample email since none existed.";
        }
    }
    
    if ($updated) {
        echo "<h2>✅ Sample Email Updated Successfully</h2>";
        echo "<p>$updateMessage</p>";
        echo "<p>The sample email now has realistic order confirmation content.</p>";
        echo "<p>You can now test the Edit/Resend functionality with proper email content.</p>";
        echo '<p><a href="../index.php?page=admin&section=settings" class="admin-link">← Back to Admin Settings</a></p>';
    } else {
        echo "<h2>❌ Failed to Update Sample Email</h2>";
        echo "<p>Could not update or create a sample email. Please check the debug information above.</p>";
        echo '<p><a href="debug_email_logs.php" class="admin-link">→ View Debug Information</a></p>';
        echo '<p><a href="../index.php?page=admin&section=settings" class="admin-link">← Back to Admin Settings</a></p>';
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error Updating Sample Email</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo '<p><a href="../index.php?page=admin&section=settings" class="admin-link">← Back to Admin Settings</a></p>';
}
?> 