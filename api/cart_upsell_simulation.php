<?php
/**
 * Cart Upsell Simulation API
 * Following .windsurfrules: < 300 lines.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/upsell_rules_helper.php';
require_once __DIR__ . '/../includes/upsell/simulator.php';

try {
    Database::getInstance();
    
    // Ensure table exists
    Database::execute("CREATE TABLE IF NOT EXISTS upsell_simulations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        profile_json LONGTEXT NULL,
        cart_skus_json LONGTEXT NULL,
        criteria_json LONGTEXT NULL,
        upsells_json LONGTEXT NULL,
        rationale_json LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    Response::validateMethod(['POST']);
    $input = Response::getJsonInput() ?? [];
    
    $limit = isset($input['limit']) ? max(1, (int)$input['limit']) : 4;
    $res = simulate_shopper_upsells($input['profile'] ?? [], $limit);

    // Persist to DB
    $user = class_exists('AuthHelper') ? (AuthHelper::getCurrentUser() ?? []) : [];
    $uid = (int)($user['id'] ?? $user['user_id'] ?? 0);

    Database::execute(
        "INSERT INTO upsell_simulations (created_by, profile_json, cart_skus_json, criteria_json, upsells_json) VALUES (?, ?, ?, ?, ?)",
        [
            $uid ?: null,
            json_encode($res['profile']),
            json_encode($res['cart_skus']),
            json_encode($res['criteria']),
            json_encode($res['recommendations'])
        ]
    );
    
    $res['id'] = (int)Database::lastInsertId();
    Response::success($res);

} catch (Throwable $e) {
    Response::serverError('Simulation failed', $e->getMessage());
}
