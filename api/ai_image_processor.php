<?php
/**
 * AI Image Processor - Conductor
 *
 * Automatically processes images to crop to the outermost edges of objects.
 * Uses AI vision analysis when available, with fallback edge detection.
 * Supports WebP conversion and transparency preservation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';
require_once __DIR__ . '/../includes/ai/helpers/GDImageHelper.php';
require_once __DIR__ . '/../includes/ai/helpers/VisionHeuristics.php';

class AIImageProcessor
{
    private $pdo;
    private $aiProviders;

    public function __construct()
    {
        try {
            $this->pdo = Database::getInstance();
            $this->aiProviders = getAIProviders();
        } catch (Exception $e) {
            error_log("AIImageProcessor initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Process image with automatic edge detection and cropping
     */
    public function processImage($imagePath, $options = [])
    {
        $defaults = [
            'convertToWebP' => true,
            'quality' => 90,
            'preserveTransparency' => true,
            'useAI' => true,
            'fallbackTrimPercent' => 0.05
        ];

        $opts = array_merge($defaults, $options);

        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }

        $result = [
            'success' => false,
            'original_path' => $imagePath,
            'processed_path' => null,
            'processing_steps' => [],
            'ai_analysis' => null,
            'crop_data' => null
        ];

        try {
            // Step 1: AI Analysis or Heuristic edge detection
            $result['processing_steps'][] = 'Starting edge analysis...';

            $cropBounds = null;
            if ($opts['useAI'] && $this->aiProviders->currentModelSupportsImages()) {
                $response = $this->aiProviders->detectObjectBoundaries($imagePath);
                $cropBounds = VisionHeuristics::validateCropBounds($response);
                if ($cropBounds) {
                    $result['ai_analysis'] = $cropBounds;
                    $result['processing_steps'][] = 'AI edge detection successful';
                }
            }

            if (!$cropBounds) {
                $result['processing_steps'][] = 'Using heuristic edge detection';
                $cropBounds = GDImageHelper::detectEdges($imagePath);
                if (!$cropBounds) {
                    $cropBounds = VisionHeuristics::getFallbackCropBounds($imagePath, $opts['fallbackTrimPercent']);
                }
            }

            // Step 2: Apply cropping
            $result['processing_steps'][] = 'Applying smart cropping...';
            $imageInfo = getimagesize($imagePath);
            $sourceImage = GDImageHelper::createImageResource($imagePath, $imageInfo[2]);
            
            if (!$sourceImage) throw new Exception("Failed to create image resource");

            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            $cropX = (int)($cropBounds['left'] * $sourceWidth);
            $cropY = (int)($cropBounds['top'] * $sourceHeight);
            $cropWidth = (int)(($cropBounds['right'] - $cropBounds['left']) * $sourceWidth);
            $cropHeight = (int)(($cropBounds['bottom'] - $cropBounds['top']) * $sourceHeight);

            $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
            if ($opts['preserveTransparency']) {
                imagealphablending($croppedImage, false);
                imagesavealpha($croppedImage, true);
                $transparent = imagecolorallocatealpha($croppedImage, 0, 0, 0, 127);
                imagefill($croppedImage, 0, 0, $transparent);
            }

            imagecopyresampled($croppedImage, $sourceImage, 0, 0, $cropX, $cropY, $cropWidth, $cropHeight, $cropWidth, $cropHeight);
            imagedestroy($sourceImage);

            $result['crop_data'] = $cropBounds;

            // Step 3: Save / Convert
            if ($opts['convertToWebP']) {
                $result['processing_steps'][] = 'Converting to WebP...';
                $pathInfo = pathinfo($imagePath);
                $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
                GDImageHelper::saveToWebP($croppedImage, $webpPath, $opts['quality']);
                
                if ($imagePath !== $webpPath && file_exists($imagePath)) {
                    // Decide if we overwrite or keep. Original logic overwrote if same ext.
                    // If converting to webp, we usually keep original or rename.
                }
                $result['processed_path'] = $webpPath;
            } else {
                $result['processing_steps'][] = 'Saving processed image...';
                if ($imageInfo[2] === IMAGETYPE_PNG) {
                    GDImageHelper::saveToPNG($croppedImage, $imagePath);
                } elseif ($imageInfo[2] === IMAGETYPE_WEBP) {
                    GDImageHelper::saveToWebP($croppedImage, $imagePath, $opts['quality']);
                } elseif ($imageInfo[2] === IMAGETYPE_GIF && $opts['preserveTransparency']) {
                    $pathInfo = pathinfo($imagePath);
                    $gifSafePngPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '__wf_proc.png';
                    GDImageHelper::saveToPNG($croppedImage, $gifSafePngPath, 1);
                    $result['processed_path'] = $gifSafePngPath;
                    imagedestroy($croppedImage);
                    $result['processing_steps'][] = 'Processing completed successfully';
                    $result['success'] = true;
                    return $result;
                } else {
                    imagejpeg($croppedImage, $imagePath, $opts['quality']);
                }
                $result['processed_path'] = $imagePath;
            }

            imagedestroy($croppedImage);
            $result['processing_steps'][] = 'Processing completed successfully';
            $result['success'] = true;

        } catch (Exception $e) {
            $result['processing_steps'][] = 'Error: ' . $e->getMessage();
            error_log("AI Image Processor Error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Process background image with resizing and dual format optimization
     */
    public function processBackgroundImage($imagePath, $options = [])
    {
        $defaults = [
            'createDualFormat' => true,
            'webp_quality' => 90,
            'png_compression' => 1,
            'preserve_transparency' => true,
            'resizeDimensions' => ['width' => 1920, 'height' => 1080],
            'resizeMode' => 'fit'
        ];

        $opts = array_merge($defaults, $options);
        if (!file_exists($imagePath)) throw new Exception("Image not found");

        $result = [
            'success' => false,
            'original_path' => $imagePath,
            'processing_steps' => []
        ];

        try {
            $imageInfo = getimagesize($imagePath);
            $sourceImage = GDImageHelper::createImageResource($imagePath, $imageInfo[2]);
            if (!$sourceImage) throw new Exception("Failed to load image");

            $result['original_dimensions'] = ['width' => imagesx($sourceImage), 'height' => imagesy($sourceImage)];
            
            // Perform resize
            $resizedImage = GDImageHelper::resize(
                $sourceImage, 
                $opts['resizeDimensions'], 
                $opts['resizeMode'], 
                $opts['preserve_transparency']
            );
            
            $pathInfo = pathinfo($imagePath);
            $basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];

            if ($opts['createDualFormat']) {
                $result['webp_path'] = $basePath . '.webp';
                GDImageHelper::saveToWebP($resizedImage, $result['webp_path'], $opts['webp_quality']);
                
                $result['png_path'] = $basePath . '.png';
                GDImageHelper::saveToPNG($resizedImage, $result['png_path'], $opts['png_compression']);
                
                $result['processed_path'] = $result['webp_path'];
                $result['formats_created'] = ['webp', 'png'];
            } else {
                $outputPath = $basePath . '_processed.' . $pathInfo['extension'];
                if ($imageInfo[2] === IMAGETYPE_WEBP) {
                    GDImageHelper::saveToWebP($resizedImage, $outputPath, $opts['webp_quality']);
                } elseif ($imageInfo[2] === IMAGETYPE_PNG) {
                    GDImageHelper::saveToPNG($resizedImage, $outputPath, $opts['png_compression']);
                } else {
                    imagejpeg($resizedImage, $outputPath, $opts['webp_quality']);
                }
                $result['processed_path'] = $outputPath;
            }

            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            $result['success'] = true;
            $result['processing_steps'][] = 'Background processing complete';
        } catch (Exception $e) {
            $result['processing_steps'][] = 'Error: ' . $e->getMessage();
            error_log("Background Processor Error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Convert an image to dual output formats (WebP + optional PNG fallback).
     */
    public function convertToDualFormat($imagePath, $options = [])
    {
        $defaults = [
            'webp_quality' => 90,
            'png_compression' => 1,
            'preserve_transparency' => true,
            'force_png' => true
        ];
        $opts = array_merge($defaults, $options);

        $result = [
            'success' => false,
            'source_path' => $imagePath,
            'webp_path' => null,
            'png_path' => null
        ];

        if (!file_exists($imagePath)) {
            return $result;
        }

        try {
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                throw new Exception('Invalid image for conversion');
            }

            $sourceImage = GDImageHelper::createImageResource($imagePath, $imageInfo[2]);
            if (!$sourceImage) {
                throw new Exception('Failed to open source image for conversion');
            }

            $pathInfo = pathinfo($imagePath);
            $basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
            $webpPath = $basePath . '.webp';
            $pngPath = $basePath . '.png';

            if (!GDImageHelper::saveToWebP($sourceImage, $webpPath, (int)$opts['webp_quality'])) {
                imagedestroy($sourceImage);
                throw new Exception('Failed to generate WebP output');
            }
            $result['webp_path'] = $webpPath;

            if (!empty($opts['force_png'])) {
                if (!GDImageHelper::saveToPNG($sourceImage, $pngPath, (int)$opts['png_compression'])) {
                    imagedestroy($sourceImage);
                    throw new Exception('Failed to generate PNG output');
                }
                $result['png_path'] = $pngPath;
            }

            imagedestroy($sourceImage);
            $result['success'] = true;
        } catch (Throwable $e) {
            error_log('convertToDualFormat failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Update database with processing information
     */
    public function updateProcessedImageRecord($sku, $originalPath, $processedPath, $processingData)
    {
        try {
            Database::execute("
                UPDATE item_images 
                SET processed_with_ai = 1, original_path = ?, processing_date = NOW(), ai_trim_data = ?
                WHERE sku = ? AND image_path = ?
            ", [$originalPath, json_encode($processingData), $sku, $processedPath]);
        } catch (Exception $e) {
            error_log("Failed to update processed image record: " . $e->getMessage());
        }
    }
}
