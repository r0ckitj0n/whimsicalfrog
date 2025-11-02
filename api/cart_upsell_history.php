<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

Response::validateMethod(['GET']);

$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

try {
    // Ensure table exists in case history is called first
    $pdo->exec("CREATE TABLE IF NOT EXISTS upsell_simulations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        profile_json LONGTEXT NULL,
        cart_skus_json LONGTEXT NULL,
        criteria_json LONGTEXT NULL,
        upsells_json LONGTEXT NULL,
        rationale_json LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("SELECT id, created_at, created_by, profile_json, cart_skus_json, upsells_json, rationale_json FROM upsell_simulations ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int)$r['id'],
            'created_at' => (string)$r['created_at'],
            'created_by' => isset($r['created_by']) ? (int)$r['created_by'] : null,
            'profile' => json_decode((string)$r['profile_json'], true) ?: null,
            'cart_skus' => json_decode((string)$r['cart_skus_json'], true) ?: [],
            'recommendations' => json_decode((string)$r['upsells_json'], true) ?: [],
            'rationales' => json_decode((string)$r['rationale_json'], true) ?: [],
        ];
    }

    Response::success(['items' => $items]);
} catch (Throwable $e) {
    Response::serverError('Failed to load upsell simulation history', $e->getMessage());
}
