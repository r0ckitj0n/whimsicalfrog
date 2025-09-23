<?php
// Proxy footer partial, render footer component and close HTML document
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
?>
</body>
</html>
