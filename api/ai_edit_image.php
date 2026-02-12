<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/helpers/ImageUploadHelper.php';
require_once __DIR__ . '/../includes/helpers/MultiImageUploadHelper.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/ai_image_processor.php';
require_once __DIR__ . '/../includes/backgrounds/manager.php';

header('Content-Type: application/json; charset=utf-8');

function wf_openai_images_edits_request(string $apiKey, array $postFields): array
{
    $ch = curl_init('https://api.openai.com/v1/images/edits');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 180,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_POSTFIELDS => $postFields
    ]);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('OpenAI image edit request failed: ' . $curlErr);
    }

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('OpenAI image edit returned invalid JSON');
    }

    return [
        'status' => $status,
        'decoded' => $decoded
    ];
}

function wf_openai_edit_image(
    string $apiKey,
    string $model,
    string $prompt,
    string $sourceImageAbs
): array
{
    $file = new CURLFile($sourceImageAbs, mime_content_type($sourceImageAbs) ?: 'image/png', basename($sourceImageAbs));
    $postFields = [
        'model' => $model,
        'prompt' => $prompt,
        'n' => '1',
        'image' => $file
    ];
    if (!str_starts_with($model, 'gpt-image-')) {
        // Legacy DALL-E edits expect this field; GPT image models ignore it.
        $postFields['response_format'] = 'b64_json';
    }

    $result = wf_openai_images_edits_request($apiKey, $postFields);
    $status = (int) ($result['status'] ?? 0);
    $decoded = (array) ($result['decoded'] ?? []);

    if ($status >= 400) {
        $msg = (string) ($decoded['error']['message'] ?? $decoded['error'] ?? 'Unknown OpenAI error');
        throw new RuntimeException('OpenAI image edit failed (HTTP ' . $status . '): ' . $msg);
    }

    return $decoded;
}

function wf_resolve_openai_edit_model(string $requestedModel): string
{
    $normalized = strtolower(trim($requestedModel));
    $allowed = [
        'dall-e-2',
        'gpt-image-1',
        'gpt-image-1-mini',
        'gpt-image-1.5'
    ];
    if (in_array($normalized, $allowed, true)) {
        return $normalized;
    }

    // Prefer GPT image edits by default when model setting is unknown.
    return 'gpt-image-1';
}

function wf_prepare_openai_edit_source_image(string $sourceImageAbs): array
{
    $info = @getimagesize($sourceImageAbs);
    $type = (int) ($info[2] ?? 0);
    $image = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = @imagecreatefromjpeg($sourceImageAbs);
            break;
        case IMAGETYPE_WEBP:
            if (!function_exists('imagecreatefromwebp')) {
                throw new RuntimeException('Server lacks WEBP support required to convert source image');
            }
            $image = @imagecreatefromwebp($sourceImageAbs);
            break;
        case IMAGETYPE_GIF:
            $image = @imagecreatefromgif($sourceImageAbs);
            break;
        case IMAGETYPE_PNG:
            $image = @imagecreatefrompng($sourceImageAbs);
            break;
        default:
            throw new RuntimeException('Unsupported source image type for AI edit upload');
    }

    if (!$image) {
        throw new RuntimeException('Failed to read source image for AI edit upload');
    }

    $width = imagesx($image);
    $height = imagesy($image);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($image);
        throw new RuntimeException('Invalid source image dimensions for AI edit upload');
    }

    // Force RGBA output so OpenAI edits API accepts the uploaded image mode.
    $rgbaImage = imagecreatetruecolor($width, $height);
    if (!$rgbaImage) {
        imagedestroy($image);
        throw new RuntimeException('Failed to allocate RGBA buffer for AI edit upload');
    }
    imagealphablending($rgbaImage, false);
    imagesavealpha($rgbaImage, true);
    $transparent = imagecolorallocatealpha($rgbaImage, 0, 0, 0, 127);
    imagefill($rgbaImage, 0, 0, $transparent);
    imagecopy($rgbaImage, $image, 0, 0, 0, 0, $width, $height);
    imagedestroy($image);

    $tmp = tempnam(sys_get_temp_dir(), 'wf-ai-edit-src-png-');
    if ($tmp === false) {
        imagedestroy($rgbaImage);
        throw new RuntimeException('Failed to create temp PNG for AI edit upload');
    }
    if (!imagepng($rgbaImage, $tmp, 1)) {
        imagedestroy($rgbaImage);
        @unlink($tmp);
        throw new RuntimeException('Failed to convert source image to PNG for AI edit upload');
    }
    imagedestroy($rgbaImage);

    return [
        'path' => $tmp,
        'cleanup' => $tmp
    ];
}

