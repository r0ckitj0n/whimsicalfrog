<?php
// includes/area_mappings/helpers/AreaMappingUploadHelper.php

require_once __DIR__ . '/../../helpers/ImageUploadHelper.php';
require_once __DIR__ . '/../../secret_store.php';

class AreaMappingUploadHelper
{
    /**
     * Handle content image upload
     */
    public static function handleUpload()
    {
        $maxBytes = 10 * 1024 * 1024; // 10MB
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid method', 405);
        }

        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            throw new Exception('No image uploaded', 400);
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed (code ' . $file['error'] . ')', 400);
        }
        if ($file['size'] > $maxBytes) {
            throw new Exception('File too large (max 10MB)', 400);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new Exception('Unsupported file type', 400);
        }

        $projectRoot = dirname(__DIR__, 3);
        $destDir = $projectRoot . '/images/signs/';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new Exception('Failed to prepare upload directory', 500);
        }

        $slug = 'sign-' . date('Ymd-His') . '-' . substr(md5(random_bytes(8)), 0, 6);
        $absOriginal = $destDir . $slug . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $absOriginal)) {
            throw new Exception('Failed to save uploaded file', 500);
        }
        @chmod($absOriginal, 0644);

        $finalWebp = null;
        if (class_exists('AIImageProcessor')) {
            $processor = new AIImageProcessor();
            try {
                // Use processBackgroundImage which handles dual format (webp+png)
                $result = $processor->processBackgroundImage($absOriginal, [
                    'createDualFormat' => true,
                    'webp_quality' => 90,
                    'png_compression' => 1,
                    'preserve_transparency' => true,
                    'resizeDimensions' => ['width' => 500, 'height' => 500],
                    'resizeMode' => 'contain'
                ]);
                if ($result && !empty($result['webp_path'])) {
                    $finalWebp = $result['webp_path'];
                } else {
                    $finalWebp = $absOriginal;
                }
                if (!empty($result['png_path']) && file_exists($result['png_path'])) {
                    @chmod($result['png_path'], 0644);
                }
            } catch (Throwable $e) {
                error_log("Sign upload processor error: " . $e->getMessage());
                $finalWebp = $absOriginal;
            }
        } else {
            $finalWebp = $absOriginal;
        }

        // Normalize to site-relative URL
        return '/' . ltrim(str_replace($projectRoot, '', $finalWebp), '/');
    }

    public static function handleGenerateShortcutImage(array $input): array
    {
        $roomNumber = trim((string) ($input['room_number'] ?? $input['room'] ?? ''));
        $targetRaw = trim((string) ($input['content_target'] ?? ''));
        $linkLabel = trim((string) ($input['link_label'] ?? ''));
        $mappingId = (int) ($input['mapping_id'] ?? 0);
        $size = trim((string) ($input['size'] ?? '1024x1024'));
        $provider = strtolower(trim((string) ($input['provider'] ?? 'openai')));

        if ($roomNumber === '' || !preg_match('/^[0-9A-Za-z]+$/', $roomNumber)) {
            throw new Exception('room_number is required (alphanumeric)', 422);
        }
        if ($targetRaw === '') {
            throw new Exception('content_target is required', 422);
        }
        if (!in_array($size, ['1024x1024'], true)) {
            throw new Exception('Invalid size. Allowed: 1024x1024', 422);
        }
        if ($provider !== 'openai') {
            throw new Exception('Only provider=openai is supported', 422);
        }

        $targetRoomNumber = self::resolveTargetRoomNumber($targetRaw);
        $targetRoomMeta = self::loadRoomMetadata($targetRoomNumber);

        $settingsRows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
        $settings = [];
        foreach ($settingsRows as $row) {
            $settings[(string) $row['setting_key']] = $row['setting_value'];
        }

        $apiKey = (string) (secret_get('openai_api_key') ?? $settings['openai_api_key'] ?? '');
        if ($apiKey === '') {
            throw new Exception('OpenAI API key is missing in AI settings', 500);
        }

        $model = trim((string) ($input['model'] ?? $settings['openai_image_model'] ?? 'gpt-image-1'));
        if ($model === '') {
            $model = 'gpt-image-1';
        }
        $textModel = trim((string) ($settings['openai_model'] ?? 'gpt-4o-mini'));
        if ($textModel === '') {
            $textModel = 'gpt-4o-mini';
        }

        $styleBrief = '';
        try {
            $roomImageAbs = self::resolveRoomImageAbsPath($targetRoomMeta, $targetRoomNumber);
            if ($roomImageAbs !== '') {
                $styleBrief = self::analyzeRoomStyleWithAi($apiKey, $textModel, $roomImageAbs);
            }
        } catch (Throwable $styleErr) {
            error_log('shortcut sign room style analysis skipped: ' . $styleErr->getMessage());
        }

        $prompt = self::buildShortcutSignPrompt([
            'room_number' => $roomNumber,
            'target_room_number' => $targetRoomNumber,
            'target_room_name' => (string) ($targetRoomMeta['room_name'] ?? ''),
            'target_door_label' => (string) ($targetRoomMeta['door_label'] ?? ''),
            'target_description' => (string) ($targetRoomMeta['description'] ?? ''),
            'link_label' => $linkLabel,
            'style_brief' => $styleBrief
        ]);

        $apiResponse = self::openAiGenerateTransparentImage($apiKey, $model, $prompt, $size);
        $first = $apiResponse['data'][0] ?? null;
        if (!is_array($first)) {
            throw new RuntimeException('OpenAI did not return generated image data');
        }

        $sourcePath = '';
        $tmpFiles = [];

        if (!empty($first['b64_json'])) {
            $bytes = base64_decode((string) $first['b64_json'], true);
            if ($bytes === false) {
                throw new RuntimeException('OpenAI returned invalid base64 image data');
            }
            $tmp = tempnam(sys_get_temp_dir(), 'wf-sign-b64-');
            if (!$tmp) {
                throw new RuntimeException('Unable to create temporary image file');
            }
            file_put_contents($tmp, $bytes);
            $sourcePath = $tmp;
            $tmpFiles[] = $tmp;
        } elseif (!empty($first['url'])) {
            $sourcePath = self::downloadImageToTemp((string) $first['url']);
            $tmpFiles[] = $sourcePath;
        } else {
            throw new RuntimeException('OpenAI response did not contain b64_json or url image payload');
        }

        $projectRoot = dirname(__DIR__, 3);
        $destDir = $projectRoot . '/images/signs';
        ImageUploadHelper::ensureDir($destDir);

        $safeRoom = ImageUploadHelper::slugify($targetRoomNumber);
        $nameHint = (string) ($targetRoomMeta['door_label'] ?? $targetRoomMeta['room_name'] ?? $targetRoomNumber);
        $safeBase = ImageUploadHelper::slugify($nameHint !== '' ? $nameHint : ('room-' . $targetRoomNumber));
        $unique = 'sign-ai-' . $safeBase . '-' . $safeRoom . '-' . substr(uniqid('', true), -6);

        $pngRel = 'images/signs/' . $unique . '.png';
        $webpRel = 'images/signs/' . $unique . '.webp';
        $pngAbs = $projectRoot . '/' . $pngRel;
        $webpAbs = $projectRoot . '/' . $webpRel;

        self::writePngPreservingTransparency($sourcePath, $pngAbs);
        if (!self::imageHasTransparency($pngAbs)) {
            throw new RuntimeException('Generated sign did not include transparency. Please retry.');
        }

        $webpUrl = null;
        if (function_exists('imagewebp')) {
            ImageUploadHelper::convertToWebP($pngAbs, $webpAbs, 92);
            if (file_exists($webpAbs) && (int) @filesize($webpAbs) > 0) {
                $webpUrl = '/' . ltrim($webpRel, '/');
            }
        }

        foreach ($tmpFiles as $tmpFile) {
            if (is_string($tmpFile) && $tmpFile !== '' && file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        $pngUrl = '/' . ltrim($pngRel, '/');
        $result = [
            'image_url' => $webpUrl ?: $pngUrl,
            'png_url' => $pngUrl,
            'webp_url' => $webpUrl,
            'room_number' => $roomNumber,
            'target_room_number' => $targetRoomNumber,
            'prompt_text' => $prompt,
            'provider' => 'openai',
            'model' => $model
        ];

        if ($mappingId > 0) {
            try {
                require_once __DIR__ . '/AreaMappingSignHelper.php';
                AreaMappingSignHelper::recordAssetForMapping(
                    $mappingId,
                    $roomNumber,
                    $result['image_url'],
                    $result['png_url'] ?? null,
                    $result['webp_url'] ?? null,
                    'ai_generate',
                    true
                );
            } catch (Throwable $e) {
                error_log('shortcut sign asset record failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    private static function resolveTargetRoomNumber(string $contentTarget): string
    {
        if (preg_match('/^room:([0-9A-Za-z]+)$/i', $contentTarget, $matches)) {
            $candidate = (string) ($matches[1] ?? '');
        } elseif (preg_match('/^[0-9A-Za-z]+$/', $contentTarget)) {
            $candidate = $contentTarget;
        } else {
            throw new Exception('content_target must be in room:<room_number> format', 422);
        }

        return strlen($candidate) === 1 ? strtoupper($candidate) : $candidate;
    }

    private static function loadRoomMetadata(string $roomNumber): array
    {
        $row = Database::queryOne(
            'SELECT room_name, door_label, description, background_url FROM room_settings WHERE room_number = ? LIMIT 1',
            [$roomNumber]
        );

        return is_array($row) ? $row : [
            'room_name' => '',
            'door_label' => '',
            'description' => '',
            'background_url' => ''
        ];
    }

    private static function buildShortcutSignPrompt(array $ctx): string
    {
        $targetRoomNumber = trim((string) ($ctx['target_room_number'] ?? ''));
        $targetRoomName = trim((string) ($ctx['target_room_name'] ?? ''));
        $targetDoorLabel = trim((string) ($ctx['target_door_label'] ?? ''));
        $targetDescription = trim((string) ($ctx['target_description'] ?? ''));
        $linkLabel = trim((string) ($ctx['link_label'] ?? ''));
        $styleBrief = trim((string) ($ctx['style_brief'] ?? ''));

        $destinationLabel = $targetDoorLabel !== ''
            ? $targetDoorLabel
            : ($targetRoomName !== '' ? $targetRoomName : ('Room ' . $targetRoomNumber));

        $lines = [
            'Create one isolated decorative wayfinding sign icon for an ecommerce room shortcut.',
            'The sign should visually fit this destination room theme: ' . ($targetRoomName !== '' ? $targetRoomName : ('Room ' . $targetRoomNumber)) . '.',
            'Destination label context: ' . $destinationLabel . '.',
            $targetDescription !== '' ? ('Room description context: ' . $targetDescription . '.') : 'Room description context: themed retail space.',
            $linkLabel !== '' ? ('Shortcut button label context: ' . $linkLabel . '.') : 'Shortcut button label context: room shortcut.',
            $styleBrief !== '' ? ('Match this analyzed room visual style: ' . $styleBrief . '.') : 'Match the destination room visual style with consistent color and material language.',
            'STYLE: polished, whimsical handcrafted sign prop, centered, front-facing, production-ready.',
            'BACKGROUND: fully transparent alpha background only.',
            'CONSTRAINTS: no full scene, no environment, no walls, no floor, no frame around canvas, no watermark, no text rendering, no letters.',
            'Output a single sign object with clean edges and preserved transparency.'
        ];

        return implode("\n", $lines);
    }

    private static function resolveRoomImageAbsPath(array $roomMeta, string $targetRoomNumber): string
    {
        $projectRoot = realpath(dirname(__DIR__, 3)) ?: dirname(__DIR__, 3);

        $candidates = [];
        $backgroundUrl = trim((string) ($roomMeta['background_url'] ?? ''));
        if ($backgroundUrl !== '') {
            $path = parse_url($backgroundUrl, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/images/')) {
                $candidates[] = $projectRoot . '/' . ltrim($path, '/');
            }
        }

        $bg = Database::queryOne(
            'SELECT image_filename, png_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
            [$targetRoomNumber]
        );
        if (is_array($bg)) {
            $pngFilename = trim((string) ($bg['png_filename'] ?? ''));
            $imageFilename = trim((string) ($bg['image_filename'] ?? ''));
            $webpFilename = trim((string) ($bg['webp_filename'] ?? ''));
            if ($pngFilename !== '') {
                $candidates[] = $projectRoot . '/images/' . ltrim($pngFilename, '/');
            }
            if ($imageFilename !== '') {
                $candidates[] = $projectRoot . '/images/' . ltrim($imageFilename, '/');
            }
            if ($webpFilename !== '') {
                $candidates[] = $projectRoot . '/images/' . ltrim($webpFilename, '/');
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    private static function analyzeRoomStyleWithAi(string $apiKey, string $textModel, string $imageAbsPath): string
    {
        $bytes = @file_get_contents($imageAbsPath);
        if ($bytes === false || $bytes === '') {
            return '';
        }

        $mime = mime_content_type($imageAbsPath) ?: 'image/png';
        $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);

        $body = [
            'model' => $textModel,
            'input' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Analyze this room image and return one concise style brief (max 90 words) describing color palette, materials, ornament style, mood, and rendering style. Output plain text only.'
                    ],
                    [
                        'type' => 'input_image',
                        'image_url' => $dataUrl
                    ]
                ]
            ]],
            'max_output_tokens' => 220,
            'temperature' => 0.2
        ];

        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Room style analysis request failed: ' . $err);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Room style analysis returned invalid JSON');
        }
        if ($status >= 400) {
            $msg = (string) ($decoded['error']['message'] ?? $decoded['error'] ?? 'Unknown OpenAI error');
            throw new RuntimeException('Room style analysis failed (HTTP ' . $status . '): ' . $msg);
        }

        $text = trim((string) ($decoded['output_text'] ?? ''));
        if ($text !== '') {
            return preg_replace('/\s+/', ' ', $text) ?: '';
        }

        if (is_array($decoded['output'] ?? null)) {
            foreach ($decoded['output'] as $entry) {
                $content = is_array($entry['content'] ?? null) ? $entry['content'] : [];
                foreach ($content as $block) {
                    $candidate = trim((string) ($block['text'] ?? ''));
                    if ($candidate !== '') {
                        return preg_replace('/\s+/', ' ', $candidate) ?: '';
                    }
                }
            }
        }

        return '';
    }

    private static function openAiGenerateTransparentImage(string $apiKey, string $model, string $prompt, string $size): array
    {
        $baseBody = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'n' => 1
        ];

        $attemptBodies = [
            $baseBody + ['background' => 'transparent', 'response_format' => 'b64_json'],
            $baseBody + ['background' => 'transparent'],
            $baseBody + ['response_format' => 'b64_json'],
            $baseBody
        ];

        $lastError = 'OpenAI image generation failed';
        foreach ($attemptBodies as $body) {
            $ch = curl_init('https://api.openai.com/v1/images/generations');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)
            ]);

            $raw = curl_exec($ch);
            $curlErr = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new RuntimeException('OpenAI request failed: ' . $curlErr);
            }

            $decoded = json_decode($raw, true);
            if ($status >= 400) {
                $message = is_array($decoded) ? ((string) (($decoded['error']['message'] ?? '') ?: ($decoded['error'] ?? ''))) : '';
                $fallback = is_string($raw) ? $raw : 'Unknown OpenAI error';
                $lastError = 'OpenAI image generation failed (HTTP ' . $status . '): ' . ($message !== '' ? $message : $fallback);

                $isRecoverableFieldError = is_string($message)
                    && (stripos($message, 'response_format') !== false || stripos($message, 'background') !== false);
                if ($isRecoverableFieldError) {
                    continue;
                }
                throw new RuntimeException($lastError);
            }

            if (!is_array($decoded)) {
                throw new RuntimeException('OpenAI image response was not valid JSON');
            }

            return $decoded;
        }

        throw new RuntimeException($lastError);
    }

    private static function downloadImageToTemp(string $url): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wf-sign-url-');
        if (!$tmp) {
            throw new RuntimeException('Unable to create temporary file for image download');
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
            throw new RuntimeException('Failed to download generated image: ' . ($err ?: ('HTTP ' . $status)));
        }

        file_put_contents($tmp, $body);
        return $tmp;
    }

    private static function writePngPreservingTransparency(string $srcPath, string $destPath): void
    {
        $info = getimagesize($srcPath);
        if (!$info) {
            throw new RuntimeException('Generated image is invalid');
        }

        $mime = strtolower((string) ($info['mime'] ?? ''));
        if ($mime === 'image/png') {
            $img = imagecreatefrompng($srcPath);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $img = imagecreatefromwebp($srcPath);
        } elseif ($mime === 'image/jpeg') {
            $img = imagecreatefromjpeg($srcPath);
        } else {
            throw new RuntimeException('Unsupported generated image type: ' . ($mime ?: 'unknown'));
        }

        if (!$img) {
            throw new RuntimeException('Failed to decode generated image');
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        if (!imagepng($img, $destPath, 1)) {
            imagedestroy($img);
            throw new RuntimeException('Failed to save generated PNG');
        }
        imagedestroy($img);
        @chmod($destPath, 0644);
    }

    private static function imageHasTransparency(string $pngPath): bool
    {
        $img = imagecreatefrompng($pngPath);
        if (!$img) {
            return false;
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $stepX = max(1, (int) floor($width / 40));
        $stepY = max(1, (int) floor($height / 40));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha > 0) {
                    imagedestroy($img);
                    return true;
                }
            }
        }

        imagedestroy($img);
        return false;
    }
}
