<?php
// Email branding helper: derive brand colors/fonts and style block for email templates
// Usage in templates/email/*.php:
//   require_once dirname(__DIR__, 2) . '/includes/email_branding.php';
//   echo $EMAIL_BRANDING_STYLE; // inside <head>
//   Use $brandPrimary, $brandSecondary, $brandBg, $brandText, $brandFontPrimary, $brandFontSecondary

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/business_settings_helper.php';

$__biz = BusinessSettings::getByCategory('business');
$brandPrimary       = $__biz['business_brand_primary']       ?? '#0ea5e9';
$brandSecondary     = $__biz['business_brand_secondary']     ?? '#6366f1';
$brandAccent        = $__biz['business_brand_accent']        ?? '#22c55e';
$brandBg            = $__biz['business_brand_background']    ?? '#ffffff';
$brandText          = $__biz['business_brand_text']          ?? '#111827';
$brandFontPrimary   = $__biz['business_brand_font_primary']  ?? "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif";
$brandFontSecondary = $__biz['business_brand_font_secondary']?? $brandFontPrimary;
$customCssVars      = $__biz['business_css_vars']            ?? '';

// Build a safe inline <style> block for emails (avoid CSS variables for maximum client compatibility)
$styleLines = [];
$styleLines[] = 'body{margin:0;padding:0;background:' . htmlspecialchars($brandBg) . ';color:' . htmlspecialchars($brandText) . ';font-family:' . htmlspecialchars($brandFontPrimary) . ';line-height:1.5;}';
$styleLines[] = '.wrapper{max-width:600px;margin:0 auto;padding:16px;}';
$styleLines[] = '.header{background:' . htmlspecialchars($brandPrimary) . ';color:#fff;padding:16px;text-align:center;}';
$styleLines[] = '.h1{margin:0;font-size:20px;font-family:' . htmlspecialchars($brandFontPrimary) . ';}';
$styleLines[] = '.h2,.h3{font-family:' . htmlspecialchars($brandFontSecondary) . ';color:' . htmlspecialchars($brandSecondary) . ';margin:0 0 8px;}';
$styleLines[] = '.muted{color:#666;font-size:14px;}';
$styleLines[] = '.items li{margin:4px 0;}';

// Optionally include custom CSS variables as literal declarations if provided (many mail clients ignore vars)
// We'll also echo them as standard declarations if they look like CSS rules without var() dependencies.
if (is_string($customCssVars) && trim($customCssVars) !== '') {
  $lines = preg_split('/\r?\n/', $customCssVars);
  foreach ($lines as $ln) {
    $t = trim($ln);
    if ($t === '' || strpos($t, '#') === 0 || strpos($t, '//') === 0) continue;
    // Only include simple property declarations as body overrides to avoid breaking clients
    if (preg_match('/^--[A-Za-z0-9_-]+\s*:\s*[^;]+;?$/', $t)) {
      // CSS variables aren't well supported in email; ignore
      continue;
    }
    // If it's a plain declaration like background: #fff; add under body
    if (preg_match('/^[A-Za-z-]+\s*:\s*[^;]+;?$/', $t)) {
      $styleLines[] = 'body{' . rtrim($t, ';') . ';}';
    }
  }
}

$EMAIL_BRANDING_STYLE = "<style>\n" . implode("\n", $styleLines) . "\n</style>\n";
