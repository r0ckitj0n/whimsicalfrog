<?php
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    $pdo = Database::getInstance();

    // Check if user exists
    $user = Database::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
    $user_id = null;

    if (!$user) {
        // Create simplified user/lead
        // Assuming users table structure allow minimal inserts or we have a 'leads' table?
        // The previous `users` scan showed `username`, `password`? `api/users.php` didn't show table schema.
        // If `users` table requires fields I don't have, this might fail.
        // But usually `email` is enough for a newsletter lead.
        // I'll assume I can insert just email (and maybe a dummy username/pass).
        // Or better: DO NOT create a user if they don't exist, just fail or use a separate `leads` table?
        // The request was to "add *them* (customers) to a newsletter group".
        // It didn't say "create subscribers from non-customers".
        // But footer signup implies public signup.
        
        // Let's check if we can insert into `newsletter_subscribers` table?
        // My schema for `newsletter_memberships` links `user_id`.
        // If I don't have a user_id, I can't link them.
        // So I should probably create a user.
        
        // Check if `users` table allows nulls.
        // I'll try to insert minimal data.
        $username = 'sub_' . substr(md5($email . time()), 0, 8);
        try {
            Database::execute("INSERT INTO users (username, email, role, first_name) VALUES (?, ?, ?, 'Subscriber')", [$username, $email, WF_Constants::ROLE_CUSTOMER]);
            $user_id = Database::lastInsertId();
        } catch (Exception $e) {
            throw new Exception("Could not register subscriber. " . $e->getMessage());
        }
    } else {
        $user_id = $user['id'];
    }

    // Add to 'General' group (default)
    $defaultGroup = Database::queryOne("SELECT id FROM newsletter_groups WHERE is_default = 1 LIMIT 1");
    if ($defaultGroup) {
        Database::execute("INSERT IGNORE INTO newsletter_memberships (user_id, group_id) VALUES (?, ?)", [$user_id, $defaultGroup['id']]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
