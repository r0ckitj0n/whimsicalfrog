<?php
// Email Debug Script
// This script helps debug email configuration and authentication issues

header('Content-Type: text/html; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>🔧 Email System Debug Information</h2>";

// 1. Check Session Authentication
echo "<h3>1. Session Authentication</h3>";
echo "<pre>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Raw Session Data: " . print_r($_SESSION, true) . "\n";

if (isset($_SESSION['user'])) {
    echo "User Data Found: YES\n";
    echo "User Role: " . ($_SESSION['user']['role'] ?? 'not set') . "\n";
    echo "User ID: " . ($_SESSION['user']['userId'] ?? 'not set') . "\n";
    echo "Username: " . ($_SESSION['user']['username'] ?? 'not set') . "\n";
    
    $isAdmin = isset($_SESSION['user']['role']) && 
               ($_SESSION['user']['role'] === 'Admin' || $_SESSION['user']['role'] === 'admin');
    echo "Is Admin: " . ($isAdmin ? "YES" : "NO") . "\n";
} else {
    echo "User Data Found: NO\n";
    echo "❌ User not logged in or session corrupted\n";
}
echo "</pre>";

// 2. Check Email Configuration
echo "<h3>2. Email Configuration</h3>";
if (file_exists('email_config.php')) {
    require_once 'email_config.php';
    echo "<pre>";
    echo "Config file loaded: YES\n";
    echo "SMTP Enabled: " . (defined('SMTP_ENABLED') ? (SMTP_ENABLED ? 'YES' : 'NO') : 'NOT DEFINED') . "\n";
    echo "SMTP Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED') . "\n";
    echo "SMTP Port: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED') . "\n";
    echo "SMTP Username: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED') . "\n";
    echo "SMTP Password: " . (defined('SMTP_PASSWORD') ? (SMTP_PASSWORD ? '[SET]' : '[EMPTY]') : 'NOT DEFINED') . "\n";
    echo "SMTP Encryption: " . (defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'NOT DEFINED') . "\n";
    echo "From Email: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'NOT DEFINED') . "\n";
    echo "From Name: " . (defined('FROM_NAME') ? FROM_NAME : 'NOT DEFINED') . "\n";
    echo "</pre>";
} else {
    echo "<p>❌ email_config.php file not found</p>";
}

// 3. Check Database Connection
echo "<h3>3. Database Connection</h3>";
try {
    require_once 'config.php';
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    echo "<p>✅ Database connection successful</p>";
    
    // Check if email_logs table exists
    try {
        $stmt = $pdo->query("DESCRIBE email_logs");
        echo "<p>✅ email_logs table exists</p>";
        
        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) FROM email_logs");
        $count = $stmt->fetchColumn();
        echo "<p>📊 Email logs count: $count records</p>";
    } catch (Exception $e) {
        echo "<p>❌ email_logs table missing: " . $e->getMessage() . "</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// 4. Test SMTP Connection (if configured)
echo "<h3>4. SMTP Connection Test</h3>";
if (defined('SMTP_ENABLED') && SMTP_ENABLED && defined('SMTP_HOST')) {
    echo "<p>Testing SMTP connection to " . SMTP_HOST . ":" . SMTP_PORT . "...</p>";
    
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        (SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '') . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context
    );
    
    if ($socket) {
        $response = fgets($socket, 515);
        fclose($socket);
        
        if (substr($response, 0, 3) == '220') {
            echo "<p>✅ SMTP server connection successful</p>";
            echo "<p>Server response: " . htmlspecialchars(trim($response)) . "</p>";
        } else {
            echo "<p>⚠️ SMTP server responded but not ready: " . htmlspecialchars(trim($response)) . "</p>";
        }
    } else {
        echo "<p>❌ Cannot connect to SMTP server: $errstr ($errno)</p>";
    }
} else {
    echo "<p>ℹ️ SMTP not configured or disabled</p>";
}

// 5. Environment Information
echo "<h3>5. Environment Information</h3>";
echo "<pre>";
echo "Server: " . $_SERVER['HTTP_HOST'] . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set') . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . __FILE__ . "\n";
echo "Current Working Directory: " . getcwd() . "\n";
echo "Date/Time: " . date('Y-m-d H:i:s T') . "\n";
echo "</pre>";

// 6. Test Email Function
echo "<h3>6. Email Function Test</h3>";
if (isset($_GET['test_email']) && $_GET['test_email'] === 'simple') {
    echo "<p>Testing simple PHP mail() function...</p>";
    
    $to = 'test@example.com';
    $subject = 'Debug Test Email';
    $message = '<h2>Debug Test</h2><p>This is a test email from the debug script.</p>';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Debug Script <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    $result = mail($to, $subject, $message, $headers);
    
    if ($result) {
        echo "<p>✅ mail() function returned true</p>";
    } else {
        echo "<p>❌ mail() function returned false</p>";
    }
}

echo "<hr>";
echo "<p><strong>Test Actions:</strong></p>";
echo "<ul>";
echo "<li><a href='?test_email=simple'>Test Simple PHP Mail</a></li>";
echo "<li><a href='/api/get_email_history.php'>Test Email History API</a></li>";
echo "<li><a href='/api/get_email_config.php'>Test Email Config API</a></li>";
echo "</ul>";

echo "<p><em>Debug completed at " . date('Y-m-d H:i:s T') . "</em></p>";
?> 