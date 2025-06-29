<?php
/**
 * Room Helper - Centralized room functionality for WhimsicalFrog
 * Eliminates PHP code duplication across room files
 */

class RoomHelper {
    private $pdo;
    private $roomNumber;
    private $roomType;
    private $roomItems = [];
    private $roomCategoryName = '';
    private $roomSettings = null;
    private $seoData = [];
    
    public function __construct($roomNumber = '2') {
        $this->roomNumber = $roomNumber;
        $this->roomType = "room{$roomNumber}";
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        try {
            // Use centralized Database class
            $this->pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Room Helper database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Load room data including items, settings, and SEO data
     */
    public function loadRoomData($categories = []) {
        try {
            $this->loadRoomCategory($categories);
            $this->loadRoomSettings();
            $this->buildSeoData();
            return true;
        } catch (Exception $e) {
            error_log("Error loading room data for room {$this->roomNumber}: " . $e->getMessage());
            $this->loadFallbackData($categories);
            return false;
        }
    }
    
    /**
     * Load primary category for this room
     */
    private function loadRoomCategory($categories) {
        $stmt = $this->pdo->prepare("
            SELECT rca.*, c.name, c.description, c.id as category_id
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            WHERE rca.room_number = ? AND rca.is_primary = 1
            LIMIT 1
        ");
        $stmt->execute([$this->roomNumber]);
        $primaryCategory = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($primaryCategory) {
            $this->roomCategoryName = $primaryCategory['name'];
            
            // Get items for this category if it exists in loaded categories
            if (isset($categories[$this->roomCategoryName])) {
                $this->roomItems = $categories[$this->roomCategoryName];
            }
        }
    }
    
    /**
     * Load room settings from database
     */
    private function loadRoomSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM room_settings WHERE room_number = ?");
        $stmt->execute([$this->roomNumber]);
        $this->roomSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Build SEO data for the room
     */
    private function buildSeoData() {
        $this->seoData = [
            'title' => $this->roomSettings ? $this->roomSettings['room_name'] : "Shop {$this->roomCategoryName}",
            'description' => $this->roomSettings ? $this->roomSettings['description'] : "Browse our collection of {$this->roomCategoryName} at WhimsicalFrog",
            'category' => $this->roomCategoryName,
            'products' => $this->roomItems,
            'canonical' => "/?page=room{$this->roomNumber}",
            'image' => "images/{$this->roomType}.webp"
        ];
    }
    
    /**
     * Load fallback data when database fails
     */
    private function loadFallbackData($categories) {
        $fallbackMap = [
            '2' => 'T-Shirts',
            '3' => 'Tumblers', 
            '4' => 'Artwork',
            '5' => 'Sublimation',
            '6' => 'Window Wraps'
        ];
        
        if (isset($fallbackMap[$this->roomNumber]) && isset($categories[$fallbackMap[$this->roomNumber]])) {
            $this->roomCategoryName = $fallbackMap[$this->roomNumber];
            $this->roomItems = $categories[$fallbackMap[$this->roomNumber]];
            
            // Build fallback SEO data
            $this->seoData = [
                'title' => "Shop {$this->roomCategoryName} - WhimsicalFrog",
                'description' => "Browse our collection of {$this->roomCategoryName} at WhimsicalFrog",
                'category' => $this->roomCategoryName,
                'products' => $this->roomItems,
                'canonical' => "/?page=room{$this->roomNumber}",
                'image' => "images/{$this->roomType}.webp"
            ];
        }
    }
    
    /**
     * Generate structured data for SEO
     */
    public function generateStructuredData() {
        $structuredData = [
            "@context" => "https://schema.org",
            "@type" => "CollectionPage",
            "name" => $this->seoData['title'],
            "description" => $this->seoData['description'],
            "url" => "https://whimsicalfrog.us" . $this->seoData['canonical'],
            "image" => "https://whimsicalfrog.us/" . $this->seoData['image'],
            "mainEntity" => [
                "@type" => "ItemList",
                "name" => $this->seoData['category'] . " Collection",
                "numberOfItems" => count($this->seoData['products']),
                "itemListElement" => []
            ]
        ];
        
        // Add products to structured data
        foreach ($this->seoData['products'] as $index => $product) {
            $structuredData['mainEntity']['itemListElement'][] = [
                "@type" => "ListItem",
                "position" => $index + 1,
                "item" => [
                    "@type" => "Product",
                    "name" => $product['productName'] ?? $product['name'],
                    "sku" => $product['sku'],
                    "description" => $product['description'] ?? '',
                    "offers" => [
                        "@type" => "Offer",
                        "price" => $product['retailPrice'] ?? $product['price'],
                        "priceCurrency" => "USD",
                        "availability" => ($product['stockLevel'] ?? 0) > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
                    ]
                ]
            ];
        }
        
        return json_encode($structuredData, JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Render SEO meta tags
     */
    public function renderSeoTags() {
        $title = htmlspecialchars($this->seoData['title'] ?? '');
        $description = htmlspecialchars($this->seoData['description'] ?? '');
        $category = htmlspecialchars($this->seoData['category'] ?? '');
        $canonical = htmlspecialchars($this->seoData['canonical'] ?? '');
        $image = htmlspecialchars($this->seoData['image'] ?? '');
        
        return "
        <!-- SEO Meta Tags -->
        <title>{$title} | WhimsicalFrog</title>
        <meta name=\"description\" content=\"{$description}\">
        <meta name=\"keywords\" content=\"{$category}, WhimsicalFrog, custom products, online store\">
        <link rel=\"canonical\" href=\"https://whimsicalfrog.us{$canonical}\">

        <!-- Open Graph Tags -->
        <meta property=\"og:title\" content=\"{$title}\">
        <meta property=\"og:description\" content=\"{$description}\">
        <meta property=\"og:image\" content=\"https://whimsicalfrog.us/{$image}\">
        <meta property=\"og:url\" content=\"https://whimsicalfrog.us{$canonical}\">
        <meta property=\"og:type\" content=\"website\">

        <!-- Twitter Card Tags -->
        <meta name=\"twitter:card\" content=\"summary_large_image\">
        <meta name=\"twitter:title\" content=\"{$title}\">
        <meta name=\"twitter:description\" content=\"{$description}\">
        <meta name=\"twitter:image\" content=\"https://whimsicalfrog.us/{$image}\">

        <!-- Structured Data -->
        <script type=\"application/ld+json\">
        {$this->generateStructuredData()}
        </script>";
    }
    
    /**
     * Render required CSS links
     */
    public function renderCssLinks() {
        $timestamp = time();
        return "
        <!-- Room-specific CSS -->
        <link href=\"css/room-headers.css?v={$timestamp}\" rel=\"stylesheet\">
        <link href=\"css/room-popups.css?v={$timestamp}\" rel=\"stylesheet\">
        <link href=\"css/room-styles.css?v={$timestamp}\" rel=\"stylesheet\">";
    }
    
    /**
     * Render required JavaScript
     */
    public function renderJavaScript() {
        return "
        <!-- Room-specific JavaScript -->
        <script>
        // Set room-specific data
        window.roomItems = " . json_encode($this->roomItems) . ";
        window.roomNumber = '{$this->roomNumber}';
        window.roomType = '{$this->roomType}';
        
        // Add modal mode class if needed
        " . (isset($_GET['modal']) ? "document.body.classList.add('room-modal-mode');" : "") . "
        </script>";
    }
    
    /**
     * Render room container with background
     */
    public function renderRoomContainer($content = '') {
        return "
        <div class=\"room-container\">
            <div class=\"room-overlay-wrapper\" style=\"background-image: url('images/{$this->roomType}.webp?v=cb2');\">
                <div class=\"room-overlay-content\">
                    {$content}
                </div>
            </div>
        </div>";
    }
    
    /**
     * Render room header with back button and title
     */
    public function renderRoomHeader() {
        $roomName = $this->roomSettings['room_name'] ?? $this->roomCategoryName;
        $roomDescription = $this->roomSettings['description'] ?? '';
        
        return "
        <div class=\"room-header-overlay\">
            <div class=\"back-button-container\">
                <a href=\"/?page=main_room\" class=\"back-to-main-button\">
                    <svg width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\">
                        <path d=\"m12 19-7-7 7-7\"></path>
                        <path d=\"m19 12-7 7-7-7\"></path>
                    </svg>
                    <span>Back to Main Room</span>
                </a>
            </div>
            
            <div class=\"room-title-overlay\">
                <h2 class=\"room-title\">{$roomName}</h2>
                " . ($roomDescription ? "<p class=\"room-description\">{$roomDescription}</p>" : "") . "
            </div>
        </div>";
    }
    
    /**
     * Render product icons from room items
     */
    public function renderProductIcons() {
        $html = '<div class="shelf-area">';
        
        foreach ($this->roomItems as $index => $item) {
            $stockLevel = intval($item['stockLevel'] ?? 0);
            $outOfStockClass = $stockLevel <= 0 ? ' out-of-stock' : '';
            $outOfStockBadge = $stockLevel <= 0 ? '<div class="out-of-stock-badge">Out of Stock</div>' : '';
            
            // Get image path with fallbacks
            $imagePath = $this->getItemImagePath($item);
            
            // Add image information to item data for popup
            $itemWithImage = $item;
            $itemWithImage['primaryImageUrl'] = $imagePath;
            
            $html .= "
            <div class=\"item-icon{$outOfStockClass}\" 
                 data-product-id=\"" . htmlspecialchars($item['sku']) . "\"
                 data-stock=\"{$stockLevel}\"
                 data-index=\"{$index}\"
                 onmouseenter=\"showGlobalPopup(this, " . htmlspecialchars(json_encode($itemWithImage)) . ")\"
                 onmouseleave=\"hideGlobalPopup()\"
                 onclick=\"showGlobalPopup(this, " . htmlspecialchars(json_encode($itemWithImage)) . ")\"
                 style=\"cursor: pointer;\">
                <img src=\"{$imagePath}\" alt=\"" . htmlspecialchars($item['name'] ?? $item['productName'] ?? 'Product') . "\" loading=\"lazy\">
                {$outOfStockBadge}
            </div>";
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get item image path with fallbacks
     */
    private function getItemImagePath($item) {
        // Try multiple image path options
        $imagePaths = [
            $item['image'] ?? null,
            $item['imageUrl'] ?? null,
            "images/items/{$item['sku']}A.webp",
            "images/items/{$item['sku']}A.png",
            "images/placeholder.png"
        ];
        
        foreach ($imagePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }
        
        return "images/placeholder.png";
    }
    
    /**
     * Get room data
     */
    public function getRoomData() {
        return [
            'roomNumber' => $this->roomNumber,
            'roomType' => $this->roomType,
            'roomItems' => $this->roomItems,
            'roomCategoryName' => $this->roomCategoryName,
            'roomSettings' => $this->roomSettings,
            'seoData' => $this->seoData
        ];
    }
    
    // Getters
    public function getRoomNumber() { return $this->roomNumber; }
    public function getRoomType() { return $this->roomType; }
    public function getRoomItems() { return $this->roomItems; }
    public function getRoomCategoryName() { return $this->roomCategoryName; }
    public function getRoomSettings() { return $this->roomSettings; }
    public function getSeoData() { return $this->seoData; }
}
?> 