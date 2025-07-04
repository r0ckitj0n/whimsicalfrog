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
    
    /**
     * Constructor - Initialize room helper
     */
    public function __construct($roomNumber) {
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
        return "
        <!-- All room styling now handled by database-driven CSS system -->
        ";
    }
    
    /**
     * Render required JavaScript
     */
    public function renderJavaScript() {
        $coordinates = $this->getRoomCoordinates();
        
        return "
        <!-- Room-specific JavaScript -->
        <script>
        // Set room-specific data
        window.roomItems = " . json_encode($this->roomItems) . ";
        window.roomNumber = '{$this->roomNumber}';
        window.roomType = '{$this->roomType}';
        window.ROOM_TYPE = '{$this->roomType}';
        
        // Room coordinate system data
        window.originalImageWidth = 1280;
        window.originalImageHeight = 896;
        window.baseAreas = " . json_encode($coordinates) . ";
        window.roomOverlayWrapper = null;
        
        // Initialize coordinate system when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
            if (window.roomOverlayWrapper && window.baseAreas && window.baseAreas.length > 0) {
                updateItemPositions();
                
                // Update positions on window resize
                let resizeTimeout;
                window.addEventListener('resize', function() {
                    clearTimeout(resizeTimeout);
                    resizeTimeout = setTimeout(function() {
                        updateItemPositions();
                        adjustTitleBoxSize();
                    }, 100);
                });
            }
            
            // Initialize title box sizing
            adjustTitleBoxSize();
        });
        
        // Function to update item positions with scaling
        function updateItemPositions() {
            if (!window.roomOverlayWrapper || !window.baseAreas) return;
            
            const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
            const wrapperHeight = window.roomOverlayWrapper.offsetHeight;
            
            const wrapperAspectRatio = wrapperWidth / wrapperHeight;
            const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;
            
            let renderedImageWidth, renderedImageHeight;
            let offsetX = 0;
            let offsetY = 0;
            
            if (wrapperAspectRatio > imageAspectRatio) {
                renderedImageHeight = wrapperHeight;
                renderedImageWidth = renderedImageHeight * imageAspectRatio;
                offsetX = (wrapperWidth - renderedImageWidth) / 2;
            } else {
                renderedImageWidth = wrapperWidth;
                renderedImageHeight = renderedImageWidth / imageAspectRatio;
                offsetY = (wrapperHeight - renderedImageHeight) / 2;
            }
            
            const scaleX = renderedImageWidth / window.originalImageWidth;
            const scaleY = renderedImageHeight / window.originalImageHeight;
            
            window.baseAreas.forEach((areaData, index) => {
                const itemElement = document.getElementById('item-icon-' + index);
                if (itemElement && areaData) {
                    itemElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
                    itemElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
                    itemElement.style.width = (areaData.width * scaleX) + 'px';
                    itemElement.style.height = (areaData.height * scaleY) + 'px';
                }
            });
        }
        
        // Function to adjust title box size and text size dynamically
        function adjustTitleBoxSize() {
            const titleOverlay = document.querySelector('.room-title-overlay');
            if (!titleOverlay) return;
            
            const title = titleOverlay.querySelector('.room-title');
            const description = titleOverlay.querySelector('.room-description');
            
            if (!title) return;
            
            // Calculate content-based sizing
            const titleLength = title.textContent.length;
            const descriptionLength = description ? description.textContent.length : 0;
            const totalLength = titleLength + descriptionLength;
            
            // Get screen size for responsive adjustments
            const screenWidth = window.innerWidth;
            const isMobile = screenWidth <= 480;
            const isTablet = screenWidth <= 768;
            
            // Dynamic width based on content length and screen size
            let dynamicWidth;
            if (isMobile) {
                if (totalLength <= 25) dynamicWidth = '140px';
                else if (totalLength <= 40) dynamicWidth = '180px';
                else if (totalLength <= 60) dynamicWidth = '220px';
                else dynamicWidth = '240px';
            } else if (isTablet) {
                if (totalLength <= 30) dynamicWidth = '160px';
                else if (totalLength <= 50) dynamicWidth = '210px';
                else if (totalLength <= 70) dynamicWidth = '250px';
                else dynamicWidth = '280px';
            } else {
                if (totalLength <= 30) dynamicWidth = '200px';
                else if (totalLength <= 50) dynamicWidth = '250px';
                else if (totalLength <= 80) dynamicWidth = '300px';
                else dynamicWidth = '400px';
            }
            
            // Dynamic padding based on content and screen size
            let dynamicPadding;
            if (isMobile) {
                if (totalLength <= 30) dynamicPadding = '6px 10px';
                else dynamicPadding = '8px 12px';
            } else if (isTablet) {
                if (totalLength <= 30) dynamicPadding = '8px 12px';
                else dynamicPadding = '10px 14px';
            } else {
                if (totalLength <= 30) dynamicPadding = '10px 14px';
                else if (totalLength <= 50) dynamicPadding = '12px 16px';
                else dynamicPadding = '14px 18px';
            }
            
            // Apply dynamic styling
            titleOverlay.style.width = dynamicWidth;
            titleOverlay.style.padding = dynamicPadding;
            
            // Dynamic text sizing based on available width and content length
            const availableWidth = parseInt(dynamicWidth) - (parseInt(dynamicPadding.split(' ')[1]) * 2);
            
            // Calculate optimal font sizes to fit content without wrapping
            let titleFontSize, descriptionFontSize;
            
            if (isMobile) {
                // Mobile text sizing
                if (titleLength <= 15) titleFontSize = '1.6rem';
                else if (titleLength <= 25) titleFontSize = '1.3rem';
                else if (titleLength <= 35) titleFontSize = '1.1rem';
                else titleFontSize = '1rem';
                
                if (descriptionLength <= 30) descriptionFontSize = '0.9rem';
                else if (descriptionLength <= 50) descriptionFontSize = '0.8rem';
                else descriptionFontSize = '0.7rem';
            } else if (isTablet) {
                // Tablet text sizing
                if (titleLength <= 15) titleFontSize = '2rem';
                else if (titleLength <= 25) titleFontSize = '1.7rem';
                else if (titleLength <= 35) titleFontSize = '1.4rem';
                else titleFontSize = '1.2rem';
                
                if (descriptionLength <= 30) descriptionFontSize = '1.1rem';
                else if (descriptionLength <= 50) descriptionFontSize = '1rem';
                else descriptionFontSize = '0.9rem';
            } else {
                // Desktop text sizing
                if (titleLength <= 15) titleFontSize = '2.5rem';
                else if (titleLength <= 25) titleFontSize = '2.2rem';
                else if (titleLength <= 35) titleFontSize = '1.9rem';
                else if (titleLength <= 45) titleFontSize = '1.6rem';
                else titleFontSize = '1.4rem';
                
                if (descriptionLength <= 30) descriptionFontSize = '1.3rem';
                else if (descriptionLength <= 50) descriptionFontSize = '1.2rem';
                else if (descriptionLength <= 70) descriptionFontSize = '1.1rem';
                else descriptionFontSize = '1rem';
            }
            
            // Apply font sizes and allow natural word wrapping
            if (title) {
                title.style.fontSize = titleFontSize;
                // Remove any forced no-wrap styling to allow natural wrapping
                title.style.whiteSpace = '';
                title.style.overflow = '';
                title.style.textOverflow = '';
            }
            
            if (description) {
                description.style.fontSize = descriptionFontSize;
                // Remove any forced no-wrap styling to allow natural wrapping
                description.style.whiteSpace = '';
                description.style.overflow = '';
                description.style.textOverflow = '';
            }
        }
        
        // Add modal mode class if needed
        " . (isset($_GET['modal']) ? "document.body.classList.add('room-modal-mode');" : "") . "
        </script>
        
        <!-- Load CSS initializer for global CSS variables -->
        <script src=\"js/css-initializer.js\"></script>
        
        <!-- Load room coordinate manager -->
        <script src=\"js/room-coordinate-manager.js\"></script>
        
        <!-- Initialize global CSS variables -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load global CSS variables from database
            if (typeof loadGlobalCSS === 'function') {
                loadGlobalCSS();
            }
        });
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
                <a href=\"/?page=room_main\" class=\"back-to-main-button\">
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
        $html = '<div class="shelf-area" id="shelf-area">';
        
        foreach ($this->roomItems as $index => $item) {
            $stockLevel = intval($item['stockLevel'] ?? 0);
            $outOfStockClass = $stockLevel <= 0 ? ' out-of-stock' : '';
            $outOfStockBadge = $stockLevel <= 0 ? '<div class="out-of-stock-badge">Out of Stock</div>' : '';
            
            // Get image path with fallbacks
            $imagePath = $this->getItemImagePath($item);
            
            // Add image information to item data for popup
            $itemWithImage = $item;
            $itemWithImage['primaryImageUrl'] = $imagePath;
            
            // Generate item icon without positioning - let JavaScript handle scaling
            $html .= "
            <div class=\"item-icon{$outOfStockClass}\" 
                 id=\"item-icon-{$index}\"
                 data-product-id=\"" . htmlspecialchars($item['sku']) . "\"
                 data-stock=\"{$stockLevel}\"
                 data-index=\"{$index}\"
                 onmouseenter=\"showGlobalPopup(this, " . htmlspecialchars(json_encode($itemWithImage)) . ")\"
                 onmouseleave=\"hideGlobalPopup()\"
                 onclick=\"showItemDetailsModal('" . htmlspecialchars($item['sku']) . "')\">
                <img src=\"{$imagePath}\" alt=\"" . htmlspecialchars($item['name'] ?? $item['productName'] ?? 'Product') . "\" loading=\"lazy\">
                {$outOfStockBadge}
            </div>";
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get room coordinates for positioning items
     */
    private function getRoomCoordinates() {
        try {
            $stmt = $this->pdo->prepare("SELECT coordinates FROM room_maps WHERE room_type = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$this->roomType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['coordinates'])) {
                $coordinates = json_decode($result['coordinates'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($coordinates)) {
                    return $coordinates;
                }
            }
        } catch (Exception $e) {
            error_log("Error loading room coordinates: " . $e->getMessage());
        }
        
        return [];
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