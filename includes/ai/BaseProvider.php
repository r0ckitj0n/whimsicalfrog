<?php
// includes/ai/BaseProvider.php

require_once __DIR__ . '/AIProviderInterface.php';

abstract class BaseProvider implements AIProviderInterface
{
    protected $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    protected function makeAPICall($url, $data, $headers, $method = 'POST')
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => (int) ($this->settings['ai_timeout'] ?? 30),
            CURLOPT_SSL_VERIFYPEER => true
        ];

        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data) : $data;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

        if ($response === false) {
            throw new Exception("API call failed: " . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception("API error (HTTP $httpCode): " . $response);
        }

        return json_decode($response, true);
    }

    protected function parseAIResponse($content)
    {
        if (is_array($content))
            return $content;

        // Clean markdown code blocks if present
        $cleanContent = preg_replace('/^```json\s*|\s*```$/', '', trim($content));

        $decoded = json_decode($cleanContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Fallback: try to find JSON object in string
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded)
                return $decoded;
        }

        return ['raw_response' => $content, 'error' => 'Failed to parse JSON'];
    }

    protected function imageToBase64($imagePath)
    {
        // Check if it's already a data URI or base64 string
        if (strpos($imagePath, 'data:') === 0) {
            if (preg_match('/^data:([^;]+);base64,(.*)$/', $imagePath, $matches)) {
                return [
                    'mime_type' => $matches[1],
                    'data' => $matches[2]
                ];
            }
        }

        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }
        $imageData = file_get_contents($imagePath);
        $mimeType = mime_content_type($imagePath);
        return [
            'data' => base64_encode($imageData),
            'mime_type' => $mimeType
        ];
    }

    protected function buildMarketingPrompt($name, $description, $category, $brandVoice, $contentTone)
    {
        $voiceInstruction = !empty($brandVoice) ? "\n\nCRITICAL REQUIREMENT: Use a distinctly {$brandVoice} brand voice." : "";
        $toneInstruction = !empty($contentTone) ? "\n\nCRITICAL REQUIREMENT: Maintain a distinctly {$contentTone} tone." : "";

        return "Generate marketing content for:
Name: {$name}
Description: {$description}
Category: {$category}
{$voiceInstruction}{$toneInstruction}

Return ONLY valid JSON with fields: title, description, keywords (array), target_audience, selling_points (array).";
    }

    protected function buildPricingPrompt($name, $description, $category, $cost_price)
    {
        return "Suggest pricing for:
Name: {$name}
Description: {$description}
Category: {$category}
Cost: \${$cost_price}

Return ONLY valid JSON with fields: price (number), reasoning, factors (array), components (array of {type, label, amount, explanation}).";
    }

    protected function buildDimensionsPrompt($name, $description, $category)
    {
        return "Suggest shipping dimensions/weight for:
Name: {$name}
Description: {$description}
Category: {$category}

Return ONLY valid JSON: {\"weight_oz\": <number>, \"dimensions_in\": {\"length\": <number>, \"width\": <number>, \"height\": <number>}}";
    }

    public function supportsImages(): bool
    {
        return false;
    }
}
