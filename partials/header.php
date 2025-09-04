<?php
// Proxy header partial now outputs full HTML document start, head, and header component

// Ensure session is started before reading $_SESSION for auth/user id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mark that the global layout has been bootstrapped
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    define('WF_LAYOUT_BOOTSTRAPPED', true);
}

require_once dirname(__DIR__) . '/includes/vite_helper.php';
// Ensure core helpers are available (get_active_background, etc)
require_once dirname(__DIR__) . '/includes/functions.php';

// Ensure dynamic HTML is not cached so stale pages don't reference outdated hashed assets
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
?>
<?php
// Derive page slug with precedence: for admin, use full path (admin/<section>); otherwise use router-provided $page, then ?page=, then path
$bodyClasses = [];
$bodyBgUrl = '';
if (isset($page) && $page === 'admin') {
    $reqPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $segments = $reqPath !== '' ? explode('/', $reqPath) : ['admin'];
    $section = $segments[1] ?? 'dashboard';
    $aliases = [
        'index' => 'dashboard', 'home' => 'dashboard', 'order' => 'orders', 'product' => 'inventory', 'products' => 'inventory',
        'customer' => 'customers', 'users' => 'customers', 'report' => 'reports', 'marketing' => 'marketing', 'pos' => 'pos',
        'settings' => 'settings', 'admin_settings' => 'settings', 'admin_settings.php' => 'settings', 'categories' => 'categories',
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
$pageSlug = preg_replace('/\.php$/i', '', $pageSlug);
$segments = explode('/', $pageSlug);
$isAdmin = isset($segments[0]) && $segments[0] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>WhimsicalFrog</title>
    <?php
    $___req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $___is_admin_path = ($___req_path === 'admin') || (strpos($___req_path, 'admin/') === 0);
    if (!$___is_admin_path) {
        echo vite('js/app.js');
    }
    // Always ensure admin navbar has a horizontal layout on admin pages (fallback before external CSS)
    if ($isAdmin) {
        echo <<<'STYLE'
<style id="wf-admin-nav-fallback-global">
:root{--wf-header-height:64px}
.site-header, .universal-page-header{margin:0!important;padding-top:4px!important;padding-bottom:4px!important}
.header-container{margin:0 auto;max-width:1200px}
.site-header .nav-links{display:flex!important;gap:14px!important;align-items:center!important;flex-wrap:wrap!important;margin:0!important;padding:0!important}
.site-header .nav-links a{display:inline-flex!important;align-items:center!important;text-decoration:none}
.site-header nav ul{list-style:none;margin:0;padding:0;display:flex;gap:14px;flex-wrap:wrap}
.site-header nav ul>li{display:inline-flex}
.admin-tab-navigation{position:fixed!important;top:var(--wf-admin-nav-top, calc(var(--wf-header-height,64px) + 12px))!important;left:0;right:0;z-index:2000;margin:0!important;padding:4px 10px!important;display:flex!important;justify-content:center!important;align-items:center!important;width:100%!important;text-align:center!important}
.admin-tab-navigation>*{display:flex!important;flex-direction:row!important;flex-wrap:wrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;margin:0 auto!important;padding:0!important;width:100%!important;text-align:center!important}
.admin-tab-navigation ul{list-style:none!important;margin:0 auto!important;padding:0!important;display:flex!important;flex-wrap:wrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;width:100%!important;text-align:center!important}
.admin-tab-navigation .container,.admin-tab-navigation .wrapper,.admin-tab-navigation .flex,.admin-tab-navigation > div,.admin-tab-navigation .u-display-flex{max-width:1200px;margin:0 auto!important;width:100%!important;display:flex!important;justify-content:center!important;align-items:center!important}
.admin-tab-navigation ul>li{display:inline-flex!important;margin:0!important;padding:0!important}
.admin-tab-navigation .admin-nav-tab{display:inline-flex!important;align-items:center!important;justify-content:center!important;white-space:nowrap;border-radius:9999px;padding:10px 16px;text-decoration:none;margin:0!important;width:auto!important;max-width:none!important;flex:0 0 auto!important}
body[data-page^=admin] #admin-section-content{padding-top:var(--wf-admin-content-pad,12px)!important}
@media (min-width:0px){.admin-tab-navigation .flex,.admin-tab-navigation>div,.admin-tab-navigation ul{flex-direction:row!important;align-items:center!important}}
</style>
STYLE;
        // Dynamically compute header height to tighten the gap precisely
        echo <<<'SCRIPT'
<script>
(function(){
  try {
    var computeLayout = function(){
      var h = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
      if (h && h.getBoundingClientRect) {
        var hh = Math.max(40, Math.round(h.getBoundingClientRect().height));
        document.documentElement.style.setProperty("--wf-header-height", hh + "px");
      }
      var hc = document.querySelector('.header-content');
      if (hc && hc.getBoundingClientRect) {
        var bottom = Math.round(hc.getBoundingClientRect().bottom + 12);
        document.documentElement.style.setProperty("--wf-admin-nav-top", bottom + "px");
      }
      var nav = document.querySelector('.admin-tab-navigation');
      if (nav && nav.getBoundingClientRect) {
        var nh = Math.round(nav.getBoundingClientRect().height + 12);
        document.documentElement.style.setProperty("--wf-admin-content-pad", nh + "px");
      }
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", computeLayout, {once:true}); else computeLayout();
    window.addEventListener("load", computeLayout, {once:true});
    window.addEventListener("resize", computeLayout);
    try {
      if (window.ResizeObserver) {
        var ro = new ResizeObserver(function(){computeLayout();});
        var hc = document.querySelector('.header-content'); if (hc) ro.observe(hc);
        var h = document.querySelector('.site-header') || document.querySelector('.universal-page-header'); if (h) ro.observe(h);
      } else {
        var t = setInterval(computeLayout, 500);
        setTimeout(function(){clearInterval(t);}, 4000);
      }
    } catch(_) {}
  } catch(e) {}
})();
</script>
SCRIPT;
    }
    // If on admin/settings ensure assets are emitted and avoid kill-switching JS
    if ($isAdmin && (strpos($pageSlug, 'admin/settings') === 0)) {
        $qs = $_GET ?? [];
        $wf_full = isset($qs['wf_full']) && $qs['wf_full'] === '1';
        $wf_minimal = isset($qs['wf_minimal']) && $qs['wf_minimal'] === '1';
        $wf_section = isset($qs['wf_section']) && $qs['wf_section'] !== '';
        $lightByDefault = (!$wf_full && !$wf_section) || $wf_minimal;
        // Keep a light-mode marker for styling, but do NOT disable JS; bridge must run.
        echo '<script>window.WF_ADMIN_LIGHT=' . ($lightByDefault ? 'true' : 'false') . ';window.WF_DISABLE_ADMIN_SETTINGS_JS=false;</script>' . "\n";
        if ($lightByDefault) {
            // Minimal inline CSS fallback to keep admin navbar horizontal in light mode
            echo <<<'STYLE'
<style id="wf-admin-nav-fallback">
:root{--wf-header-height:64px}
.site-header, .universal-page-header{margin:0!important;padding-top:6px!important;padding-bottom:6px!important}
.header-container{margin:0 auto;max-width:1200px}
.site-header .nav-links{display:flex!important;flex-direction:row!important;gap:14px!important;align-items:center!important;flex-wrap:nowrap!important;margin:0!important;padding:0!important}
.site-header .nav-links a{display:inline-flex!important;align-items:center!important;text-decoration:none}
.site-header nav ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:row!important;gap:14px;flex-wrap:nowrap!important}
.site-header nav ul>li{display:inline-flex}
.admin-tab-navigation{position:fixed;top:var(--wf-admin-nav-top, calc(var(--wf-header-height,64px) + 22px));left:0;right:0;z-index:2000;margin:0!important;padding:6px 12px!important;display:flex!important;justify-content:center!important;align-items:center!important;width:100%!important}
.admin-tab-navigation>*{display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;margin:0 auto!important;padding:0!important;width:100%!important;text-align:center!important}
.admin-tab-navigation .admin-nav-tab{display:inline-flex!important;align-items:center!important;justify-content:center!important;white-space:nowrap;border-radius:9999px;padding:10px 16px;text-decoration:none;margin:0!important;width:auto!important;max-width:none!important;flex:0 0 auto!important}
.admin-tab-navigation .admin-nav-tab, .admin-tab-navigation .admin-nav-tab:visited{color:inherit;text-decoration:none}
</style>
STYLE;
            // Optional: Prevent hash-driven modal auto-opens and suppress overlays unless user triggered
            // Gated behind wf_light_guard=1 to avoid interfering by default
            if (isset($qs['wf_light_guard']) && $qs['wf_light_guard'] === '1') {
                echo <<<'SCRIPT'
<script>(function(){
  // CSS squelch: force-hide common modals unless attribute lifted
  try {
    var style = document.createElement('style');
    style.setAttribute('data-wf-squelch-style', '1');
    style.textContent =
      'html[data-wf-squelch="1"] #searchModal,' +
      'html[data-wf-squelch="1"] .wf-search-modal,' +
      'html[data-wf-squelch="1"] #databaseTablesModal,' +
      'html[data-wf-squelch="1"] #loggingStatusModal,' +
      'html[data-wf-squelch="1"] .admin-modal-overlay,' +
      'html[data-wf-squelch="1"] .modal-overlay,' +
      'html[data-wf-squelch="1"] [id$="Modal"],' +
      'html[data-wf-squelch="1"] [id*="modal"],' +
      'html[data-wf-squelch="1"] [class*="modal"] {' +
      '  display: none !important;' +
      '  visibility: hidden !important;' +
      '  opacity: 0 !important;' +
      '}';
    document.head.appendChild(style);
    document.documentElement.setAttribute('data-wf-squelch', '1');
  } catch(_) {}
  try {
    if (location.hash && location.hash.length > 1) {
      var clean = location.pathname + location.search;
      history.replaceState(null, document.title, clean);
    }
  } catch(e) {}
  // Suppress documentation/help/search/logs modal auto-opens in light mode
  try {
    var mkGuard = function(fnName){
      var original = (window && window[fnName]) ? window[fnName] : null;
      window[fnName] = function(){
        var recentlyClicked = (typeof Date !== 'undefined') && (window.__wf_last_click_ts ? (Date.now() - window.__wf_last_click_ts) < 600 : false);
        if (!recentlyClicked) { return false; }
        if (typeof original === 'function') return original.apply(this, arguments);
        return false;
      };
    };
    window.addEventListener('click', function(){ window.__wf_last_click_ts = Date.now(); }, true);
    ['openHelpDocumentationModal','openSystemDocumentationModal','openDocumentationHub','openDatabaseTablesModal','createDatabaseTablesModal','openSearchModal','createLoggingStatusModal','openLoggingStatusModal','openOverlay','openModal','showModal','displayModal'].forEach(mkGuard);

    // Also guard framework openers if present (WFModals.open)
    if (window.WFModals && typeof window.WFModals.open === 'function') {
      var _wfOpen = window.WFModals.open;
      window.WFModals.open = function(){
        var allow = (new URLSearchParams(location.search)).get('wf_allow_modals') === '1' || (window.__wf_last_click_ts && (Date.now() - window.__wf_last_click_ts) < 600);
        if (!allow) return false;
        return _wfOpen.apply(this, arguments);
      };
    }
    // Guard SearchModal.prototype.open if defined later
    (function waitSearchModal(){
      try {
        if (window.SearchModal && window.SearchModal.prototype && typeof window.SearchModal.prototype.open === 'function' && !window.SearchModal.prototype.__wfWrapped) {
          var _orig = window.SearchModal.prototype.open;
          window.SearchModal.prototype.open = function(){
            var allow = (new URLSearchParams(location.search)).get('wf_allow_modals') === '1' || (window.__wf_last_click_ts && (Date.now() - window.__wf_last_click_ts) < 600);
            if (!allow) return false;
            return _orig.apply(this, arguments);
          };
          window.SearchModal.prototype.__wfWrapped = true;
          return;
        }
      } catch(_) {}
      setTimeout(waitSearchModal, 50);
    })();
  } catch(_) {}
  var userTapTs = 0;
  var allowWindow = 600; // ms window to allow modal after explicit user click
  window.addEventListener("click", function(){
    userTapTs = Date.now();
    try {
      // Temporarily lift squelch to allow intended modal opens
      document.documentElement.removeAttribute('data-wf-squelch');
      clearTimeout(window.__wf_squelch_timer);
      window.__wf_squelch_timer = setTimeout(function(){
        // Re-enable squelch after window if no further interaction
        document.documentElement.setAttribute('data-wf-squelch', '1');
      }, allowWindow + 100);
    } catch(_) {}
  }, true);
  function hideOverlays(){
    try {
      var sel = ".admin-modal-overlay, #documentationHubModal, [id*=documentation][class*=modal], [id*=help][class*=modal], #searchModal, #loggingStatusModal, #databaseTablesModal";
      document.querySelectorAll(sel).forEach(function(m){ m.style.display = "none"; m.classList.add("hidden"); });
    } catch(_) {}
  }
  function shouldAllow(){ return (Date.now() - userTapTs) <= allowWindow; }
  function guardOverlays(){
    try {
      var mo = new MutationObserver(function(muts){
        var allow = shouldAllow();
        muts.forEach(function(m){
          if (!m.target) return;
          var el = m.target.nodeType === 1 ? m.target : null;
          if (!el) return;
          if (el.classList && el.classList.contains("admin-modal-overlay")) {
            if (!allow) { el.style.display = "none"; el.classList.add("hidden"); }
          }
          (m.addedNodes||[]).forEach(function(n){
            if (n.nodeType === 1 && n.classList && n.classList.contains("admin-modal-overlay")) {
              if (!shouldAllow()) { n.style.display = "none"; n.classList.add("hidden"); }
            }
            if (n.nodeType === 1 && (n.id === 'searchModal' || n.id === 'loggingStatusModal' || n.id === 'databaseTablesModal')) {
              if (!shouldAllow()) { n.style.display = 'none'; n.classList.add('hidden'); }
            }
            if (n.nodeType === 1 && (/(?:^|\s)modal(?:\s|$)/i.test(n.className || '') || /modal/i.test(n.id || ''))) {
              if (!shouldAllow()) { n.style.display = 'none'; n.classList.add('hidden'); }
            }
          });
          // Revert unauthorized class/attribute changes on specific modals
          try {
            var id = el.id || '';
            var cls = el.className || '';
            if ((!allow) && (id === 'searchModal' || id === 'loggingStatusModal' || id === 'databaseTablesModal' || /wf-search-modal/.test(cls) || /modal/i.test(id) || /(\b|_)modal(\b|_)/i.test(cls))) {
              el.classList.add('hidden');
              el.classList.remove('show');
              el.style.display = 'none';
            }
          } catch(_) {}
        });
      });
      mo.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ["style","class","aria-hidden"] });
    } catch(_) {}
  }
  var onReady = function(){ hideOverlays(); guardOverlays(); };
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", onReady, { once: true });
  else onReady();
})();</script>
SCRIPT;
            }
        }
        // Always emit admin settings entry so the bridge is available; heavy legacy loads lazily inside the entry
        if (function_exists('vite')) {
            if (!defined('WF_ADMIN_SETTINGS_ASSETS_EMITTED')) { define('WF_ADMIN_SETTINGS_ASSETS_EMITTED', true); echo vite('js/admin-settings.js'); }
        }
    }
    ?>
    <!-- Vite manages CSS; fallbacks removed -->
    <!-- Page info is exposed via body data-* attributes; WF_PAGE_INFO inline script removed -->
