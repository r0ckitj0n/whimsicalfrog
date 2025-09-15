<?php



// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration (absolute paths)
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';

// Require admin authentication for discount code management
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
            $requiredFields = ['id', 'code', 'type', 'value', 'start_date', 'end_date', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) { // Allow 0 for value, min_order, max_uses
                    throw new Exception("Missing required field: " . ucfirst(str_replace('_', ' ', $field)));
                }
            }

            $id = $_POST['id'];
            $code = trim($_POST['code']);
            $type = $_POST['type'];
            $value = floatval($_POST['value']);
            $min_order_amount = isset($_POST['min_order_amount']) ? floatval($_POST['min_order_amount']) : 0.00;
            $max_uses = isset($_POST['max_uses']) ? intval($_POST['max_uses']) : 0;
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            if (empty($code)) {
                throw new Exception("Discount code cannot be empty.");
            }
            if ($value <= 0) {
                throw new Exception("Discount value must be greater than zero.");
            }
            if (strtotime($end_date) < strtotime($start_date)) {
                throw new Exception("End date cannot be before start date.");
            }

            // Check if code already exists
            $row = Database::queryOne("SELECT id FROM discount_codes WHERE code = ?", [$code]);
            if ($row) {
                throw new Exception("Discount code '{$code}' already exists.");
            }

            $sql = "INSERT INTO discount_codes (id, code, type, value, min_order_amount, max_uses, start_date, end_date, status, created_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            Database::execute($sql, [
                $id,
                $code,
                $type,
                $value,
                $min_order_amount,
                $max_uses,
                $start_date,
                $end_date,
                $status
            ]);

            $_SESSION['success_message'] = "Discount code '{$code}' created successfully!";
            $response = ['success' => true, 'message' => "Discount code '{$code}' created successfully!"];

        } elseif ($action === 'update') {
            // Validate required fields
            $requiredFields = ['id', 'code', 'type', 'value', 'start_date', 'end_date', 'status'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) {
                    throw new Exception("Missing required field for update: " . ucfirst(str_replace('_', ' ', $field)));
                }
            }

            $id = $_POST['id'];
            $code = trim($_POST['code']);
            $type = $_POST['type'];
            $value = floatval($_POST['value']);
            $min_order_amount = isset($_POST['min_order_amount']) ? floatval($_POST['min_order_amount']) : 0.00;
            $max_uses = isset($_POST['max_uses']) ? intval($_POST['max_uses']) : 0;
            // current_uses is managed by the system when a code is applied, not directly updated here.
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $status = $_POST['status'];

            if (empty($id)) {
                throw new Exception("Discount ID is required for update.");
            }
            if (empty($code)) {
                throw new Exception("Discount code cannot be empty.");
            }
            if ($value <= 0) {
                throw new Exception("Discount value must be greater than zero.");
            }
            if (strtotime($end_date) < strtotime($start_date)) {
                throw new Exception("End date cannot be before start date.");
            }

            // Check if code already exists for a DIFFERENT ID
            $row = Database::queryOne("SELECT id FROM discount_codes WHERE code = ? AND id != ?", [$code, $id]);
            if ($row) {
                throw new Exception("Discount code '{$code}' already exists for another discount.");
            }

            $sql = "UPDATE discount_codes SET 
                    code = ?, 
                    type = ?, 
                    value = ?, 
                    min_order_amount = ?, 
                    max_uses = ?, 
                    start_date = ?, 
                    end_date = ?, 
                    status = ? 
                    WHERE id = ?";
            Database::execute($sql, [
                $code,
                $type,
                $value,
                $min_order_amount,
                $max_uses,
                $start_date,
                $end_date,
                $status,
                $id
            ]);

            $_SESSION['success_message'] = "Discount code '{$code}' updated successfully!";
            $response = ['success' => true, 'message' => "Discount code '{$code}' updated successfully!"];

        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("Discount ID is required for deletion.");
            }
            $id = $_POST['id'];

            $sql = "DELETE FROM discount_codes WHERE id = ?";
            $affected = Database::execute($sql, [$id]);

            if ($affected > 0) {
                $_SESSION['success_message'] = "Discount code deleted successfully!";
                $response = ['success' => true, 'message' => "Discount code deleted successfully!"];
            } else {
                throw new Exception("Discount code not found or already deleted.");
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
    // Add #discount-codes-section to go to the correct tab/section
    $redirect_url = '/?page=admin&section=marketing#discount-codes-section';
    header("Location: " . $redirect_url);
    exit;
}
