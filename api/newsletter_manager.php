<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';

header('Content-Type: application/json');

// Basic auth check placeholder (assumes admin context)
// if (!is_admin()) { http_response_code(403); echo json_encode(['error'=>'Unauthorized']); exit; }

$action = $_GET['action'] ?? 'list_campaigns';
$method = $_SERVER['REQUEST_METHOD'];

function generateNewsletterHtml($subject, $content)
{
    $business_name = BusinessSettings::getBusinessName();
    $businessUrl = BusinessSettings::getSiteUrl('');
    $domain = BusinessSettings::getBusinessDomain();

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { margin: 0; padding: 0; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f9fafb; color: #374151; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 20px; margin-bottom: 20px; }
            .header { background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 1px solid #e5e7eb; }
            .header h1 { margin: 0; font-size: 24px; color: #111827; font-family: "Merienda", cursive; color: #87ac3a; }
            .content { padding: 30px 20px; line-height: 1.6; font-size: 16px; color: #374151; }
            .footer { background-color: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
            .footer a { color: #87ac3a; text-decoration: none; }
            .btn { display: inline-block; background-color: #87ac3a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; }
        </style>
        <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@700&display=swap" rel="stylesheet">
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($business_name) . '</h1>
            </div>
            <div class="content">
                ' . nl2br($content) . ' 
            </div>
            <div class="footer">
                <p>You are receiving this email because you subscribed to our newsletter.</p>
                <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($business_name) . '. <a href="' . htmlspecialchars($businessUrl) . '">' . htmlspecialchars($domain) . '</a></p>
            </div>
        </div>
    </body>
    </html>';
}

try {
    $pdo = Database::getInstance();

    // --- SUBSCRIBERS ---
    if ($action === 'list') {
        // Return all unique users who are in at least one group
        $subscribers = Database::queryAll("
            SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, 1 as is_active, 
                   (SELECT MIN(created_at) FROM newsletter_memberships WHERE user_id = u.id) as subscribed_at
            FROM users u
            JOIN newsletter_memberships m ON u.id = m.user_id
            ORDER BY subscribed_at DESC
        ");
        echo json_encode(['success' => true, 'subscribers' => $subscribers]);
        exit;
    }

    if ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? ($_POST['id'] ?? $_GET['id'] ?? null);
        if (!$id)
            throw new Exception("Missing Subscriber ID");
        // Remove from all groups
        Database::execute("DELETE FROM newsletter_memberships WHERE user_id = ?", [$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'add_subscriber') {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address");
        }

        // Check if user already exists
        $existing = Database::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $userId = $existing['id'];
        } else {
            // Create minimal user
            $username = 'sub_' . time() . '_' . rand(1000, 9999);
            Database::execute(
                "INSERT INTO users (username, email, role, first_name) VALUES (?, ?, ?, 'Subscriber')",
                [$username, $email, WF_Constants::ROLE_CUSTOMER]
            );
            $userId = Database::lastInsertId();
        }

        // Add to default newsletter group
        $defaultGroup = Database::queryOne("SELECT id FROM newsletter_groups WHERE is_default = 1");
        if (!$defaultGroup) {
            // Create default group if it doesn't exist
            Database::execute("INSERT INTO newsletter_groups (name, description, is_default) VALUES ('General', 'Default newsletter group', 1)");
            $defaultGroup = ['id' => Database::lastInsertId()];
        }

        // Add membership (ignore if already exists)
        Database::execute("INSERT IGNORE INTO newsletter_memberships (user_id, group_id) VALUES (?, ?)", [$userId, $defaultGroup['id']]);

        echo json_encode(['success' => true, 'user_id' => $userId]);
        exit;
    }

    if ($action === 'update_subscriber') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        if (!$id)
            throw new Exception("Missing Subscriber ID");

        $is_active = $data['is_active'] ?? null;
        if ($is_active !== null) {
            if ($is_active) {
                // Re-add to default group
                $defaultGroup = Database::queryOne("SELECT id FROM newsletter_groups WHERE is_default = 1");
                if ($defaultGroup) {
                    Database::execute("INSERT IGNORE INTO newsletter_memberships (user_id, group_id) VALUES (?, ?)", [$id, $defaultGroup['id']]);
                }
            } else {
                // Remove from all groups (unsubscribe)
                Database::execute("DELETE FROM newsletter_memberships WHERE user_id = ?", [$id]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }


    // --- GROUPS ---
    if ($action === 'list_groups') {
        $groups = Database::queryAll("SELECT * FROM newsletter_groups ORDER BY name ASC");
        // Get count of members
        foreach ($groups as &$g) {
            $c = Database::queryOne("SELECT COUNT(*) as cnt FROM newsletter_memberships WHERE group_id = ?", [$g['id']]);
            $g['member_count'] = $c['cnt'] ?? 0;
        }
        echo json_encode(['success' => true, 'groups' => $groups]);
        exit;
    }

    if ($action === 'save_group') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $name = $data['name'] ?? 'Untitled Group';
        $desc = $data['description'] ?? '';

        if ($id) {
            Database::execute("UPDATE newsletter_groups SET name=?, description=? WHERE id=?", [$name, $desc, $id]);
        } else {
            Database::execute("INSERT INTO newsletter_groups (name, description) VALUES (?, ?)", [$name, $desc]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_group') {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        if (!$id)
            throw new Exception("Missing ID");
        // Check if default
        $g = Database::queryOne("SELECT is_default FROM newsletter_groups WHERE id=?", [$id]);
        if ($g && $g['is_default'])
            throw new Exception("Cannot delete default group");

        Database::execute("DELETE FROM newsletter_groups WHERE id=?", [$id]);
        Database::execute("DELETE FROM newsletter_memberships WHERE group_id=?", [$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // --- CAMPAIGNS ---
    if ($action === 'list_campaigns') {
        $campaigns = Database::queryAll("
            SELECT c.*, g.name as group_name 
            FROM newsletter_campaigns c 
            LEFT JOIN newsletter_groups g ON c.target_group_id = g.id 
            ORDER BY c.created_at DESC
        ");
        echo json_encode(['success' => true, 'campaigns' => $campaigns]);
        exit;
    }

    if ($action === 'save_campaign') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $subject = $data['subject'] ?? '(No Subject)';
        $content = $data['content'] ?? '';
        $groupId = $data['target_group_id'] ?? null;
        $status = $data['status'] ?? WF_Constants::NEWSLETTER_STATUS_DRAFT;

        if ($id) {
            Database::execute(
                "UPDATE newsletter_campaigns SET subject=?, content=?, target_group_id=?, status=? WHERE id=?",
                [$subject, $content, $groupId, $status, $id]
            );
        } else {
            Database::execute(
                "INSERT INTO newsletter_campaigns (subject, content, target_group_id, status) VALUES (?, ?, ?, ?)",
                [$subject, $content, $groupId, $status]
            );
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_campaign') {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        if (!$id)
            throw new Exception("Missing ID");
        Database::execute("DELETE FROM newsletter_campaigns WHERE id=?", [$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'send_campaign') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        if (!$id)
            throw new Exception("Missing Campaign ID");

        $campaign = Database::queryOne("SELECT * FROM newsletter_campaigns WHERE id=?", [$id]);
        if (!$campaign)
            throw new Exception("Campaign not found");

        $groupId = $campaign['target_group_id'];
        if (!$groupId)
            throw new Exception("No target group selected");

        // Get members
        // Assuming 'users' table has 'email'
        $members = Database::queryAll("
            SELECT u.email, u.first_name 
            FROM newsletter_memberships m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.group_id = ?
        ", [$groupId]);

        $count = 0;

        // Configure EmailHelper using BusinessSettings
        require_once __DIR__ . '/../includes/secret_store.php';
        $emailSettings = BusinessSettings::getByCategory('email');
        $smtpEnabledVal = $emailSettings['smtp_enabled'] ?? false;
        $smtpEnabled = is_bool($smtpEnabledVal) ? $smtpEnabledVal : in_array(strtolower((string) $smtpEnabledVal), ['1', 'true', 'yes', 'on'], true);

        $fromEmail = BusinessSettings::getBusinessEmail();
        $fromName = BusinessSettings::getBusinessName();
        $secUser = secret_get('smtp_username');
        $secPass = secret_get('smtp_password');

        EmailHelper::configure([
            'smtp_enabled' => $smtpEnabled,
            'smtp_host' => (string) ($emailSettings['smtp_host'] ?? ''),
            'smtp_port' => (int) ($emailSettings['smtp_port'] ?? 587),
            'smtp_username' => $secUser ?: '',
            'smtp_password' => $secPass ?: '',
            'smtp_encryption' => (string) ($emailSettings['smtp_encryption'] ?? 'tls'),
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to' => $fromEmail,
        ]);

        $htmlContent = generateNewsletterHtml($campaign['subject'], $campaign['content']);
        $opts = [
            'is_html' => true,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
        ];

        foreach ($members as $m) {
            if ($m['email']) {
                try {
                    if (EmailHelper::send($m['email'], $campaign['subject'], $htmlContent, $opts)) {
                        $count++;
                    }
                } catch (Exception $e) {
                    error_log("Failed to send newsletter to {$m['email']}: " . $e->getMessage());
                }
            }
        }

        Database::execute("UPDATE newsletter_campaigns SET status=?, sent_at=NOW() WHERE id=?", [WF_Constants::NEWSLETTER_STATUS_SENT, $id]);

        echo json_encode(['success' => true, 'sent_count' => $count]);
        exit;
    }

    // --- MEMBERSHIPS (for customer editor) ---
    if ($action === 'get_customer_groups') {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id)
            throw new Exception("Missing User ID");

        // Get all groups and mark subscribed ones
        $allGroups = Database::queryAll("SELECT id, name FROM newsletter_groups ORDER BY name ASC");
        $userGroups = Database::queryAll("SELECT group_id FROM newsletter_memberships WHERE user_id = ?", [$user_id]);
        $userGroupIds = array_column($userGroups, 'group_id');

        foreach ($allGroups as &$g) {
            $g['subscribed'] = in_array($g['id'], $userGroupIds);
        }

        echo json_encode(['success' => true, 'groups' => $allGroups]);
        exit;
    }

    if ($action === 'toggle_membership') {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        $groupId = $data['group_id'] ?? null;
        $subscribed = $data['subscribed'] ?? false; // true = add, false = remove

        if (!$user_id || !$groupId)
            throw new Exception("Missing params");

        if ($subscribed) {
            // Add (ignore if exists)
            Database::execute("INSERT IGNORE INTO newsletter_memberships (user_id, group_id) VALUES (?, ?)", [$user_id, $groupId]);
        } else {
            // Remove
            Database::execute("DELETE FROM newsletter_memberships WHERE user_id=? AND group_id=?", [$user_id, $groupId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception("Invalid action");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
