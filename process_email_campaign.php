<?php
session_start();

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'api/config.php'; // Corrected path for root directory file

// Default response
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
        $action = $_POST['action'];

        if ($action === 'create') {
            // Validate required fields
            $requiredFields = ['id', 'name', 'subject', 'content', 'target_audience', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: " . ucfirst($field));
                }
            }

            $id = $_POST['id'];
            $name = $_POST['name'];
            $subject = $_POST['subject'];
            $content = $_POST['content'];
            $target_audience = $_POST['target_audience'];
            $status = $_POST['status'];
            $sent_date = null;

            if ($status === 'scheduled' && !empty($_POST['sent_date'])) {
                $sent_date = date('Y-m-d H:i:s', strtotime($_POST['sent_date']));
            } elseif ($status === 'sent') {
                $sent_date = date('Y-m-d H:i:s'); // Set current time if status is 'sent' immediately
            }


            $sql = "INSERT INTO email_campaigns (id, name, subject, content, status, created_date, sent_date, target_audience) 
                    VALUES (:id, :name, :subject, :content, :status, NOW(), :sent_date, :target_audience)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':subject' => $subject,
                ':content' => $content,
                ':status' => $status,
                ':sent_date' => $sent_date,
                ':target_audience' => $target_audience
            ]);

            $_SESSION['success_message'] = "Email campaign '{$name}' created successfully!";
            $response = ['success' => true, 'message' => "Email campaign '{$name}' created successfully!"];

        } elseif ($action === 'update') {
            // Validate required fields
            $requiredFields = ['id', 'name', 'subject', 'content', 'target_audience', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field for update: " . ucfirst($field));
                }
            }

            $id = $_POST['id'];
            $name = $_POST['name'];
            $subject = $_POST['subject'];
            $content = $_POST['content'];
            $target_audience = $_POST['target_audience'];
            $status = $_POST['status'];
            $sent_date = null;

            if ($status === 'scheduled' && !empty($_POST['sent_date'])) {
                $sent_date = date('Y-m-d H:i:s', strtotime($_POST['sent_date']));
            } elseif ($status === 'sent' && empty($_POST['original_sent_date'])) { // Only set sent_date if it wasn't already sent
                 $sent_date = date('Y-m-d H:i:s');
            } elseif (!empty($_POST['original_sent_date'])) {
                $sent_date = $_POST['original_sent_date']; // Keep original if already set
            }


            $sql = "UPDATE email_campaigns SET 
                    name = :name, 
                    subject = :subject, 
                    content = :content, 
                    status = :status, 
                    sent_date = :sent_date, 
                    target_audience = :target_audience 
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':subject' => $subject,
                ':content' => $content,
                ':status' => $status,
                ':sent_date' => $sent_date,
                ':target_audience' => $target_audience
            ]);

            $_SESSION['success_message'] = "Email campaign '{$name}' updated successfully!";
            $response = ['success' => true, 'message' => "Email campaign '{$name}' updated successfully!"];

        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("Campaign ID is required for deletion.");
            }
            $id = $_POST['id'];

            // Optional: First delete related sends if you have foreign key constraints or want to clean up
            // $stmt_sends = $pdo->prepare("DELETE FROM email_campaign_sends WHERE campaign_id = :campaign_id");
            // $stmt_sends->execute([':campaign_id' => $id]);

            $sql = "DELETE FROM email_campaigns WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Email campaign deleted successfully!";
                $response = ['success' => true, 'message' => "Email campaign deleted successfully!"];
            } else {
                throw new Exception("Email campaign not found or already deleted.");
            }
        } elseif ($action === 'send_campaign') {
            if (empty($_POST['id'])) {
                throw new Exception("Campaign ID is required for sending.");
            }
            $campaign_id = $_POST['id'];

            // Fetch campaign details
            $stmt_campaign = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = :id");
            $stmt_campaign->execute([':id' => $campaign_id]);
            $campaign = $stmt_campaign->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                throw new Exception("Campaign not found.");
            }
            if ($campaign['status'] === 'sent') {
                throw new Exception("Campaign has already been sent.");
            }

            // Fetch subscribers based on target audience
            $subscribers_sql = "SELECT id, email FROM email_subscribers WHERE status = 'active'";
            // TODO: Implement target_audience filtering (e.g., 'customers', 'non-customers')
            // For now, sending to all active subscribers
            
            $stmt_subscribers = $pdo->prepare($subscribers_sql);
            $stmt_subscribers->execute();
            $subscribers = $stmt_subscribers->fetchAll(PDO::FETCH_ASSOC);

            if (empty($subscribers)) {
                throw new Exception("No active subscribers found for this campaign.");
            }

            $pdo->beginTransaction();
            $sent_count = 0;
            $current_time = date('Y-m-d H:i:s');

            // In a real application, you would loop and send emails here.
            // For this simulation, we'll just record the sends.
            $stmt_insert_send = $pdo->prepare(
                "INSERT INTO email_campaign_sends (id, campaign_id, subscriber_id, sent_date) 
                 VALUES (:id, :campaign_id, :subscriber_id, :sent_date)"
            );

            foreach ($subscribers as $subscriber) {
                $send_id_prefix = 'ECS';
                $send_id_chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $send_id = $send_id_prefix;
                for ($i = 0; $i < 7; $i++) { // Generate a 7-char random part for the ID
                    $send_id .= $send_id_chars[rand(0, strlen($send_id_chars) - 1)];
                }
                
                $stmt_insert_send->execute([
                    ':id' => $send_id,
                    ':campaign_id' => $campaign_id,
                    ':subscriber_id' => $subscriber['id'],
                    ':sent_date' => $current_time
                ]);
                $sent_count++;
            }

            // Update campaign status and sent_date
            $stmt_update_campaign = $pdo->prepare(
                "UPDATE email_campaigns SET status = 'sent', sent_date = :sent_date WHERE id = :id"
            );
            $stmt_update_campaign->execute([':sent_date' => $current_time, ':id' => $campaign_id]);

            $pdo->commit();
            $_SESSION['success_message'] = "Campaign '{$campaign['name']}' sent to {$sent_count} subscribers.";
            $response = ['success' => true, 'message' => "Campaign '{$campaign['name']}' sent to {$sent_count} subscribers."];

        } else {
            throw new Exception("Invalid action specified.");
        }

    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        $response = ['success' => false, 'message' => "Database error: " . $e->getMessage(), 'details' => $e->getTraceAsString()];
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        $response = ['success' => false, 'message' => "Error: " . $e->getMessage(), 'details' => $e->getTraceAsString()];
    }
} else {
    $_SESSION['error_message'] = "Invalid request method or action not set.";
    $response = ['success' => false, 'message' => "Invalid request method or action not set."];
}

// Redirect back to the marketing page or return JSON
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // AJAX request, return JSON
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    // Standard form submission, redirect
    $redirect_url = '/?page=admin&section=marketing';
    if (isset($_POST['id']) && $action !== 'delete') { // If an ID was involved and it wasn't a delete, maybe stay on an edit page or specific tab
         // $redirect_url .= '&tool=email-campaigns&campaign_id=' . $_POST['id']; // Example
    }
    header("Location: " . $redirect_url);
    exit;
}
?>