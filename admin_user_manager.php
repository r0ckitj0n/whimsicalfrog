<?php
// Simple Admin User Manager
require_once 'api/config.php';

// Simple authentication check (you can improve this)
$adminToken = $_GET['token'] ?? $_POST['token'] ?? '';
if ($adminToken !== 'whimsical_admin_2024') {
    die('<h2>Access Denied</h2><p>Please provide admin token: ?token=whimsical_admin_2024</p>');
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
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
        echo "<div style='padding: 10px; margin: 10px 0; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>$message</div>";
    }
    
    echo "<h3>Current Users:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0; width: 100%;'>";
    echo "<tr><th>Username</th><th>Email</th><th>Role</th><th>First Name</th><th>Last Name</th><th>Actions</th></tr>";
    
    foreach ($users as $user) {
        $isAdmin = strtolower($user['role']) === 'admin';
        $roleColor = $isAdmin ? '#28a745' : '#6c757d';
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td style='color: $roleColor; font-weight: bold;'>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td>" . htmlspecialchars($user['firstName'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($user['lastName'] ?? '') . "</td>";
        echo "<td>";
        
        if (!$isAdmin) {
            echo "<form method='post' style='display: inline;'>";
            echo "<input type='hidden' name='token' value='whimsical_admin_2024'>";
            echo "<input type='hidden' name='action' value='make_admin'>";
            echo "<input type='hidden' name='username' value='" . htmlspecialchars($user['username']) . "'>";
            echo "<button type='submit' style='background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;'>Make Admin</button>";
            echo "</form>";
        } else {
            echo "<form method='post' style='display: inline;'>";
            echo "<input type='hidden' name='token' value='whimsical_admin_2024'>";
            echo "<input type='hidden' name='action' value='make_customer'>";
            echo "<input type='hidden' name='username' value='" . htmlspecialchars($user['username']) . "'>";
            echo "<button type='submit' style='background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;'>Remove Admin</button>";
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