<?php



// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'api/config.php'; // Corrected path for root directory file
require_once 'includes/auth_helper.php';

// Require admin authentication for social media management
AuthHelper::requireAdmin();

// Default response
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        try {
            $pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
        $action = $_POST['action'];

        if ($action === 'create') {
            // Validate required fields
            $requiredFields = ['id', 'platform', 'content', 'scheduled_date', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Missing required field: " . ucfirst(str_replace('_', ' ', $field)));
                }
            }

            $id = $_POST['id'];
            $platform = $_POST['platform'];
            $content = $_POST['content'];
            $image_url = isset($_POST['image_url']) ? $_POST['image_url'] : null;
            $scheduled_date = date('Y-m-d H:i:s', strtotime($_POST['scheduled_date']));
            $status = $_POST['status'];
            $posted_date = null;

            // Character limit validation for Twitter
            if (strtolower($platform) === 'twitter' && strlen($content) > 280) {
                throw new Exception("Twitter posts cannot exceed 280 characters.");
            }

            $sql = "INSERT INTO social_posts (id, platform, content, image_url, scheduled_date, posted_date, status, created_date) 
                    VALUES (:id, :platform, :content, :image_url, :scheduled_date, :posted_date, :status, NOW())";
            Database::execute($sql, [
                ':id' => $id,
                ':platform' => $platform,
                ':content' => $content,
                ':image_url' => $image_url,
                ':scheduled_date' => $scheduled_date,
                ':posted_date' => $posted_date,
                ':status' => $status
            ]);

            $_SESSION['success_message'] = "Social post created successfully!";
            $response = ['success' => true, 'message' => "Social post created successfully!"];

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
            $image_url = isset($_POST['image_url']) ? $_POST['image_url'] : null;
            $scheduled_date = date('Y-m-d H:i:s', strtotime($_POST['scheduled_date']));
            $status = $_POST['status'];
            $posted_date = isset($_POST['posted_date']) ? $_POST['posted_date'] : null;

            // Character limit validation for Twitter
            if (strtolower($platform) === 'twitter' && strlen($content) > 280) {
                throw new Exception("Twitter posts cannot exceed 280 characters.");
            }

            $sql = "UPDATE social_posts SET 
                    platform = :platform, 
                    content = :content, 
                    image_url = :image_url, 
                    scheduled_date = :scheduled_date, 
                    posted_date = :posted_date, 
                    status = :status 
                    WHERE id = :id";
            Database::execute($sql, [
                ':id' => $id,
                ':platform' => $platform,
                ':content' => $content,
                ':image_url' => $image_url,
                ':scheduled_date' => $scheduled_date,
                ':posted_date' => $posted_date,
                ':status' => $status
            ]);

            $_SESSION['success_message'] = "Social post updated successfully!";
            $response = ['success' => true, 'message' => "Social post updated successfully!"];

        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("Post ID is required for deletion.");
            }
            $id = $_POST['id'];

            $sql = "DELETE FROM social_posts WHERE id = :id";
            $affected = Database::execute($sql, [':id' => $id]);

            if ($affected > 0) {
                $_SESSION['success_message'] = "Social post deleted successfully!";
                $response = ['success' => true, 'message' => "Social post deleted successfully!"];
            } else {
                throw new Exception("Social post not found or already deleted.");
            }

        } elseif ($action === 'publish_now') {
            if (empty($_POST['id'])) {
                throw new Exception("Post ID is required for publishing.");
            }
            $id = $_POST['id'];
            $current_time = date('Y-m-d H:i:s');

            // In a real application, you would integrate with social media APIs here
            // For this simulation, we'll just update the status and posted_date

            $sql = "UPDATE social_posts SET status = 'posted', posted_date = :posted_date WHERE id = :id";
            $affected = Database::execute($sql, [
                ':posted_date' => $current_time,
                ':id' => $id
            ]);

            if ($affected > 0) {
                $_SESSION['success_message'] = "Social post published successfully!";
                $response = ['success' => true, 'message' => "Social post published successfully!"];
            } else {
                throw new Exception("Social post not found or already published.");
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
