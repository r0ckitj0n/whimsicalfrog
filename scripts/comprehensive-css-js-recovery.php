<?php
/**
 * Comprehensive CSS and JavaScript Recovery Script
 * Systematically extracts and integrates missing settings from backup files
 */

echo "🔄 Starting Comprehensive CSS & JavaScript Recovery...\n\n";

// Configuration
$baseDir = dirname(__DIR__);
$mainCssFile = $baseDir . '/css/main.css';
$backupDir = $baseDir . '/backups';
$legacyDir = $backupDir . '/legacy_bigfiles';
$jsDir = $baseDir . '/js';
$logFile = $baseDir . '/logs/recovery-' . date('Y-m-d_H-i-s') . '.log';

// Ensure logs directory exists
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

logMessage("=== CSS & JavaScript Recovery Started ===", $logFile);

// Step 1: Analyze current main.css for missing patterns
logMessage("Step 1: Analyzing current main.css structure...", $logFile);
$currentCss = file_get_contents($mainCssFile);
$currentSize = strlen($currentCss);
logMessage("Current main.css size: " . number_format($currentSize) . " bytes", $logFile);

// Step 2: Define critical CSS files to recover from
$criticalCssFiles = [
    // Legacy comprehensive files
    $legacyDir . '/css/admin-styles.css',
    $legacyDir . '/css/global-modals.css', 
    $legacyDir . '/css/room-iframe.css',
    $legacyDir . '/css/room-main.css',
    $legacyDir . '/css/room-modal.css',
    $legacyDir . '/css/room-popups.css',
    $legacyDir . '/css/room-styles.css',
    
    // Individual backup files
    $backupDir . '/components.css',
    $backupDir . '/utilities.css',
    $backupDir . '/pages.css',
    $backupDir . '/modals.css',
    $backupDir . '/room-iframe.css',
    $backupDir . '/room-modal.css',
    $backupDir . '/room.css',
    $backupDir . '/backgrounds.css',
    $backupDir . '/admin.css',
    $backupDir . '/standard-classes.css'
];

// Step 3: Extract and analyze CSS content
logMessage("Step 2: Extracting CSS from backup files...", $logFile);
$recoveredCss = [];
$totalRecoveredSize = 0;

foreach ($criticalCssFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $size = strlen($content);
        $recoveredCss[basename($file)] = $content;
        $totalRecoveredSize += $size;
        logMessage("✅ Recovered: " . basename($file) . " (" . number_format($size) . " bytes)", $logFile);
    } else {
        logMessage("❌ Missing: " . basename($file), $logFile);
    }
}

logMessage("Total recovered CSS: " . number_format($totalRecoveredSize) . " bytes from " . count($recoveredCss) . " files", $logFile);

// Step 4: Identify missing CSS patterns
logMessage("Step 3: Identifying missing CSS patterns...", $logFile);
$missingPatterns = [];

// Check for critical CSS classes/selectors that might be missing
$criticalSelectors = [
    // Admin interface selectors
    '.admin-panel', '.admin-tab', '.admin-modal',
    // Room system selectors  
    '.room-container', '.room-modal', '.room-popup', '.room-iframe',
    // Shop/product selectors
    '.product-card', '.shop-container', '.item-card',
    // Modal selectors
    '.modal-overlay', '.modal-content', '.modal-header',
    // Form selectors
    '.form-container', '.form-group', '.form-input',
    // Button selectors
    '.btn-primary', '.btn-secondary', '.btn-success',
    // Navigation selectors
    '.nav-container', '.nav-item', '.nav-link'
];

foreach ($criticalSelectors as $selector) {
    if (strpos($currentCss, $selector) === false) {
        // Check if any recovered files contain this selector
        foreach ($recoveredCss as $filename => $content) {
            if (strpos($content, $selector) !== false) {
                if (!isset($missingPatterns[$selector])) {
                    $missingPatterns[$selector] = [];
                }
                $missingPatterns[$selector][] = $filename;
            }
        }
    }
}

logMessage("Found " . count($missingPatterns) . " potentially missing CSS patterns", $logFile);

