<?php
require_once __DIR__ . '/../api/config.php';

echo "Connecting to the database...\n";

try {
    $pdo = Database::getInstance();
    echo "Database connection successful.\n\n";

    $updates = [
        ['old_png' => 'home_background.png', 'new_png' => 'background_home.png', 'new_webp' => 'background_home.webp', 'page' => 'landing'],
        ['old_png' => 'room_main.png', 'new_png' => 'background_room_main.png', 'new_webp' => 'background_room_main.webp', 'page' => 'room_main']
    ];

    foreach ($updates as $update) {
        echo "Updating entry for page: {$update['page']}...\n";
        
        $stmt = $pdo->prepare(
            'UPDATE backgrounds SET image_filename = :new_png, webp_filename = :new_webp WHERE image_filename = :old_png'
        );
        
        $stmt->execute([
            ':new_png' => $update['new_png'],
            ':new_webp' => $update['new_webp'],
            ':old_png' => $update['old_png']
        ]);
        
        $rowCount = $stmt->rowCount();
        
        if ($rowCount > 0) {
            echo "Success: Updated $rowCount row(s) for {$update['page']}. ('{$update['old_png']}' -> '{$update['new_png']}').\n";
        } else {
            echo "Notice: No rows needed updating for {$update['page']} (already up-to-date or '{$update['old_png']}' not found).\n";
        }
        echo "---\n";
    }

    echo "\nScript finished successfully.\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("An unexpected error occurred: " . $e->getMessage() . "\n");
}
