<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Ensure admin access
AuthHelper::requireAdmin();

$action = $_GET['action'] ?? $_SERVER['REQUEST_METHOD'];
$pdo = Database::getInstance();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC");
        $coupons = $stmt->fetchAll();
        echo json_encode(['success' => true, 'coupons' => $coupons]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'delete') {
            $id = $input['id'] ?? null;
            if (!$id)
                throw new Exception("Missing ID");

            $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            // Create or Update
            $id = $input['id'] ?? null;
            $code = trim($input['code'] ?? '');
            $description = $input['description'] ?? '';
            $type = $input['type'] ?? WF_Constants::COUPON_TYPE_FIXED;
            $value = floatval($input['value'] ?? 0);
            $min_order_amount = floatval($input['min_order_amount'] ?? 0);
            $expires_at = !empty($input['expires_at']) ? $input['expires_at'] : null;
            $is_active = isset($input['is_active']) ? (bool) $input['is_active'] : true;

            if (!$code)
                throw new Exception("Code is required");

            if ($id) {
                // Update
                $sql = "UPDATE coupons SET 
                        code = ?, description = ?, type = ?, value = ?, 
                        min_order_amount = ?, expires_at = ?, is_active = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code, $description, $type, $value, $min_order_amount, $expires_at, $is_active, $id]);
            } else {
                // Create
                // Check duplicate
                $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch())
                    throw new Exception("Coupon code already exists");

                $sql = "INSERT INTO coupons (code, description, type, value, min_order_amount, expires_at, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code, $description, $type, $value, $min_order_amount, $expires_at, $is_active]);
                $id = $pdo->lastInsertId();
            }

            echo json_encode(['success' => true, 'id' => $id]);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
