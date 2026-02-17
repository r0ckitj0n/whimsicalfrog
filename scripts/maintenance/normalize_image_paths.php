<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/helpers/ImagePathNormalizer.php';

Database::getInstance();

$updates = [
    'backgrounds' => 0,
    'room_settings' => 0,
    'area_mappings' => 0,
    'shortcut_sign_assets' => 0,
    'item_images' => 0,
    'items' => 0,
];

Database::beginTransaction();
try {
    $backgroundRows = Database::queryAll('SELECT id, image_filename, png_filename, webp_filename FROM backgrounds');
    foreach ($backgroundRows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $image = ImagePathNormalizer::normalizeBackgroundDbRef((string) ($row['image_filename'] ?? ''));
        $png = ImagePathNormalizer::normalizeBackgroundDbRef((string) ($row['png_filename'] ?? ''));
        $webp = ImagePathNormalizer::normalizeBackgroundDbRef((string) ($row['webp_filename'] ?? ''));

        if ($image === (string) ($row['image_filename'] ?? '')
            && $png === (string) ($row['png_filename'] ?? '')
            && $webp === (string) ($row['webp_filename'] ?? '')
        ) {
            continue;
        }

        Database::execute(
            'UPDATE backgrounds SET image_filename = ?, png_filename = ?, webp_filename = ? WHERE id = ? LIMIT 1',
            [$image, $png !== '' ? $png : null, $webp !== '' ? $webp : null, $id]
        );
        $updates['backgrounds']++;
    }

    $roomRows = Database::queryAll('SELECT room_number, background_url FROM room_settings');
    foreach ($roomRows as $row) {
        $room = (string) ($row['room_number'] ?? '');
        $normalized = ImagePathNormalizer::normalizeBackgroundUrl((string) ($row['background_url'] ?? ''));
        if ($normalized === (string) ($row['background_url'] ?? '')) {
            continue;
        }
        Database::execute('UPDATE room_settings SET background_url = ? WHERE room_number = ? LIMIT 1', [$normalized, $room]);
        $updates['room_settings']++;
    }

    $mapRows = Database::queryAll('SELECT id, content_image, link_image FROM area_mappings');
    foreach ($mapRows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $content = (string) ($row['content_image'] ?? '');
        $link = (string) ($row['link_image'] ?? '');
        $newContent = $content !== '' ? ImagePathNormalizer::normalizeSignUrl($content) : '';
        $newLink = $link !== '' ? ImagePathNormalizer::normalizeSignUrl($link) : '';
        if ($newContent === $content && $newLink === $link) {
            continue;
        }
        Database::execute(
            'UPDATE area_mappings SET content_image = ?, link_image = ? WHERE id = ? LIMIT 1',
            [$newContent !== '' ? $newContent : null, $newLink !== '' ? $newLink : null, $id]
        );
        $updates['area_mappings']++;
    }

    $assetRows = Database::queryAll('SELECT id, image_url, png_url, webp_url FROM shortcut_sign_assets');
    foreach ($assetRows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $image = (string) ($row['image_url'] ?? '');
        $png = (string) ($row['png_url'] ?? '');
        $webp = (string) ($row['webp_url'] ?? '');
        $newImage = $image !== '' ? ImagePathNormalizer::normalizeSignUrl($image) : '';
        $newPng = $png !== '' ? ImagePathNormalizer::normalizeSignUrl($png) : '';
        $newWebp = $webp !== '' ? ImagePathNormalizer::normalizeSignUrl($webp) : '';
        if ($newImage === $image && $newPng === $png && $newWebp === $webp) {
            continue;
        }
        Database::execute(
            'UPDATE shortcut_sign_assets SET image_url = ?, png_url = ?, webp_url = ? WHERE id = ? LIMIT 1',
            [$newImage !== '' ? $newImage : null, $newPng !== '' ? $newPng : null, $newWebp !== '' ? $newWebp : null, $id]
        );
        $updates['shortcut_sign_assets']++;
    }

    $itemRows = Database::queryAll('SELECT id, image_path FROM item_images');
    foreach ($itemRows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $imagePath = (string) ($row['image_path'] ?? '');
        $normalized = ImagePathNormalizer::normalizeItemDbPath($imagePath);
        if ($normalized === $imagePath || $normalized === '') {
            continue;
        }
        Database::execute('UPDATE item_images SET image_path = ? WHERE id = ? LIMIT 1', [$normalized, $id]);
        $updates['item_images']++;
    }

    $itemUrlRows = Database::queryAll('SELECT sku, image_url FROM items WHERE image_url IS NOT NULL AND TRIM(image_url) <> ""');
    foreach ($itemUrlRows as $row) {
        $sku = (string) ($row['sku'] ?? '');
        $imageUrl = (string) ($row['image_url'] ?? '');
        $normalized = ImagePathNormalizer::normalizeItemDbPath($imageUrl);
        if ($normalized === '' || $normalized === $imageUrl) {
            continue;
        }
        Database::execute('UPDATE items SET image_url = ? WHERE sku = ? LIMIT 1', [$normalized, $sku]);
        $updates['items']++;
    }

    Database::commit();
} catch (Throwable $e) {
    Database::rollBack();
    fwrite(STDERR, "normalize_image_paths failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode(['success' => true, 'updates' => $updates], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
