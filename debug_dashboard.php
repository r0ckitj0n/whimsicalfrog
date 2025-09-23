<?php

require_once __DIR__ . '/api/config.php';
header('Content-Type: text/plain');

$pdo = Database::getInstance();
$stmt = $pdo->query("SELECT * FROM dashboard_sections ORDER BY display_order ASC");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Dashboard sections count: " . count($sections) . "\n";
foreach ($sections as $section) {
    echo "- {$section['section_key']} (active: {$section['is_active']})\n";
}
