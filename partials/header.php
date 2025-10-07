<?php
// Proxy header partial now outputs full HTML document start, head, and header component

// Ensure session is started with consistent cookie params (apex + www)
require_once dirname(__DIR__) . '/includes/session.php';
// Ensure DB + env are initialized before attempting auth reconstruction
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
// Reconstruct from WF_AUTH if needed before rendering login state
try {
    ensureSessionStarted();
} catch (\Throwable $e) {
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }
    $cookieDomain = '.' . $baseDomain;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'None',
    ]);
}

// Mark that the global layout has been bootstrapped
if (!defined('WF_LAYOUT_BOOTSTRAPPED')) {
    define('WF_LAYOUT_BOOTSTRAPPED', true);
}

require_once dirname(__DIR__) . '/includes/vite_helper.php';
// Ensure core helpers are available (get_active_background, etc)
require_once dirname(__DIR__) . '/includes/functions.php';
// Background helpers (provides get_landing_background_path fallback)
require_once dirname(__DIR__) . '/includes/background_helpers.php';

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
    if (isset($aliases[$sectionKey])) {
        $section = $aliases[$sectionKey];
    }
    $pageSlug = 'admin/' . $section;
} elseif (isset($page) && is_string($page) && $page !== '') {
    $pageSlug = $page;
} elseif (isset($_GET['page']) && is_string($_GET['page']) && $_GET['page'] !== '') {
    $pageSlug = $_GET['page'];
} else {
    $pageSlug = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($pageSlug === '') {
        $pageSlug = 'landing';
    }
}
$pageSlug = preg_replace('/\.php$/i', '', $pageSlug);
$segments = explode('/', $pageSlug);
$isAdmin = isset($segments[0]) && $segments[0] === 'admin';

// Route-based admin detection for consistent navbar rendering across all admin URLs
try {
    $__req_path_for_admin = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
} catch (\Throwable $e) {
    $__req_path_for_admin = '';
}
$__is_admin_route = ($__req_path_for_admin === 'admin') || (strpos($__req_path_for_admin, 'admin/') === 0) || (strpos($__req_path_for_admin, 'admin_router.php') !== false);

