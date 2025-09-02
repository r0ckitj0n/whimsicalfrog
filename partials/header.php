<?php
// Proxy header partial now outputs full HTML document start, head, and header component

// Ensure session is started before reading $_SESSION for auth/user id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/vite_helper.php';
// Ensure core helpers are available (get_active_background, etc)
require_once dirname(__DIR__) . '/includes/functions.php';
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

// Derive page slug with precedence: for admin, use full path (admin/<section>); otherwise use router-provided $page, then ?page=, then path
if (isset($page) && $page === 'admin') {
    // Compute admin section from the request URI
    $reqPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $segments = $reqPath !== '' ? explode('/', $reqPath) : ['admin'];
    // Expect format: ['admin', '<section>']
    $section = $segments[1] ?? 'dashboard';
    // Normalize a few common aliases similar to index.php
    $aliases = [
        'index' => 'dashboard',
        'home' => 'dashboard',
        'order' => 'orders',
        'product' => 'inventory',
        'products' => 'inventory',
        'customer' => 'customers',
        'users' => 'customers',
        'report' => 'reports',
        'marketing' => 'marketing',
        'pos' => 'pos',
        'settings' => 'settings',
        'categories' => 'categories',
    ];
    $sectionKey = strtolower($section);
    if (isset($aliases[$sectionKey])) { $section = $aliases[$sectionKey]; }
    $pageSlug = 'admin/' . $section;
} elseif (isset($page) && is_string($page) && $page !== '') {
    $pageSlug = $page;
} elseif (isset($_GET['page']) && is_string($_GET['page']) && $_GET['page'] !== '') {
    $pageSlug = $_GET['page'];
} else {
    $pageSlug = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($pageSlug === '') { $pageSlug = 'landing'; }
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

// Attach database-configurable background for Shop page (fallback to room_main)
if ($pageSlug === 'shop') {
    if (function_exists('get_active_background')) {
        $shopBg = get_active_background('shop');
        if (!$shopBg) {
            $shopBg = get_active_background('room_main');
        }
        if (!$shopBg) {
            $shopBg = '/images/backgrounds/background_room_main.webp';
        }
        if ($shopBg) {
            $bodyBgUrl = $shopBg;
            $bodyClasses[] = 'room-bg-main';
        }
    }
}
// Attach database-configurable background for ALL Admin pages
if ($pageSlug === 'admin' || strpos($pageSlug, 'admin/') === 0) {
    $adminBg = '';
    if (function_exists('get_active_background')) {
        $adminBg = get_active_background('admin_settings');
    }
    // Fallback to images/backgrounds/background_settings.(webp|png), auto-generate webp from png if missing
    if (!$adminBg || !is_string($adminBg) || $adminBg === '') {
        $defaultPngAbs = dirname(__DIR__) . '/images/backgrounds/background_settings.png';
        $defaultWebpRel = '/images/backgrounds/background_settings.webp';
        $defaultWebpAbs = dirname(__DIR__) . $defaultWebpRel;

        if (!file_exists($defaultWebpAbs) && file_exists($defaultPngAbs)) {
            $info = @getimagesize($defaultPngAbs);
            if ($info && ($info['mime'] === 'image/png' || $info['mime'] === 'image/jpeg')) {
                if ($info['mime'] === 'image/png') {
                    $img = @imagecreatefrompng($defaultPngAbs);
                    if ($img) {
                        imagepalettetotruecolor($img);
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                        @imagewebp($img, $defaultWebpAbs, 92);
                        imagedestroy($img);
                    }
                } elseif ($info['mime'] === 'image/jpeg') {
                    $img = @imagecreatefromjpeg($defaultPngAbs);
                    if ($img) {
                        @imagewebp($img, $defaultWebpAbs, 90);
                        imagedestroy($img);
                    }
                }
            }
        }

        if (file_exists($defaultWebpAbs)) {
            $adminBg = $defaultWebpRel;
        } elseif (file_exists($defaultPngAbs)) {
            $adminBg = '/images/backgrounds/background_settings.png';
        }
    }
    if ($adminBg) {
        $bodyBgUrl = $adminBg;
        $bodyClasses[] = 'room-bg-admin-settings';
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
// Expose user id for authenticated sessions (mirror whoami.php logic):
// prefer $_SESSION['user']['userId'], then ['id'], then $_SESSION['user_id']
$__wf_user_id = null;
$__wf_user_id_raw = null;
if ($__wf_is_logged_in) {
    if (function_exists('getUserId')) {
        $__wf_user_id = getUserId();
        $tmp = $__wf_user_id;
        $__wf_user_id_raw = is_scalar($tmp) ? (string)$tmp : null;
    } else {
        if (!empty($_SESSION['user'])) {
            $u = $_SESSION['user'];
            if (isset($u['userId'])) {
                $__wf_user_id = $u['userId'];
                $__wf_user_id_raw = is_scalar($u['userId']) ? (string)$u['userId'] : null;
            } elseif (isset($u['id'])) {
                $__wf_user_id = $u['id'];
                $__wf_user_id_raw = is_scalar($u['id']) ? (string)$u['id'] : null;
            }
        } elseif (isset($_SESSION['user_id'])) {
            $__wf_user_id = $_SESSION['user_id'];
            $__wf_user_id_raw = is_scalar($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null;
        }
    }
    // Only accept positive integers (normalized)
    if (!is_null($__wf_user_id)) {
        $__wf_user_id = (int) $__wf_user_id;
        if ($__wf_user_id <= 0) { $__wf_user_id = null; }
    }
}
?>
<?php
  // Build inline background style so pages like About/Contact fully cover viewport
  $bodyStyle = '';
  if ($bodyBgUrl) {
      $safeBg = htmlspecialchars($bodyBgUrl, ENT_QUOTES, 'UTF-8');
      // Inline styles are disallowed by CI guard. We'll set background via JS using data-bg-url.
  }
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>" <?php echo $bodyBgUrl ? 'data-bg-url="' . htmlspecialchars($bodyBgUrl) . '"' : ''; ?> data-page="<?php echo htmlspecialchars($pageSlug); ?>" data-path="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $__wf_is_logged_in ? 'true' : 'false'; ?>"
  <?php echo ($__wf_user_id !== null) ? 'data-user-id="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>
  <?php echo ($__wf_user_id_raw !== null && $__wf_user_id_raw !== '') ? 'data-user-id-raw="' . htmlspecialchars($__wf_user_id_raw) . '"' : ''; ?>
  <?php echo ($__wf_user_id !== null) ? 'data-user-id-norm="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>
>
<?php
// Render the visual header component
include_once dirname(__DIR__) . '/components/header_template.php';
