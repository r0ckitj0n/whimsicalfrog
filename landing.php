<?php
/**
 * Landing page section.
 * This file now only contains the HTML structure and fetches the coordinates.
 * All JavaScript logic has been moved to js/landing-page.js.
 */

// Fetch coordinates from the database
try {
    $stmt = Database::getInstance()->prepare("SELECT coordinates FROM room_maps WHERE room_type = 'landing' AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $map = $stmt->fetch(PDO::FETCH_ASSOC);
    $landingCoordsJson = $map ? $map['coordinates'] : '[]';
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

