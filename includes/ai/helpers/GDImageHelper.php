<?php
// includes/ai/helpers/GDImageHelper.php

/**
 * Helper class for GD-based image operations like edge detection and format conversion
 */
class GDImageHelper
{
    /**
     * Basic edge detection using GD library
     */
    public static function detectEdges($imagePath)
    {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return null;
        }

        $image = self::createImageResource($imagePath, $imageInfo[2]);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Find bounds by scanning for non-background pixels
        $bounds = self::findContentBounds($image, $width, $height);

        imagedestroy($image);

        if ($bounds) {
            return [
                'left' => $bounds['left'] / $width,
                'top' => $bounds['top'] / $height,
                'right' => $bounds['right'] / $width,
                'bottom' => $bounds['bottom'] / $height,
                'confidence' => 0.6,
                'description' => 'GD-based edge detection'
            ];
        }

        return null;
    }

    /**
     * Create image resource based on type
     */
    public static function createImageResource($path, $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($path);
                if ($img) {
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                }
                return $img;
            case IMAGETYPE_WEBP:
                $img = imagecreatefromwebp($path);
                if ($img) {
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                }
                return $img;
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($path);
                if ($img) {
                    $transparentIndex = imagecolortransparent($img);
                    if ($transparentIndex >= 0) {
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                    }
                }
                return $img;
            default:
                return null;
        }
    }

    /**
     * Find content bounds by scanning pixels
     */
    private static function findContentBounds($image, $width, $height)
    {
        // Sample corners to determine background color
        $cornerColors = [
            imagecolorat($image, 0, 0),
            imagecolorat($image, $width - 1, 0),
            imagecolorat($image, 0, $height - 1),
            imagecolorat($image, $width - 1, $height - 1)
        ];

        // Use most common corner color as background
        $counts = array_count_values($cornerColors);
        arsort($counts);
        $backgroundColor = array_key_first($counts);

        $left = $width; $right = 0; $top = $height; $bottom = 0;
        $tolerance = 30; // Color difference tolerance

        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $color = imagecolorat($image, $x, $y);
                if (self::colorDifference($color, $backgroundColor) > $tolerance) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        // Add padding (2%)
        $padding = min($width, $height) * 0.02;
        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min($width, $right + $padding);
        $bottom = min($height, $bottom + $padding);

        if ($left < $right && $top < $bottom && ($right - $left) > $width * 0.1 && ($bottom - $top) > $height * 0.1) {
            return ['left' => $left, 'top' => $top, 'right' => $right, 'bottom' => $bottom];
        }

        return null;
    }

    /**
     * Resize image with aspect ratio preservation options
     */
    public static function resize($sourceImage, $targetDimensions, $mode = 'fit', $supportsTransparency = true)
    {
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $targetWidth = $targetDimensions['width'];
        $targetHeight = $targetDimensions['height'];

        switch ($mode) {
            case 'stretch':
                $newWidth = $targetWidth; $newHeight = $targetHeight;
                $srcX = $srcY = 0; $srcWidth = $sourceWidth; $srcHeight = $sourceHeight;
                break;
            case 'fill':
                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;
                if ($sourceRatio > $targetRatio) {
                    $srcHeight = $sourceHeight; $srcWidth = (int)($sourceHeight * $targetRatio);
                    $srcX = (int)(($sourceWidth - $srcWidth) / 2); $srcY = 0;
                } else {
                    $srcWidth = $sourceWidth; $srcHeight = (int)($sourceWidth / $targetRatio);
                    $srcX = 0; $srcY = (int)(($sourceHeight - $srcHeight) / 2);
                }
                $newWidth = $targetWidth; $newHeight = $targetHeight;
                break;
            case 'fit':
            default:
                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;
                if ($sourceRatio > $targetRatio) {
                    $newWidth = $targetWidth; $newHeight = (int)($targetWidth / $sourceRatio);
                } else {
                    $newHeight = $targetHeight; $newWidth = (int)($targetHeight * $sourceRatio);
                }
                $srcX = $srcY = 0; $srcWidth = $sourceWidth; $srcHeight = $sourceHeight;
                break;
        }

        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($supportsTransparency) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);
            imagealphablending($newImage, true);
        } else {
            $backgroundColor = imagecolorallocate($newImage, 255, 255, 255);
            imagefill($newImage, 0, 0, $backgroundColor);
        }

        $destX = (int)(($targetWidth - $newWidth) / 2);
        $destY = (int)(($targetHeight - $newHeight) / 2);
        if ($mode === 'fill' || $mode === 'stretch') $destX = $destY = 0;

        imagecopyresampled($newImage, $sourceImage, $destX, $destY, $srcX, $srcY, $newWidth, $newHeight, $srcWidth, $srcHeight);
        
        if ($supportsTransparency) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        return $newImage;
    }

    /**
     * euclidean color distance
     */
    private static function colorDifference($color1, $color2)
    {
        $r1 = ($color1 >> 16) & 0xFF; $g1 = ($color1 >> 8) & 0xFF; $b1 = $color1 & 0xFF;
        $r2 = ($color2 >> 16) & 0xFF; $g2 = ($color2 >> 8) & 0xFF; $b2 = $color2 & 0xFF;
        return sqrt(pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2));
    }

    /**
     * Check if image has transparency
     */
    public static function hasTransparency($image, $type)
    {
        if ($type === IMAGETYPE_JPEG) return false;
        if ($type === IMAGETYPE_GIF) return imagecolortransparent($image) >= 0;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Sample points for transparency
        $samplePoints = min(200, ($width * $height) / 100);
        for ($i = 0; $i < $samplePoints; $i++) {
            $color = imagecolorat($image, rand(0, $width - 1), rand(0, $height - 1));
            if ((($color & 0x7F000000) >> 24) > 0) return true;
        }
        return false;
    }

    /**
     * Convert to WebP
     */
    public static function saveToWebP($image, $path, $quality = 90)
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $result = imagewebp($image, $path, $quality);
        if ($result) chmod($path, 0644);
        return $result;
    }

    /**
     * Convert to PNG
     */
    public static function saveToPNG($image, $path, $compression = 1)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $newImg = imagecreatetruecolor($width, $height);
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
        $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
        imagefill($newImg, 0, 0, $transparent);
        imagecopy($newImg, $image, 0, 0, 0, 0, $width, $height);
        
        $result = imagepng($newImg, $path, $compression);
        imagedestroy($newImg);
        if ($result) chmod($path, 0644);
        return $result;
    }
}
