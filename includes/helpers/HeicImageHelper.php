<?php
/**
 * HEIC/HEIF → JPEG conversion for inventory uploads (Imagick when available).
 */

class HeicImageHelper
{
    public static function isHeicPath(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['heic', 'heif'], true);
    }

    public static function isHeicUpload(string $filename, string $mime = ''): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['heic', 'heif'], true)) {
            return true;
        }
        $mime = strtolower(trim($mime));
        return in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true);
    }

    /**
     * Convert HEIC/HEIF to a JPEG next to the source (or $destPath).
     * Returns absolute JPEG path on success, null on failure.
     */
    public static function convertToJpeg(string $srcPath, ?string $destPath = null): ?string
    {
        if (!is_file($srcPath)) {
            return null;
        }
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            error_log('HeicImageHelper: Imagick extension unavailable');
            return null;
        }

        $destPath = $destPath ?: (preg_replace('/\.(heic|heif)$/i', '', $srcPath) . '.jpg');
        try {
            $im = new Imagick();
            $im->readImage($srcPath);
            if (method_exists($im, 'autoOrient')) {
                @$im->autoOrient();
            }
            $im->setImageFormat('jpeg');
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(92);
            $im->stripImage();
            if (!$im->writeImage($destPath)) {
                $im->clear();
                $im->destroy();
                return null;
            }
            $im->clear();
            $im->destroy();
            @chmod($destPath, 0644);
            if (!is_file($destPath) || (int) filesize($destPath) <= 0) {
                @unlink($destPath);
                return null;
            }
            return $destPath;
        } catch (Throwable $e) {
            error_log('HeicImageHelper convert failed: ' . $e->getMessage());
            return null;
        }
    }
}
