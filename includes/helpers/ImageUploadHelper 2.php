<?php
/**
 * includes/helpers/ImageUploadHelper.php
 * Helper class for image uploads and processing
 */

class ImageUploadHelper {
    public static function slugify($text) {
        $text = trim($text);
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text ?: 'img';
    }

    public static function ensureDir($path) {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new Exception('Failed to create directory: ' . $path);
            }
        }
    }

    public static function convertToWebP($srcPath, $destPath, $quality = 90) {
        $info = getimagesize($srcPath);
        if (!$info) throw new Exception('Invalid image file.');
        
        switch ($info['mime']) {
            case 'image/jpeg': $image = imagecreatefromjpeg($srcPath); break;
            case 'image/png': 
                $image = imagecreatefrompng($srcPath);
                imagepalettetotruecolor($image);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            case 'image/webp':
                if (!copy($srcPath, $destPath)) throw new Exception('Failed to copy WEBP.');
                return $destPath;
            default: throw new Exception('Unsupported type: ' . $info['mime']);
        }

        if (!$image) throw new Exception('Failed to create resource.');
        if (!imagewebp($image, $destPath, $quality)) {
            imagedestroy($image);
            throw new Exception('Failed to generate WebP.');
        }
        imagedestroy($image);
        return $destPath;
    }

    public static function resizeFillToPng($srcPath, $destPath, $targetW = 1280, $targetH = 896) {
        $info = getimagesize($srcPath);
        if (!$info) throw new Exception('Invalid image file.');
        
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($srcPath); break;
            case IMAGETYPE_PNG: 
                $src = imagecreatefrompng($srcPath); 
                imagealphablending($src, false); 
                imagesavealpha($src, true); 
                break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) throw new Exception('No WEBP support');
                $src = imagecreatefromwebp($srcPath); 
                imagealphablending($src, false); 
                imagesavealpha($src, true); 
                break;
            default: throw new Exception('Unsupported type');
        }

        $sw = imagesx($src); $sh = imagesy($src);
        $srcRatio = $sw / $sh; $tgtRatio = $targetW / $targetH;
        
        if ($srcRatio > $tgtRatio) {
            $srcH = $sh; $srcW = (int)($sh * $tgtRatio); $sx = (int)(($sw - $srcW) / 2); $sy = 0;
        } else {
            $srcW = $sw; $srcH = (int)($sw / $tgtRatio); $sx = 0; $sy = (int)(($sh - $srcH) / 2);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127); imagefill($dst, 0, 0, $transparent);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $targetW, $targetH, $srcW, $srcH);
        imagepng($dst, $destPath, 1);
        imagedestroy($src); imagedestroy($dst);
        chmod($destPath, 0644);
        return $destPath;
    }
}
