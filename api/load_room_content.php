<?php
/**
 * Load Room Content API
 *
 * Loads room content for display in modal overlay
 * Returns HTML content for rooms 2-6 based on room_template.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET requests allowed']);
    exit();
}

// Get room number from query parameters
$roomNumber = isset($_GET['room_number']) ? $_GET['room_number'] : 'A';
$isModal = isset($_GET['modal']) ? true : false;

// Load room helpers for dynamic validation
require_once __DIR__ . '/room_helpers.php';

// Validate room number dynamically
if (!isValidRoom($roomNumber)) {
    echo json_encode(['success' => false, 'message' => 'Invalid room number.']);
    exit();
}

try {
    // Get database connection
    $pdo = Database::getInstance();

    // Generate room content HTML
    $roomContent = generateRoomContent($roomNumber, $pdo, $isModal);

    // Also get room metadata for the modal
    $roomMetadata = getRoomMetadata($roomNumber, $pdo);

    echo json_encode([
        'success' => true,
        'content' => $roomContent,
        'room_number' => $roomNumber,
        'metadata' => $roomMetadata,
        'is_modal' => $isModal
    ]);

} catch (Exception $e) {
    error_log("Error loading room content: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load room content: ' . $e->getMessage()
    ]);
}

/**
 * Generate room content HTML
 */
