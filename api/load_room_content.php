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

$roomNumber = $_GET['room_number'] ?? 'A';
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
    $coords = $cd['coordinates'] ?? [];

    ob_start(); ?>
    <div id="modalRoomPage" class="modal-room-page" data-room="<?php echo $roomNumber; ?>">
      <div class="room-modal-iframe-container">
        <div class="room-overlay-wrapper room-modal-content-wrapper">
          <!-- Room content loaded -->
          <?php foreach ($items as $i => $it):
            $idx = $i + 1;
            $c = $coords[$i] ?? ['top'=>0,'left'=>0,'width'=>80,'height'=>80];
          ?>
            <div class="room-product-icon area-<?php echo $idx; ?>"
                 style="position: absolute !important; top: <?php echo $c['top']; ?>px !important; left: <?php echo $c['left']; ?>px !important; width: <?php echo $c['width']; ?>px !important; height: <?php echo $c['height']; ?>px !important;"
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
        // Room coordinate scaling for modal iframe
        document.addEventListener('DOMContentLoaded', function() {
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
                        roomWrapper.style.backgroundImage = `url('${imageUrl}')`;
                        roomWrapper.style.backgroundSize = 'cover';
                        roomWrapper.style.backgroundPosition = 'center';
                        roomWrapper.style.backgroundRepeat = 'no-repeat';

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
                        // Store original coordinates from inline styles
                        originalTop = parseFloat(icon.style.top) || 0;
                        originalLeft = parseFloat(icon.style.left) || 0;
                        originalWidth = parseFloat(icon.style.width) || 80;
                        originalHeight = parseFloat(icon.style.height) || 80;



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
        });
      </script>
    </div>
    <?php return ob_get_clean();
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
            return ['coordinates' => json_decode($row['coordinates'], true)];
        }
    } catch (Exception $e) {
        error_log('coords error: ' . $e->getMessage());
    }
    return ['coordinates' => []];
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