// If we are on /admin with a query param ?section=settings, normalize the slug so
// admin-settings assets load and the bridge can detect the route consistently
if ($isAdmin && isset($_GET['section']) && is_string($_GET['section']) && $_GET['section'] !== '') {
    $q = strtolower($_GET['section']);
    $aliases = [
        'index' => 'dashboard', 'home' => 'dashboard', 'order' => 'orders', 'orders' => 'orders', 'product' => 'inventory', 'products' => 'inventory',
        'customer' => 'customers', 'user' => 'customers', 'users' => 'customers', 'report' => 'reports', 'reports' => 'reports', 'marketing' => 'marketing', 'pos' => 'pos',
        'settings' => 'settings', 'admin_settings' => 'settings', 'admin_settings.php' => 'settings', 'categories' => 'categories',
    ];
    if (isset($aliases[$q])) {
        $q = $aliases[$q];
    }
    // Normalize to admin/<section> for ALL sections, not just settings
    if ($q) {
        $pageSlug = 'admin/' . $q;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>WhimsicalFrog</title>
    <script>
      // Force API client to use the current origin/port (important for localhost dev)
      try { window.__WF_BACKEND_ORIGIN = window.location.origin; } catch(_) {}
    </script>
    <?php
    // Debug breadcrumb: emit a one-time header version marker and currently attached <script> srcs
    $header_ts = date('c');
echo "<script>(function(){try{console.log('[WF-Header] version ', '" . addslashes($header_ts) . "'); var ss=[].map.call(document.getElementsByTagName('script'), function(s){return s && s.src || ''}).filter(Boolean); if (ss && ss.length) console.log('[WF-Header] existing scripts:', ss);}catch(_){}})();</script>\n";
?>
    <?php
// Always load the main application bundle so global CSS/JS are available on ALL pages,
// including admin routes. Previously this was suppressed on admin paths which caused
// missing CSS for admin pages other than settings.
echo vite('js/app.js');
// Always load header bootstrap to enable login modal and auth sync on all pages (incl. admin)
echo vite('js/header-bootstrap.js');
// Final inline override to guarantee help chips are 36x36 with primary brand color
echo <<<'STYLE'
<style id="wf-help-chip-override">
  .admin-tab-navigation #adminHelpDocsBtn,
  .admin-tab-navigation #adminHelpToggleBtn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    min-height: 36px !important;
    border-radius: 9999px !important;
    padding: 0 !important;
    margin: 0 !important;
    box-sizing: border-box !important;
    background-color: var(--brand-primary, #22c55e) !important;
    color: #fff !important;
    border: none !important;
    box-shadow: none !important;
  }
  .admin-tab-navigation #adminHelpDocsBtn:hover,
  .admin-tab-navigation #adminHelpToggleBtn:hover {
    filter: brightness(0.95) !important;
    box-shadow: 0 0 0 3px rgba(0,0,0,0.08) !important;
  }
  .admin-tab-navigation #adminHelpDocsBtn .help-q { font-size: 18px; line-height: 1; font-weight: 700; transform: translateY(-1px); }
  .admin-tab-navigation #adminHelpToggleBtn .wf-toggle { width: 22px; height: 12px; }
</style>
STYLE;
// Expose strict fail-fast flag to client (default true unless explicitly disabled)
try {
    $strictRaw = getenv('WF_STRICT_FAILFAST');
    $strictOn = !($strictRaw === '0' || strtolower((string)$strictRaw) === 'false');
    echo '<script>window.WF_STRICT_FAILFAST=' . ($strictOn ? 'true' : 'false') . ';try{document.documentElement.setAttribute("data-strict",' . ($strictOn ? "'1'" : "'0'") . ');}catch(_){}</script>' . "\n";
} catch (\Throwable $e) { /* noop */
}
// Client bootstrap: sync header auth via whoami on every page load as a safety net
echo <<<'SCRIPT'
<script>
(function(){
  try{
    var origin = (window.__WF_BACKEND_ORIGIN && typeof window.__WF_BACKEND_ORIGIN==='string') ? window.__WF_BACKEND_ORIGIN : window.location.origin;
    var url = origin.replace(/\/$/,'') + '/api/whoami.php';
    fetch(url, {credentials:'include'}).then(function(r){return r.ok?r.json():null}).then(function(j){
      if (!j) return;
      if (j && j.userId != null) {
        try { document.body.setAttribute('data-is-logged-in','true'); document.body.setAttribute('data-user-id', String(j.userId)); } catch(_){}
        // Do NOT dispatch wf:login-success from passive whoami; avoid triggering seal redirects on every page load
      }
    }).catch(function(){/* noop */});
    // Fallback: if whoami did not yield, try WF_AUTH_V cookie to drive UI immediately
    try {
      var cookies = (document.cookie||'').split(';').map(function(s){return s.trim();});
      var kv = {};
      for (var i=0;i<cookies.length;i++){ var p=cookies[i].split('='); if(p.length>=2){ kv[decodeURIComponent(p[0])] = p.slice(1).join('='); } }
      if (kv['WF_AUTH_V']) {
        try {
          var raw = atob(kv['WF_AUTH_V']);
          var obj = JSON.parse(raw);
          if (obj && obj.uid) {
            document.body.setAttribute('data-is-logged-in','true');
            document.body.setAttribute('data-user-id', String(obj.uid));
            // Do NOT dispatch wf:login-success from client hint; avoid seal redirect loops
          }
        } catch(_){}
      }
    } catch(_){}
  }catch(_){/* noop */}
})();
</script>
SCRIPT;
// One-time safety: if any module dispatches wf:login-success, force a sealing redirect
// This ensures cookies persist even if an older login-modal bundle is cached
echo <<<'SCRIPT'
<script>
(function(){
  try{
    if (window.__wf_seal_listener_installed) return; window.__wf_seal_listener_installed = true;
    window.addEventListener('wf:login-success', function(ev){
      try {
        if (window.__wf_seal_redirected) return; // guard
        var backend = (typeof window.__WF_BACKEND_ORIGIN === 'string' && window.__WF_BACKEND_ORIGIN) ? window.__WF_BACKEND_ORIGIN : window.location.origin;
        var target = (window.__wf_desired_return_url && typeof window.__wf_desired_return_url === 'string') ? window.__wf_desired_return_url : (window.location.pathname + window.location.search + window.location.hash);
        var url = new URL('/api/seal_login.php', backend);
        url.searchParams.set('to', target || '/');
        window.__wf_seal_redirected = true;
        setTimeout(function(){ window.location.assign(url.toString()); }, 200);
      } catch(_){ /* noop */ }
    });
  }catch(_){/* noop */}
})();
</script>
SCRIPT;
// Always ensure admin navbar has a horizontal layout on admin ROUTES (fallback before external CSS)
// Global header offset to keep content and modals clear of the fixed header, site-wide
echo <<<'STYLE'
<style id="wf-global-header-offset">
  :root{--wf-header-height:64px; --wf-overlay-offset: calc(var(--wf-header-height) + 12px)}
  body{padding-top:var(--wf-header-height)}
  /* Ensure common overlays/modals are not obscured by the header */
  .admin-modal-overlay,
  .modal-overlay,
  [role="dialog"].overlay,
  .wf-search-modal,
  #searchModal,
  #loggingStatusModal,
  #databaseTablesModal {
    padding-top: var(--wf-overlay-offset) !important;
    align-items: flex-start !important;
    z-index: var(--wf-admin-overlay-z, var(--z-admin-overlay, 10100)) !important; /* above header and nav using unified token */
    display: flex !important;             /* ensure flex context */
    justify-content: center !important;   /* center horizontally */
  }
  /* Some frameworks center with margin; neutralize so padding-top is effective */
  .admin-modal-overlay .admin-modal,
  .modal-overlay .modal,
  [role="dialog"].overlay .modal {
    margin-top: 0 !important;
    top: auto !important;                 /* ignore top:50% */
    transform: none !important;           /* ignore translate(-50%, -50%) */
    position: relative !important;        /* avoid absolute/fixed recentering */
  }
