<?php
require_once 'config.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
function checkAuth()
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit();
    }

    $userRole = strtolower($_SESSION['user']['role'] ?? '');
    if ($userRole !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit();
    }
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get action from query params, form data, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // If no action found in GET/POST, check JSON input
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }

    switch ($action) {

        case 'list':
            checkAuth();

            $stmt = $pdo->prepare("
                SELECT s.*, 
                       COUNT(si.item_sku) as item_count,
                       CASE 
                           WHEN s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date THEN 'active'
                           WHEN s.is_active = 1 AND NOW() < s.start_date THEN 'scheduled'
                           WHEN s.is_active = 1 AND NOW() > s.end_date THEN 'expired'
                           ELSE 'inactive'
                       END as status
                FROM sales s
                LEFT JOIN sale_items si ON s.id = si.sale_id
                GROUP BY s.id
                ORDER BY s.created_at DESC
            ");
            $stmt->execute();
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'sales' => $sales]);
            break;

        case 'get':
            checkAuth();

            $saleId = $_GET['id'] ?? 0;
            if (!$saleId) {
                echo json_encode(['error' => 'Sale ID required']);
                break;
            }

            // Get sale details
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                echo json_encode(['error' => 'Sale not found']);
                break;
            }

            // Get sale items
            $stmt = $pdo->prepare("
                SELECT si.item_sku, i.name as item_name, i.retailPrice as original_price
                FROM sale_items si
                JOIN items i ON si.item_sku = i.sku
                WHERE si.sale_id = ?
            ");
            $stmt->execute([$saleId]);
            $saleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sale['items'] = $saleItems;
            echo json_encode(['success' => true, 'sale' => $sale]);
            break;

        case 'create':
            checkAuth();

            $input = json_decode(file_get_contents('php://input'), true);

            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $discountPercentage = $input['discount_percentage'] ?? 0;
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $isActive = $input['is_active'] ?? true;
            $items = $input['items'] ?? [];

            if (!$name || !$discountPercentage || !$startDate || !$endDate) {
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Create sale
                $stmt = $pdo->prepare("
                    INSERT INTO sales (name, description, discount_percentage, start_date, end_date, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $discountPercentage, $startDate, $endDate, $isActive]);
                $saleId = $pdo->lastInsertId();

                // Add sale items
                if (!empty($items)) {
                    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, item_sku) VALUES (?, ?)");
                    foreach ($items as $itemSku) {
                        $stmt->execute([$saleId, $itemSku]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'sale_id' => $saleId, 'message' => 'Sale created successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Failed to create sale: ' . $e->getMessage()]);
            }
            break;

        case 'update':
            checkAuth();

            $input = json_decode(file_get_contents('php://input'), true);

            $saleId = $input['id'] ?? 0;
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $discountPercentage = $input['discount_percentage'] ?? 0;
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $isActive = $input['is_active'] ?? true;
            $items = $input['items'] ?? [];

            if (!$saleId || !$name || !$discountPercentage || !$startDate || !$endDate) {
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }

            $pdo->beginTransaction();

            try {
                // Update sale
                $stmt = $pdo->prepare("
                    UPDATE sales 
                    SET name = ?, description = ?, discount_percentage = ?, start_date = ?, end_date = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $discountPercentage, $startDate, $endDate, $isActive, $saleId]);

                // Remove existing sale items
                $stmt = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
                $stmt->execute([$saleId]);

                // Add new sale items
                if (!empty($items)) {
                    $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, item_sku) VALUES (?, ?)");
                    foreach ($items as $itemSku) {
                        $stmt->execute([$saleId, $itemSku]);
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Sale updated successfully']);

            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => 'Failed to update sale: ' . $e->getMessage()]);
            }
            break;

        case 'delete':
            checkAuth();

            $saleId = $_POST['id'] ?? $_GET['id'] ?? 0;
            if (!$saleId) {
                echo json_encode(['error' => 'Sale ID required']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $result = $stmt->execute([$saleId]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Sale deleted successfully']);
            } else {
                echo json_encode(['error' => 'Failed to delete sale']);
            }
            break;

        case 'get_active_sales':
            // Get currently active sales for an item (public endpoint)
            $itemSku = $_GET['item_sku'] ?? '';

            if (!$itemSku) {
                echo json_encode(['success' => true, 'sales' => []]);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT s.*, si.item_sku
                FROM sales s
                JOIN sale_items si ON s.id = si.sale_id
                WHERE si.item_sku = ? 
                AND s.is_active = 1
                AND NOW() BETWEEN s.start_date AND s.end_date
                ORDER BY s.discount_percentage DESC
                LIMIT 1
            ");
            $stmt->execute([$itemSku]);
            $activeSale = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'sale' => $activeSale]);
            break;

        case 'get_all_items':
            checkAuth();

            // Get all items for sale assignment
            $stmt = $pdo->prepare("SELECT sku, name, retailPrice FROM items ORDER BY name");
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'toggle_active':
            checkAuth();

            $saleId = $_POST['id'] ?? 0;
            if (!$saleId) {
                echo json_encode(['error' => 'Sale ID required']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE sales SET is_active = NOT is_active WHERE id = ?");
            $result = $stmt->execute([$saleId]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Sale status updated']);
            } else {
                echo json_encode(['error' => 'Failed to update sale status']);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?> 