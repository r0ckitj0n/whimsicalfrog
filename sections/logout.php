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
<<<<<<< HEAD
    

<!-- Database-driven CSS for logout -->
<style id="logout-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadLogoutCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=logout');
            const cssText = await response.text();
            const styleElement = document.getElementById('logout-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ logout CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load logout CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>logout CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadLogoutCSS);
</script>
</head>
<body>
    <div class="container">
        <h1 class="title">You have been logged out successfully.</h1>
        <p class="text">Thank you for visiting Whimsical Frog.</p>
        <a href="/?page=login" class="btn btn-primary">Log In Again</a>
        <a href="/" class="btn btn-secondary">Return Home</a>
=======
    <link rel="stylesheet" href="/css/tailwind.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white shadow rounded-lg max-w-md w-full text-center">
        <h1 class="text-2xl font-bold text-green-700">You have been logged out successfully.</h1>
        <p class="text-gray-600">Thank you for visiting Whimsical Frog.</p>
        <a href="/?page=login" class="inline-block bg-green-600 text-white rounded hover:bg-green-700 font-semibold">Log In Again</a>
        <a href="/" class="inline-block bg-gray-200 text-gray-800 rounded hover:bg-gray-300 font-semibold">Return Home</a>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    </div>
</body>
</html> 