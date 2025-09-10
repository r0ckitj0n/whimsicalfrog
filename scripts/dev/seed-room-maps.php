<?php
// scripts/dev/seed-room-maps.php
// Seeds room_maps with the canonical "Original" coordinates for rooms 0..5 and sets them active.

declare(strict_types=1);
require_once __DIR__ . '/../../api/config.php';

function upsert_map(string $roomNumber, array $coords, string $name = 'Original'): void {
    // Ensure only one active map: deactivate others, then insert/update desired map and activate it.
    $exists = Database::queryOne(
        "SELECT id FROM room_maps WHERE room_number = ? AND map_name = ? LIMIT 1",
        [$roomNumber, $name]
    );
    $json = json_encode($coords, JSON_UNESCAPED_SLASHES);
    if ($exists) {
        $id = (int)$exists['id'];
        Database::execute("UPDATE room_maps SET coordinates = ?, updated_at = NOW() WHERE id = ?", [$json, $id]);
        // Make it active and others inactive
        Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = ? AND id <> ?", [$roomNumber, $id]);
        Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$id]);
        echo "Updated map $name for room $roomNumber (id=$id) and set active\n";
    } else {
        Database::execute(
            "INSERT INTO room_maps (room_number, map_name, coordinates, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())",
            [$roomNumber, $name, $json]
        );
        $id = (int)Database::lastInsertId();
        Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = ? AND id <> ?", [$roomNumber, $id]);
        echo "Inserted map $name for room $roomNumber (id=$id) and set active\n";
    }
}

