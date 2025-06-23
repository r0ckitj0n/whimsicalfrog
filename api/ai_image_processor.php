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
    
    public function __construct() {
        global $dsn, $user, $pass, $options;
        $this->pdo = new PDO($dsn, $user, $pass, $options);
        $this->aiProviders = new AIProviders();
    }
    
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
        
        // Generate output path
        $pathInfo = pathinfo($imagePath);
        $outputPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_cropped.' . $pathInfo['extension'];
        
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
     * Convert image to WebP format
     */
    private function convertToWebP($imagePath, $quality = 90, $preserveTransparency = true) {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception("Cannot read image for WebP conversion");
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
                // Already WebP, just return original path
                return $imagePath;
            default:
                throw new Exception("Unsupported image type for WebP conversion");
        }
        
        if (!$sourceImage) {
            throw new Exception("Failed to create image resource for WebP conversion");
        }
        
        // Preserve transparency
        if ($preserveTransparency) {
            imagealphablending($sourceImage, true);
            imagesavealpha($sourceImage, true);
        }
        
        // Generate WebP path
        $pathInfo = pathinfo($imagePath);
        $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
        
        // Convert to WebP
        if (!imagewebp($sourceImage, $webpPath, $quality)) {
            imagedestroy($sourceImage);
            throw new Exception("Failed to convert to WebP");
        }
        
        imagedestroy($sourceImage);
        chmod($webpPath, 0644);
        
        return $webpPath;
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