<?php
/**
 * Rebuilt Load Room Content API
 *
 * Generates room content HTML for modal (rooms 1–5) and returns JSON.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';

// Headers
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

$roomNumber = isset($_GET['room_number']) ? $_GET['room_number'] : 'A';
$isModal = isset($_GET['modal']);

if (!isValidRoom($roomNumber)) {
    echo json_encode(['success' => false, 'message' => 'Invalid room']);
    exit;
}

try {
    $pdo = Database::getInstance();
    // Generate content and metadata
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
 * Generate room content HTML
 */
function generateRoomContent($roomNumber, $pdo, $isModal = false)
{
    $lookup = is_numeric($roomNumber) ? $roomNumber + 1 : $roomNumber;
    $roomType = "room{$lookup}";
    $items = [];
    $categoryName = '';

    // Primary category
    $stmt = $pdo->prepare(
        "SELECT rca.category_id, c.name, c.description
         FROM room_category_assignments rca
         JOIN categories c ON c.id = rca.category_id
         WHERE rca.room_number = ? AND rca.is_primary = 1 LIMIT 1"
    );
    $stmt->execute([$lookup]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $categoryName = $cat['name'];
        $stmt = $pdo->prepare(
            "SELECT i.*, img.image_path, img.is_primary, img.alt_text
             FROM items i
             LEFT JOIN item_images img ON img.sku=i.sku AND img.is_primary=1
             WHERE i.category_id=? ORDER BY i.sku ASC"
        );
        $stmt->execute([$cat['category_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Room settings
    $stmt = $pdo->prepare("SELECT room_name, description FROM room_settings WHERE room_number=?");
    $stmt->execute([$lookup]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC);

    // Coordinates
    $coordData = loadRoomCoordinates($roomType, $pdo);
    $coords = $coordData['coordinates'] ?? [];

    ob_start(); ?>
    <div id="modalRoomPage" class="modal-room-page" data-room="<?php echo $roomNumber; ?>">
      <div class="room-modal-iframe-container room-bg-<?php echo $roomType; ?>">
        <div class="room-overlay-wrapper room-modal-content-wrapper">
          <div id="debug-items-count">Items: <?php echo count($items); ?></div>
          <?php foreach ($items as $i => $it):
            $idx = $i+1;
            $c = $coords[$i] ?? ['top'=>0,'left'=>0,'width'=>80,'height'=>80];
          ?>
            <div class="room-product-icon area-<?php echo $idx; ?>"
                 style="position:absolute;top:<?php echo $c['top']; ?>px;left:<?php echo $c['left']; ?>px;width:<?php echo $c['width']; ?>px;height:<?php echo $c['height']; ?>px;">
              <picture>
                <source srcset="<?php echo getImageUrl($it['image_path'],'items'); ?>" type="image/webp">
                <img src="<?php echo getImageUrl($it['image_path'],'items','png'); ?>"
                     alt="<?php echo htmlspecialchars($it['name']); ?>" class="room-product-icon-img">
              </picture>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

/**
 * Load room coordinates from database
 */
function loadRoomCoordinates($roomType, $pdo)
{
    try {
        $stmt = $pdo->prepare("SELECT coordinates FROM room_maps WHERE room_type=? AND is_active=1 ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$roomType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['coordinates'=>json_decode($row['coordinates'],true)];
        }
    } catch (Exception $e) {
        error_log('coord error: '.$e->getMessage());
    }
    return ['coordinates'=>[]];
}

/**
 * Get room metadata for modal
 */
function getRoomMetadata($roomNumber, $pdo)
{
    $lookup = is_numeric($roomNumber)?$roomNumber+1:$roomNumber;
    // Settings
    $stmt = $pdo->prepare("SELECT room_name, description FROM room_settings WHERE room_number=?");
    $stmt->execute([$lookup]);
    $rs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    // Category
    $stmt = $pdo->prepare(
        "SELECT c.name,c.description FROM room_category_assignments rca JOIN categories c ON c.id=rca.category_id WHERE rca.room_number=? AND rca.is_primary=1 LIMIT 1"
    );
    $stmt->execute([$lookup]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'room_number'=>$roomNumber,
        'room_name'=>$rs['room_name'] ?? '',
        'description'=>$rs['description'] ?? '',
        'category'=>$cat['name'] ?? ''
    ];
}

/**
 * Helper for WebP/PNG fallback
 */
function getImageUrl($path, $dir, $ext='webp')
{
    if (!$path) return '';
    $base = rtrim(
        ($dir[0]=='/'?'':'/').$dir,'/'
    ).'/'.ltrim($path,'/');
    if ($ext==='png') {
        return preg_replace('/\.[^.]+$/','.png',$base);
    }
    return preg_replace('/\.[^.]+$/','.webp',$base);
}
?>
