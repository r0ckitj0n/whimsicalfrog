<?php

// scripts/dev/propose-fixes.php
// DRY-RUN: Propose fixes for backgrounds filenames and room_settings numbering.
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

function fileExistsRel($rel)
{
    $path = __DIR__ . '/../../' . ltrim($rel, '/');
    return file_exists($path);
}

try {
    require_once __DIR__ . '/../../api/config.php';
    $pdo = Database::getInstance(); // Keep for quote() when generating SQL strings

    $proposals = [
        'backgrounds' => [
            'mismatches' => [],
            'notes' => []
        ],
        'room_settings' => [
            'renumbers' => [],
            'notes' => []
        ],
    ];

    // 1) Backgrounds filename reconciliation
    $rows = Database::queryAll("SELECT id, room_type, background_name, image_filename, webp_filename, is_active FROM backgrounds ORDER BY room_type");

    foreach ($rows as $row) {
        $desiredPng = $row['image_filename'];
        $desiredWebp = $row['webp_filename'];
        $pngOk = fileExistsRel('images/backgrounds/' . $desiredPng);
        $webpOk = $desiredWebp ? fileExistsRel('images/backgrounds/' . $desiredWebp) : true;

        if (!$pngOk || !$webpOk) {
            // try canonical names
            $canonical = null;
            if ($row['room_type'] === 'landing') {
                $canonical = ['png' => 'background_home.png', 'webp' => 'background_home.webp'];
            } elseif ($row['room_type'] === 'room_main') {
                $canonical = ['png' => 'background_room_main.png', 'webp' => 'background_room_main.webp'];
            } elseif (preg_match('/^room(\d+)$/', $row['room_type'], $m)) {
                $n = $m[1];
                $canonical = ['png' => "background_room{$n}.png", 'webp' => "background_room{$n}.webp"];
            }
            if ($canonical) {
                $canonPngOk = fileExistsRel('images/backgrounds/' . $canonical['png']);
                $canonWebpOk = fileExistsRel('images/backgrounds/' . $canonical['webp']);
                $proposals['backgrounds']['mismatches'][] = [
                    'id' => $row['id'],
                    'room_type' => $row['room_type'],
                    'current' => ['png' => $row['image_filename'], 'webp' => $row['webp_filename']],
                    'exists' => ['png' => $pngOk, 'webp' => $webpOk],
                    'proposed' => ['png' => $canonical['png'], 'webp' => $canonical['webp']],
                    'proposed_exists' => ['png' => $canonPngOk, 'webp' => $canonWebpOk],
                    'sql' => sprintf(
                        "UPDATE backgrounds SET image_filename = %s, webp_filename = %s WHERE id = %d;",
                        $pdo->quote($canonical['png']),
                        $pdo->quote($canonical['webp']),
                        (int)$row['id']
                    )
                ];
            } else {
                $proposals['backgrounds']['notes'][] = 'No canonical mapping for ' . $row['room_type'];
            }
        }
    }

    // 2) room_settings numbering corrections (0->A, 1->B)
    $settings = Database::queryAll("SELECT id, room_number, room_name FROM room_settings ORDER BY display_order, id");
    foreach ($settings as $r) {
        $old = (string)$r['room_number'];
        $new = null;
        if ($old === '0') {
            $new = 'A';
        } elseif ($old === '1') {
            $new = 'B';
        } elseif (ctype_digit($old) && (int)$old >= 2) {
            $new = (string)((int)$old - 1);
        }
        if ($new !== null && $new !== $old) {
            $proposals['room_settings']['renumbers'][] = [
                'id' => $r['id'],
                'room_name' => $r['room_name'],
                'from' => $old,
                'to' => $new,
                'sql' => sprintf(
                    "UPDATE room_settings SET room_number = %s WHERE id = %d;",
                    $pdo->quote($new),
                    (int)$r['id']
                )
            ];
        }
    }

    echo json_encode(['ok' => true, 'proposals' => $proposals], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
