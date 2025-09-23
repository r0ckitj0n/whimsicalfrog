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
    <?php vite('src/js/app.js'); ?>
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
    } catch (\Throwable $___e) { /* noop */
    }
?>
</head>
<body>
