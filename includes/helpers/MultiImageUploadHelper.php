<?php
/**
 * includes/helpers/MultiImageUploadHelper.php
 * Helper class for multi-image upload processing
 */

class MultiImageUploadHelper {
    public static function getNextSuffix($sku, &$usedSuffixes) {
        for ($i = 0; $i < 26; $i++) {
            $suffix = chr(65 + $i);
            if (!in_array($suffix, $usedSuffixes)) {
                $usedSuffixes[] = $suffix;
                return $suffix;
            }
        }
        return null;
    }

    public static function processImageAtPathForDualFormat($absPath, $projectRoot, $useAI = true) {
        $processor = new AIImageProcessor();
        $aiResult = $processor->processImage($absPath, [
            'convertToWebP' => false,
            'quality' => 90,
            'preserveTransparency' => true,
            'useAI' => (bool) $useAI,
            'fallbackTrimPercent' => 0.05
        ]);

        $processedPath = $aiResult['success'] ? $aiResult['processed_path'] : $absPath;
        $formatResult = $processor->convertToDualFormat($processedPath, [
            'webp_quality' => 92,
            'png_compression' => 1,
            'preserve_transparency' => true,
            'force_png' => true
        ]);

        if ($formatResult['success']) {
            $finalPath = ltrim(str_replace($projectRoot . '/', '', $formatResult['webp_path']), '/');
            if ($processedPath !== $absPath && file_exists($processedPath)) {
                unlink($processedPath);
            }
            return [
                'success' => true,
                'path' => $finalPath,
                'webp_path' => $formatResult['webp_path'] ?? null,
                'png_path' => $formatResult['png_path'] ?? null
            ];
        }
        return ['success' => false];
    }

    public static function processImageForDualFormat($absPath, $sku, $suffix, $itemsDir, $projectRoot, $useAI = true) {
        $result = self::processImageAtPathForDualFormat($absPath, $projectRoot, $useAI);
        if (!$result['success']) {
            return ['success' => false];
        }

        $pngPath = $itemsDir . $sku . $suffix . '.png';
        $generatedPngPath = $result['png_path'] ?? null;
        if ($generatedPngPath && file_exists($generatedPngPath)) {
            copy($generatedPngPath, $pngPath);
            chmod($pngPath, 0644);
        }

        return ['success' => true, 'path' => $result['path']];
    }

    public static function processImageWithAI($absPath, $sku, $suffix, $itemsDir, $projectRoot) {
        return self::processImageForDualFormat($absPath, $sku, $suffix, $itemsDir, $projectRoot, true);
    }

    public static function convertToDualFormatOnly($absPath, $sku, $suffix, $itemsDir, $projectRoot) {
        $processor = new AIImageProcessor();
        $formatResult = $processor->convertToDualFormat($absPath, [
            'webp_quality' => 90,
            'png_compression' => 1,
            'preserve_transparency' => true,
            'force_png' => true
        ]);

        if ($formatResult['success']) {
            $finalPath = ltrim(str_replace($projectRoot . '/', '', $formatResult['webp_path']), '/');
            $pngPath = $itemsDir . $sku . $suffix . '.png';
            if ($formatResult['png_path'] && file_exists($formatResult['png_path'])) {
                copy($formatResult['png_path'], $pngPath);
                chmod($pngPath, 0644);
            }
            return ['success' => true, 'path' => $finalPath];
        }
        return ['success' => false];
    }
}