</style>
STYLE;
// Server-side branding CSS variables from Business Settings (applies without JS)
try {
    require_once dirname(__DIR__) . '/api/business_settings_helper.php';
    $biz = BusinessSettings::getByCategory('business');
    $vars = [];
    $sanitize = function ($v) { return trim((string)$v); };
    if (!empty($biz['business_brand_primary'])) {
        $vars[] = "--brand-primary: "   . $sanitize($biz['business_brand_primary'])   . ';';
    }
    if (!empty($biz['business_brand_secondary'])) {
        $vars[] = "--brand-secondary: " . $sanitize($biz['business_brand_secondary']) . ';';
    }
    if (!empty($biz['business_brand_accent'])) {
        $vars[] = "--brand-accent: "    . $sanitize($biz['business_brand_accent'])    . ';';
    }
    if (!empty($biz['business_brand_background'])) {
        $vars[] = "--brand-bg: "        . $sanitize($biz['business_brand_background']). ';';
    }
    if (!empty($biz['business_brand_text'])) {
        $vars[] = "--brand-text: "      . $sanitize($biz['business_brand_text'])      . ';';
    }
    if (!empty($biz['business_brand_font_primary'])) {
        $vars[] = "--brand-font-primary: "   . $sanitize($biz['business_brand_font_primary'])   . ';';
    }
    if (!empty($biz['business_brand_font_secondary'])) {
        $vars[] = "--brand-font-secondary: " . $sanitize($biz['business_brand_font_secondary']) . ';';
    }
    $custom = isset($biz['business_css_vars']) ? (string)$biz['business_css_vars'] : '';
    $customLines = [];
    if ($custom !== '') {
        foreach (preg_split('/\r?\n/', $custom) as $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '#') === 0 || strpos($t, '//') === 0) {
                continue;
            }
            if (preg_match('/^--[A-Za-z0-9_-]+\s*:\s*[^;]+;?$/', $t)) {
                // Ensure it ends with semicolon
                if (substr($t, -1) !== ';') {
                    $t .= ';';
                }
                $customLines[] = $t;
            }
        }
    }
    if (!empty($vars) || !empty($customLines)) {
        echo "<style id=\"wf-branding-vars\">:root{\n" . implode("\n", $vars) . (empty($customLines) ? '' : ("\n" . implode("\n", $customLines))) . "\n}</style>\n";
    }
} catch (\Throwable $___e) { /* noop */
}
// Compute header height on all routes and update CSS variable live
echo <<<'SCRIPT'
<script>(function(){
  function computeHeaderHeight(){
    try{
      var h = document.querySelector('.site-header') || document.querySelector('.universal-page-header');
      if (h && h.getBoundingClientRect){
        var hh = Math.max(40, Math.round(h.getBoundingClientRect().height));
        document.documentElement.style.setProperty('--wf-header-height', hh + 'px');
      }
      var headerBottom = 0;
      if (h && h.getBoundingClientRect) headerBottom = Math.round(h.getBoundingClientRect().bottom);
      var nav = document.querySelector('.admin-tab-navigation');
      var navBottom = 0;
      if (nav && nav.getBoundingClientRect) navBottom = Math.round(nav.getBoundingClientRect().bottom);
      var offset = Math.max(headerBottom, navBottom) + 12;
      if (offset > 0) document.documentElement.style.setProperty('--wf-overlay-offset', offset + 'px');
    }catch(_){}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', computeHeaderHeight, {once:true}); else computeHeaderHeight();
  window.addEventListener('load', computeHeaderHeight, {once:true});
  window.addEventListener('resize', computeHeaderHeight);
  if (window.ResizeObserver){ try{ var ro=new ResizeObserver(computeHeaderHeight); var h=document.querySelector('.site-header')||document.querySelector('.universal-page-header'); if(h) ro.observe(h);}catch(_){}}
})();</script>
SCRIPT;

// Global runtime guard: ensure any modal/overlay added is offset below header
echo <<<'SCRIPT'
<script>
(function(){
  function headerHeight(){
    try{
      var h=document.querySelector('.site-header')||document.querySelector('.universal-page-header');
      return (h&&h.getBoundingClientRect)?Math.max(40,Math.round(h.getBoundingClientRect().height)):64;
    }catch(_){return 64;}
  }
  function offsetOverlay(el){
    try{
      if(!el||el.__wfOffsetApplied) return; // idempotent
      var isOverHeader = !!(el.classList && el.classList.contains('over-header'));
      if (!isOverHeader) {
        var hh=headerHeight();
        el.style.paddingTop = (hh+12)+"px";
        el.style.alignItems = 'flex-start';
      }
      // JS fallback z-index; do NOT downgrade explicit over-header overlays
      if (!isOverHeader && !el.hasAttribute('data-z-lock')) {
        el.style.zIndex = '10100';
      }
      var dlg = el.querySelector('.admin-modal,.modal,[role="document"],[role="dialog"]');
      if (dlg) { dlg.style.marginTop='0'; }
      el.__wfOffsetApplied = true;
    }catch(_){/* noop */}
  }
  function sweep(){
    try {
      document.querySelectorAll('.admin-modal-overlay,.modal-overlay,[role="dialog"].overlay,#searchModal,#loggingStatusModal,#databaseTablesModal').forEach(offsetOverlay);
    } catch (_) { /* noop */ }
  }
  function wrap(obj, name){
    try{
      if(!obj||typeof obj[name]!== 'function' || obj[name].__wfWrapped) return;
      var orig=obj[name];
      obj[name]=function(){
        var res=orig.apply(this, arguments);
        sweep();
        return res;
      };
      obj[name].__wfWrapped=true;
    }catch(_){/* noop */}
  }
  // Wrap common openers
  wrap(window,'showModal');
  wrap(window,'openModal');
  wrap(window,'__wfShowModal');
  if (window.WFModals && typeof window.WFModals.open === 'function') wrap(window.WFModals,'open');

  // Observe DOM additions
  try{
    var mo=new MutationObserver(function(muts){
      muts.forEach(function(m){
        (m.addedNodes||[]).forEach(function(n){
          if(n && n.nodeType===1){
            if (/(^|\s)(admin-modal-overlay|modal-overlay)(\s|$)/.test(n.className||'') || /^(searchModal|loggingStatusModal|databaseTablesModal)$/.test(n.id||'') || (n.getAttribute && n.getAttribute('role')==='dialog')) {
              offsetOverlay(n);
            }
          }
        });
      });
    });
    mo.observe(document.body, {childList:true, subtree:true});
  }catch(_){/* noop */}

  // Initial sweep
  sweep();
})();
</script>
SCRIPT;

if ($__is_admin_route) {
    echo <<<'STYLE'
<style id="wf-admin-nav-fallback-global">
:root{--wf-header-height:64px}
.site-header, .universal-page-header{margin:0!important;padding-top:0px!important;padding-bottom:0px!important;height:64px!important;min-height:64px!important}
.header-container{margin:0 auto;max-width:1200px}
.site-header .nav-links{display:flex!important;gap:14px!important;align-items:center!important;flex-wrap:wrap!important;margin:0!important;padding:0!important}
.site-header .nav-links a{display:inline-flex!important;align-items:center!important;text-decoration:none}
.site-header nav ul{list-style:none;margin:0;padding:0;display:flex;gap:14px;flex-wrap:wrap}
.site-header nav ul>li{display:inline-flex}
.admin-tab-navigation{position:fixed!important;top:68px!important;left:0;right:0;z-index:2000;margin:0!important;padding:0px 10px!important;display:flex!important;justify-content:center!important;align-items:center!important;width:100%!important;text-align:center!important}
.admin-tab-navigation>*{display:flex!important;flex-direction:row!important;flex-wrap:wrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;margin:0 auto!important;padding:0!important;width:100%!important;text-align:center!important}
.admin-tab-navigation ul{list-style:none!important;margin:0 auto!important;padding:0!important;display:flex!important;flex-wrap:wrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;width:100%!important;text-align:center!important}
.admin-tab-navigation .container,.admin-tab-navigation .wrapper,.admin-tab-navigation .flex,.admin-tab-navigation > div,.admin-tab-navigation .u-display-flex{max-width:1200px;margin:0 auto!important;width:100%!important;display:flex!important;justify-content:center!important;align-items:center!important}
.admin-tab-navigation ul>li{display:inline-flex!important;margin:0!important;padding:0!important}
.admin-tab-navigation .admin-nav-tab{display:inline-flex!important;align-items:center!important;justify-content:center!important;white-space:nowrap;border-radius:9999px;padding:10px 18px;text-decoration:none;margin:0!important;width:auto!important;max-width:none!important;flex:0 0 auto!important}
/* Inline spacing rules removed (source-of-truth in CSS files) */
/* Standalone Settings template (no #admin-section-content wrapper): apply to direct child only */
/* (kept intentionally minimal) */
body[data-page='admin/settings'] > .settings-page{padding-top:0!important}
@media (min-width:0px){.admin-tab-navigation .flex,.admin-tab-navigation>div,.admin-tab-navigation ul{flex-direction:row!important;align-items:center!important}}
</style>
STYLE;
    // Removed dynamic layout computation - using static CSS positioning instead
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
.site-header, .universal-page-header{margin:0!important;padding-top:0px!important;padding-bottom:0px!important;height:64px!important;min-height:64px!important}
.header-container{margin:0 auto;max-width:1200px}
.site-header .nav-links{display:flex!important;flex-direction:row!important;gap:14px!important;align-items:center!important;flex-wrap:nowrap!important;margin:0!important;padding:0!important}
.site-header .nav-links a{display:inline-flex!important;align-items:center!important;text-decoration:none}
.site-header nav ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:row!important;gap:14px;flex-wrap:nowrap!important}
.site-header nav ul>li{display:inline-flex}
.admin-tab-navigation{position:fixed;top:72px;left:0;right:0;z-index:2000;margin:0!important;padding:0px 12px!important;display:flex!important;justify-content:center!important;align-items:center!important;width:100%!important}
.admin-tab-navigation>*{display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;gap:10px!important;justify-content:center!important;align-items:center!important;margin:0 auto!important;padding:0!important;width:100%!important;text-align:center!important}
.admin-tab-navigation .admin-nav-tab{display:inline-flex!important;align-items:center!important;justify-content:center!important;white-space:nowrap;border-radius:9999px;padding:10px 16px;text-decoration:none;margin:0!important;width:auto!important;max-width:none!important;flex:0 0 auto!important}
.admin-tab-navigation .admin-nav-tab, .admin-tab-navigation .admin-nav-tab:visited{color:inherit;text-decoration:none}
/* Standalone Settings template (light mode): apply to direct child only */
body[data-page='admin/settings'] > .settings-page{padding-top:156px!important}

/* Settings page: override navbar position for more space */
body[data-page='admin/settings'] .admin-tab-navigation{top:76px!important}
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
    // Admin settings assets are now loaded via app.js per-page imports to prevent double-loading
    // Early squelch (optional): prevent auto-opening of modals/panels before admin-settings bundle initializes
    // Disabled by default to avoid potential performance issues. Enable with ?wf_early_squelch=1
    if (isset($_GET['wf_early_squelch']) && $_GET['wf_early_squelch'] === '1') {
        echo <<<'SCRIPT'
<script>(function(){
  try {
    var isSettings = (document.body && (document.body.getAttribute('data-page')||'').indexOf('admin/settings') === 0) || (location.pathname.indexOf('/admin') === 0 && location.pathname.indexOf('settings') !== -1);
    if (!isSettings) return;
    var params = new URLSearchParams(location.search || '');
    var allow = params.get('wf_allow_modals') === '1' || params.get('wf_allow_panels') === '1';
    if (allow) return;

    var STYLE_ID = 'wf-early-settings-squelch';
    if (!document.getElementById(STYLE_ID)) {
      var css = [
        'html[data-early-settings-squelch="1"] .admin-modal-overlay,',
        'html[data-early-settings-squelch="1"] .modal-overlay,',
        'html[data-early-settings-squelch="1"] [role="dialog"],',
        'html[data-early-settings-squelch="1"] .overlay,',
        'html[data-early-settings-squelch="1"] .drawer,',
        'html[data-early-settings-squelch="1"] .sheet,',
        // Specific known IDs/panels
        'html[data-early-settings-squelch="1"] #websiteLogsModal,',
        'html[data-early-settings-squelch="1"] #websiteLogsContainer,',
        'html[data-early-settings-squelch="1"] #websiteLogsPanel,',
        'html[data-early-settings-squelch="1"] #databaseTablesModal,',
        'html[data-early-settings-squelch="1"] #databaseTablesContainer,',
        'html[data-early-settings-squelch="1"] #databaseTablesPanel,',
        'html[data-early-settings-squelch="1"] #searchResultsModal,',
        'html[data-early-settings-squelch="1"] #searchResults,',
        'html[data-early-settings-squelch="1"] #searchResultsContainer,',
        'html[data-early-settings-squelch="1"] #searchResultsPanel'
      ].join('\n') + '{display:none!important;visibility:hidden!important;opacity:0!important;}';
      var st = document.createElement('style');
      st.id = STYLE_ID; st.textContent = css; document.head.appendChild(st);
    }
    document.documentElement.setAttribute('data-early-settings-squelch','1');

    var mo = null, rafPending = false;
    try {
      var sweep = function(){
        rafPending = false;
        if (document.documentElement.getAttribute('data-early-settings-squelch') !== '1') return;
        var targets = [
          '#websiteLogsModal', '#websiteLogsContainer', '#websiteLogsPanel',
          '#databaseTablesModal', '#databaseTablesContainer', '#databaseTablesPanel',
          '#searchResultsModal', '#searchResults', '#searchResultsContainer', '#searchResultsPanel'
        ];
        targets.forEach(function(sel){
          document.querySelectorAll(sel).forEach(function(el){
            try { el.style.setProperty('display','none','important'); } catch(_) {}
            try { el.style.setProperty('visibility','hidden','important'); } catch(_) {}
            try { el.classList.add('hidden'); } catch(_) {}
            try { el.classList.remove('show'); } catch(_) {}
          });
        });
      };
      mo = new MutationObserver(function(){
        if (rafPending) return; rafPending = true; requestAnimationFrame(sweep);
      });
      mo.observe(document.documentElement, { subtree:true, attributes:true, childList:true, attributeFilter:['class','style','aria-hidden'] });
    } catch(_) {}

    var lift = function(){
      try { document.documentElement.removeAttribute('data-early-settings-squelch'); } catch(_) {}
      try { if (mo) mo.disconnect(); } catch(_) {}
    };
    window.addEventListener('pointerdown', lift, { once:true, capture:true });
    window.addEventListener('keydown', lift, { once:true, capture:true });
  } catch(_) {}
})();</script>
SCRIPT;
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
    $landingBg = '';
    if (function_exists('get_active_background')) {
        $landingBg = get_active_background('landing');
    }
    // Strict: no fallbacks; if not configured, log and do not set a background
    if ($landingBg) {
        if ($landingBg[0] !== '/') {
            $landingBg = '/' . ltrim($landingBg, '/');
        }
        $bodyBgUrl = $landingBg;
        $bodyClasses[] = 'room-bg-landing';
        // Background applied successfully
    } else {
        echo "<script>console.error('[Header] ❌ No active landing background configured; none will be applied');</script>\n";
    }
}
// Attach background for Main Room page (prefer DB-configured room 0 "room_main" asset)
if ($pageSlug === 'room_main') {
    if (function_exists('get_active_background')) {
        $mainBg = get_active_background('room_main');
        if ($mainBg) {
            if ($mainBg[0] !== '/') {
                $mainBg = '/' . ltrim($mainBg, '/');
            }
            $bodyBgUrl = $mainBg;
            $bodyClasses[] = 'room-bg-main';
        } else {
            echo "<script>console.error('[Header] No active main room background configured; none will be applied');</script>\n";
        }
    }
}
// Attach room_main background for About and Contact pages
if ($pageSlug === 'about' || $pageSlug === 'contact') {
    if (function_exists('get_active_background')) {
        $roomBg = get_active_background('room_main');
        if ($roomBg) {
            $bodyBgUrl = $roomBg;
            $bodyClasses[] = 'room-bg-main';
        } else {
            echo "<script>console.error('[Header] No active background configured for about/contact pages (expects room_main)');</script>\n";
        }
    }
}

// Attach database-configurable background for Shop page (strict: no fallback)
if ($pageSlug === 'shop') {
    if (function_exists('get_active_background')) {
        $shopBg = get_active_background('shop');
        if ($shopBg) {
            $bodyBgUrl = $shopBg;
            $bodyClasses[] = 'room-bg-main';
        } else {
            echo "<script>console.error('[Header] No active background configured for shop page');</script>\n";
        }
    }
}
// Attach STATIC background for ALL Admin pages (avoid DB on admin routes)
if ($pageSlug === 'admin' || strpos($pageSlug, 'admin/') === 0) {
    $adminBg = '';
    $defaultPngAbs = dirname(__DIR__) . '/images/backgrounds/background-settings.png';
    $defaultWebpRel = '/images/backgrounds/background-settings.webp';
    $defaultWebpAbs = dirname(__DIR__) . $defaultWebpRel;
    if (file_exists($defaultWebpAbs)) {
        $adminBg = $defaultWebpRel;
    } elseif (file_exists($defaultPngAbs)) {
        $adminBg = '/images/backgrounds/background-settings.png';
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
        if ($__wf_user_id <= 0) {
            $__wf_user_id = null;
        }
    }
}
?>
<?php
// Build inline background style so pages like About/Contact fully cover viewport
$bodyStyle = '';
$bodyBgUrlOut = $bodyBgUrl;
if ($bodyBgUrl) {
    // Add cache-busting based on file modification time so replacing the same filename updates immediately
    try {
        $bgPath = parse_url($bodyBgUrl, PHP_URL_PATH);
        if ($bgPath) {
            $fsPath = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__), '/') . $bgPath;
            if (is_file($fsPath)) {
                $mt = @filemtime($fsPath);
                if ($mt) {
                    $bodyBgUrlOut = $bodyBgUrl . (strpos($bodyBgUrl, '?') !== false ? '&' : '?') . 'v=' . $mt;
                }
            }
        }
    } catch (\Throwable $e) { /* non-fatal */
    }
    $safeBg = htmlspecialchars($bodyBgUrlOut, ENT_QUOTES, 'UTF-8');
    // Inline styles are disallowed by CI guard. We'll set background via JS using data-bg-url.
}
?>
<?php
// (CSS/JS for admin/settings was handled in <head>)
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>" <?php echo $bodyBgUrlOut ? 'data-bg-url="' . htmlspecialchars($bodyBgUrlOut) . '"' : ''; ?> data-page="<?php echo htmlspecialchars($pageSlug); ?>" data-path="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?>" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>" data-is-logged-in="<?php echo $__wf_is_logged_in ? 'true' : 'false'; ?>"
  <?php echo ($__wf_user_id !== null) ? 'data-user-id="' . htmlspecialchars($__wf_user_id) . '"' : ''; ?>
