<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

// Logout the user
logoutUser();

// Redirect to landing page
header('Location: /?page=landing');
exit;
?> 