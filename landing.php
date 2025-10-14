<?php
/**
 * Landing page section.
 * This file now only contains the HTML structure and fetches the coordinates.
 * All JavaScript logic has been moved to js/landing-page.js.
 */

// Include database connection
require_once __DIR__ . '/includes/database.php';

// Fetch coordinates from the database (room_maps now uses room_number, not room_type)
try {
    $pdo = Database::getInstance();
    $landingCoordsJson = '[]';
    $candidates = ['A', 'landing']; // Landing should only source from these, not main room '0'

    $rawJson = null;
    // 1) Try active maps in priority order
    foreach ($candidates as $rn) {
        $stmt = $pdo->prepare("SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$rn]);
        $map = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($map && !empty($map['coordinates'])) {
            $rawJson = $map['coordinates'];
            break;
        }
    }

    // 2) If none active, fallback to the most recent for those room_numbers
    if ($rawJson === null) {
        foreach ($candidates as $rn) {
            $stmt = $pdo->prepare("SELECT coordinates FROM room_maps WHERE room_number = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
            $stmt->execute([$rn]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($map && !empty($map['coordinates'])) {
                $rawJson = $map['coordinates'];
                break;
            }
        }
    }

    // 3) Parse and filter to only .area-1 for landing (ignore other areas if present)
    if ($rawJson !== null) {
        $arr = json_decode($rawJson, true);
        if (is_array($arr)) {
            $filtered = array_values(array_filter($arr, function ($a) {
                if (!is_array($a) || !isset($a['selector'])) {
                    return false;
                }
                $sel = (string)$a['selector'];
                return $sel === '.area-1' || $sel === 'area-1';
            }));
            if (!empty($filtered)) {
                $landingCoordsJson = json_encode($filtered, JSON_UNESCAPED_SLASHES);
            }
        }
    }

    // 4) If still empty, provide the known-good fallback
    if ($landingCoordsJson === '[]') {
        $fallback = [[ 'selector' => '.area-1', 'top' => 411, 'left' => 601, 'width' => 125, 'height' => 77 ]];
        $landingCoordsJson = json_encode($fallback, JSON_UNESCAPED_SLASHES);
    }
} catch (Exception $e) {
    error_log('Error fetching landing page coordinates: ' . $e->getMessage());
    $landingCoordsJson = json_encode([[ 'selector' => '.area-1', 'top' => 411, 'left' => 601, 'width' => 125, 'height' => 77 ]], JSON_UNESCAPED_SLASHES);
}
?>

<section id="landingPage" class="relative landing-section" data-coords='<?php echo htmlspecialchars($landingCoordsJson, ENT_NOQUOTES, 'UTF-8'); ?>'>
    <div class="landing-content">
        <a href="/room_main" class="clickable-area area-1 landing-link" title="Enter the Main Room">
            <picture>
                <source srcset="images/signs/sign-welcome.webp" type="image/webp">
                <source srcset="images/signs/sign-welcome.png" type="image/png">
                <img src="images/signs/sign-welcome.png" alt="Welcome - Click to Enter" class="landing-sign">
            </picture>
        </a>
    </div>
</section>
