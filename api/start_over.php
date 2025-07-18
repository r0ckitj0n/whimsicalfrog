<?php
/**
 * WhimsicalFrog Start Over API
 * Wipes all data except admin accounts - DANGEROUS OPERATION
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check authentication with fallback token support
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin or has valid admin token
if (!isAdminWithToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get JSON input
    $jsonInput = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonInput['confirm']) || $jsonInput['confirm'] !== true) {
        throw new Exception('Confirmation required');
    }

    // Start transaction for safety
    $pdo->beginTransaction();

    $deletedData = [];
    $errors = [];

    try {
        // 1. Delete order-related data
        $stmt = $pdo->prepare("DELETE FROM order_items");
        $stmt->execute();
        $deletedData['order_items'] = $stmt->rowCount();

        $stmt = $pdo->prepare("DELETE FROM orders");
        $stmt->execute();
        $deletedData['orders'] = $stmt->rowCount();

        // 2. Delete item-related data
        $stmt = $pdo->prepare("DELETE FROM item_images");
        $stmt->execute();
        $deletedData['item_images'] = $stmt->rowCount();

        $stmt = $pdo->prepare("DELETE FROM items");
        $stmt->execute();
        $deletedData['items'] = $stmt->rowCount();

        // 3. Delete customer accounts (but preserve admins)
        $stmt = $pdo->prepare("DELETE FROM users WHERE LOWER(role) != 'admin'");
        $stmt->execute();
        $deletedData['customer_accounts'] = $stmt->rowCount();

        // 4. Delete related data tables that reference the above
        $relatedTables = [
            'item_color_assignments',
            'item_size_assignments',
            'item_analytics',
            'item_marketing_preferences',
            'price_suggestions',
            'cost_suggestions',
            'marketing_suggestions',
            'social_posts',
            'email_logs',
            'discount_codes',
            'email_campaigns',
            'email_subscribers'
        ];

        foreach ($relatedTables as $table) {
            try {
                // Check if table exists first
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);

                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("DELETE FROM `$table`");
                    $stmt->execute();
                    $deletedData[$table] = $stmt->rowCount();
                }
            } catch (Exception $e) {
                $errors[] = "Warning: Could not clear table $table: " . $e->getMessage();
            }
        }

        // 5. Reset auto-increment IDs for fresh start
        $resetTables = [
            'orders' => 1,
            'order_items' => 1,
            'items' => 1,
            'item_images' => 1,
            'users' => 1000, // Start user IDs at 1000 to avoid conflicts
        ];

        foreach ($resetTables as $table => $startValue) {
            try {
                $stmt = $pdo->prepare("ALTER TABLE `$table` AUTO_INCREMENT = ?");
                $stmt->execute([$startValue]);
            } catch (Exception $e) {
                $errors[] = "Warning: Could not reset auto-increment for $table: " . $e->getMessage();
            }
        }

        // 6. Clean up uploaded item images from filesystem
        $itemImagesDir = __DIR__ . '/../images/items/';
        if (is_dir($itemImagesDir)) {
            $files = glob($itemImagesDir . '*');
            $deletedFiles = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    try {
                        unlink($file);
                        $deletedFiles++;
                    } catch (Exception $e) {
                        $errors[] = "Warning: Could not delete file " . basename($file);
                    }
                }
            }
            $deletedData['item_image_files'] = $deletedFiles;
        }

        // Commit transaction
        $pdo->commit();

        // Get count of preserved admin accounts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(role) = 'admin'");
        $stmt->execute();
        $preservedAdmins = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => 'System reset completed successfully',
            'deleted_data' => $deletedData,
            'preserved_admins' => $preservedAdmins,
            'warnings' => $errors,
            'summary' => [
                'total_orders_deleted' => $deletedData['orders'] ?? 0,
                'total_items_deleted' => $deletedData['items'] ?? 0,
                'total_customers_deleted' => $deletedData['customer_accounts'] ?? 0,
                'admin_accounts_preserved' => $preservedAdmins
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'Start over operation failed - no data was deleted'
    ]);
}

?> 