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
            "SELECT i.*, img.image_path, img.is_primary, img.alt_text
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
        <?php
            $bgFileName = "background_{$roomType}.webp";
            $bgPath = file_exists(__DIR__ . '/../images/backgrounds/' . $bgFileName)
                ? '/images/backgrounds/' . $bgFileName
                : '/images/backgrounds/background_home.webp';
      ?>
      <div class="room-modal-iframe-container" style="background-image: url('<?php echo htmlspecialchars($bgPath, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center;">
        <div class="room-overlay-wrapper room-modal-content-wrapper">
          <div id="debug-items-count">Items: <?php echo count($items); ?></div>
          <?php foreach ($items as $i => $it):
            $idx = $i + 1;
            $c = $coords[$i] ?? ['top'=>0,'left'=>0,'width'=>80,'height'=>80];
          ?>
            <div class="room-product-icon area-<?php echo $idx; ?>"
                 style="position:absolute; top:<?php echo $c['top']; ?>px;
                        left:<?php echo $c['left']; ?>px;
                        width:<?php echo $c['width']; ?>px;
                        height:<?php echo $c['height']; ?>px;">
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
    $stmt = $pdo->prepare("SELECT room_name, description FROM room_settings WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'room_number' => $roomNumber,
        'room_name'   => $rs['room_name'] ?? '',
        'description' => $rs['description'] ?? '',
        'category'    => $rs['room_name'] ?? ''
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
    // Build path within images directory
    $cleanPath = ltrim($path, '/');
    $extension = ($ext === 'png') ? 'png' : 'webp';
    $fileName = preg_replace('/\.[^\.]+$/', '.' . $extension, $cleanPath);
    $imageDir = rtrim('/images/' . trim($dir, '/'), '/');
    return $imageDir . '/' . $fileName;
}
?>
