<?php
/**
 * Load Room Content API
 *
 * Returns JSON with HTML content and metadata for room modals.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Only GET allowed']);
    exit;
}

$roomNumber = $_GET['room'] ?? $_GET['room_number'] ?? 'A';
$isModal = isset($_GET['modal']);

if (!isValidRoom($roomNumber)) {
    echo json_encode(['success' => false, 'message' => 'Invalid room']);
    exit;
}

try {
    $pdo = Database::getInstance();
    $content = generateRoomContent($roomNumber, $pdo, $isModal);
    $metadata = getRoomMetadata($roomNumber, $pdo);

    echo json_encode([
        'success' => true,
        'content' => $content,
        'room_number' => $roomNumber,
        'metadata' => $metadata,
        'is_modal' => $isModal
    ]);
} catch (Exception $e) {
    error_log('load_room_content error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Generate room content HTML for modal or inline view
 */
function generateRoomContent($roomNumber, $pdo, $isModal = false)
{
    $roomType = "room{$roomNumber}";

    // fetch category name from room_settings
    $meta = getRoomMetadata($roomNumber, $pdo);
    $categoryName = $meta['category'];
    // fetch category info by name

    // fetch category info by name
    $stmt = $pdo->prepare("SELECT id, description FROM categories WHERE name = ? LIMIT 1");
    $stmt->execute([$categoryName]);
    $catInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $catId = $catInfo['id'] ?? null;
    

    $items = [];
    if ($catId) {
        $stmt = $pdo->prepare(
            "SELECT i.*,
                    i.stockLevel,
                    i.retailPrice,
                    COALESCE(img.image_path, i.imageUrl) as image_path,
                    img.is_primary,
                    img.alt_text
             FROM items i
             LEFT JOIN item_images img ON img.sku = i.sku AND img.is_primary = 1
             WHERE i.category = ? ORDER BY i.sku ASC"
        );
        $stmt->execute([$meta['category']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // fetch room settings
    $stmt = $pdo->prepare("SELECT room_name, description FROM room_settings WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // load coordinates
    $cd = loadRoomCoordinates($roomType, $pdo);
    $coordsRaw = $cd['coordinates'] ?? [];
    // Normalize coordinates to a zero-based numeric array in original order
    $coords = array_values($coordsRaw);

    ob_start(); ?>
    <div id="modalRoomPage" class="modal-room-page" data-room="<?php echo $roomNumber; ?>">
      
      <!-- Modal Header with Title and Description -->
      <?php if (!empty($rs['room_name']) || !empty($rs['description'])): ?>
      <div class="room-title-overlay" style="display:none!important;">
        <?php if (!empty($rs['room_name'])): ?>
          <h3><?php echo htmlspecialchars($rs['room_name']); ?></h3>
        <?php endif; ?>
        <?php if (!empty($rs['description'])): ?>
          <p><?php echo htmlspecialchars($rs['description']); ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      
      <div class="room-modal-iframe-container">
        <div class="room-overlay-wrapper room-modal-content-wrapper">
          <!-- Room content loaded -->
          
          <?php 
          // Debug output for development
          $itemCount = count($items);
          $coordCount = count($coords);
          echo "<!-- DEBUG: Room $roomNumber - Category: '$categoryName' - Items: $itemCount - Coords: $coordCount -->\n";
          
          if (empty($items)) {
              echo "<!-- DEBUG: No items found for category '$categoryName' in room $roomNumber -->\n";
          }
          ?>
          
          <?php foreach ($items as $i => $it):
            $idx = $i + 1;
            $coordIdx = $coordCount ? ($i % $coordCount) : 0;
            $cRaw = $coords[$coordIdx] ?? [];
            // Allow coordinate arrays to be either associative or numeric index based
            if (isset($cRaw[0], $cRaw[1])) {
                // numeric indexed array [top,left,width,height]
                $cRaw = [
                    'top' => $cRaw[0],
                    'left'=> $cRaw[1],
                    'width'=> $cRaw[2] ?? 80,
                    'height'=> $cRaw[3] ?? 80,
                ];
            }
            $top = $cRaw['top'] ?? $cRaw['Top'] ?? $cRaw['y'] ?? $cRaw['Y'] ?? 0;
            $left = $cRaw['left'] ?? $cRaw['Left'] ?? $cRaw['x'] ?? $cRaw['X'] ?? 0;
            $width = $cRaw['width'] ?? $cRaw['Width'] ?? $cRaw['w'] ?? $cRaw['W'] ?? 80;
            $height = $cRaw['height'] ?? $cRaw['Height'] ?? $cRaw['h'] ?? $cRaw['H'] ?? 80;
            $c = ['top'=>$top,'left'=>$left,'width'=>$width,'height'=>$height];
          ?>
            <div class="room-product-icon positioned area-<?php echo $idx; ?>"
                 data-sku="<?php echo htmlspecialchars($it['sku']); ?>"
                 data-name="<?php echo htmlspecialchars($it['name']); ?>"
                 data-price="<?php echo htmlspecialchars($it['retailPrice'] ?? $it['price'] ?? '0', ENT_QUOTES); ?>"
                 data-stock-level="<?php echo htmlspecialchars($it['stockLevel'] ?? $it['stock_level'] ?? '0', ENT_QUOTES); ?>"
                 data-category="<?php echo htmlspecialchars($it['category'] ?? ''); ?>"
                 data-image="<?php echo getImageUrl($it['image_path'],'items','png'); ?>"
                 data-description="<?php echo htmlspecialchars($it['description'] ?? '', ENT_QUOTES); ?>"
                 data-marketing-label="<?php echo htmlspecialchars($it['marketingLabel'] ?? '', ENT_QUOTES); ?>"
                  data-original-top="<?php echo $c['top']; ?>"
                  data-original-left="<?php echo $c['left']; ?>"
                  data-original-width="<?php echo $c['width']; ?>"
                  data-original-height="<?php echo $c['height']; ?>"
                 style="position: absolute; top: <?php echo $c['top']; ?>px; left: <?php echo $c['left']; ?>px; width: <?php echo $c['width']; ?>px; height: <?php echo $c['height']; ?>px; --icon-top: <?php echo $c['top']; ?>px; --icon-left: <?php echo $c['left']; ?>px; --icon-width: <?php echo $c['width']; ?>px; --icon-height: <?php echo $c['height']; ?>px; background: rgba(255, 255, 255, 0.9); border: 2px solid rgba(255, 255, 255, 0.3); border-radius: 8px; backdrop-filter: blur(2px); box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);"
                 data-debug-position="top:<?php echo $c['top']; ?> left:<?php echo $c['left']; ?> w:<?php echo $c['width']; ?> h:<?php echo $c['height']; ?>">
              <picture>
                <source srcset="<?php echo getImageUrl($it['image_path'],'items'); ?>" type="image/webp">
                <img src="<?php echo getImageUrl($it['image_path'],'items','png'); ?>"
                     alt="<?php echo htmlspecialchars($it['name'] ?? ''); ?>"
                     class="room-product-icon-img">
              </picture>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <script>
        // Set global room type variables for coordinate manager
        const roomNumber = document.getElementById('modalRoomPage')?.getAttribute('data-room');
        if (roomNumber) {
            window.ROOM_TYPE = `room${roomNumber}`;
            window.roomType = `room${roomNumber}`;
            window.roomNumber = roomNumber;
            console.log(`[Room Modal] Set global room variables: ROOM_TYPE=${window.ROOM_TYPE}, roomNumber=${window.roomNumber}`);
        }
        
        // Room coordinate scaling for modal iframe
        // Use immediate execution since DOMContentLoaded has already fired in modal context
        (function initializeModalRoom() {
            const originalImageWidth = 1280;
            const originalImageHeight = 896;



            // Set background image for room wrapper
            async function loadRoomBackground() {
                const roomWrapper = document.querySelector('.room-overlay-wrapper');
                const modalPage = document.getElementById('modalRoomPage');
                if (!roomWrapper || !modalPage) return;

                const roomNumber = modalPage.getAttribute('data-room');
                const roomType = `room${roomNumber}`;

                try {
                    const response = await fetch(`/api/get_background.php?room_type=${roomType}`);
                    const data = await response.json();

                    if (data.success && data.background) {
                        const bg = data.background;
                        const supportsWebP = document.documentElement.classList.contains('webp');
                        let filename = supportsWebP && bg.webp_filename ? bg.webp_filename : bg.image_filename;

                        // Ensure filename includes backgrounds/ prefix
                        if (!filename.startsWith('backgrounds/')) {
                            filename = `backgrounds/${filename}`;
                        }

                        const imageUrl = `/images/${filename}`;
                        roomWrapper.style.setProperty('--room-bg-image', `url('${imageUrl}')`);

                        console.log(`[Room Modal Iframe] Background loaded: ${imageUrl}`);
                    }
                } catch (err) {
                    console.error('[Room Modal Iframe] Error loading background:', err);
                }
            }

            function scaleRoomCoordinates() {
                const roomWrapper = document.querySelector('.room-overlay-wrapper');
                if (!roomWrapper) return;

                const wrapperRect = roomWrapper.getBoundingClientRect();
                if (wrapperRect.width === 0 || wrapperRect.height === 0) return;

                // Calculate scale factor using CSS "background-size: cover" algorithm
                const scaleX = wrapperRect.width / originalImageWidth;
                const scaleY = wrapperRect.height / originalImageHeight;
                const scale = Math.max(scaleX, scaleY); // Use larger scale for "cover" behavior

                // Calculate offset for "background-position: center center"
                const scaledImageWidth = originalImageWidth * scale;
                const scaledImageHeight = originalImageHeight * scale;
                const offsetX = (wrapperRect.width - scaledImageWidth) / 2;
                const offsetY = (wrapperRect.height - scaledImageHeight) / 2;

                // Get all product icons and scale their coordinates
                const productIcons = document.querySelectorAll('.room-product-icon');
                productIcons.forEach(icon => {
                    // Get original coordinates from data attributes if available, otherwise from inline styles
                    let originalTop, originalLeft, originalWidth, originalHeight;

                    if (icon.dataset.originalTop) {
                        // Use stored original coordinates
                        originalTop = parseFloat(icon.dataset.originalTop);
                        originalLeft = parseFloat(icon.dataset.originalLeft);
                        originalWidth = parseFloat(icon.dataset.originalWidth);
                        originalHeight = parseFloat(icon.dataset.originalHeight);
                    } else {
                        // Read original coordinates from CSS custom properties
                        const computedStyle = getComputedStyle(icon);
                        originalTop = parseFloat(computedStyle.getPropertyValue('--icon-top')) || 0;
                        originalLeft = parseFloat(computedStyle.getPropertyValue('--icon-left')) || 0;
                        originalWidth = parseFloat(computedStyle.getPropertyValue('--icon-width')) || 80;
                        originalHeight = parseFloat(computedStyle.getPropertyValue('--icon-height')) || 80;

                        console.log(`[Room Modal] Reading coordinates for icon: top=${originalTop}, left=${originalLeft}, w=${originalWidth}, h=${originalHeight}`);

                        // Store for future scaling operations
                        icon.dataset.originalTop = originalTop;
                        icon.dataset.originalLeft = originalLeft;
                        icon.dataset.originalWidth = originalWidth;
                        icon.dataset.originalHeight = originalHeight;
                    }

                    // Apply scaling
                    const scaledTop = Math.round((originalTop * scale) + offsetY);
                    const scaledLeft = Math.round((originalLeft * scale) + offsetX);
                    const scaledWidth = Math.round(originalWidth * scale);
                    const scaledHeight = Math.round(originalHeight * scale);

                    // Apply scaled coordinates with !important to maintain CSS specificity
                    icon.style.setProperty('top', scaledTop + 'px', 'important');
                    icon.style.setProperty('left', scaledLeft + 'px', 'important');
                    icon.style.setProperty('width', scaledWidth + 'px', 'important');
                    icon.style.setProperty('height', scaledHeight + 'px', 'important');
                });

                console.log(`[Room Modal] Scaled ${productIcons.length} product icons with scale factor: ${scale.toFixed(3)}`);
                console.log(`[Room Modal] Wrapper dimensions: ${wrapperRect.width}x${wrapperRect.height}`);
            }



            // Load background and then scale coordinates
            loadRoomBackground().then(() => {
                // Wait for content to be fully loaded and sized
                setTimeout(() => {
                    scaleRoomCoordinates();
                    // Double-check scaling after a bit more time
                    setTimeout(scaleRoomCoordinates, 500);
                }, 300);
            });

            // Re-scale on window resize
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(scaleRoomCoordinates, 100);
            });
        })(); // End immediate execution
      </script>
    </div>
    <?php return ob_get_clean();
}

/**
 * Get default room coordinates when database has no data
 */
function getDefaultRoomCoordinates($roomType)
{
    $defaultCoords = [
        'room1' => [ // T-Shirts & Apparel - spread across room
            ['top' => 200, 'left' => 150, 'width' => 80, 'height' => 80],
            ['top' => 350, 'left' => 300, 'width' => 80, 'height' => 80],
            ['top' => 180, 'left' => 450, 'width' => 80, 'height' => 80],
            ['top' => 400, 'left' => 600, 'width' => 80, 'height' => 80]
        ],
        'room2' => [ // Tumblers & Drinkware - positioned near kitchen area
            ['top' => 250, 'left' => 200, 'width' => 80, 'height' => 80],
            ['top' => 180, 'left' => 350, 'width' => 80, 'height' => 80],
            ['top' => 320, 'left' => 480, 'width' => 80, 'height' => 80],
            ['top' => 280, 'left' => 600, 'width' => 80, 'height' => 80]
        ],
        'room3' => [ // Custom Artwork - wall positions
            ['top' => 100, 'left' => 200, 'width' => 100, 'height' => 80],
            ['top' => 150, 'left' => 400, 'width' => 100, 'height' => 80],
            ['top' => 200, 'left' => 600, 'width' => 100, 'height' => 80]
        ],
        'room4' => [ // Sublimation Items - center table area
            ['top' => 300, 'left' => 400, 'width' => 80, 'height' => 80]
        ],
        'room5' => [ // Window Wraps - near window
            ['top' => 242, 'left' => 261, 'width' => 108, 'height' => 47]
        ]
    ];
    
    return $defaultCoords[$roomType] ?? [];
}

/**
 * Load room coordinates from room_maps table
 */
function loadRoomCoordinates($roomType, $pdo)
{
    try {
        $stmt = $pdo->prepare(
            "SELECT coordinates FROM room_maps WHERE room_type = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1"
        );
        $stmt->execute([$roomType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $coords = json_decode($row['coordinates'], true);
            // Validate that coordinates look plausible (non-zero). If not, fall back to defaults.
            if (!empty($coords)) {
                // assume first coord exists
                $first = $coords[0];
                if ((int)($first['top'] ?? 0) !== 0 || (int)($first['left'] ?? 0) !== 0) {
                    return ['coordinates' => $coords];
                }
            }
            // otherwise continue to default
            // do not return invalid coordinates; fallback to default below
        }
    } catch (Exception $e) {
        error_log('coords error: ' . $e->getMessage());
    }
    
    // Fallback coordinates when database has no data
    return ['coordinates' => getDefaultRoomCoordinates($roomType)];
}

/**
 * Get room metadata for modal
 */
function getRoomMetadata($roomNumber, $pdo)
{
    // Get room settings
    $stmt = $pdo->prepare("SELECT room_name, description FROM room_settings WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Get primary category for this room
    $stmt = $pdo->prepare("
        SELECT c.name as category_name
        FROM room_category_assignments rca
        JOIN categories c ON rca.category_id = c.id
        WHERE rca.room_number = ? AND rca.is_primary = 1
        LIMIT 1
    ");
    $stmt->execute([$roomNumber]);
    $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);
    $categoryName = $categoryData ? $categoryData['category_name'] : '';

    return [
        'room_number' => $roomNumber,
        'room_name'   => $rs['room_name'] ?? '',
        'description' => $rs['description'] ?? '',
        'category'    => $categoryName,
        'room_type'   => "room{$roomNumber}"
    ];
}

/**
 * Build image URL with fallback extension
 */
function getImageUrl($path, $dir, $ext = 'webp')
{
    if (empty($path)) {
        return '';
    }

    // Clean the path and remove leading slash
    $cleanPath = ltrim($path, '/');

    // If path already contains the full images directory structure, use it as-is
    if (strpos($cleanPath, 'images/' . $dir . '/') === 0) {
        $extension = ($ext === 'png') ? 'png' : 'webp';
        $fileName = preg_replace('/\.[^\.]+$/', '.' . $extension, $cleanPath);
        return '/' . $fileName;
    }

    // Otherwise, build the full path
    $extension = ($ext === 'png') ? 'png' : 'webp';
    $fileName = preg_replace('/\.[^\.]+$/', '.' . $extension, $cleanPath);
    $imageDir = rtrim('/images/' . trim($dir, '/'), '/');
    return $imageDir . '/' . $fileName;
}
?>
