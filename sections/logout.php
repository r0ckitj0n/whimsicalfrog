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
    <link rel="stylesheet" href="/css/tailwind.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow rounded-lg max-w-md w-full text-center">
        <h1 class="text-2xl font-bold text-green-700">You have been logged out successfully.</h1>
        <p class="text-gray-600">Thank you for visiting Whimsical Frog.</p>
        <a href="/?page=login" class="inline-block bg-green-600 text-white rounded hover:bg-green-700 font-semibold">Log In Again</a>
        <a href="/" class="inline-block bg-gray-200 text-gray-800 rounded hover:bg-gray-300 font-semibold">Return Home</a>
    </div>
</body>
</html> 