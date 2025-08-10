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
    <!-- Inject PHP page information for JavaScript -->
    <script>
        window.WF_PAGE_INFO = <?php echo json_encode([
            'page' => $page ?? 'landing',
            'url' => $_SERVER['REQUEST_URI'] ?? '/'
        ]); ?>;
    </script>
</head>
<?php
// --- Dynamic body classes & inline styles ---------------------------------
$bodyClasses = [];
$bodyInlineStyle = '';

$pageSlug = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($pageSlug === '') {
    $pageSlug = 'landing';
}

// Normalize common file-based routes to slugs (e.g., about.php -> about)
$pageSlug = preg_replace('/\.php$/i', '', $pageSlug);

// Attach background for landing page
if ($pageSlug === 'landing') {
    if (function_exists('get_active_background')) {
        $landingBg = get_active_background('landing');
        if ($landingBg) {
            $bodyInlineStyle = "background-image:url('{$landingBg}'); background-size:cover; background-position:center;";
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
            $bodyInlineStyle = "background-image:url('{$roomBg}'); background-size:cover; background-position:center;";
            $bodyClasses[] = 'room-bg-main';
        }
    }
}
// Page metadata for JS routing
$segments = explode('/', $pageSlug);
$isAdmin = isset($segments[0]) && $segments[0] === 'admin';
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>" <?php echo $bodyInlineStyle ? 'style="' . $bodyInlineStyle . '"' : ''; ?> data-page="<?php echo htmlspecialchars($pageSlug); ?>" data-path="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>">
<?php
// Render the visual header component
include_once dirname(__DIR__) . '/components/header_template.php';
