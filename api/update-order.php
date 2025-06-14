<?php
// api/update-order.php
require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

if (empty($input['orderId'])) { http_response_code(400); echo json_encode(['error'=>'orderId required']); exit; }
$orderId = $input['orderId'];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // Ensure order exists
    $chk = $pdo->prepare('SELECT id FROM orders WHERE id = ?');
    $chk->execute([$orderId]);
    if (!$chk->fetch()) { http_response_code(404); echo json_encode(['error'=>'Order not found']); exit; }

    $pdo->beginTransaction();

    // --- Update order scalar fields (reuse logic similar to update-payment-status) ---
    $updateMap=[]; $params=[':orderId'=>$orderId];
    $scalarFields = [
        'status'=>'status',
        'trackingNumber'=>'trackingNumber',
        'paymentMethod'=>'paymentMethod',
        'shippingMethod'=>'shippingMethod',
        'paymentStatus'=>'paymentStatus',
        'paymentDate'=>'paymentDate',
        'paymentNotes'=>'paymentNotes',
        'shippingAddress'=>'shippingAddress',
        'checkNumber'=>'checkNumber'
    ];
    foreach ($scalarFields as $k=>$col) {
        if (array_key_exists($k,$input)) {
            $updateMap[] = "$col = :$k";
            $params[":$k"] = $input[$k] === '' ? null : $input[$k];
        }
    }
    if ($updateMap) {
        $sql='UPDATE orders SET '.implode(', ',$updateMap).' WHERE id = :orderId';
        $stmt=$pdo->prepare($sql);
        $stmt->execute($params);
    }

    // --- Update items ---
    if (isset($input['items']) && is_array($input['items'])) {
        // delete existing items
        $pdo->prepare('DELETE FROM order_items WHERE orderId = ?')->execute([$orderId]);
        
        // Get the next order item ID sequence number
        $itemCountStmt = $pdo->prepare('SELECT COUNT(*) FROM order_items');
        $itemCountStmt->execute();
        $itemCount = $itemCountStmt->fetchColumn();
        
        $insert = $pdo->prepare('INSERT INTO order_items (id, orderId, productId, quantity, price) VALUES (?,?,?,?, (SELECT basePrice FROM products WHERE id = ?))');
        $itemIndex = 0;
        foreach ($input['items'] as $row) {
            if (empty($row['productId']) || empty($row['quantity'])) continue;
            
            // Generate streamlined order item ID
            $itemSequence = str_pad($itemCount + $itemIndex + 1, 3, '0', STR_PAD_LEFT);
            $itemId = 'OI' . $itemSequence;
            $itemIndex++;
            
            $qty = (int)$row['quantity'];
            $pid = $row['productId'];
            $insert->execute([$itemId, $orderId, $pid, $qty, $pid]);
        }
        // recalc total
        $totalStmt=$pdo->prepare('SELECT SUM(quantity*price) AS total FROM order_items WHERE orderId = ?');
        $totalStmt->execute([$orderId]);
        $newTotal = $totalStmt->fetchColumn() ?: 0;
        $pdo->prepare('UPDATE orders SET total = ? WHERE id = ?')->execute([$newTotal, $orderId]);
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'orderId'=>$orderId]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['error'=>'Server error','details'=>$e->getMessage()]);
} 