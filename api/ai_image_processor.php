<?php
/**
 * AI Image Processor
 * 
 * Automatically processes images to crop to the outermost edges of objects
 * Uses AI vision analysis when available, with fallback edge detection
 * Supports WebP conversion and transparency preservation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';

class AIImageProcessor {
    private $pdo;
    private $aiProviders;
// __construct function moved to constructor_manager.php for centralization
    
    /**
     * Process image with automatic edge detection and cropping
     */
    public function processImage($imagePath, $options = []) {
        $defaults = [
            'convertToWebP' => true,
            'quality' => 90,
            'preserveTransparency' => true,
            'useAI' => true,
            'fallbackTrimPercent' => 0.05 // 5% trim from each edge as fallback
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
            // Step 1: AI Analysis for edge detection
            $result['processing_steps'][] = 'Starting AI edge analysis...';
            
            if ($opts['useAI']) {
                $cropBounds = $this->analyzeImageEdgesWithAI($imagePath);
                if ($cropBounds) {
                    $result['ai_analysis'] = $cropBounds;
                    $result['processing_steps'][] = 'AI edge detection successful';
                } else {
                    $result['processing_steps'][] = 'AI edge detection failed, using fallback';
                    $cropBounds = $this->getFallbackCropBounds($imagePath, $opts['fallbackTrimPercent']);
                }
            } else {
                $result['processing_steps'][] = 'Using fallback edge detection';
                $cropBounds = $this->getFallbackCropBounds($imagePath, $opts['fallbackTrimPercent']);
            }
            
            // Step 2: Apply cropping
            $result['processing_steps'][] = 'Applying smart cropping...';
            $croppedPath = $this->applyCropping($imagePath, $cropBounds, $opts);
            $result['crop_data'] = $cropBounds;
            
            // Step 3: Convert to WebP if requested
            if ($opts['convertToWebP']) {
                $result['processing_steps'][] = 'Converting to WebP format...';
                $webpPath = $this->convertToWebP($croppedPath, $opts['quality'], $opts['preserveTransparency']);
                
                // Clean up intermediate file if different
                if ($croppedPath !== $imagePath && $croppedPath !== $webpPath) {
                    unlink($croppedPath);
                }
                
                $result['processed_path'] = $webpPath;
            } else {
                $result['processed_path'] = $croppedPath;
            }
            
            $result['processing_steps'][] = 'Processing completed successfully';
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['processing_steps'][] = 'Error: ' . $e->getMessage();
            error_log("AI Image Processor Error: " . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Analyze image edges using AI vision
     */
    private function analyzeImageEdgesWithAI($imagePath) {
        try {
            // Check if AI provider supports image analysis
            if (!$this->aiProviders->currentModelSupportsImages()) {
                return null;
            }
            
            $prompt = "Analyze this image and determine the optimal crop boundaries to show only the main object(s) with minimal background. 
                      Please identify the outermost edges of the main subject and provide crop coordinates.
                      Respond with JSON in this exact format:
                      {
                        \"crop_left_percent\": 0.1,
                        \"crop_top_percent\": 0.15,
                        \"crop_right_percent\": 0.9,
                        \"crop_bottom_percent\": 0.85,
                        \"confidence\": 0.8,
                        \"description\": \"Detected main object boundaries\"
                      }
                      
                      The percentages should be decimal values (0.0 to 1.0) representing the position relative to the image dimensions.
                      crop_left_percent: left edge of crop area (0.0 = far left)
                      crop_top_percent: top edge of crop area (0.0 = top)
                      crop_right_percent: right edge of crop area (1.0 = far right)
                      crop_bottom_percent: bottom edge of crop area (1.0 = bottom)";
            
            $response = $this->makeAIImageAnalysisCall($imagePath, $prompt);
            
            if ($response) {
                // Validate and sanitize the response
                return $this->validateCropBounds($response);
            }
            
        } catch (Exception $e) {
            error_log("AI edge analysis failed: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Make AI image analysis call using configured provider
     */
    private function makeAIImageAnalysisCall($imagePath, $prompt) {
        try {
            $settings = $this->aiProviders->getSettings();
            $provider = $settings['ai_provider'];
            
            switch ($provider) {
                case 'openai':
                    return $this->analyzeWithOpenAI($imagePath, $prompt, $settings);
                case 'anthropic':
                    return $this->analyzeWithAnthropic($imagePath, $prompt, $settings);
                case 'google':
                    return $this->analyzeWithGoogle($imagePath, $prompt, $settings);
                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log("AI analysis call failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * OpenAI vision analysis
     */
    private function analyzeWithOpenAI($imagePath, $prompt, $settings) {
        $apiKey = $settings['openai_api_key'];
        if (empty($apiKey)) {
            return null;
        }
        
        $imageData = $this->imageToBase64($imagePath);
        
        $data = [
            'model' => 'gpt-4o', // Use vision model
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$imageData['mime_type']};base64,{$imageData['data']}"
                            ]
                        ]
                    ]
                ]
            ],
            'temperature' => 0.1, // Low temperature for consistent edge detection
            'max_tokens' => 300
        ];
        
        $response = $this->makeAPICall(
            'https://api.openai.com/v1/chat/completions',
            $data,
            [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]
        );
        
        if ($response && isset($response['choices'][0]['message']['content'])) {
            return json_decode($response['choices'][0]['message']['content'], true);
        }
        
        return null;
    }
    
    /**
     * Anthropic vision analysis
     */
    private function analyzeWithAnthropic($imagePath, $prompt, $settings) {
        $apiKey = $settings['anthropic_api_key'];
        if (empty($apiKey)) {
            return null;
        }
        
        $imageData = $this->imageToBase64($imagePath);
        
        $data = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 300,
            'temperature' => 0.1,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $imageData['mime_type'],
                                'data' => $imageData['data']
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        $response = $this->makeAPICall(
            'https://api.anthropic.com/v1/messages',
            $data,
            [
                'x-api-key: ' . $apiKey,
                'Content-Type: application/json',
                'anthropic-version: 2023-06-01'
            ]
        );
        
        if ($response && isset($response['content'][0]['text'])) {
            return json_decode($response['content'][0]['text'], true);
        }
        
        return null;
    }
    
    /**
     * Google vision analysis
     */
    private function analyzeWithGoogle($imagePath, $prompt, $settings) {
        $apiKey = $settings['google_api_key'];
        if (empty($apiKey)) {
            return null;
        }
        
        $imageData = $this->imageToBase64($imagePath);
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $imageData['mime_type'],
                                'data' => $imageData['data']
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 300
            ]
        ];
        
        $model = 'gemini-1.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        
        $response = $this->makeAPICall($url, $data, ['Content-Type: application/json']);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return json_decode($response['candidates'][0]['content']['parts'][0]['text'], true);
        }
        
        return null;
    }
    
    /**
     * Validate and sanitize crop bounds from AI response
     */
    private function validateCropBounds($response) {
        if (!is_array($response)) {
            return null;
        }
        
        $required = ['crop_left_percent', 'crop_top_percent', 'crop_right_percent', 'crop_bottom_percent'];
        foreach ($required as $field) {
            if (!isset($response[$field]) || !is_numeric($response[$field])) {
                return null;
            }
        }
        
        // Sanitize values to ensure they're within valid ranges
        $bounds = [
            'left' => max(0, min(0.9, floatval($response['crop_left_percent']))),
            'top' => max(0, min(0.9, floatval($response['crop_top_percent']))),
            'right' => max(0.1, min(1.0, floatval($response['crop_right_percent']))),
            'bottom' => max(0.1, min(1.0, floatval($response['crop_bottom_percent']))),
            'confidence' => floatval($response['confidence'] ?? 0.5),
            'description' => $response['description'] ?? 'AI-detected crop bounds'
        ];
        
        // Ensure right > left and bottom > top
        if ($bounds['right'] <= $bounds['left']) {
            $bounds['right'] = $bounds['left'] + 0.1;
        }
        if ($bounds['bottom'] <= $bounds['top']) {
            $bounds['bottom'] = $bounds['top'] + 0.1;
        }
        
        return $bounds;
    }
    
    /**
     * Get fallback crop bounds with basic edge trimming
     */
    private function getFallbackCropBounds($imagePath, $trimPercent = 0.05) {
        // Try basic edge detection using GD
        try {
            $edgeBounds = $this->detectEdgesWithGD($imagePath);
            if ($edgeBounds) {
                return $edgeBounds;
            }
        } catch (Exception $e) {
            error_log("GD edge detection failed: " . $e->getMessage());
        }
        
        // Ultimate fallback: symmetric trim
        return [
            'left' => $trimPercent,
            'top' => $trimPercent,
            'right' => 1.0 - $trimPercent,
            'bottom' => 1.0 - $trimPercent,
            'confidence' => 0.3,
            'description' => 'Fallback symmetric trim'
        ];
    }
    
    /**
     * Basic edge detection using GD library
     */
    private function detectEdgesWithGD($imagePath) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return null;
        }
        
        // Create image resource based on type
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($imagePath);
                break;
            default:
                return null;
        }
        
        if (!$image) {
            return null;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Find bounds by scanning for non-background pixels
        $bounds = $this->findContentBounds($image, $width, $height);
        
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
     * Find content bounds by scanning pixels
     */
    private function findContentBounds($image, $width, $height) {
        // Sample corners to determine background color
        $cornerColors = [
            imagecolorat($image, 0, 0),
            imagecolorat($image, $width - 1, 0),
            imagecolorat($image, 0, $height - 1),
            imagecolorat($image, $width - 1, $height - 1)
        ];
        
        // Use most common corner color as background
        $bgColor = array_count_values($cornerColors);
        arsort($bgColor);
        $backgroundColor = array_key_first($bgColor);
        
        // Find bounds
        $left = $width;
        $right = 0;
        $top = $height;
        $bottom = 0;
        
        $tolerance = 30; // Color difference tolerance
        
        // Scan for non-background pixels
        for ($y = 0; $y < $height; $y += 2) { // Skip every other row for performance
            for ($x = 0; $x < $width; $x += 2) { // Skip every other column for performance
                $color = imagecolorat($image, $x, $y);
                
                if ($this->colorDifference($color, $backgroundColor) > $tolerance) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }
        
        // Add small padding
        $padding = min($width, $height) * 0.02; // 2% padding
        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min($width, $right + $padding);
        $bottom = min($height, $bottom + $padding);
        
        // Validate bounds
        if ($left < $right && $top < $bottom && 
            ($right - $left) > $width * 0.1 && 
            ($bottom - $top) > $height * 0.1) {
            return [
                'left' => $left,
                'top' => $top,
                'right' => $right,
                'bottom' => $bottom
            ];
        }
        
        return null;
    }
    
    /**
     * Calculate color difference
     */
    private function colorDifference($color1, $color2) {
        $r1 = ($color1 >> 16) & 0xFF;
        $g1 = ($color1 >> 8) & 0xFF;
        $b1 = $color1 & 0xFF;
        
        $r2 = ($color2 >> 16) & 0xFF;
        $g2 = ($color2 >> 8) & 0xFF;
        $b2 = $color2 & 0xFF;
        
        return sqrt(pow($r1 - $r2, 2) + pow($g1 - $g2, 2) + pow($b1 - $b2, 2));
    }
    
    /**
     * Apply cropping to image
     */
    private function applyCropping($imagePath, $cropBounds, $options) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception("Cannot read image information");
        }
        
        // Create source image
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($imagePath);
                break;
            default:
                throw new Exception("Unsupported image type");
        }
        
        if (!$sourceImage) {
            throw new Exception("Failed to create image resource");
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Calculate crop dimensions
        $cropX = (int)($cropBounds['left'] * $sourceWidth);
        $cropY = (int)($cropBounds['top'] * $sourceHeight);
        $cropWidth = (int)(($cropBounds['right'] - $cropBounds['left']) * $sourceWidth);
        $cropHeight = (int)(($cropBounds['bottom'] - $cropBounds['top']) * $sourceHeight);
        
        // Create cropped image
        $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
        
        // Preserve transparency for PNG/WebP
        if ($options['preserveTransparency']) {
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            $transparent = imagecolorallocatealpha($croppedImage, 0, 0, 0, 127);
            imagefill($croppedImage, 0, 0, $transparent);
        }
        
        // Copy and resize
        imagecopyresampled(
            $croppedImage, $sourceImage,
            0, 0, $cropX, $cropY,
            $cropWidth, $cropHeight, $cropWidth, $cropHeight
        );
        
        // Generate output path (overwrite original)
        $pathInfo = pathinfo($imagePath);
        $outputPath = $imagePath; // Use original path to overwrite
        
        // Save cropped image
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                imagejpeg($croppedImage, $outputPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($croppedImage, $outputPath, 1);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($croppedImage, $outputPath, 90);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($croppedImage);
        
        // Set permissions
        chmod($outputPath, 0644);
        
        return $outputPath;
    }
    
    /**
     * Convert image to dual format (PNG + WebP) for maximum compatibility
     */
    public function convertToDualFormat($imagePath, $options = []) {
        $defaults = [
            'webp_quality' => 90,
            'png_compression' => 1, // 0-9, 1 is good quality/size balance
            'preserve_transparency' => true,
            'force_png' => false // Force PNG creation even if source is PNG
        ];
        
        $opts = array_merge($defaults, $options);
        
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception("Cannot read image for dual format conversion");
        }
        
        $result = [
            'success' => false,
            'png_path' => null,
            'webp_path' => null,
            'original_format' => null,
            'has_transparency' => false
        ];
        
        // Determine original format
        $result['original_format'] = image_type_to_extension($imageInfo[2], false);
        
        // Create source image and check for transparency
        $sourceImage = $this->createImageResource($imagePath, $imageInfo[2]);
        if (!$sourceImage) {
            throw new Exception("Failed to create image resource for dual format conversion");
        }
        
        // Check for transparency
        $result['has_transparency'] = $this->hasTransparency($sourceImage, $imageInfo[2]);
        
        $pathInfo = pathinfo($imagePath);
        $basePath = $pathInfo['dirname'] . '/' . $pathInfo['filename'];
        
        try {
            // Create PNG version (lossless, preserves transparency)
            $pngPath = $basePath . '.png';
            if ($opts['force_png'] || $imageInfo[2] !== IMAGETYPE_PNG) {
                $result['png_path'] = $this->convertToPNG($sourceImage, $pngPath, $opts);
            } else {
                // Already PNG, just copy/use existing
                $result['png_path'] = $imagePath;
            }
            
            // Create WebP version (high quality, preserves transparency)
            $webpPath = $basePath . '.webp';
            $result['webp_path'] = $this->convertToWebP($sourceImage, $webpPath, $opts);
            
            $result['success'] = true;
            
        } finally {
            imagedestroy($sourceImage);
        }
        
        return $result;
    }
    
    /**
     * Convert image to PNG format (lossless) with enhanced transparency preservation
     */
    private function convertToPNG($sourceImage, $outputPath, $options = []) {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        // Create new PNG image with proper transparency support
        $pngImage = imagecreatetruecolor($width, $height);
        
        // Enhanced transparency preservation for backgrounds and complex images
        if ($options['preserve_transparency']) {
            // Disable alpha blending and enable alpha saving
            imagealphablending($pngImage, false);
            imagesavealpha($pngImage, true);
            
            // Create fully transparent background
            $transparent = imagecolorallocatealpha($pngImage, 0, 0, 0, 127);
            imagefill($pngImage, 0, 0, $transparent);
            
            // Re-enable alpha blending for proper copying
            imagealphablending($pngImage, true);
            
            // Ensure source image alpha is preserved
            imagesavealpha($sourceImage, true);
        }
        
        // Copy image data with alpha channel preservation
        imagecopy($pngImage, $sourceImage, 0, 0, 0, 0, $width, $height);
        
        // Final alpha preservation before saving
        if ($options['preserve_transparency']) {
            imagealphablending($pngImage, false);
            imagesavealpha($pngImage, true);
        }
        
        // Save PNG with compression (0-9, where 0 = no compression, 9 = max compression)
        $compression = $options['png_compression'] ?? 1; // Low compression for quality
        if (!imagepng($pngImage, $outputPath, $compression)) {
            imagedestroy($pngImage);
            throw new Exception("Failed to create PNG with transparency");
        }
        
        imagedestroy($pngImage);
        chmod($outputPath, 0644);
        
        return $outputPath;
    }
    
    /**
     * Convert image to WebP format (high quality) with enhanced transparency preservation
     */
    private function convertToWebP($sourceImage, $outputPath, $options = []) {
        $quality = $options['webp_quality'] ?? 90;
        
        // Enhanced WebP transparency support for backgrounds
        if ($options['preserve_transparency']) {
            // Ensure alpha blending is properly configured for WebP
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
        }
        
        // Convert to WebP with transparency support
        // Note: WebP supports both lossy and lossless compression with transparency
        if (!imagewebp($sourceImage, $outputPath, $quality)) {
            throw new Exception("Failed to convert to WebP with transparency support");
        }
        
        chmod($outputPath, 0644);
        
        return $outputPath;
    }
    
    /**
     * Create image resource from file with enhanced transparency support
     */
    private function createImageResource($imagePath, $imageType) {
        $image = null;
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                if ($image) {
                    // Essential for PNG transparency preservation
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            case IMAGETYPE_WEBP:
                $image = imagecreatefromwebp($imagePath);
                if ($image) {
                    // Preserve WebP transparency
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                }
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($imagePath);
                if ($image) {
                    // Handle GIF transparency
                    $transparentIndex = imagecolortransparent($image);
                    if ($transparentIndex >= 0) {
                        imagealphablending($image, false);
                        imagesavealpha($image, true);
                    }
                }
                break;
            default:
                throw new Exception("Unsupported image type: " . $imageType);
        }
        
        if (!$image) {
            throw new Exception("Failed to create image resource from: " . $imagePath);
        }
        
        return $image;
    }
    
    /**
     * Check if image has transparency (enhanced detection for backgrounds)
     */
    private function hasTransparency($image, $imageType) {
        if ($imageType === IMAGETYPE_JPEG) {
            return false; // JPEG doesn't support transparency
        }
        
        if ($imageType === IMAGETYPE_GIF) {
            return imagecolortransparent($image) >= 0;
        }
        
        if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP) {
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Enhanced transparency detection for background images
            // Check corners first (common for backgrounds with transparent edges)
            $cornerChecks = [
                [0, 0], [0, $height-1], [$width-1, 0], [$width-1, $height-1]
            ];
            
            foreach ($cornerChecks as $point) {
                $color = imagecolorat($image, $point[0], $point[1]);
                $alpha = ($color & 0x7F000000) >> 24;
                if ($alpha > 0) {
                    return true; // Found transparency in corners
                }
            }
            
            // Sample edge pixels (backgrounds often have transparent edges)
            $edgeChecks = [
                // Top edge
                [$width/4, 0], [$width/2, 0], [3*$width/4, 0],
                // Bottom edge
                [$width/4, $height-1], [$width/2, $height-1], [3*$width/4, $height-1],
                // Left edge
                [0, $height/4], [0, $height/2], [0, 3*$height/4],
                // Right edge
                [$width-1, $height/4], [$width-1, $height/2], [$width-1, 3*$height/4]
            ];
            
            foreach ($edgeChecks as $point) {
                $x = (int)$point[0];
                $y = (int)$point[1];
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color & 0x7F000000) >> 24;
                if ($alpha > 0) {
                    return true; // Found transparency in edges
                }
            }
            
            // Sample random pixels throughout image for comprehensive check
            $samplePoints = min(200, $width * $height / 100); // More samples for better detection
            for ($i = 0; $i < $samplePoints; $i++) {
                $x = rand(0, $width - 1);
                $y = rand(0, $height - 1);
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color & 0x7F000000) >> 24;
                if ($alpha > 0) {
                    return true; // Found transparency
                }
            }
        }
        
        return false;
    }
    
    /**
     * Utility methods
     */
    private function imageToBase64($imagePath) {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }
        
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            throw new Exception("Failed to read image file: " . $imagePath);
        }
        
        $mimeType = mime_content_type($imagePath);
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            throw new Exception("Unsupported image type: " . $mimeType);
        }
        
        return [
            'data' => base64_encode($imageData),
            'mime_type' => $mimeType
        ];
    }
    
    private function makeAPICall($url, $data, $headers) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            throw new Exception("API call failed with HTTP code: " . $httpCode);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Process background image with resizing and dual format optimization
     */
    public function processBackgroundImage($imagePath, $options = []) {
        $defaults = [
            'createDualFormat' => true, // Create both PNG and WebP
            'webp_quality' => 90,
            'png_compression' => 1,
            'preserve_transparency' => true, // Backgrounds may have transparency
            'useAI' => false, // Background images typically don't need AI edge detection
            'resizeDimensions' => ['width' => 1920, 'height' => 1080],
            'resizeMode' => 'fit' // 'fit', 'fill', 'stretch'
        ];
        
        $opts = array_merge($defaults, $options);
        
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }
        
        $result = [
            'success' => false,
            'original_path' => $imagePath,
            'png_path' => null,
            'webp_path' => null,
            'processed_path' => null, // Primary path (WebP if dual format)
            'original_processed_path' => null,
            'processing_steps' => [],
            'original_dimensions' => null,
            'final_dimensions' => $opts['resizeDimensions'],
            'formats_created' => []
        ];
        
        try {
            // Get original image dimensions
            $imageInfo = getimagesize($imagePath);
            if (!$imageInfo) {
                throw new Exception("Cannot read image information");
            }
            
            $result['original_dimensions'] = [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1]
            ];
            
            $result['processing_steps'][] = 'Starting background image processing...';
            
            // Step 1: Resize image to target dimensions
            $result['processing_steps'][] = 'Resizing image to target dimensions...';
            $resizedPath = $this->resizeImage($imagePath, $opts['resizeDimensions'], $opts['resizeMode']);
            $result['original_processed_path'] = $resizedPath;
            
            // Step 2: Create dual format (PNG + WebP) for browser compatibility
            if ($opts['createDualFormat']) {
                $result['processing_steps'][] = 'Creating dual format (PNG + WebP) for maximum compatibility...';
                
                $dualFormatOptions = [
                    'webp_quality' => $opts['webp_quality'],
                    'png_compression' => $opts['png_compression'],
                    'preserve_transparency' => $opts['preserve_transparency'],
                    'force_png' => true // Always create PNG for compliance
                ];
                
                $formatResult = $this->convertToDualFormat($resizedPath, $dualFormatOptions);
                
                if ($formatResult['success']) {
                    $result['png_path'] = $formatResult['png_path'];
                    $result['webp_path'] = $formatResult['webp_path'];
                    $result['processed_path'] = $formatResult['webp_path']; // Primary is WebP
                    
                    if ($formatResult['png_path']) {
                        $result['formats_created'][] = 'PNG (lossless, browser compliance)';
                    }
                    if ($formatResult['webp_path']) {
                        $result['formats_created'][] = 'WebP (optimized, modern browsers)';
                    }
                    
                    if ($formatResult['has_transparency']) {
                        $result['processing_steps'][] = 'Transparency preserved in both formats';
                    }
                } else {
                    throw new Exception('Dual format conversion failed');
                }
            } else {
                // Fallback to single format
                $result['processed_path'] = $resizedPath;
                $result['formats_created'][] = 'Original format only';
            }
            
            $result['processing_steps'][] = 'Background processing completed successfully';
            $result['success'] = true;
            
        } catch (Exception $e) {
            $result['processing_steps'][] = 'Error: ' . $e->getMessage();
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Resize image to specific dimensions
     */
    private function resizeImage($imagePath, $targetDimensions, $mode = 'fit') {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception("Cannot read image for resizing");
        }
        
        // Create source image with enhanced transparency support
        $sourceImage = $this->createImageResource($imagePath, $imageInfo[2]);
        
        if (!$sourceImage) {
            throw new Exception("Failed to create image resource for resizing");
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        $targetWidth = $targetDimensions['width'];
        $targetHeight = $targetDimensions['height'];
        
        // Calculate dimensions based on resize mode
        switch ($mode) {
            case 'stretch':
                // Stretch to exact dimensions (may distort aspect ratio)
                $newWidth = $targetWidth;
                $newHeight = $targetHeight;
                $srcX = $srcY = 0;
                $srcWidth = $sourceWidth;
                $srcHeight = $sourceHeight;
                break;
                
            case 'fill':
                // Fill entire target area (may crop image)
                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;
                
                if ($sourceRatio > $targetRatio) {
                    // Source is wider - crop width
                    $srcHeight = $sourceHeight;
                    $srcWidth = (int)($sourceHeight * $targetRatio);
                    $srcX = (int)(($sourceWidth - $srcWidth) / 2);
                    $srcY = 0;
                } else {
                    // Source is taller - crop height
                    $srcWidth = $sourceWidth;
                    $srcHeight = (int)($sourceWidth / $targetRatio);
                    $srcX = 0;
                    $srcY = (int)(($sourceHeight - $srcHeight) / 2);
                }
                
                $newWidth = $targetWidth;
                $newHeight = $targetHeight;
                break;
                
            case 'fit':
            default:
                // Fit within dimensions (maintain aspect ratio, may have padding)
                $sourceRatio = $sourceWidth / $sourceHeight;
                $targetRatio = $targetWidth / $targetHeight;
                
                if ($sourceRatio > $targetRatio) {
                    // Source is wider - fit to width
                    $newWidth = $targetWidth;
                    $newHeight = (int)($targetWidth / $sourceRatio);
                } else {
                    // Source is taller - fit to height
                    $newHeight = $targetHeight;
                    $newWidth = (int)($targetHeight * $sourceRatio);
                }
                
                $srcX = $srcY = 0;
                $srcWidth = $sourceWidth;
                $srcHeight = $sourceHeight;
                break;
        }
        
        // Create new image with proper transparency handling
        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // Enhanced transparency preservation for background images
        $supportsTransparency = in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF]);
        
        if ($supportsTransparency) {
            // Preserve transparency for PNG/WebP/GIF backgrounds
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);
            imagealphablending($newImage, true);
        } else {
            // Set white background for JPEG (no transparency support)
            $backgroundColor = imagecolorallocate($newImage, 255, 255, 255);
            imagefill($newImage, 0, 0, $backgroundColor);
        }
        
        // Center image if using 'fit' mode
        $destX = (int)(($targetWidth - $newWidth) / 2);
        $destY = (int)(($targetHeight - $newHeight) / 2);
        
        // For fill and stretch modes, use full area
        if ($mode === 'fill' || $mode === 'stretch') {
            $destX = $destY = 0;
        }
        
        // Perform the resize with proper alpha handling
        imagecopyresampled(
            $newImage, $sourceImage,
            $destX, $destY, $srcX, $srcY,
            $newWidth, $newHeight, $srcWidth, $srcHeight
        );
        
        // Final transparency preservation step before saving
        if ($supportsTransparency) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }
        
        // Generate output path
        $pathInfo = pathinfo($imagePath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_resized.' . $pathInfo['extension'];
        
        // Save resized image with transparency preservation
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $outputPath, 90);
                break;
            case IMAGETYPE_PNG:
                // Use low compression to preserve transparency quality
                imagepng($newImage, $outputPath, 1);
                break;
            case IMAGETYPE_WEBP:
                // High quality WebP with transparency support
                imagewebp($newImage, $outputPath, 90);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $outputPath);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        // Set permissions
        chmod($outputPath, 0644);
        
        return $outputPath;
    }

    /**
     * Update database with processing information
     */
    public function updateProcessedImageRecord($sku, $originalPath, $processedPath, $processingData) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE item_images 
                SET processed_with_ai = ?, original_path = ?, processing_date = NOW(), ai_trim_data = ?
                WHERE sku = ? AND image_path = ?
            ");
            
            $stmt->execute([
                1, // processed_with_ai
                $originalPath,
                json_encode($processingData),
                $sku,
                $processedPath
            ]);
            
        } catch (PDOException $e) {
            error_log("Failed to update processed image record: " . $e->getMessage());
        }
    }
}
?> 