</head>
<?php
// --- Dynamic body classes & inline styles ---------------------------------
// (pageSlug, isAdmin) were computed above

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
// Attach STATIC background for ALL Admin pages (avoid DB on admin routes)
if ($pageSlug === 'admin' || strpos($pageSlug, 'admin/') === 0) {
    $adminBg = '';
    $defaultPngAbs = dirname(__DIR__) . '/images/backgrounds/background_settings.png';
    $defaultWebpRel = '/images/backgrounds/background_settings.webp';
    $defaultWebpAbs = dirname(__DIR__) . $defaultWebpRel;
    if (file_exists($defaultWebpAbs)) {
        $adminBg = $defaultWebpRel;
    } elseif (file_exists($defaultPngAbs)) {
        $adminBg = '/images/backgrounds/background_settings.png';
    }
    if ($adminBg) {
        $bodyBgUrl = $adminBg;
        $bodyClasses[] = 'room-bg-admin-settings';
    }
}
// Page metadata for JS routing (isAdmin already computed)
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
<?php
    // (CSS/JS for admin/settings was handled in <head>)
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>" <?php echo $bodyBgUrl ? 'data-bg-url="' . htmlspecialchars($bodyBgUrl) . '"' : ''; ?> data-page="<?php echo htmlspecialchars($pageSlug); ?>" data-path="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $__wf_is_logged_in ? 'true' : 'false'; ?>"
  <?php echo ($__wf_user_id !== null) ? 'data-user-id="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>
  <?php echo ($__wf_user_id_raw !== null && $__wf_user_id_raw !== '') ? 'data-user-id-raw="' . htmlspecialchars($__wf_user_id_raw) . '"' : ''; ?>
  <?php echo ($__wf_user_id !== null) ? 'data-user-id-norm="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>
>
<script>(function(){try{var b=document.body;var url=b&&b.getAttribute('data-bg-url');if(url){b.style.backgroundImage='url('+url+')';b.style.backgroundSize='cover';b.style.backgroundPosition='center';b.style.backgroundRepeat='no-repeat';b.style.minHeight='100vh';}}catch(e){}})();</script>
<?php
// Render the visual header component
include_once dirname(__DIR__) . '/components/header_template.php';
