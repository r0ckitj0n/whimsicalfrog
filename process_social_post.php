<?php
session_start();

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/../api/config.php'; // Adjusted path to be relative to this file

// Default response
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Function to generate a unique ID if not provided
function generatePostId($prefix = 'SP', $length = 7) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $id = $prefix;
    for ($i = 0; $i < $length; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $action = $_POST['action'];

        if ($action === 'create') {
            // Validate required fields
            $requiredFields = ['platform', 'content', 'scheduled_date', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: " . ucfirst(str_replace('_', ' ', $field)));
                }
            }

            $id = !empty($_POST['id']) ? $_POST['id'] : generatePostId();
            $platform = $_POST['platform'];
            $content = $_POST['content'];
            $image_url = !empty($_POST['image_url']) ? $_POST['image_url'] : null;
            $scheduled_date = date('Y-m-d H:i:s', strtotime($_POST['scheduled_date']));
            $status = $_POST['status'];
            // posted_date is set when status becomes 'posted'

            $sql = "INSERT INTO social_posts (id, platform, content, image_url, scheduled_date, status, created_date) 
                    VALUES (:id, :platform, :content, :image_url, :scheduled_date, :status, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':platform' => $platform,
                ':content' => $content,
                ':image_url' => $image_url,
                ':scheduled_date' => $scheduled_date,
                ':status' => $status
            ]);

            $_SESSION['success_message'] = "Social post for {$platform} created successfully!";
            $response = ['success' => true, 'message' => "Social post for {$platform} created successfully!"];

        } elseif ($action === 'update') {
            // Validate required fields
            $requiredFields = ['id', 'platform', 'content', 'scheduled_date', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field for update: " . ucfirst(str_replace('_', ' ', $field)));
                }
            }

            $id = $_POST['id'];
            $platform = $_POST['platform'];
            $content = $_POST['content'];
            $image_url = !empty($_POST['image_url']) ? $_POST['image_url'] : null;
            $scheduled_date = date('Y-m-d H:i:s', strtotime($_POST['scheduled_date']));
            $status = $_POST['status'];
            $posted_date = null;

            // If status is changing to 'posted', set posted_date
            if ($status === 'posted') {
                $stmt_check_status = $pdo->prepare("SELECT status FROM social_posts WHERE id = :id");
                $stmt_check_status->execute([':id' => $id]);
                $current_post = $stmt_check_status->fetch(PDO::FETCH_ASSOC);
                if ($current_post && $current_post['status'] !== 'posted') {
                    $posted_date = date('Y-m-d H:i:s');
                } elseif ($current_post && $current_post['status'] === 'posted' && isset($_POST['original_posted_date'])) {
                    $posted_date = $_POST['original_posted_date']; // Keep original if already posted
                }
            }


            $sql = "UPDATE social_posts SET 
                    platform = :platform, 
                    content = :content, 
                    image_url = :image_url, 
                    scheduled_date = :scheduled_date, 
                    status = :status" .
                    ($posted_date ? ", posted_date = :posted_date" : "") .
                   " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            $params = [
                ':id' => $id,
                ':platform' => $platform,
                ':content' => $content,
                ':image_url' => $image_url,
                ':scheduled_date' => $scheduled_date,
                ':status' => $status
            ];
            if ($posted_date) {
                $params[':posted_date'] = $posted_date;
            }
            
            $stmt->execute($params);

            $_SESSION['success_message'] = "Social post for {$platform} updated successfully!";
            $response = ['success' => true, 'message' => "Social post for {$platform} updated successfully!"];

        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("Post ID is required for deletion.");
            }
            $id = $_POST['id'];

            $sql = "DELETE FROM social_posts WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Social post deleted successfully!";
                $response = ['success' => true, 'message' => "Social post deleted successfully!"];
            } else {
                throw new Exception("Social post not found or already deleted.");
            }
        } elseif ($action === 'post_now') { // Simulate posting now
            if (empty($_POST['id'])) {
                throw new Exception("Post ID is required to post now.");
            }
            $id = $_POST['id'];

            $sql = "UPDATE social_posts SET status = 'posted', posted_date = NOW() WHERE id = :id AND status != 'posted'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['success_message'] = "Social post marked as posted successfully!";
                $response = ['success' => true, 'message' => "Social post marked as posted successfully!"];
            } else {
                 $stmt_check = $pdo->prepare("SELECT status FROM social_posts WHERE id = :id");
                 $stmt_check->execute([':id' => $id]);
                 $post_status = $stmt_check->fetchColumn();
                 if ($post_status === 'posted') {
                    throw new Exception("Social post was already posted.");
                 } else {
                    throw new Exception("Social post not found or could not be updated.");
                 }
            }
        } else {
            throw new Exception("Invalid action specified.");
        }

    } catch (PDOException $e) {
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
    // Add #social-media-section to go to the correct tab/section
    $redirect_url = '/?page=admin&section=marketing#social-media-section';
    header("Location: " . $redirect_url);
    exit;
}
?>
