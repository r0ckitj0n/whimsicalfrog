<?php
// Proxy footer partial, render footer component and close HTML document
// Mark as included to avoid double-includes from header's shutdown fallback
if (!defined('WF_GLOBAL_FOOTER_INCLUDED')) {
    define('WF_GLOBAL_FOOTER_INCLUDED', true);
}

// Footer intentionally left blank or replaced with minimal content as per site requirements.
// Include a minimal placeholder if desired:
// echo '<!-- Footer removed -->';

// Ensure the global popup markup is available site-wide just before closing body
require_once __DIR__ . '/../components/global_popup.php';
if (function_exists('renderGlobalPopup')) {
    echo renderGlobalPopup();
    // If any CSS is provided by the component, render it too (currently empty)
    if (function_exists('renderGlobalPopupCSS')) {
        echo renderGlobalPopupCSS();
    }
}
// Ensure the Account Settings modal markup is available site-wide
require_once __DIR__ . '/../components/account_settings_modal.php';
if (function_exists('renderAccountSettingsModal')) {
    echo renderAccountSettingsModal();
}
// Optional site footer note / html from Business Settings (render only on About page)
@require_once __DIR__ . '/../api/business_settings_helper.php';
try {
    // Determine current route slug for conditional rendering
    $req = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($req, PHP_URL_PATH) ?: '/';
    $slug = trim($path, '/');
    if ($slug === '') { $slug = 'landing'; }
    $segments = explode('/', $slug);
    $pageSlug = $segments[0] ?? 'landing';

    $note = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_footer_note', '') : '';
    $html = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_footer_html', '') : '';
    $purl = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_privacy_url', '') : '';
    $turl = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_terms_url', '') : '';
    $pol = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_policy_url', '') : '';
    $hasContent = (trim($note) !== '' || trim(strip_tags($html)) !== '' || $purl !== '' || $turl !== '' || $pol !== '');
    $showLegal = ($pageSlug === 'about');
    if ($hasContent && $showLegal) {
        echo "\n<footer class=\"site-footer text-center text-sm text-gray-600 py-6\">";
        if (trim($html) !== '') {
            echo $html;
        } elseif (trim($note) !== '') {
            echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
        }
        $links = [];
        if (true) { $links[] = '<a class="link-brand" href="/privacy.php">Privacy</a>'; }
        if (true) { $links[] = '<a class="link-brand" href="/terms.php">Terms</a>'; }
        if (true) { $links[] = '<a class="link-brand" href="/policy.php">Policies</a>'; }
        if (!empty($links)) {
            echo '<div class="mt-2 space-x-3">'.implode('<span class="mx-1">•</span>', $links).'</div>';
        }
        echo "</footer>\n";
    }
} catch (Throwable $e) {}
?>
</body>
</html>
