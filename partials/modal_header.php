<?php
// partials/modal_header.php
// A lightweight header for use in iframe modals.
// It includes only the essential Vite assets (CSS/JS) without the full site layout.

require_once dirname(__DIR__) . '/includes/vite_helper.php';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Section</title>
    <?php
    // Always try the Vite dev server first (WF_VITE_ORIGIN > hot file > default localhost:5176),
    // but avoid dev on HTTPS pages if Vite origin is HTTP (mixed content will block HMR in iframes)
    $origin = getenv('WF_VITE_ORIGIN');
    if (!$origin && file_exists(dirname(__DIR__) . '/hot')) {
        $origin = trim((string)@file_get_contents(dirname(__DIR__) . '/hot'));
    }
    if (!$origin) { $origin = 'http://localhost:5176'; }
    try {
        $parts = @parse_url($origin);
        if (is_array($parts) && ($parts['host'] ?? '') === '127.0.0.1') {
            $origin = ($parts['scheme'] ?? 'http') . '://localhost' . (isset($parts['port']) ? (':' . $parts['port']) : '') . ($parts['path'] ?? '');
        }
    } catch (Throwable $e) { /* ignore */ }
    $probe = function(string $o){
        $u = rtrim($o,'/') . '/@vite/client';
        $ctx = stream_context_create(['http'=>['timeout'=>0.6,'ignore_errors'=>true],'https'=>['timeout'=>0.6,'ignore_errors'=>true]]);
        return @file_get_contents($u, false, $ctx) !== false;
    };
    $pageIsHttps = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
        ? (strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443));
    $originIsHttp = (stripos($origin, 'http://') === 0);
    if ($probe($origin) && !($pageIsHttps && $originIsHttp)) {
        $o = rtrim($origin,'/');
        // Load HMR client and entry
        echo '<script crossorigin="anonymous" type="module" src="' . $o . '/@vite/client"></script>' . "\n";
        echo '<script crossorigin="anonymous" type="module" src="' . $o . '/src/entries/app.js"></script>' . "\n";
        // If an extra entry was requested (e.g., a page-specific iframe bundle), emit it in dev as well
        if (isset($extraViteEntry) && is_string($extraViteEntry) && $extraViteEntry !== '') {
            echo '<script crossorigin="anonymous" type="module" src="' . $o . '/' . ltrim($extraViteEntry, '/') . '"></script>' . "\n";
        }
        // Dynamic icon CSS (admin icon map)
        echo '<link rel="stylesheet" href="/api/admin_icon_map.php?action=get_css">' . "\n";
    } else {
        // Fall back to production manifest bundles (or when mixed content would block HMR)
        if ($pageIsHttps && $originIsHttp) {
            echo "<!-- modal_header: HTTPS page with HTTP Vite origin; using production assets to avoid mixed content -->\n";
        }
        echo vite('js/app.js');
    }
    ?>
    <?php if (isset($extraViteEntry) && is_string($extraViteEntry) && $extraViteEntry !== ''): ?>
      <?php echo vite_css($extraViteEntry); ?>
      <?php echo vite($extraViteEntry); ?>
    <?php endif; ?>
    <?php
    // Inject server-side branding CSS variables just like the main header
    try {
        if (file_exists(dirname(__DIR__) . '/api/business_settings_helper.php')) {
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
        }
    } catch (\Throwable $___e) { /* noop */ }
    ?>
    <script>
    (function(){
      try {
        var k = 'wf_admin_actions_icons';
        var seenKey = 'wf_admin_icons_legend_seen';
        var on = (localStorage.getItem(k) || '') === '1';
        var cl = document.documentElement.classList;
        if (on) cl.add('admin-actions-icons'); else cl.remove('admin-actions-icons');
        function maybeShowLegend(){
          try {
            if ((localStorage.getItem(k) || '') !== '1') return;
            if (localStorage.getItem(seenKey) === '1') return;
            var box = document.createElement('div');
            box.className = 'wf-icons-legend';
            box.innerHTML = ''
              + '<h4>Actions Icons</h4>'
              + '<ul>'
              + '  <li><span class="legend-icon" data-role="preview"></span> Preview HTML</li>'
              + '  <li><span class="legend-icon" data-role="preview-inline"></span> Preview Inline</li>'
              + '  <li><span class="legend-icon" data-role="send"></span> Send Test Email</li>'
              + '  <li><span class="legend-icon" data-role="edit"></span> Edit</li>'
              + '  <li><span class="legend-icon" data-role="duplicate"></span> Duplicate</li>'
              + '  <li><span class="legend-icon" data-role="archive"></span> Archive</li>'
              + '</ul>'
              + '<div class="legend-footer"><button type="button" class="legend-close" aria-label="Close">Got it</button></div>';
            document.body.appendChild(box);
            var close = box.querySelector('.legend-close');
            if (close) close.addEventListener('click', function(){ try { localStorage.setItem(seenKey, '1'); } catch(_){}; try { box.remove(); } catch(_){}});
            setTimeout(function(){ try { localStorage.setItem(seenKey, '1'); } catch(_){}; try { box.remove(); } catch(_){ } }, 8000);
          } catch(_){ }
        }
        // Show immediately if applicable
        if (on) setTimeout(maybeShowLegend, 0);
        window.addEventListener('storage', function(ev){
          try {
            if (!ev || ev.key !== k) return;
            var set = ev.newValue === '1';
            if (set) cl.add('admin-actions-icons'); else cl.remove('admin-actions-icons');
            if (set) setTimeout(maybeShowLegend, 0);
          } catch(_){ }
        });
      } catch(_){ }
    })();
    </script>
</head>
<?php
  // Build optional body attributes for embed context and page tagging
  $___attrs = [];
  try { if (isset($_GET['modal']) && $_GET['modal'] == '1') { $___attrs[] = 'data-embed="1"'; } } catch (Throwable $___e) {}
  try { if (isset($page) && $page) { $___attrs[] = 'data-page="' . htmlspecialchars((string)$page, ENT_QUOTES) . '"'; } } catch (Throwable $___e) {}
  $___attr_str = empty($___attrs) ? '' : (' ' . implode(' ', $___attrs));
?>
<body<?= $___attr_str ?>>
