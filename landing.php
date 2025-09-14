<?php
/**
 * Landing page section.
 * This file now only contains the HTML structure and fetches the coordinates.
 * All JavaScript logic has been moved to js/landing-page.js.
 */

// Fetch coordinates from the database (room_maps now uses room_number, not room_type)
try {
    $pdo = Database::getInstance();
    $landingCoordsJson = '[]';
    $candidates = ['A', 'landing', '0']; // Prefer 'A' if present, then 'landing', then fallback to room 0

    // 1) Try active maps in priority order
    foreach ($candidates as $rn) {
        $stmt = $pdo->prepare("SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$rn]);
        $map = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($map && isset($map['coordinates']) && $map['coordinates'] !== '' && $map['coordinates'] !== null) {
            $landingCoordsJson = $map['coordinates'];
            break;
        }
    }

    // 2) If none active, fallback to the most recent for those room_numbers
    if ($landingCoordsJson === '[]') {
        foreach ($candidates as $rn) {
            $stmt = $pdo->prepare("SELECT coordinates FROM room_maps WHERE room_number = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
            $stmt->execute([$rn]);
            $map = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($map && isset($map['coordinates']) && $map['coordinates'] !== '' && $map['coordinates'] !== null) {
                $landingCoordsJson = $map['coordinates'];
                break;
            }
        }
    }
} catch (Exception $e) {
    error_log('Error fetching landing page coordinates: ' . $e->getMessage());
    $landingCoordsJson = '[]';
}
?>

<section id="landingPage" class="relative" data-coords='<?php echo htmlspecialchars($landingCoordsJson, ENT_NOQUOTES, 'UTF-8'); ?>'>
    <a href="/room_main" class="clickable-area area-1" title="Enter the Main Room">
        <picture>
            <source srcset="images/signs/sign_welcome.webp" type="image/webp">
            <source srcset="images/signs/sign_welcome.png" type="image/png">
            <img src="images/signs/sign_welcome.png" alt="Welcome - Click to Enter">
        </picture>
    </a>
</section>

