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

    public static function processImageWithAI($absPath, $sku, $suffix, $itemsDir, $projectRoot) {
        $processor = new AIImageProcessor();
        $aiResult = $processor->processImage($absPath, [
            'convertToWebP' => false,
            'quality' => 90,
            'preserveTransparency' => true,
            'useAI' => true,
            'fallbackTrimPercent' => 0.05
        ]);

        $processedPath = $aiResult['success'] ? $aiResult['processed_path'] : $absPath;
        $formatResult = $processor->convertToDualFormat($processedPath, [
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
            if ($processedPath !== $absPath && file_exists($processedPath)) {
                unlink($processedPath);
            }
            return ['success' => true, 'path' => $finalPath];
        }
        return ['success' => false];
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
