<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $imageDir = __DIR__ . '/../images/products/';
    
    $renames = [
        'AW001A.png' => 'WF-AR-001A.png',
        'TS001A.png' => 'WF-TS-001A.png',
        'TS001B.webp' => 'WF-TS-001B.webp',
        'TS001C.png' => 'WF-TS-001C.png',
        'TS001D.png' => 'WF-TS-001D.png',
        'TS002A.webp' => 'WF-TS-002A.webp',
        'TU001A.png' => 'WF-TU-001A.png',
        'TU002A.png' => 'WF-TU-002A.png'
    ];
    
    $results = [];
    foreach ($renames as $old => $new) {
        $oldPath = $imageDir . $old;
        $newPath = $imageDir . $new;
        
        if (file_exists($oldPath) && !file_exists($newPath)) {
            if (rename($oldPath, $newPath)) {
                $results[] = "✅ $old → $new";
            } else {
                $results[] = "❌ Failed: $old";
            }
        } else {
            $results[] = "⏭️ Skipped: $old";
        }
    }
    
    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 