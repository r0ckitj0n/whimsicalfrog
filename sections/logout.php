<?php
session_start();
// Destroy all session data
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out</title>
    <style>
        body { background-color: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .container { background: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 8px; padding: 2rem; max-width: 28rem; width: 100%; text-align: center; }
        .title { font-size: 1.5rem; font-weight: bold; color: #15803d; margin-bottom: 1rem; }
        .text { margin-bottom: 1.5rem; color: #6b7280; }
        .btn { display: inline-block; padding: 0.5rem 1.5rem; border-radius: 4px; text-decoration: none; font-weight: 600; }
        .btn-primary { background-color: #16a34a; color: white; }
        .btn-primary:hover { background-color: #15803d; }
        .btn-secondary { background-color: #e5e7eb; color: #374151; margin-left: 1rem; }
        .btn-secondary:hover { background-color: #d1d5db; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">You have been logged out successfully.</h1>
        <p class="text">Thank you for visiting Whimsical Frog.</p>
        <a href="/?page=login" class="btn btn-primary">Log In Again</a>
        <a href="/" class="btn btn-secondary">Return Home</a>
    </div>
</body>
</html> 