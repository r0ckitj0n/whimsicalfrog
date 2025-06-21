<?php
/**
 * Enhanced SEO System for WhimsicalFrog
 * Adds structured data and advanced SEO features using Marketing Manager data
 */

require_once 'config.php';
require_once 'marketing_helper.php';

class SEOEnhancer {
    private $pdo;
    private $marketingHelper;
    
    public function __construct() {
        global $dsn, $user, $pass, $options;
        $this->pdo = new PDO($dsn, $user, $pass, $options);
        $this->marketingHelper = $GLOBALS['marketingHelper'];
    }
    
    /**
     * Generate JSON-LD structured data for products
     */
    public function generateProductStructuredData($sku, $product) {
        $marketingData = $this->marketingHelper->getMarketingData($sku);
        $sellingPoints = $this->marketingHelper->getSellingPoints($sku);
        $keywords = $this->marketingHelper->getKeywords($sku);
        
        $structuredData = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $marketingData['suggested_title'] ?? $product['name'],
            "description" => $marketingData['suggested_description'] ?? $product['description'],
            "sku" => $sku,
            "brand" => [
                "@type" => "Brand",
                "name" => "Whimsical Frog"
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => "https://whimsicalfrog.us/?page=shop&product=" . $sku,
                "priceCurrency" => "USD",
                "price" => $product['price'] ?? "0.00",
                "availability" => ($product['stock'] ?? 0) > 0 ? 
                    "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                "seller" => [
                    "@type" => "Organization",
                    "name" => "Whimsical Frog"
                ]
            ]
        ];
        
        // Add category if available
        if (!empty($product['category'])) {
            $structuredData["category"] = $product['category'];
        }
        
        // Add features from selling points
        if (!empty($sellingPoints)) {
            $structuredData["additionalProperty"] = [];
            foreach (array_slice($sellingPoints, 0, 5) as $point) {
                $structuredData["additionalProperty"][] = [
                    "@type" => "PropertyValue",
                    "name" => "Feature",
                    "value" => $point
                ];
            }
        }
        
        // Add keywords
        if (!empty($keywords)) {
            $structuredData["keywords"] = implode(", ", $keywords);
        }
        
        return json_encode($structuredData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Generate LocalBusiness structured data
     */
    public function generateLocalBusinessStructuredData() {
        return json_encode([
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => "Whimsical Frog",
            "description" => "Custom crafts and personalized items including t-shirts, tumblers, artwork, sublimation, and window wraps",
            "url" => "https://whimsicalfrog.us",
            "@id" => "https://whimsicalfrog.us",
            "telephone" => "+1-XXX-XXX-XXXX", // Replace with actual phone
            "address" => [
                "@type" => "PostalAddress",
                "addressLocality" => "Your City", // Replace with actual city
                "addressRegion" => "Your State", // Replace with actual state
                "addressCountry" => "US"
            ],
            "geo" => [
                "@type" => "GeoCoordinates",
                "latitude" => "0.0", // Replace with actual coordinates
                "longitude" => "0.0"
            ],
            "openingHours" => "Mo-Fr 09:00-17:00", // Replace with actual hours
            "priceRange" => "$-$$",
            "paymentAccepted" => "Cash, Credit Card, PayPal, Venmo",
            "currenciesAccepted" => "USD"
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Generate breadcrumb structured data
     */
    public function generateBreadcrumbStructuredData($page, $productName = null) {
        $breadcrumbs = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => [
                [
                    "@type" => "ListItem",
                    "position" => 1,
                    "name" => "Home",
                    "item" => "https://whimsicalfrog.us"
                ]
            ]
        ];
        
        if ($page === 'shop') {
            $breadcrumbs["itemListElement"][] = [
                "@type" => "ListItem",
                "position" => 2,
                "name" => "Shop",
                "item" => "https://whimsicalfrog.us/?page=shop"
            ];
            
            if ($productName) {
                $breadcrumbs["itemListElement"][] = [
                    "@type" => "ListItem",
                    "position" => 3,
                    "name" => $productName,
                    "item" => "https://whimsicalfrog.us/?page=shop&product=" . $_GET['product']
                ];
            }
        }
        
        return json_encode($breadcrumbs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Generate enhanced meta tags with marketing data
     */
    public function generateEnhancedMetaTags($page, $productSku = null) {
        $seoData = $this->marketingHelper->generatePageSEO($page, $productSku);
        $metaTags = [];
        
        // Basic meta tags
        $metaTags[] = '<title>' . htmlspecialchars($seoData['title']) . '</title>';
        $metaTags[] = '<meta name="description" content="' . htmlspecialchars($seoData['description']) . '">';
        $metaTags[] = '<meta name="keywords" content="' . htmlspecialchars($seoData['keywords']) . '">';
        
        // Open Graph tags
        $metaTags[] = '<meta property="og:title" content="' . htmlspecialchars($seoData['title']) . '">';
        $metaTags[] = '<meta property="og:description" content="' . htmlspecialchars($seoData['description']) . '">';
        $metaTags[] = '<meta property="og:type" content="' . ($productSku ? 'product' : 'website') . '">';
        $metaTags[] = '<meta property="og:url" content="https://whimsicalfrog.us' . $_SERVER['REQUEST_URI'] . '">';
        $metaTags[] = '<meta property="og:site_name" content="Whimsical Frog">';
        
        // Twitter Card tags
        $metaTags[] = '<meta name="twitter:card" content="summary_large_image">';
        $metaTags[] = '<meta name="twitter:title" content="' . htmlspecialchars($seoData['title']) . '">';
        $metaTags[] = '<meta name="twitter:description" content="' . htmlspecialchars($seoData['description']) . '">';
        
        // Additional SEO tags
        $metaTags[] = '<meta name="robots" content="index, follow">';
        $metaTags[] = '<meta name="author" content="Whimsical Frog">';
        $metaTags[] = '<link rel="canonical" href="https://whimsicalfrog.us' . $_SERVER['REQUEST_URI'] . '">';
        
        // Product-specific tags
        if ($productSku) {
            $marketingData = $this->marketingHelper->getMarketingData($productSku);
            if ($marketingData) {
                $metaTags[] = '<meta property="product:brand" content="Whimsical Frog">';
                $metaTags[] = '<meta property="product:availability" content="in stock">';
                $metaTags[] = '<meta property="product:condition" content="new">';
                
                // Add selling points as additional meta tags
                $sellingPoints = $this->marketingHelper->getSellingPoints($productSku);
                if (!empty($sellingPoints)) {
                    $metaTags[] = '<meta name="product:features" content="' . htmlspecialchars(implode(', ', array_slice($sellingPoints, 0, 3))) . '">';
                }
            }
        }
        
        return implode("\n    ", $metaTags);
    }
    
    /**
     * Generate all structured data for a page
     */
    public function generateAllStructuredData($page, $productSku = null, $productData = null) {
        $structuredDataScripts = [];
        
        // Always include LocalBusiness data
        $structuredDataScripts[] = '<script type="application/ld+json">' . 
            $this->generateLocalBusinessStructuredData() . '</script>';
        
        // Add breadcrumb data
        $productName = $productData['name'] ?? null;
        $structuredDataScripts[] = '<script type="application/ld+json">' . 
            $this->generateBreadcrumbStructuredData($page, $productName) . '</script>';
        
        // Add product data if available
        if ($productSku && $productData) {
            $structuredDataScripts[] = '<script type="application/ld+json">' . 
                $this->generateProductStructuredData($productSku, $productData) . '</script>';
        }
        
        return implode("\n    ", $structuredDataScripts);
    }
    
    /**
     * Optimize image alt tags with marketing keywords
     */
    public function optimizeImageAltTag($sku, $defaultAlt = '') {
        $keywords = $this->marketingHelper->getKeywords($sku);
        $sellingPoints = $this->marketingHelper->getSellingPoints($sku);
        
        $altText = $defaultAlt;
        
        if (!empty($keywords)) {
            $altText .= ' - ' . implode(', ', array_slice($keywords, 0, 3));
        }
        
        if (!empty($sellingPoints)) {
            $altText .= ' - ' . $sellingPoints[0];
        }
        
        return trim($altText, ' -');
    }
}

// Global instance
$GLOBALS['seoEnhancer'] = new SEOEnhancer();

// Helper functions
function generateEnhancedMetaTags($page, $productSku = null) {
    return $GLOBALS['seoEnhancer']->generateEnhancedMetaTags($page, $productSku);
}

function generateAllStructuredData($page, $productSku = null, $productData = null) {
    return $GLOBALS['seoEnhancer']->generateAllStructuredData($page, $productSku, $productData);
}

function optimizeImageAltTag($sku, $defaultAlt = '') {
    return $GLOBALS['seoEnhancer']->optimizeImageAltTag($sku, $defaultAlt);
}

?> 