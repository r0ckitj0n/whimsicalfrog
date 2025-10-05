<?php

// Server-side brand CSS fallback for web pages where head/partials may be missing
// Outputs :root CSS variables and a few utility classes based on Business Settings.
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/business_settings_helper.php';

$__biz = BusinessSettings::getByCategory('business');
$bp  = $__biz['business_brand_primary']       ?? '#0ea5e9';
$bs  = $__biz['business_brand_secondary']     ?? '#6366f1';
$ba  = $__biz['business_brand_accent']        ?? '#22c55e';
$bg  = $__biz['business_brand_background']    ?? '#ffffff';
$tx  = $__biz['business_brand_text']          ?? '#111827';
$ff1 = $__biz['business_brand_font_primary']  ?? "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif";
$ff2 = $__biz['business_brand_font_secondary'] ?? $ff1;

// If the value is a single family with spaces and no commas or quotes, wrap in single quotes
$quoteIfNeeded = function($v){
  $v = trim((string)$v);
  if ($v === '') return $v;
  if (strpos($v, ',') !== false) return $v; // already a stack
  if (strpbrk($v, "'\"") !== false) return $v; // already quoted
  if (strpos($v, ' ') !== false) return "'".$v."'";
  return $v;
};
$ff1 = $quoteIfNeeded($ff1);
$ff2 = $quoteIfNeeded($ff2);
$vars = [
  "--brand-primary: $bp;",
  "--brand-secondary: $bs;",
  "--brand-accent: $ba;",
  "--brand-bg: $bg;",
  "--brand-text: $tx;",
  "--brand-font-primary: $ff1;",
  "--brand-font-secondary: $ff2;",
  // Bridge to utility variable names used across the app
  "--font-primary: $ff1;",
  "--font-family-primary: $ff1;",
  "--font-secondary: $ff2;",
];

$css = ":root{\n" . implode("\n", $vars) . "\n}\n" .
".text-brand-primary{color:var(--brand-primary)!important;}\n" .
".text-brand-secondary{color:var(--brand-secondary)!important;}\n" .
".bg-brand-light{background:rgba(14,165,233,0.08)}\n" .
".wf-brand-font{font-family:var(--brand-font-primary) !important;}\n" .
".btn-brand{background:var(--brand-primary);color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer;}\n" .
".link-brand{color:var(--brand-secondary);}\n";

echo "<style id=\"wf-brand-fallback\">\n$css\n</style>\n";
