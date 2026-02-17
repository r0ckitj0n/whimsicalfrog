<?php

// Include the configuration file
require_once __DIR__ . '/config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if specific user ID is requested
    $user_id = $_GET['id'] ?? null;

    if ($user_id) {
        // Query for specific user - handle both integer IDs and string IDs (like emails or alphanumeric)
        $userData = Database::queryOne('SELECT * FROM users WHERE id = ? OR email = ? OR username = ?', [$user_id, $user_id, $user_id]);

        if (!$userData) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        // Fetch metadata
        require_once dirname(__DIR__) . '/includes/user_meta.php';
        $meta = get_user_meta_bulk($userData['id']);
        $primaryAddress = Database::queryOne(
            "SELECT address_line_1, address_line_2, city, state, zip_code
             FROM addresses
             WHERE owner_type = ? AND owner_id = ?
             ORDER BY is_default DESC, id ASC
             LIMIT 1",
            ['customer', $userData['id']]
        ) ?: [];

        // Fetch order history - handle orders linked by ID, email, or username
        $orders = Database::queryAll('SELECT * FROM orders WHERE user_id = ? OR user_id = ? OR user_id = ? ORDER BY created_at DESC', [$userData['id'], $userData['email'], $userData['username']]) ?: [];

        // Format single user data (normalized to snake_case)
        $formattedUser = [
            'id' => $userData['id'] ?? null,
            'username' => $userData['username'] ?? '',
            'email' => $userData['email'] ?? '',
            'role' => $userData['role'] ?? '',
            'first_name' => $userData['first_name'] ?? '',
            'last_name' => $userData['last_name'] ?? '',
            'phone_number' => $userData['phone_number'] ?? '',
            'address_line_1' => $primaryAddress['address_line_1'] ?? '',
            'address_line_2' => $primaryAddress['address_line_2'] ?? '',
            'city' => $primaryAddress['city'] ?? '',
            'state' => $primaryAddress['state'] ?? '',
            'zip_code' => $primaryAddress['zip_code'] ?? '',
            // Metadata
            'company' => $meta['company'] ?? '',
            'job_title' => $meta['job_title'] ?? '',
            'preferred_contact' => $meta['preferred_contact'] ?? 'email',
            'preferred_language' => $meta['preferred_language'] ?? 'English',
            'marketing_opt_in' => $meta['marketing_opt_in'] ?? '1',
            'status' => $meta['status'] ?? 'active',
            'vip' => $meta['vip'] ?? '0',
            'tax_exempt' => $meta['tax_exempt'] ?? '0',
            'referral_source' => $meta['referral_source'] ?? '',
            'birthdate' => $meta['birthdate'] ?? '',
            'tags' => $meta['tags'] ?? '',
            'admin_notes' => $meta['admin_notes'] ?? '',
            // Order History
            'order_history' => $orders
        ];

        // Return single user as JSON
        echo json_encode($formattedUser);
        exit;
    } else {
        // Query for all users with primary/default address
        $users = Database::queryAll(
            "SELECT u.*,
                    pa.address_line_1,
                    pa.address_line_2,
                    pa.city,
                    pa.state,
                    pa.zip_code
             FROM users u
             LEFT JOIN (
                 SELECT ca1.owner_id, ca1.address_line_1, ca1.address_line_2, ca1.city, ca1.state, ca1.zip_code
                 FROM addresses ca1
                 LEFT JOIN addresses ca2
                   ON ca2.owner_type = ca1.owner_type
                  AND ca2.owner_id = ca1.owner_id
                  AND (
                       ca2.is_default > ca1.is_default
                       OR (ca2.is_default = ca1.is_default AND ca2.id < ca1.id)
                  )
                 WHERE ca1.owner_type = 'customer' AND ca2.id IS NULL
             ) pa ON pa.owner_id = u.id"
        );

        // Fetch order counts - handle orders linked by ID, email, or username
        $orderCounts = [];
        // This query joins orders with users to aggregate counts across all possible link identifiers
        $countsRows = Database::queryAll('
            SELECT u.id as actual_user_id, COUNT(o.id) as cnt 
            FROM users u
            INNER JOIN orders o ON o.user_id = u.id OR o.user_id = u.email OR o.user_id = u.username
            GROUP BY u.id
        ');
        foreach ($countsRows as $row) {
            $orderCounts[$row['actual_user_id']] = (int) $row['cnt'];
        }

        // Fetch all metadata bulk
        $allMeta = [];
        $metaRows = Database::queryAll('SELECT user_id, meta_key, meta_value FROM users_meta');
        foreach ($metaRows as $mrow) {
            $allMeta[$mrow['user_id']][$mrow['meta_key']] = $mrow['meta_value'];
        }

        // Map database fields to expected output format (normalized to snake_case)
        $formattedUsers = array_map(function ($user) use ($orderCounts, $allMeta) {
            $id = $user['id'] ?? null;
            $meta = $allMeta[$id] ?? [];
            return [
                'id' => $id,
                'username' => $user['username'] ?? '',
                'email' => $user['email'] ?? '',
                'role' => $user['role'] ?? '',
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'phone_number' => $user['phone_number'] ?? '',
                'address_line_1' => $user['address_line_1'] ?? '',
                'address_line_2' => $user['address_line_2'] ?? '',
                'city' => $user['city'] ?? '',
                'state' => $user['state'] ?? '',
                'zip_code' => $user['zip_code'] ?? '',
                'order_count' => $orderCounts[$id] ?? 0,
                // Include metadata in list for consistency
                'company' => $meta['company'] ?? '',
                'status' => $meta['status'] ?? 'active',
                'vip' => $meta['vip'] ?? '0'
            ];
        }, $users);

        // Return users as JSON
        echo json_encode($formattedUsers);
    }

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