>
<script>(function(){try{var b=document.body;var url=b&&b.getAttribute('data-bg-url');if(url){b.style.backgroundImage='url('+url+')';b.style.backgroundSize='cover';b.style.backgroundPosition='center';b.style.backgroundRepeat='no-repeat';/* Only set minHeight for non-admin pages */if(!b.getAttribute('data-page')||b.getAttribute('data-page').indexOf('admin')!==0){b.style.minHeight='100vh';}}}catch(e){}})();</script>
<?php
// Render the visual header component
include_once dirname(__DIR__) . '/components/header_template.php';

// Render Admin Nav Tabs consistently on all admin route pages
// Use robust path-based detection (REQUEST_URI) to avoid reliance on $pageSlug
try {
    $___req_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
} catch (\Throwable $e) {
    $___req_path = '';
}
$__is_admin_route = ($___req_path === 'admin') || (strpos($___req_path, 'admin/') === 0) || (strpos($___req_path, 'admin_router.php') !== false);
if ($__is_admin_route) {
    // Derive admin section from REQUEST_URI: '/admin/<section>' -> '<section>'
    $adminSection = '';
    try {
        $parts = explode('/', $___req_path); // e.g., ['admin','customers']
        if (isset($parts[1]) && $parts[0] === 'admin') {
            $adminSection = strtolower(trim($parts[1]));
        }
    } catch (\Throwable $e) {
    }
    // Fallback to ?section=
    if (isset($_GET['section']) && is_string($_GET['section']) && $_GET['section'] !== '') {
        $adminSection = strtolower((string)$_GET['section']);
    }
    // Expose as $section for the component API
    $section = $adminSection;
    include_once dirname(__DIR__) . '/components/admin_nav_tabs.php';
    // Dedupe any accidental duplicate navbars rendered by legacy includes
    echo <<<'SCRIPT'
<script>(function(){try{var bars = document.querySelectorAll('.admin-tab-navigation');if(bars&&bars.length>1){for(var i=1;i<bars.length;i++){bars[i].parentNode&&bars[i].parentNode.removeChild(bars[i]);}}}catch(_){}})();</script>
SCRIPT;
}