function wf_download_to_temp(string $url): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'wf-ai-edit-url-');
    if (!$tmp) {
        throw new RuntimeException('Failed to create temp file for OpenAI image URL');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        @unlink($tmp);
        throw new RuntimeException('Failed to download edited image: ' . ($err ?: ('HTTP ' . $status)));
    }

    file_put_contents($tmp, $body);
    return $tmp;
}

function wf_edited_image_to_temp(array $openAiResponse): string
{
    $first = $openAiResponse['data'][0] ?? null;
    if (!is_array($first)) {
        throw new RuntimeException('OpenAI did not return edited image data');
    }

    if (!empty($first['b64_json'])) {
        $bytes = base64_decode((string) $first['b64_json'], true);
        if ($bytes === false) {
            throw new RuntimeException('OpenAI returned invalid base64 image data');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'wf-ai-edit-b64-');
        if (!$tmp) {
            throw new RuntimeException('Failed to create temp file for edited image');
        }
        file_put_contents($tmp, $bytes);
        return $tmp;
    }

    if (!empty($first['url'])) {
        return wf_download_to_temp((string) $first['url']);
    }

    throw new RuntimeException('OpenAI image edit response had no b64_json or url payload');
}

function wf_resolve_local_image_abs(string $sourceImageUrl): string
{
    $sourceImageUrl = trim($sourceImageUrl);
    if ($sourceImageUrl === '') {
        throw new RuntimeException('source_image_url is required');
    }

    $path = parse_url($sourceImageUrl, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';
    if ($path === '') {
        throw new RuntimeException('source_image_url must include a valid path');
    }

    if (!str_starts_with($path, '/images/')) {
        throw new RuntimeException('source_image_url must point to /images/...');
    }

    $relative = ltrim($path, '/');
    $absolute = realpath(__DIR__ . '/../' . $relative);
    if ($absolute === false || !is_file($absolute)) {
        throw new RuntimeException('Selected source image file was not found on disk');
    }

    $projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    if (!str_starts_with($absolute, $projectRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('source_image_url resolved outside allowed image directory');
    }

    return $absolute;
}

function wf_try_resolve_local_image_abs(string $sourceImageUrl): string
{
    try {
        return wf_resolve_local_image_abs($sourceImageUrl);
    } catch (Throwable $e) {
        return '';
    }
}

function wf_try_resolve_background_png_source_abs(int $backgroundId, string $sourceImageUrl): string
{
    if ($backgroundId > 0) {
        $row = Database::queryOne(
            'SELECT image_filename, png_filename FROM backgrounds WHERE id = ? LIMIT 1',
            [$backgroundId]
        );
        if (is_array($row)) {
            $candidates = [];
            $pngFilename = trim((string) ($row['png_filename'] ?? ''));
            if ($pngFilename !== '') {
                $candidates[] = '/images/' . ltrim($pngFilename, '/');
            }

            $imageFilename = trim((string) ($row['image_filename'] ?? ''));
            if ($imageFilename !== '' && strtolower((string) pathinfo($imageFilename, PATHINFO_EXTENSION)) === 'png') {
                $candidates[] = '/images/' . ltrim($imageFilename, '/');
            }

            foreach ($candidates as $candidate) {
                $abs = wf_try_resolve_local_image_abs($candidate);
                if ($abs !== '') {
                    return $abs;
                }
            }
        }
    }

    $path = parse_url($sourceImageUrl, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';
    if ($path !== '' && str_ends_with(strtolower($path), '.webp')) {
        $pngPath = preg_replace('/\.webp$/i', '.png', $path);
        if (is_string($pngPath) && $pngPath !== '') {
            $abs = wf_try_resolve_local_image_abs($pngPath);
            if ($abs !== '') {
                return $abs;
            }
        }
    }

    return '';
}

Response::validateMethod('POST');
AuthHelper::requireAdmin(403, 'Admin access required');

try {
    Database::getInstance();

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $targetType = trim((string) ($input['target_type'] ?? ''));
    $sourceImageUrl = trim((string) ($input['source_image_url'] ?? ''));
    $sourceBackgroundId = (int) ($input['source_background_id'] ?? 0);
    $instructions = trim((string) ($input['instructions'] ?? ''));

    if (!in_array($targetType, ['item', 'background', 'shortcut_sign'], true)) {
        Response::error('target_type must be item, background, or shortcut_sign', null, 422);
    }
    if ($instructions === '') {
        Response::error('instructions are required', null, 422);
    }

    $settingsRows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
    $settings = [];
    foreach ($settingsRows as $row) {
        $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
    }

    $apiKey = (string) (secret_get('openai_api_key') ?? $settings['openai_api_key'] ?? '');
    if ($apiKey === '') {
        throw new RuntimeException('OpenAI API key is missing in AI settings');
    }

    $requestedModel = trim((string) (
        $input['model']
        ?? $settings['openai_image_edit_model']
        ?? $settings['openai_image_model']
        ?? 'gpt-image-1'
    ));
    $model = wf_resolve_openai_edit_model($requestedModel);

    if ($targetType === 'background') {
        $sourceImageAbs = wf_try_resolve_background_png_source_abs($sourceBackgroundId, $sourceImageUrl);
        if ($sourceImageAbs === '') {
            $sourceImageAbs = wf_resolve_local_image_abs($sourceImageUrl);
        }
    } else {
        $sourceImageAbs = wf_resolve_local_image_abs($sourceImageUrl);
    }
    $editedTemp = '';
    $uploadSourceTemp = '';
    $derivedPaths = [];
    $responseData = null;
    $responseMessage = '';

    try {
        $preparedSource = wf_prepare_openai_edit_source_image($sourceImageAbs);
        $uploadSourcePath = (string) ($preparedSource['path'] ?? $sourceImageAbs);
        $uploadSourceTemp = (string) ($preparedSource['cleanup'] ?? '');

        $effectiveInstructions = trim($instructions);

        $openAiResponse = wf_openai_edit_image(
            $apiKey,
            $model,
            $effectiveInstructions,
            $uploadSourcePath
        );
        $editedTemp = wf_edited_image_to_temp($openAiResponse);

        if ($targetType === 'item') {
            $sku = trim((string) ($input['item_sku'] ?? ''));
            if ($sku === '' || preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku) !== 1) {
                Response::error('item_sku is required and must be alphanumeric with dashes', null, 422);
            }

            $item = Database::queryOne('SELECT sku FROM items WHERE sku = ? LIMIT 1', [$sku]);
            if (!$item) {
                Response::error('Item not found for provided SKU', null, 404);
            }

            $existing = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ?", [$sku]);
            $usedSuffixes = [];
            foreach ($existing as $row) {
                if (preg_match('/\/' . preg_quote($sku, '/') . '([A-Z])\./', (string) ($row['image_path'] ?? ''), $matches)) {
                    $usedSuffixes[] = $matches[1];
                }
            }

            $suffix = MultiImageUploadHelper::getNextSuffix($sku, $usedSuffixes);
            if (!$suffix) {
                throw new RuntimeException('Maximum image limit reached for this item (26 images)');
            }

            $processor = new AIImageProcessor();
            $formatResult = $processor->convertToDualFormat($editedTemp, [
                'webp_quality' => 92,
                'png_compression' => 1,
                'preserve_transparency' => true,
                'force_png' => true
            ]);
            $derivedPaths = array_filter([
                (string) ($formatResult['webp_path'] ?? ''),
                (string) ($formatResult['png_path'] ?? '')
            ]);

            if (empty($formatResult['success']) || empty($formatResult['webp_path']) || empty($formatResult['png_path'])) {
                throw new RuntimeException('Failed to convert edited item image to required formats');
            }

            $projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
            $itemsDir = $projectRoot . '/images/items';
            ImageUploadHelper::ensureDir($itemsDir);

            $webpAbs = $itemsDir . '/' . $sku . $suffix . '.webp';
            $pngAbs = $itemsDir . '/' . $sku . $suffix . '.png';
            if (!@copy((string) $formatResult['webp_path'], $webpAbs) || !@copy((string) $formatResult['png_path'], $pngAbs)) {
                throw new RuntimeException('Failed to save edited item image files');
            }
            @chmod($webpAbs, 0644);
            @chmod($pngAbs, 0644);

            $relativeWebp = 'images/items/' . $sku . $suffix . '.webp';
            $relativePng = 'images/items/' . $sku . $suffix . '.png';
            $sortOrder = (int) (Database::queryOne("SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM item_images WHERE sku = ?", [$sku])['max_sort'] ?? -1) + 1;
            $altText = 'AI Edit: ' . substr($instructions, 0, 220);
            Database::execute(
                "INSERT INTO item_images (sku, image_path, is_primary, alt_text, sort_order) VALUES (?, ?, 0, ?, ?)",
                [$sku, $relativeWebp, $altText, $sortOrder]
            );

            $responseData = [
                'target_type' => 'item',
                'item_image' => [
                    'sku' => $sku,
                    'image_path' => $relativeWebp,
                    'png_path' => $relativePng,
                    'webp_path' => $relativeWebp
                ]
            ];
            $responseMessage = 'Edited item image saved';
        } elseif ($targetType === 'background') {
            $roomRaw = trim((string) ($input['room_number'] ?? ''));
            if ($roomRaw === '' || preg_match('/^[0-9a-zA-Z]+$/', $roomRaw) !== 1) {
                Response::error('room_number is required (alphanumeric)', null, 422);
            }
            $roomNumber = normalizeRoomNumber($roomRaw);
            $safeRoom = ImageUploadHelper::slugify('room' . $roomNumber);
            $safeBase = ImageUploadHelper::slugify('ai-edit-' . $roomNumber);
            $unique = $safeBase . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);

            $imagesRoot = realpath(__DIR__ . '/../images') ?: (__DIR__ . '/../images');
            $backgroundsDir = $imagesRoot . '/backgrounds';
            ImageUploadHelper::ensureDir($backgroundsDir);

            $pngRel = 'backgrounds/' . $unique . '.png';
            $webpRel = 'backgrounds/' . $unique . '.webp';
            $pngAbs = $imagesRoot . '/' . $pngRel;
            $webpAbs = $imagesRoot . '/' . $webpRel;

            ImageUploadHelper::resizeFillToPng($editedTemp, $pngAbs, 1280, 896);
            if (function_exists('imagewebp')) {
                ImageUploadHelper::convertToWebP($pngAbs, $webpAbs, 92);
            } else {
                $webpRel = '';
            }

            $namePrefix = trim((string) ($input['background_name'] ?? 'AI Edited Background'));
            $backgroundName = $namePrefix . ' ' . date('Y-m-d H:i');
            if (Database::queryOne('SELECT id FROM backgrounds WHERE room_number = ? AND name = ? LIMIT 1', [$roomNumber, $backgroundName])) {
                $backgroundName .= ' ' . date('Ymd-His');
            }

            Database::execute(
                'INSERT INTO backgrounds (room_number, name, image_filename, png_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, ?, 0)',
                [$roomNumber, $backgroundName, $pngRel, $pngRel, $webpRel]
            );

            $backgroundId = (int) Database::lastInsertId();
            $responseData = [
                'target_type' => 'background',
                'background' => [
                    'id' => $backgroundId,
                    'room_number' => $roomNumber,
                    'name' => $backgroundName,
                    'image_filename' => $pngRel,
                    'webp_filename' => $webpRel,
                    'is_active' => 0,
                    'image_url' => '/images/' . $pngRel,
                    'webp_url' => $webpRel !== '' ? '/images/' . $webpRel : null
                ]
            ];
            $responseMessage = 'Edited background image saved';
        } else {
            $roomRaw = trim((string) ($input['room_number'] ?? ''));
            if ($roomRaw === '' || preg_match('/^[0-9a-zA-Z]+$/', $roomRaw) !== 1) {
                Response::error('room_number is required (alphanumeric)', null, 422);
            }
            $roomNumber = normalizeRoomNumber($roomRaw);
            $safeRoom = ImageUploadHelper::slugify('room' . $roomNumber);
            $safeBase = ImageUploadHelper::slugify('ai-edit-sign-' . $roomNumber);
            $unique = $safeBase . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);

            $imagesRoot = realpath(__DIR__ . '/../images') ?: (__DIR__ . '/../images');
            $signsDir = $imagesRoot . '/signs';
            ImageUploadHelper::ensureDir($signsDir);

            $pngRel = 'signs/' . $unique . '.png';
            $webpRel = 'signs/' . $unique . '.webp';
            $pngAbs = $imagesRoot . '/' . $pngRel;
            $webpAbs = $imagesRoot . '/' . $webpRel;

            ImageUploadHelper::resizeFillToPng($editedTemp, $pngAbs, 500, 500);
            if (!function_exists('imagewebp')) {
                throw new RuntimeException('WEBP support is required for shortcut sign edits');
            }
            ImageUploadHelper::convertToWebP($pngAbs, $webpAbs, 92);
            if (!file_exists($pngAbs) || !file_exists($webpAbs)) {
                throw new RuntimeException('Failed to save edited shortcut sign files');
            }

            $name = 'AI Edited Sign ' . date('Y-m-d H:i');
            $responseData = [
                'target_type' => 'shortcut_sign',
                'shortcut_sign' => [
                    'name' => $name,
                    'image_url' => '/images/' . $webpRel,
                    'png_url' => '/images/' . $pngRel,
                    'webp_url' => '/images/' . $webpRel
                ]
            ];
            $responseMessage = 'Edited shortcut sign image saved';
        }
    } finally {
        $pathsToDelete = array_merge([$editedTemp, $uploadSourceTemp], $derivedPaths);
        foreach ($pathsToDelete as $path) {
            if (is_string($path) && $path !== '' && file_exists($path)) {
                @unlink($path);
            }
        }
    }
    if (!is_array($responseData)) {
        throw new RuntimeException('AI image edit did not produce a response payload');
    }
    Response::success($responseData, $responseMessage);
} catch (Throwable $e) {
    Response::error($e->getMessage(), null, 500);
}
