<?php
require_once 'config.php';

class MarketingHelper {
    private $pdo;
    
    public function __construct() {
        global $dsn, $user, $pass, $options;
        $this->pdo = new PDO($dsn, $user, $pass, $options);
        $this->initializeTables();
    }
    
    private function initializeTables() {
        // Create SEO settings table
        $seoTableSql = "CREATE TABLE IF NOT EXISTS seo_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_type VARCHAR(50) NOT NULL,
            setting_name VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_page_setting (page_type, setting_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($seoTableSql);
        
        // Insert default SEO settings
        $this->insertDefaultSEOSettings();
    }
    
    private function insertDefaultSEOSettings() {
        $defaults = [
            ['global', 'site_title', 'Whimsical Frog - Custom Crafts & Personalized Items'],
            ['global', 'site_description', 'Discover unique custom crafts, personalized t-shirts, tumblers, artwork, and more at Whimsical Frog. Quality handmade items for every occasion.'],
            ['global', 'site_keywords', 'custom crafts, personalized items, t-shirts, tumblers, artwork, sublimation, window wraps, handmade, gifts'],
            ['home', 'page_title', 'Custom Crafts & Personalized Items | Whimsical Frog'],
            ['home', 'meta_description', 'Explore our collection of custom crafts including personalized t-shirts, tumblers, artwork, and unique handmade items. Perfect for gifts and personal use.'],
            ['shop', 'page_title', 'Shop Custom Items | Whimsical Frog'],
            ['shop', 'meta_description', 'Browse our complete collection of custom crafts, personalized items, and handmade goods. Find the perfect item for any occasion.']
        ];
        
        foreach ($defaults as $default) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO seo_settings (page_type, setting_name, setting_value) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute($default);
        }
    }
    
    public function getMarketingData($sku) {
        $stmt = $this->pdo->prepare("SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$sku]);
        $data = $stmt->fetch();
        
        if ($data) {
            // Decode JSON fields
            $jsonFields = [
                'keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages',
                'unique_selling_points', 'value_propositions', 'marketing_channels',
                'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions',
                'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes',
                'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment'
            ];
            
            foreach ($jsonFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = json_decode($data[$field], true) ?? [];
                }
            }
        }
        
        return $data;
    }
    
    public function getEnhancedDescription($sku, $fallbackDescription = '') {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['suggested_description'])) {
            return $marketingData['suggested_description'];
        }
        
        return $fallbackDescription;
    }
    
    public function getSellingPoints($sku) {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['selling_points'])) {
            return $marketingData['selling_points'];
        }
        
        return [];
    }
    
    public function getKeywords($sku) {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['seo_keywords'])) {
            return $marketingData['seo_keywords'];
        }
        
        return [];
    }
    
    public function getSEOSettings($pageType = 'global') {
        $stmt = $this->pdo->prepare("SELECT setting_name, setting_value FROM seo_settings WHERE page_type = ? OR page_type = 'global' ORDER BY page_type DESC");
        $stmt->execute([$pageType]);
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            if (!isset($result[$setting['setting_name']])) {
                $result[$setting['setting_name']] = $setting['setting_value'];
            }
        }
        
        return $result;
    }
    
    public function generatePageSEO($pageType, $productSku = null) {
        $seoSettings = $this->getSEOSettings($pageType);
        $seo = [];
        
        // Base SEO
        $seo['title'] = $seoSettings['page_title'] ?? $seoSettings['site_title'] ?? 'Whimsical Frog';
        $seo['description'] = $seoSettings['meta_description'] ?? $seoSettings['site_description'] ?? '';
        $seo['keywords'] = $seoSettings['site_keywords'] ?? '';
        
        // If product-specific page, enhance with product marketing data
        if ($productSku) {
            $marketingData = $this->getMarketingData($productSku);
            if ($marketingData) {
                // Enhance title with product name
                if (!empty($marketingData['suggested_title'])) {
                    $seo['title'] = $marketingData['suggested_title'] . ' | ' . ($seoSettings['site_title'] ?? 'Whimsical Frog');
                }
                
                // Use product description
                if (!empty($marketingData['suggested_description'])) {
                    $seo['description'] = $marketingData['suggested_description'];
                }
                
                // Add product keywords
                if (!empty($marketingData['seo_keywords'])) {
                    $productKeywords = implode(', ', $marketingData['seo_keywords']);
                    $seo['keywords'] = $productKeywords . ', ' . $seo['keywords'];
                }
            }
        }
        
        return $seo;
    }
    
    public function getCallToActions($sku) {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['call_to_action_suggestions'])) {
            return $marketingData['call_to_action_suggestions'];
        }
        
        return ['Add to Cart', 'Buy Now', 'Get Yours Today'];
    }
    
    public function getCompetitiveAdvantages($sku) {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['competitive_advantages'])) {
            return $marketingData['competitive_advantages'];
        }
        
        return [];
    }
    
    public function getTargetAudience($sku) {
        $marketingData = $this->getMarketingData($sku);
        
        if ($marketingData && !empty($marketingData['target_audience'])) {
            return $marketingData['target_audience'];
        }
        
        return '';
    }
}

// Global instance for easy access
$GLOBALS['marketingHelper'] = new MarketingHelper();

// Helper functions for templates
function getMarketingData($sku) {
    return $GLOBALS['marketingHelper']->getMarketingData($sku);
}

function getEnhancedDescription($sku, $fallback = '') {
    return $GLOBALS['marketingHelper']->getEnhancedDescription($sku, $fallback);
}

function getSellingPoints($sku) {
    return $GLOBALS['marketingHelper']->getSellingPoints($sku);
}

function getProductKeywords($sku) {
    return $GLOBALS['marketingHelper']->getKeywords($sku);
}

function generatePageSEO($pageType, $productSku = null) {
    return $GLOBALS['marketingHelper']->generatePageSEO($pageType, $productSku);
}

function getCallToActions($sku) {
    return $GLOBALS['marketingHelper']->getCallToActions($sku);
}

function getCompetitiveAdvantages($sku) {
    return $GLOBALS['marketingHelper']->getCompetitiveAdvantages($sku);
}

function getTargetAudience($sku) {
    return $GLOBALS['marketingHelper']->getTargetAudience($sku);
}
?> 