// Step 5: Create comprehensive recovery CSS
logMessage("Step 4: Creating comprehensive recovery CSS...", $logFile);
$recoveryContent = "\n\n/* ==============================================\n";
$recoveryContent .= "   COMPREHENSIVE RECOVERY - " . date('Y-m-d H:i:s') . "\n";
$recoveryContent .= "   ============================================== */\n\n";

foreach ($recoveredCss as $filename => $content) {
    $recoveryContent .= "/* === RECOVERED FROM: $filename === */\n";
    $recoveryContent .= $content . "\n\n";
}

// Step 6: Create backup and append recovery content
logMessage("Step 5: Creating backup and integrating recovery content...", $logFile);
$backupFile = $mainCssFile . '.pre-recovery-' . date('Y-m-d_H-i-s') . '.backup';
copy($mainCssFile, $backupFile);
logMessage("✅ Created backup: " . basename($backupFile), $logFile);

// Append recovery content to main.css
file_put_contents($mainCssFile, $recoveryContent, FILE_APPEND);
$newSize = filesize($mainCssFile);
$addedSize = $newSize - $currentSize;

logMessage("✅ Appended recovery content to main.css", $logFile);
logMessage("Size change: +" . number_format($addedSize) . " bytes (new total: " . number_format($newSize) . " bytes)", $logFile);

// Step 7: JavaScript Recovery
logMessage("Step 6: Starting JavaScript recovery...", $logFile);
$criticalJsFiles = [
    $legacyDir . '/js/cart-system.js',
    $legacyDir . '/js/global-popup.js', 
    $legacyDir . '/js/main.js'
];

$recoveredJs = [];
foreach ($criticalJsFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $targetFile = $jsDir . '/' . basename($file);
        
        // Check if file already exists and compare content
        if (file_exists($targetFile)) {
            $existing = file_get_contents($targetFile);
            if (strlen($content) > strlen($existing)) {
                file_put_contents($targetFile, $content);
                logMessage("✅ Updated JS: " . basename($file) . " (larger version recovered)", $logFile);
            } else {
                logMessage("ℹ️  Skipped JS: " . basename($file) . " (current version is larger/same)", $logFile);
            }
        } else {
            file_put_contents($targetFile, $content);
            logMessage("✅ Recovered JS: " . basename($file), $logFile);
        }
        $recoveredJs[] = basename($file);
    }
}

// Step 8: Generate recovery report
logMessage("Step 7: Generating recovery report...", $logFile);
$reportFile = $baseDir . '/logs/recovery-report-' . date('Y-m-d_H-i-s') . '.json';
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'css_recovery' => [
        'original_size' => $currentSize,
        'recovered_size' => $totalRecoveredSize, 
        'final_size' => $newSize,
        'files_processed' => array_keys($recoveredCss),
        'missing_patterns_found' => count($missingPatterns),
        'backup_created' => basename($backupFile)
    ],
    'js_recovery' => [
        'files_recovered' => $recoveredJs
    ],
    'missing_patterns' => $missingPatterns
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
logMessage("✅ Recovery report saved: " . basename($reportFile), $logFile);

// Step 9: Final summary
logMessage("=== RECOVERY COMPLETED SUCCESSFULLY ===", $logFile);
logMessage("📈 CSS Recovery Summary:", $logFile);
logMessage("  - Original main.css: " . number_format($currentSize) . " bytes", $logFile);
logMessage("  - Recovered content: " . number_format($totalRecoveredSize) . " bytes", $logFile);
logMessage("  - Final main.css: " . number_format($newSize) . " bytes", $logFile);
logMessage("  - Size increase: +" . number_format($addedSize) . " bytes", $logFile);
logMessage("  - Files processed: " . count($recoveredCss), $logFile);

logMessage("📈 JavaScript Recovery Summary:", $logFile);
logMessage("  - Files recovered: " . count($recoveredJs), $logFile);
logMessage("  - Files: " . implode(', ', $recoveredJs), $logFile);

logMessage("💾 Backup & Reports:", $logFile);
logMessage("  - CSS backup: " . basename($backupFile), $logFile);
logMessage("  - Recovery log: " . basename($logFile), $logFile);
logMessage("  - Recovery report: " . basename($reportFile), $logFile);

echo "\n🎉 Recovery completed! Check the Vite build and test your site functionality.\n";
echo "📄 Review the detailed recovery report at: logs/" . basename($reportFile) . "\n\n";
?>
