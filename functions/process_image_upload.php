<?php

require_once __DIR__ . '/api/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image'])) {
    echo json_encode(['success' => false,'error' => 'Invalid request']);
    exit;
}

$sku = $_POST['sku'] ?? '';
$itemId = $_POST['itemId'] ?? '';
if ($sku !== '') {
    $baseName = $sku;
} elseif ($itemId !== '') {
    $baseName = $itemId;
} else {
    $baseName = 'tmp'.time();
}

$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
$allowed = ['png','jpg','jpeg','webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false,'error' => 'Unsupported file type']);
    exit;
}

// generate filename unique
$filename = $baseName.'-'.substr(md5(uniqid()), 0, 6).'.'.$ext;
$relPath = 'images/items/'.$filename;
$absPath = dirname(__FILE__).'/'.$relPath;
$dir = dirname($absPath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
if (move_uploaded_file($_FILES['image']['tmp_name'], $absPath)) {
    chmod($absPath, 0644);
    // TEMP LOG
    error_log("[image_upload] saved $relPath\n", 3, __DIR__ . '/inventory_errors.log');
    echo json_encode(['success' => true,'imageUrl' => $relPath]);
} else {
    echo json_encode(['success' => false,'error' => 'Failed to move file']);
}
