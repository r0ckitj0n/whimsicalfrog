<?php
// Proxy header partial now outputs full HTML document start, head, and header component

require_once dirname(__DIR__) . '/includes/vite_helper.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>WhimsicalFrog</title>
    <?php // Load compiled CSS/JS from Vite manifest
    echo vite('js/app.js'); ?>
    <!-- Vite manages CSS; fallbacks removed -->
    <!-- Page info is exposed via body data-* attributes; WF_PAGE_INFO inline script removed -->
</head>
<?php
// --- Dynamic body classes & inline styles ---------------------------------
$bodyClasses = [];
$bodyBgUrl = '';

// Derive page slug with precedence: router-provided $page, then ?page=, then path
if (isset($page) && is_string($page) && $page !== '') {
    $pageSlug = $page;
} elseif (isset($_GET['page']) && is_string($_GET['page']) && $_GET['page'] !== '') {
    $pageSlug = $_GET['page'];
} else {
    $pageSlug = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($pageSlug === '') {
        $pageSlug = 'landing';
    }
}

// Normalize common file-based routes to slugs (e.g., about.php -> about)
$pageSlug = preg_replace('/\.php$/i', '', $pageSlug);

// Attach background for landing page
if ($pageSlug === 'landing') {
    if (function_exists('get_active_background')) {
        $landingBg = get_active_background('landing');
        if ($landingBg) {
            $bodyBgUrl = $landingBg;
            $bodyClasses[] = 'room-bg-landing';
        }
    }
}
// Attach room_main background for About and Contact pages
if ($pageSlug === 'about' || $pageSlug === 'contact') {
    if (function_exists('get_active_background')) {
        $roomBg = get_active_background('room_main');
        if (!$roomBg) {
            $roomBg = '/images/backgrounds/background_room_main.webp';
        }
        if ($roomBg) {
            $bodyBgUrl = $roomBg;
            $bodyClasses[] = 'room-bg-main';
        }
    }
}
// Page metadata for JS routing
$segments = explode('/', $pageSlug);
$isAdmin = isset($segments[0]) && $segments[0] === 'admin';
?>
<?php
// Determine login status for data attribute
$__wf_is_logged_in = false;
if (function_exists('isLoggedIn')) {
    $__wf_is_logged_in = isLoggedIn();
} else {
    $__wf_is_logged_in = isset($_SESSION['user']) || isset($_SESSION['user_id']);
}
?>
<?php
// Expose user id for authenticated sessions
$__wf_user_id = null;
if ($__wf_is_logged_in) {
    if (function_exists('getUserId')) {
        $__wf_user_id = getUserId();
    } elseif (isset($_SESSION['user']['userId'])) {
        $__wf_user_id = $_SESSION['user']['userId'];
    } elseif (isset($_SESSION['user_id'])) {
        $__wf_user_id = $_SESSION['user_id'];
    }
}
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>" <?php echo $bodyBgUrl ? 'data-bg-url="' . htmlspecialchars($bodyBgUrl) . '"' : ''; ?> data-page="<?php echo htmlspecialchars($pageSlug); ?>" data-path="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $__wf_is_logged_in ? 'true' : 'false'; ?>" <?php echo $__wf_user_id ? 'data-user-id="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>>
<?php
// Render the visual header component
include_once dirname(__DIR__) . '/components/header_template.php';
