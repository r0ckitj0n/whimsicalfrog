<?php
/**
 * CSS Syntax Cleanup Script
 * Fixes malformed CSS introduced during recovery process
 */

echo "🧹 Starting CSS Syntax Cleanup...\n\n";

$baseDir = dirname(__DIR__);
$mainCssFile = $baseDir . '/css/main.css';
$logFile = $baseDir . '/logs/css-cleanup-' . date('Y-m-d_H-i-s') . '.log';

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("=== CSS Syntax Cleanup Started ===", $logFile);

// Read the current CSS
$css = file_get_contents($mainCssFile);
$originalSize = strlen($css);
logMessage("Original CSS size: " . number_format($originalSize) . " bytes", $logFile);

// Create backup before cleanup
$backupFile = $mainCssFile . '.pre-cleanup-' . date('Y-m-d_H-i-s') . '.backup';
copy($mainCssFile, $backupFile);
logMessage("✅ Created backup: " . basename($backupFile), $logFile);

$cleanupCount = 0;

// Fix 1: Convert Tailwind utility classes to proper CSS
logMessage("Step 1: Converting Tailwind utility classes to proper CSS...", $logFile);

$tailwindFixes = [
    // Padding utilities
    '/px-(\d+)\s+py-(\d+);/' => 'padding: ${1}rem ${2}rem;',
    '/px-(\d+);/' => 'padding-left: ${1}rem; padding-right: ${1}rem;',
    '/py-(\d+);/' => 'padding-top: ${1}rem; padding-bottom: ${1}rem;',
    '/p-(\d+);/' => 'padding: ${1}rem;',
    
    // Margin utilities  
    '/mx-(\d+)\s+my-(\d+);/' => 'margin: ${2}rem ${1}rem;',
    '/mx-(\d+);/' => 'margin-left: ${1}rem; margin-right: ${1}rem;',
    '/my-(\d+);/' => 'margin-top: ${1}rem; margin-bottom: ${1}rem;',
    '/m-(\d+);/' => 'margin: ${1}rem;',
    
    // Border radius
    '/rounded-full;/' => 'border-radius: 9999px;',
    '/rounded-lg;/' => 'border-radius: 0.5rem;',
    '/rounded-md;/' => 'border-radius: 0.375rem;',
    '/rounded;/' => 'border-radius: 0.25rem;',
    
    // Font sizes
    '/text-xs;/' => 'font-size: 0.75rem;',
    '/text-sm;/' => 'font-size: 0.875rem;',
    '/text-base;/' => 'font-size: 1rem;',
    '/text-lg;/' => 'font-size: 1.125rem;',
    '/text-xl;/' => 'font-size: 1.25rem;',
    
    // Font weights
    '/font-thin;/' => 'font-weight: 100;',
    '/font-light;/' => 'font-weight: 300;',
    '/font-normal;/' => 'font-weight: 400;',
    '/font-medium;/' => 'font-weight: 500;',
    '/font-semibold;/' => 'font-weight: 600;',
    '/font-bold;/' => 'font-weight: 700;',
    
    // Spacing utilities that are invalid CSS properties
    '/space-x-(\d+);/' => '/* gap: ${1}rem; */ /* Use flexbox/grid gap instead */',
    '/space-y-(\d+);/' => '/* gap: ${1}rem; */ /* Use flexbox/grid gap instead */',
    
    // Display utilities
    '/block;/' => 'display: block;',
    '/inline;/' => 'display: inline;',
    '/inline-block;/' => 'display: inline-block;',
    '/flex;/' => 'display: flex;',
    '/grid;/' => 'display: grid;',
    '/hidden;/' => 'display: none;',
    
    // Common malformed patterns
    '/\s+px-2\s+py-1;/' => ' padding: 0.25rem 0.5rem;',
    '/\s+rounded-full;/' => ' border-radius: 9999px;',
    '/\s+text-xs;/' => ' font-size: 0.75rem;',
    '/\s+font-medium;/' => ' font-weight: 500;',
];

foreach ($tailwindFixes as $pattern => $replacement) {
    $newCss = preg_replace($pattern, $replacement, $css);
    if ($newCss !== $css) {
        $matches = preg_match_all($pattern, $css);
        $cleanupCount += $matches;
        $css = $newCss;
        logMessage("  ✅ Fixed pattern: " . $pattern . " (" . $matches . " occurrences)", $logFile);
    }
}

// Fix 2: Remove empty rulesets
logMessage("Step 2: Removing empty rulesets...", $logFile);
$emptyRulesetPattern = '/[^{}]*\{\s*\}/';
$matches = preg_match_all($emptyRulesetPattern, $css);
if ($matches > 0) {
    $css = preg_replace($emptyRulesetPattern, '', $css);
    $cleanupCount += $matches;
    logMessage("  ✅ Removed " . $matches . " empty rulesets", $logFile);
}

// Fix 3: Fix standalone properties (properties without selectors)
logMessage("Step 3: Fixing standalone properties...", $logFile);
$lines = explode("\n", $css);
$fixedLines = [];
$inRuleset = false;
$braceCount = 0;

foreach ($lines as $line) {
    $trimmed = trim($line);
    
    // Count braces to track if we're inside a ruleset
    $braceCount += substr_count($line, '{') - substr_count($line, '}');
    $inRuleset = $braceCount > 0;
    
    // Check if this looks like a standalone property (has : and ; but we're not in a ruleset)
    if (!$inRuleset && strpos($trimmed, ':') !== false && strpos($trimmed, ';') !== false && !empty($trimmed)) {
        // Skip this line as it's a malformed standalone property
        logMessage("  ✅ Removed standalone property: " . $trimmed, $logFile);
        $cleanupCount++;
        continue;
    }
    
    $fixedLines[] = $line;
}

$css = implode("\n", $fixedLines);

// Fix 4: Clean up excessive whitespace
logMessage("Step 4: Cleaning up excessive whitespace...", $logFile);
$css = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $css); // Multiple empty lines to double
$css = preg_replace('/\s+$/', '', $css); // Trailing whitespace

// Write the cleaned CSS
file_put_contents($mainCssFile, $css);
$newSize = strlen($css);
$sizeDiff = $originalSize - $newSize;

logMessage("=== CLEANUP COMPLETED SUCCESSFULLY ===", $logFile);
logMessage("📈 Cleanup Summary:", $logFile);
logMessage("  - Original size: " . number_format($originalSize) . " bytes", $logFile);
logMessage("  - Final size: " . number_format($newSize) . " bytes", $logFile);
logMessage("  - Size reduction: " . number_format($sizeDiff) . " bytes", $logFile);
logMessage("  - Total fixes applied: " . $cleanupCount, $logFile);
logMessage("💾 Files:", $logFile);
logMessage("  - Backup created: " . basename($backupFile), $logFile);
logMessage("  - Cleanup log: " . basename($logFile), $logFile);

echo "\n🎉 CSS cleanup completed! The build should now work without syntax errors.\n";
echo "📄 Check the detailed log at: logs/" . basename($logFile) . "\n\n";
?>
