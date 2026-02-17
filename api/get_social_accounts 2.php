<?php

/**
 * Get Social Media Accounts API
 * Returns list of connected social media accounts
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $pdo = Database::getInstance();

    // Get all social accounts
    $accounts = Database::queryAll(
        "SELECT id, platform, account_name, connected, last_updated 
         FROM social_accounts 
         ORDER BY platform, account_name"
    );

    // Add platform icons and colors
    foreach ($accounts as &$account) {
        switch (strtolower($account['platform'])) {
            case 'facebook':
                $account['icon'] = '📘';
                $account['color'] = '#1877f2';
                break;
            case 'twitter':
                $account['icon'] = '🐦';
                $account['color'] = '#1da1f2';
                break;
            case 'instagram':
                $account['icon'] = '📷';
                $account['color'] = '#e4405f';
                break;
            case 'linkedin':
                $account['icon'] = '💼';
                $account['color'] = '#0077b5';
                break;
            case 'youtube':
                $account['icon'] = '📺';
                $account['color'] = '#ff0000';
                break;
            case 'tiktok':
                $account['icon'] = '🎵';
                $account['color'] = '#000000';
                break;
            default:
                $account['icon'] = '📱';
                $account['color'] = '#6b7280';
        }

        // Format last updated
        if ($account['last_updated']) {
            $account['last_updated_formatted'] = date('M j, Y', strtotime($account['last_updated']));
        }
    }

    echo json_encode([
        'success' => true,
        'accounts' => $accounts,
        'count' => count($accounts)
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_social_accounts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in get_social_accounts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
