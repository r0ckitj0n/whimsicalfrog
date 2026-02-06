<?php
/**
 * Marketing Helper API Wrapper
 * Following .windsurfrules: < 300 lines.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/marketing/helper.php';

// Global instance for easy access in legacy templates
try { 
    $GLOBALS['marketingHelper'] = new MarketingHelper(); 
} catch (\Throwable $e) { 
    $GLOBALS['marketingHelper'] = null; 
}

/**
 * Helper functions for legacy PHP templates
 */
function getMarketingData($sku) {
    return $GLOBALS['marketingHelper'] ? $GLOBALS['marketingHelper']->getMarketingData($sku) : null;
}

function getEnhancedDescription($sku, $fallback = '') {
    return $GLOBALS['marketingHelper'] ? $GLOBALS['marketingHelper']->getEnhancedDescription($sku, $fallback) : $fallback;
}

function getUpsellLine($sku, $fallback = '') {
    return $GLOBALS['marketingHelper'] ? $GLOBALS['marketingHelper']->getUpsellLine($sku, $fallback) : ($fallback ?: 'Experience premium quality and style!');
}

function generatePageSEO($pageType, $item_sku = null) {
    return $GLOBALS['marketingHelper'] ? $GLOBALS['marketingHelper']->generatePageSEO($pageType, $item_sku) : [];
}

// Additional legacy helpers can be added here as needed, keeping this file under 300 lines.
