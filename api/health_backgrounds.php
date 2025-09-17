<?php
// api/health_backgrounds.php
// Reports missing backgrounds and missing background files for rooms 0..5

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    Database::getInstance();
    AuthHelper::requireAdmin();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

function bgFileExists(?string $filename): bool {
    if (!$filename) return false;
    $rel = '/images/backgrounds/' . ltrim($filename, '/');
    $abs = __DIR__ . '/..' . $rel;
    return is_file($abs);
}

try {
    $rooms = [0,1,2,3,4,5];
    $missingActive = [];
    $missingFiles = [];
    $details = [];

    foreach ($rooms as $rn) {
        $row = Database::queryOne("SELECT id, background_name, image_filename, webp_filename, is_active FROM backgrounds WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$rn]);
        if (!$row) {
            $missingActive[] = $rn;
            $details[] = [ 'room_number' => $rn, 'status' => 'no_active' ];
            continue;
        }
        $file = !empty($row['webp_filename']) ? $row['webp_filename'] : $row['image_filename'];
        if (!bgFileExists($file)) {
            $missingFiles[] = $rn;
            $details[] = [ 'room_number' => $rn, 'status' => 'active_missing_file', 'file' => $file ];
        } else {
            $details[] = [ 'room_number' => $rn, 'status' => 'ok', 'file' => $file ];
        }
    }

    Response::success([
        'missingActive' => $missingActive,
        'missingFiles' => $missingFiles,
        'details' => $details,
    ]);
} catch (Throwable $e) {
    Response::serverError('Health backgrounds failed', $e->getMessage());
}
