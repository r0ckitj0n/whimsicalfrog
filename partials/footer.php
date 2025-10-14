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
?>
</body>
</html>
