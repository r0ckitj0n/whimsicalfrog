<?php
header('Content-Type: application/json');
$pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
$typeMap = [
    'material' => 'inventory_materials',
    'labor' => 'inventory_labor',
    'energy' => 'inventory_energy'
];
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['inventoryId'])) {
    $inventoryId = $_GET['inventoryId'];
    $materials = $pdo->query("SELECT * FROM inventory_materials WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
    $labor = $pdo->query("SELECT * FROM inventory_labor WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
    $energy = $pdo->query("SELECT * FROM inventory_energy WHERE inventoryId = '" . addslashes($inventoryId) . "'")->fetchAll(PDO::FETCH_ASSOC);
    $result = [
        'materials' => $materials,
        'labor' => $labor,
        'energy' => $energy
    ];
    // Debug output
    if (isset($_GET['debug'])) {
        echo "<pre>DEBUG: inventoryId = ".$inventoryId."\n";
        print_r($result);
        echo "</pre>";
    }
    echo json_encode($result);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add' && isset($typeMap[$type])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $table = $typeMap[$type];
    if ($type === 'material') {
        $stmt = $pdo->prepare("INSERT INTO $table (inventoryId, name, cost) VALUES (?, ?, ?)");
        $stmt->execute([$data['inventoryId'], $data['name'], $data['cost']]);
    } elseif ($type === 'labor' || $type === 'energy') {
        $stmt = $pdo->prepare("INSERT INTO $table (inventoryId, description, cost) VALUES (?, ?, ?)");
        $stmt->execute([$data['inventoryId'], $data['description'], $data['cost']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && isset($typeMap[$type]) && isset($_GET['id'])) {
    $table = $typeMap[$type];
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update' && isset($typeMap[$type]) && isset($_GET['id'])) {
    $table = $typeMap[$type];
    $id = intval($_GET['id']);
    $data = json_decode(file_get_contents('php://input'), true);
    if ($type === 'material') {
        $stmt = $pdo->prepare("UPDATE $table SET name = ?, cost = ? WHERE id = ?");
        $stmt->execute([$data['name'], $data['cost'], $id]);
    } elseif ($type === 'labor' || $type === 'energy') {
        $stmt = $pdo->prepare("UPDATE $table SET description = ?, cost = ? WHERE id = ?");
        $stmt->execute([$data['description'], $data['cost'], $id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid request']); 