function generateRoomContent($roomNumber, $pdo, $isModal = false)
{
    $roomType = "room{$roomNumber}";

    // Get room-specific items from categories
    $roomItems = [];
    $roomCategoryName = '';
    $roomSettings = null;
    $seoData = [];

    try {
        // Get the primary category for this room
        $stmt = $pdo->prepare("
            SELECT rca.*, c.name, c.description, c.id as category_id
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            WHERE rca.room_number = ? AND rca.is_primary = 1
            LIMIT 1
        ");
        $stmt->execute([$roomNumber]);
        $primaryCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($primaryCategory) {
            $roomCategoryName = $primaryCategory['name'];

            // Get items for this category
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       COALESCE(img.image_path, i.imageUrl) as image_path,
                       img.is_primary,
                       img.alt_text
                FROM items i
                LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
                WHERE i.category = ?
                ORDER BY i.sku ASC
            ");
            $stmt->execute([$roomCategoryName]);
            $roomItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get room settings
        $stmt = $pdo->prepare("SELECT * FROM room_settings WHERE room_number = ?");
        $stmt->execute([$roomNumber]);
        $roomSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        // Build SEO data
        $seoData = [
            'title' => $roomSettings['room_name'] ?? "Room {$roomNumber}",
            'description' => $roomSettings['description'] ?? '',
            'category' => $roomCategoryName
        ];

    } catch (Exception $e) {
        error_log("Error getting room data: " . $e->getMessage());
        // Use fallback data
        $seoData = [
            'title' => "Room {$roomNumber}",
            'description' => '',
            'category' => ''
        ];
    }

    // Load room coordinate data
    $coordinateData = loadRoomCoordinates($roomType, $pdo);

    // Generate the room HTML content
    ob_start();
    ?>
    
    <!- Room Content for Modal Display ->
    <div id="modalRoomPage" class="modal-room-page" data-room="<?php echo $roomNumber; ?>">
        <!- Room Background Container with Aspect Ratio ->
        <div class="room-modal-iframe-container room-bg-<?php echo $roomType; ?>">
            <!- Content wrapper for absolute positioning ->
            <div class="room-overlay-wrapper room-modal-content-wrapper">
                
                <!- Product Icons - Positioned Dynamically ->
                <?php if (!empty($roomItems) && !empty($coordinateData)): ?>
                    <?php foreach ($roomItems as $index => $item): ?>
                        <?php
                        $iconIndex = $index + 1;
                        $coordinate = $coordinateData['coordinates'][$index] ?? null;
                        ?>
                        
                        <?php if ($coordinate): ?>
                        <div id="item-icon-<?php echo $index; ?>" class="room-product-icon area-<?php echo $iconIndex; ?>" 
                             data-sku="<?php echo htmlspecialchars($item['sku']); ?>"
                             data-index="<?php echo $iconIndex; ?>"
                             data-name="<?php echo htmlspecialchars($item['name']); ?>"
                             data-price="<?php echo htmlspecialchars($item['retailPrice'] ?? '0'); ?>"
                             data-description="<?php echo htmlspecialchars($item['description'] ?? ''); ?>"
                             data-stock="<?php echo htmlspecialchars($item['stockLevel'] ?? '0'); ?>"
                             data-category="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
                            
                            <!- Product Image ->
                            <picture>
                                <source srcset="<?php echo getImageUrl($item['image_path'] ?? '', 'items'); ?>" type="image/webp">
                                <img src="<?php echo getImageUrl($item['image_path'] ?? '', 'items', 'png'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>" 
                                     class="room-product-icon-img">
                            </picture>
                            

                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    <div class="room-overlay-wrapper"></div>
    </div>

    <!-- Data script for room helper -->
    <?php
    $itemsJson = htmlspecialchars(json_encode($roomItems), ENT_QUOTES, 'UTF-8');
    $coordsJson = htmlspecialchars(json_encode($coordinateData['coordinates'] ?? []), ENT_QUOTES, 'UTF-8');
    ?>
    <script src="/js/room-helper.js"
            data-room-items='<?php echo $itemsJson; ?>'
            data-room-number="<?php echo htmlspecialchars($roomNumber, ENT_QUOTES, 'UTF-8'); ?>"
            data-room-type="<?php echo htmlspecialchars($roomType, ENT_QUOTES, 'UTF-8'); ?>"
            data-base-areas='<?php echo $coordsJson; ?>' defer></script>
    <script src="/js/room-coordinate-manager.js" defer></script>
    <!-- Attach hover/click listeners inside iframe -->
    <script src="/js/room/iframe-event-bridge.js" defer></script>
    
    <!- Link to Centralized CSS ->
    <link rel="stylesheet" href="/css/room-iframe.css">
    
    <script>
    // Debug logging for iframe content
    console.log('🚪 [DEBUG] Iframe content loaded for room <?php echo $roomNumber; ?>');
    console.log('🚪 [DEBUG] Room type: <?php echo $roomType; ?>');
    console.log('🚪 [DEBUG] Expected CSS class: room-bg-<?php echo $roomType; ?>');
    console.log('🚪 [DEBUG] Body dimensions:', document.body.offsetWidth, 'x', document.body.offsetHeight);
    console.log('🚪 [DEBUG] Window dimensions:', window.innerWidth, 'x', window.innerHeight);
    console.log('🚪 [DEBUG] Original room image dimensions: 1280x896 (aspect ratio: 1.43)');
    
    // Check WebP support
    const supportsWebP = document.documentElement.classList.contains('webp');
    console.log('🚪 [DEBUG] WebP support:', supportsWebP);
    
    // Check if room wrapper exists
    var roomWrapper = document.querySelector('.room-modal-iframe-container');
    console.log('🚪 [DEBUG] Room wrapper found:', roomWrapper ? 'YES' : 'NO');
    
    if (roomWrapper) {
        console.log('🚪 [DEBUG] Room wrapper dimensions:', roomWrapper.offsetWidth, 'x', roomWrapper.offsetHeight);
        console.log('🚪 [DEBUG] Room wrapper aspect ratio:', (roomWrapper.offsetWidth / roomWrapper.offsetHeight).toFixed(2));
        console.log('🚪 [DEBUG] Room wrapper classes:', roomWrapper.className);
        
        // Check computed styles
        const computedStyle = window.getComputedStyle(roomWrapper);
        console.log('🚪 [DEBUG] Background image:', computedStyle.backgroundImage);
        console.log('🚪 [DEBUG] Background size:', computedStyle.backgroundSize);
        console.log('🚪 [DEBUG] Background position:', computedStyle.backgroundPosition);
        console.log('🚪 [DEBUG] Background repeat:', computedStyle.backgroundRepeat);
        console.log('🚪 [DEBUG] Container computed dimensions:', computedStyle.width, 'x', computedStyle.height);
        console.log('🚪 [DEBUG] Container display:', computedStyle.display);
        
        // Calculate expected displayed image dimensions for background-size: contain
        const containerWidth = roomWrapper.offsetWidth;
        const containerHeight = roomWrapper.offsetHeight;
        const containerAspectRatio = containerWidth / containerHeight;
        const imageAspectRatio = 1280 / 896; // 1.43
        
        let displayedImageWidth, displayedImageHeight, imageOffsetX, imageOffsetY;
        
        if (containerAspectRatio > imageAspectRatio) {
            // Container is wider than image - image fills height, width is scaled
            displayedImageHeight = containerHeight;
            displayedImageWidth = containerHeight * imageAspectRatio;
            imageOffsetX = (containerWidth - displayedImageWidth) / 2;
            imageOffsetY = 0;
        } else {
            // Container is taller than image - image fills width, height is scaled
            displayedImageWidth = containerWidth;
            displayedImageHeight = containerWidth / imageAspectRatio;
            imageOffsetX = 0;
            imageOffsetY = (containerHeight - displayedImageHeight) / 2;
        }
        
        console.log('🚪 [DEBUG] Expected displayed image dimensions:', displayedImageWidth.toFixed(0), 'x', displayedImageHeight.toFixed(0));
        console.log('🚪 [DEBUG] Expected image offset:', imageOffsetX.toFixed(0), 'x', imageOffsetY.toFixed(0));
        console.log('🚪 [DEBUG] Scale factors:', (displayedImageWidth / 1280).toFixed(3), 'x', (displayedImageHeight / 896).toFixed(3));
        
        // Test if background image is loading
        if (computedStyle.backgroundImage && computedStyle.backgroundImage !== 'none') {
            console.log('🚪 [DEBUG] Background image URL detected, testing load...');
            
            // Extract URL from computed style
            const bgUrl = computedStyle.backgroundImage.match(/url\("?([^"]+)"?\)/);
            if (bgUrl) {
                const imageUrl = bgUrl[1];
                console.log('🚪 [DEBUG] Extracted image URL:', imageUrl);
                const testImg = new Image();
                testImg.onload = () => {
                    console.log('🚪 [DEBUG] ✅ Background image loaded successfully:', imageUrl);
                    console.log('🚪 [DEBUG] Actual image dimensions:', testImg.naturalWidth, 'x', testImg.naturalHeight);
                    console.log('🚪 [DEBUG] Actual image aspect ratio:', (testImg.naturalWidth / testImg.naturalHeight).toFixed(2));
                    
                    // Verify aspect ratio matches expected
                    const expectedAspectRatio = 1280 / 896;
                    const actualAspectRatio = testImg.naturalWidth / testImg.naturalHeight;
                    if (Math.abs(expectedAspectRatio - actualAspectRatio) > 0.01) {
                        console.warn('🚪 [DEBUG] ⚠️ Image aspect ratio mismatch! Expected:', expectedAspectRatio.toFixed(2), 'Got:', actualAspectRatio.toFixed(2));
                    }
                };
                testImg.onerror = () => {
                    console.error('🚪 [DEBUG] ❌ Background image failed to load:', imageUrl);
                    
                    // Try PNG fallback
                    const pngUrl = imageUrl.replace('.webp', '.png');
                    console.log('🚪 [DEBUG] Trying PNG fallback:', pngUrl);
                    const fallbackImg = new Image();
                    fallbackImg.onload = () => {
                        console.log('🚪 [DEBUG] ✅ PNG fallback loaded successfully:', pngUrl);
                        // Apply PNG fallback
                        roomWrapper.style.backgroundImage = `url("${pngUrl}")`;
                    };
                    fallbackImg.onerror = () => {
                        console.error('🚪 [DEBUG] ❌ PNG fallback also failed:', pngUrl);
                    };
                    fallbackImg.src = pngUrl;
                };
                testImg.src = imageUrl;
            }
        } else {
            console.warn('🚪 [DEBUG] ❌ No background image detected on container');
            console.log('🚪 [DEBUG] Checking for specific class: room-bg-<?php echo $roomType; ?>');
            
            // Check if the specific room background class exists
            const hasRoomBgClass = roomWrapper.classList.contains('room-bg-<?php echo $roomType; ?>');
            console.log('🚪 [DEBUG] Has room background class:', hasRoomBgClass);
        }
    }
    
    // Check title/description elements
    const titleOverlay = document.getElementById('roomTitleOverlay');
    const roomTitle = document.getElementById('roomTitle');
    const roomDescription = document.getElementById('roomDescription');
    
    console.log('🚪 [DEBUG] Title overlay found:', titleOverlay ? 'YES' : 'NO');
    console.log('🚪 [DEBUG] Room title found:', roomTitle ? 'YES' : 'NO');
    console.log('🚪 [DEBUG] Room description found:', roomDescription ? 'YES' : 'NO');
    
    if (roomTitle) {
        console.log('🚪 [DEBUG] Title content:', roomTitle.textContent);
    }
    if (roomDescription) {
        console.log('🚪 [DEBUG] Description content:', roomDescription.textContent);
    }
    
    // Count total elements
    const totalElements = document.querySelectorAll('*').length;
    console.log('🚪 [DEBUG] Total elements in iframe:', totalElements);
    
    // Check for product icons
    const productIcons = document.querySelectorAll('.room-product-icon');
    console.log('🚪 [DEBUG] Product icons found:', productIcons.length);
    
    productIcons.forEach((icon, index) => {
        console.log(`🚪 [DEBUG] Icon ${index}:`, {
            sku: icon.getAttribute('data-sku'),
            name: icon.getAttribute('data-name'),
            position: icon.style.cssText
        });
    });
    
    // Remove forced height settings to allow aspect ratio to work
    // Wait for content to load, then let aspect ratio handle sizing
    setTimeout(() => {
        const modalPage = document.querySelector('.modal-room-page');
        if (modalPage) {
            console.log('🚪 [DEBUG] Modal page found - aspect ratio should handle sizing');
        }
        
        const container = document.querySelector('.room-modal-iframe-container');
        if (container) {
            console.log('🚪 [DEBUG] Container found - aspect ratio should handle sizing');
        }
    }, 100);
    
    console.log('Room <?php echo $roomNumber; ?> content HTML generated with aspect ratio binding');
    
    // Popup functionality handled by parent window - no local popup needed
    
    function hidePopup() {
        // Popup handled by parent window - no local popup to hide
    }
    
    function addToCart(productData) {
        // Add to cart functionality
        console.log('Adding to cart:', productData);
        
        // Close popup
        hidePopup();
        
        // You can add your cart logic here
        if (typeof parent.window.addToCart === 'function') {
            parent.window.addToCart(productData);
        }
    }
    
    // Click outside to close
    document.addEventListener('click', function(e) {
        const popup = document.getElementById('itemPopup');
        if (e.target === popup) {
            hidePopup();
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * Load room coordinates from database
 */
function loadRoomCoordinates($roomType, $pdo)
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM room_maps 
            WHERE room_type = ? AND is_active = 1 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$roomType]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return [
                'coordinates' => json_decode($result['coordinates'], true),
                'originalImageWidth' => 1280,
                'originalImageHeight' => 896
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading room coordinates: " . $e->getMessage());
    }

    // Return empty coordinates if no data found
    return [
        'coordinates' => [],
        'originalImageWidth' => 1280,
        'originalImageHeight' => 896
    ];
}

/**
 * Get room metadata for modal display
 */
function getRoomMetadata($roomNumber, $pdo)
{
    try {
        // Get room settings
        $stmt = $pdo->prepare("SELECT * FROM room_settings WHERE room_number = ?");
        $stmt->execute([$roomNumber]);
        $roomSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get primary category
        $stmt = $pdo->prepare("
            SELECT c.name as category_name, c.description as category_description
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            WHERE rca.room_number = ? AND rca.is_primary = 1
            LIMIT 1
        ");
        $stmt->execute([$roomNumber]);
        $primaryCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'room_name' => $roomSettings['room_name'] ?? "Room {$roomNumber}",
            'description' => $roomSettings['description'] ?? '',
            'door_label' => $roomSettings['door_label'] ?? '',
            'category_name' => $primaryCategory['category_name'] ?? '',
            'category_description' => $primaryCategory['category_description'] ?? ''
        ];

    } catch (Exception $e) {
        error_log("Error getting room metadata: " . $e->getMessage());
        return [
            'room_name' => "Room {$roomNumber}",
            'description' => '',
            'door_label' => '',
            'category_name' => '',
            'category_description' => ''
        ];
    }
}

/**
 * Helper function to get image URL with fallback
 */
function getImageUrl($imagePath, $directory, $extension = 'webp')
{
    if (empty($imagePath)) {
        return "/images/{$directory}/placeholder.{$extension}";
    }

    // If path already includes directory, use as-is
    if (strpos($imagePath, "/{$directory}/") !== false) {
        return $imagePath;
    }

    // Add directory prefix
    return "/images/{$directory}/" . $imagePath;
}
?> 