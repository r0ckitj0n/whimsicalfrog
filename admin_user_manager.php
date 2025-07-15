<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api/config.php';

// Use centralized authentication
requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Handle form submission
    if ($_POST['action'] ?? '' === 'make_admin') {
        $username = $_POST['username'] ?? '';
        if (!empty($username)) {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE username = ?');
            $result = $stmt->execute(['admin', $username]);
            $message = $result ? "✅ User '$username' is now an administrator!" : "❌ Failed to update user";
        }
    }
    
    if ($_POST['action'] ?? '' === 'make_customer') {
        $username = $_POST['username'] ?? '';
        if (!empty($username)) {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE username = ?');
            $result = $stmt->execute(['customer', $username]);
            $message = $result ? "✅ User '$username' is now a customer!" : "❌ Failed to update user";
        }
    }
    
    // Get all users
    $stmt = $pdo->query('SELECT * FROM users ORDER BY username');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>🔧 Admin User Manager</h2>";
    
    if (isset($message)) {
        echo "<div class='padding_10 margin_10 bg_success border_1_solid border_color_success border_radius_5'>$message</div>";
    }
    
    echo "<h3>Current Users:</h3>";
    echo "<table class='admin_table border_1_solid admin_table_header margin_20 width_100'>";
    echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>First Name</th><th>Last Name</th><th>Actions</th></tr>";
    
    foreach ($users as $user) {
        $isAdmin = strtolower($user['role']) === 'admin';
        $roleClass = $isAdmin ? 'color_success font_weight_bold' : 'color_6b7280 font_weight_bold';
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td class='$roleClass'>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['firstName'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($user['lastName'] ?? '') . "</td>";
        echo "<td>";
        
        if (!$isAdmin) {
            echo "<form method='post' class='display_inline'>";
            echo "<input type='hidden' name='action' value='make_admin'>";
            echo "<input type='hidden' name='username' value='" . htmlspecialchars($user['username']) . "'>";
            echo "<button type='submit' class='bg_success color_white border_none padding_5_10 border_radius_3 cursor_pointer'>Make Admin</button>";
            echo "</form>";
        } else {
            echo "<form method='post' class='display_inline'>";
            echo "<input type='hidden' name='action' value='make_customer'>";
            echo "<input type='hidden' name='username' value='" . htmlspecialchars($user['username']) . "'>";
            echo "<button type='submit' class='bg_danger color_white border_none padding_5_10 border_radius_3 cursor_pointer'>Remove Admin</button>";
            echo "</form>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>How the Admin Recognition Works:</h3>";
    echo "<ul>";
    echo "<li><strong>Admin Check:</strong> <code>strtolower(\$userData['role']) === 'admin'</code></li>";
    echo "<li><strong>Name Display:</strong> Uses <code>firstName + lastName</code> or falls back to <code>username</code></li>";
    echo "<li><strong>Dashboard Greeting:</strong> 'Welcome back, [Name] ([Role])'</li>";
    echo "</ul>";
    
    echo "<h3>Testing:</h3>";
    echo "<p>After making a user an admin, they can:</p>";
    echo "<ul>";
    echo "<li>Access the admin dashboard at <code>/?page=admin</code></li>";
    echo "<li>See their actual name in the greeting (if firstName/lastName are set)</li>";
    echo "<li>Access all admin-only features</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?> 