try {
    Database::getInstance();

    // Room A (landing) -> treat as room_number 'A' if used; otherwise skip or use 0
    $roomA = [
        [ 'selector' => '.area-1', 'top' => 411, 'left' => 601, 'width' => 125, 'height' => 77 ],
    ];
    // Main room (room 0)
    $room0 = [
        [ 'selector' => '.area-1', 'top' => 243, 'left' => 30,  'width' => 234, 'height' => 233 ],
        [ 'selector' => '.area-2', 'top' => 403, 'left' => 390, 'width' => 202, 'height' => 241 ],
        [ 'selector' => '.area-3', 'top' => 271, 'left' => 753, 'width' => 170, 'height' => 235 ],
        [ 'selector' => '.area-4', 'top' => 291, 'left' => 1001,'width' => 197, 'height' => 255 ],
        [ 'selector' => '.area-5', 'top' => 157, 'left' => 486, 'width' => 190, 'height' => 230 ],
    ];
    // Room 4
    $room4 = [
        [ 'selector' => '.area-1', 'top' => 235, 'left' => 193, 'width' => 115, 'height' => 77 ],
        [ 'selector' => '.area-2', 'top' => 235, 'left' => 378, 'width' => 67,  'height' => 114 ],
        [ 'selector' => '.area-3', 'top' => 205, 'left' => 499, 'width' => 103, 'height' => 81 ],
        [ 'selector' => '.area-4', 'top' => 399, 'left' => 242, 'width' => 68,  'height' => 97 ],
        [ 'selector' => '.area-5', 'top' => 426, 'left' => 375, 'width' => 89,  'height' => 61 ],
        [ 'selector' => '.area-6', 'top' => 371, 'left' => 511, 'width' => 54,  'height' => 105 ],
        [ 'selector' => '.area-7', 'top' => 339, 'left' => 621, 'width' => 58,  'height' => 77 ],
        [ 'selector' => '.area-8', 'top' => 346, 'left' => 1051,'width' => 90,  'height' => 73 ],
    ];
    // Room 1
    $room1 = [
        [ 'selector' => '.area-1', 'top' => 332, 'left' => 104, 'width' => 121, 'height' => 137 ],
        [ 'selector' => '.area-2', 'top' => 345, 'left' => 289, 'width' => 92,  'height' => 122 ],
        [ 'selector' => '.area-3', 'top' => 347, 'left' => 385, 'width' => 83,  'height' => 122 ],
        [ 'selector' => '.area-4', 'top' => 344, 'left' => 474, 'width' => 90,  'height' => 125 ],
        [ 'selector' => '.area-5', 'top' => 345, 'left' => 569, 'width' => 83,  'height' => 124 ],
        [ 'selector' => '.area-6', 'top' => 466, 'left' => 911, 'width' => 96,  'height' => 133 ],
        [ 'selector' => '.area-7', 'top' => 469, 'left' => 1067,'width' => 107, 'height' => 149 ],
    ];
    // Room 2
    $room2 = [
        [ 'selector' => '.area-1', 'top' => 176, 'left' => 447, 'width' => 74,  'height' => 146 ],
        [ 'selector' => '.area-2', 'top' => 170, 'left' => 543, 'width' => 74,  'height' => 144 ],
        [ 'selector' => '.area-3', 'top' => 162, 'left' => 634, 'width' => 76,  'height' => 148 ],
        [ 'selector' => '.area-4', 'top' => 355, 'left' => 241, 'width' => 82,  'height' => 175 ],
        [ 'selector' => '.area-5', 'top' => 352, 'left' => 333, 'width' => 86,  'height' => 164 ],
        [ 'selector' => '.area-6', 'top' => 352, 'left' => 426, 'width' => 77,  'height' => 156 ],
        [ 'selector' => '.area-7', 'top' => 355, 'left' => 508, 'width' => 68,  'height' => 143 ],
        [ 'selector' => '.area-8', 'top' => 348, 'left' => 611, 'width' => 70,  'height' => 138 ],
        [ 'selector' => '.area-9', 'top' => 345, 'left' => 691, 'width' => 64,  'height' => 126 ],
        [ 'selector' => '.area-10','top' => 572, 'left' => 241, 'width' => 83,  'height' => 162 ],
        [ 'selector' => '.area-11','top' => 564, 'left' => 333, 'width' => 79,  'height' => 154 ],
        [ 'selector' => '.area-12','top' => 546, 'left' => 420, 'width' => 74,  'height' => 153 ],
        [ 'selector' => '.area-13','top' => 533, 'left' => 502, 'width' => 64,  'height' => 143 ],
        [ 'selector' => '.area-14','top' => 523, 'left' => 575, 'width' => 64,  'height' => 139 ],
        [ 'selector' => '.area-15','top' => 511, 'left' => 647, 'width' => 64,  'height' => 127 ],
    ];
    // Room 3
    $room3 = [
        [ 'selector' => '.area-1', 'top' => 242, 'left' => 261, 'width' => 108, 'height' => 47 ],
        [ 'selector' => '.area-2', 'top' => 241, 'left' => 375, 'width' => 89,  'height' => 48 ],
        [ 'selector' => '.area-3', 'top' => 258, 'left' => 486, 'width' => 65,  'height' => 38 ],
        [ 'selector' => '.area-4', 'top' => 303, 'left' => 184, 'width' => 102, 'height' => 60 ],
        [ 'selector' => '.area-5', 'top' => 306, 'left' => 293, 'width' => 110, 'height' => 57 ],
        [ 'selector' => '.area-6', 'top' => 309, 'left' => 409, 'width' => 160, 'height' => 53 ],
        [ 'selector' => '.area-7', 'top' => 385, 'left' => 203, 'width' => 137, 'height' => 54 ],
        [ 'selector' => '.area-8', 'top' => 388, 'left' => 346, 'width' => 111, 'height' => 42 ],
        [ 'selector' => '.area-9', 'top' => 388, 'left' => 461, 'width' => 105, 'height' => 39 ],
        [ 'selector' => '.area-10','top' => 300, 'left' => 855, 'width' => 124, 'height' => 35 ],
        [ 'selector' => '.area-11','top' => 289, 'left' => 990, 'width' => 173, 'height' => 42 ],
        [ 'selector' => '.area-12','top' => 364, 'left' => 842, 'width' => 140, 'height' => 85 ],
        [ 'selector' => '.area-13','top' => 367, 'left' => 990, 'width' => 170, 'height' => 91 ],
    ];
    // Room 5
    $room5 = [
        [ 'selector' => '.area-1', 'top' => 215, 'left' => 238, 'width' => 213, 'height' => 317 ],
        [ 'selector' => '.area-2', 'top' => 235, 'left' => 550, 'width' => 148, 'height' => 265 ],
        [ 'selector' => '.area-3', 'top' => 567, 'left' => 1109, 'width' => 43,  'height' => 44 ],
        [ 'selector' => '.area-4', 'top' => 276, 'left' => 1026, 'width' => 189, 'height' => 198 ],
    ];

    // Apply all
    upsert_map('0', $room0, 'Original');
    upsert_map('1', $room1, 'Original');
    upsert_map('2', $room2, 'Original');
    upsert_map('3', $room3, 'Original');
    upsert_map('4', $room4, 'Original');
    upsert_map('5', $room5, 'Original');

    // Optionally seed landing (use room_number 'A' if you maintain A/B letters for landing)
    // If you want landing mapped under '0' only, skip this.
    // upsert_map('A', $roomA, 'Original');

    echo "Seeding complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Error: ".$e->getMessage()."\n");
    exit(1);
}
