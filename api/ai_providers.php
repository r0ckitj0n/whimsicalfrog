<?php
/**
 * AI Providers System for WhimsicalFrog
 * Conductor Pattern Implementation
 * Delegating to specialized provider classes in includes/ai/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/ai/AIProviderInterface.php';
require_once __DIR__ . '/../includes/ai/BaseProvider.php';
require_once __DIR__ . '/../includes/ai/OpenAIProvider.php';
require_once __DIR__ . '/../includes/ai/AnthropicProvider.php';
require_once __DIR__ . '/../includes/ai/GoogleProvider.php';
require_once __DIR__ . '/../includes/ai/MetaProvider.php';
require_once __DIR__ . '/../includes/ai/LocalProvider.php';

class AIProviders
{
    private $settings;
    private $provider;
    private $localProvider;
    private $lastRunDiagnostics;

    public function __construct()
    {
        // Load settings
        try {
            $this->settings = $this->loadSettingsDirectly();
        } catch (\Throwable $e) {
            error_log("AIProviders constructor error: " . $e->getMessage());
        }

        if (!$this->settings) {
            $this->settings = [
                'ai_provider' => WF_Constants::AI_PROVIDER_JONS_AI,
                'ai_temperature' => 0.7,
                'ai_max_tokens' => 1000,
                'fallback_to_local' => true,
            ];
        }

        $this->localProvider = new LocalProvider($this->settings);
        $this->initProvider();
        $this->lastRunDiagnostics = [
            'method' => null,
            'provider' => $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI,
            'model' => null,
            'fallback_attempted' => false,
            'fallback_used' => false,
            'provider_error' => null,
            'fallback_error' => null,
        ];
    }

    protected function loadSettingsDirectly()
    {
        $defaults = [
            'ai_provider' => 'jons_ai',
            'ai_temperature' => 0.7,
            'ai_max_tokens' => 1000,
            'ai_timeout' => 30,
            'fallback_to_local' => true
        ];

        try {
            Database::getInstance();
            $results = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
            foreach ($results as $row) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error loading AI settings directly: " . $e->getMessage());
        }

        require_once __DIR__ . '/../includes/secret_store.php';
        $secretKeys = ['openai_api_key', 'anthropic_api_key', 'google_api_key', 'meta_api_key'];
        foreach ($secretKeys as $k) {
            $v = secret_get($k);
            if ($v !== null && $v !== '')
                $defaults[$k] = $v;
        }

        return $defaults;
    }

    private function initProvider()
    {
        $type = $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI;
        $this->provider = $this->getProviderInstance($type);
    }

    public function getSettings()
    {
        return $this->settings;
    }

    private function recordProviderTimestamp($provider, $suffix)
    {
        try {
            $key = $provider . $suffix;
            $value = (string) time();
            $labelProvider = ucfirst($provider);
            $isTest = ($suffix === '_last_test_success_at');
            $displayName = $labelProvider . ($isTest ? ' Last Successful Test' : ' Last Successful Use');
            $description = $isTest
                ? "Unix timestamp of the last successful Test Provider run for {$labelProvider}."
                : "Unix timestamp of the last successful live AI usage for {$labelProvider}.";

            if (class_exists('Database')) {
                Database::execute(
                    "INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name)
                     VALUES ('ai', ?, ?, ?, 'number', ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name), updated_at = CURRENT_TIMESTAMP",
                    [$key, $value, $description, $displayName]
                );
            }
        } catch (Exception $e) {
            error_log("Failed to record AI provider timestamp: " . $e->getMessage());
        }
    }

    private function markUsageSuccess($provider)
    {
        $this->recordProviderTimestamp($provider, '_last_success_at');
    }

    private function markTestSuccess($provider)
    {
        $this->recordProviderTimestamp($provider, '_last_test_success_at');
    }

    /**
     * Helper to run with optional fallback
     */
    private function resolveActiveModelForDiagnostics()
    {
        $provider = $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI;
        switch ($provider) {
            case WF_Constants::AI_PROVIDER_OPENAI:
                return $this->settings['openai_model'] ?? null;
            case WF_Constants::AI_PROVIDER_ANTHROPIC:
                return $this->settings['anthropic_model'] ?? null;
            case WF_Constants::AI_PROVIDER_GOOGLE:
                return $this->settings['google_model'] ?? null;
            case WF_Constants::AI_PROVIDER_META:
                return $this->settings['meta_model'] ?? null;
            case WF_Constants::AI_PROVIDER_JONS_AI:
            default:
                return 'jons-ai';
        }
    }

    private function getProviderForMethod($method)
    {
        // Local provider does not support image analysis; use the selected provider directly so errors are not masked.
        if ($method === 'analyzeItemImage' || $method === 'detectObjectBoundaries') {
            return $this->provider;
        }
        if ($this->settings['fallback_to_local'] && $this->provider !== $this->localProvider) {
            return $this->provider;
        }
        return $this->provider;
    }

    /**
     * Resolve a dedicated vision-capable provider instance, if one is configured
     * (business_settings.ai_vision_provider) and actually supports images. Returns
     * null when no override should apply (same as primary, not configured, fails to
     * instantiate, or doesn't support images).
     */
    private function getVisionProviderOverride()
    {
        $visionProviderType = trim((string) ($this->settings['ai_vision_provider'] ?? ''));
        if ($visionProviderType === '') {
            return null;
        }
        $primaryType = $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI;
        if ($visionProviderType === $primaryType) {
            // Already using this provider as primary; no separate override needed.
            return null;
        }

        try {
            $candidate = $this->getProviderInstance($visionProviderType);
        } catch (\Throwable $e) {
            error_log("AI vision provider override failed to instantiate ({$visionProviderType}): " . $e->getMessage());
            return null;
        }

        if (!$candidate || $candidate === $this->localProvider || !$candidate->supportsImages()) {
            return null;
        }
        return $candidate;
    }

    private function runWithFallback($method, ...$args)
    {
        // Image analysis gets its own resilient provider chain (primary -> configured
        // vision override) instead of the generic local-fallback handling below, since
        // Jon's AI (LocalProvider) has no real vision analysis to fall back to.
        if ($method === 'analyzeItemImage') {
            return $this->runVisionAnalysisWithFallback($method, ...$args);
        }

        $providerName = $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI;
        $modelName = $this->resolveActiveModelForDiagnostics();
        $fallbackAllowed = !in_array($method, ['detectObjectBoundaries'], true);
        $this->lastRunDiagnostics = [
            'method' => $method,
            'provider' => $providerName,
            'model' => $modelName,
            'fallback_attempted' => false,
            'fallback_used' => false,
            'provider_error' => null,
            'fallback_error' => null,
        ];

        try {
            $activeProvider = $this->getProviderForMethod($method);
            if (method_exists($activeProvider, $method)) {
                $result = call_user_func_array([$activeProvider, $method], $args);
                $this->markUsageSuccess($providerName);
                return $result;
            }
            throw new Exception("Method $method not implemented by provider");
        } catch (Throwable $e) {
            $this->lastRunDiagnostics['provider_error'] = $e->getMessage();
            error_log("AI Provider Error [{$providerName}/{$method}]: " . $e->getMessage());
            if ($fallbackAllowed && $this->settings['fallback_to_local'] && $this->provider !== $this->localProvider) {
                $this->lastRunDiagnostics['fallback_attempted'] = true;
                if (method_exists($this->localProvider, $method)) {
                    try {
                        $result = call_user_func_array([$this->localProvider, $method], $args);
                        $this->lastRunDiagnostics['fallback_used'] = true;
                        return $result;
                    } catch (Throwable $fallbackError) {
                        $this->lastRunDiagnostics['fallback_error'] = $fallbackError->getMessage();
                        throw new Exception(
                            "Primary provider '{$providerName}' failed: {$e->getMessage()}; local fallback failed: {$fallbackError->getMessage()}",
                            0,
                            $e
                        );
                    }
                }
            }
            throw new Exception("Primary provider '{$providerName}' failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Image analysis provider chain: try the primary provider (ai_provider) first,
     * then fall back to the configured ai_vision_provider (if it's distinct and
     * actually supports images) before giving up. This covers the case where the
     * primary provider supports images in principle (e.g. gpt-4o-mini) but can't
     * actually run -- most commonly a missing/invalid API key -- while a different,
     * working vision provider is configured. Local (Jon's AI) is never attempted
     * here; suggest_all.php is responsible for degrading gracefully if every
     * provider in the chain fails.
     */
    private function runVisionAnalysisWithFallback($method, ...$args)
    {
        $primaryName = $this->settings['ai_provider'] ?? WF_Constants::AI_PROVIDER_JONS_AI;
        $primaryModel = $this->resolveActiveModelForDiagnostics();

        $this->lastRunDiagnostics = [
            'method' => $method,
            'provider' => $primaryName,
            'model' => $primaryModel,
            'fallback_attempted' => false,
            'fallback_used' => false,
            'provider_error' => null,
            'fallback_error' => null,
        ];

        // [name, provider instance, model] tuples to try in order.
        $chain = [[$primaryName, $this->provider, $primaryModel]];

        $visionOverrideName = trim((string) ($this->settings['ai_vision_provider'] ?? ''));
        if ($visionOverrideName !== '' && $visionOverrideName !== $primaryName) {
            $visionOverride = $this->getVisionProviderOverride();
            if ($visionOverride) {
                $chain[] = [$visionOverrideName, $visionOverride, $this->settings[$visionOverrideName . '_model'] ?? null];
            }
        }

        $errors = [];
        foreach ($chain as $index => $attempt) {
            [$name, $providerInstance, $model] = $attempt;
            $isFallback = $index > 0;
            if ($isFallback) {
                $this->lastRunDiagnostics['fallback_attempted'] = true;
            }
            try {
                if (!method_exists($providerInstance, $method)) {
                    throw new Exception("Method $method not implemented by provider");
                }
                $result = call_user_func_array([$providerInstance, $method], $args);
                $this->markUsageSuccess($name);
                if ($isFallback) {
                    $this->lastRunDiagnostics['fallback_used'] = true;
                    $this->lastRunDiagnostics['provider'] = $name;
                    $this->lastRunDiagnostics['model'] = $model;
                }
                return $result;
            } catch (Throwable $e) {
                $errors[$name] = $e->getMessage();
                if ($isFallback) {
                    $this->lastRunDiagnostics['fallback_error'] = $e->getMessage();
                } else {
                    $this->lastRunDiagnostics['provider_error'] = $e->getMessage();
                }
                error_log("AI Provider Error [{$name}/{$method}]: " . $e->getMessage());
            }
        }

        $names = array_keys($errors);
        if (count($names) <= 1) {
            $only = $names[0] ?? $primaryName;
            throw new Exception("Primary provider '{$only}' failed: " . ($errors[$only] ?? 'unknown error'));
        }
        throw new Exception(
            "Primary provider '{$names[0]}' failed: {$errors[$names[0]]}; vision fallback '{$names[1]}' failed: {$errors[$names[1]]}"
        );
    }

    public function getLastRunDiagnostics()
    {
        return $this->lastRunDiagnostics;
    }

    // --- Public API ---

    public function generateDimensionsSuggestion($name, $description, $category)
    {
        return $this->runWithFallback('generateDimensions', $name, $description, $category);
    }

    public function analyzeImagesForAltText($images, $name, $description, $category)
    {
        return $this->runWithFallback('generateAltText', $images, $name, $description, $category);
    }

    public function generateEnhancedMarketingContent($name, $description, $category, $imageInsights = '', $brandVoice = '', $contentTone = '', $existingData = [])
    {
        return $this->runWithFallback('generateEnhancedMarketing', $name, $description, $category, $imageInsights, $brandVoice, $contentTone, $existingData);
    }

    public function generateMarketingContent($name, $description, $category, $brandVoice = '', $contentTone = '')
    {
        return $this->runWithFallback('generateMarketing', $name, $description, $category, $brandVoice, $contentTone);
    }

    public function generateCostSuggestion($name, $description, $category)
    {
        return $this->runWithFallback('generateCost', $name, $description, $category);
    }

    public function generatePricingSuggestion($name, $description, $category, $cost_price)
    {
        return $this->runWithFallback('generatePricing', $name, $description, $category, $cost_price);
    }

    public function generateReceiptMessage($prompt, $aiSettings = null)
    {
        return $this->runWithFallback('generateReceipt', $prompt);
    }

    public function analyzeItemImage($imagePath, $existingCategories = [])
    {
        return $this->runWithFallback('analyzeItemImage', $imagePath, $existingCategories);
    }

    public function detectObjectBoundaries($imagePath)
    {
        return $this->runWithFallback('detectObjectBoundaries', $imagePath);
    }

    public function currentModelSupportsImages()
    {
        if ($this->provider->supportsImages()) {
            return true;
        }
        return $this->getVisionProviderOverride() !== null;
    }

    public function generateMarketingContentWithImages($name, $description, $category, $images = [], $brandVoice = '', $contentTone = '')
    {
        if ($this->currentModelSupportsImages() && !empty($images)) {
            return $this->runWithFallback('generateMarketingWithImages', $name, $description, $category, $images, $brandVoice, $contentTone);
        }
        return $this->generateMarketingContent($name, $description, $category, $brandVoice, $contentTone);
    }

    public function generatePricingSuggestionWithImages($name, $description, $category, $cost_price, $images = [])
    {
        if ($this->currentModelSupportsImages() && !empty($images)) {
            return $this->runWithFallback('generatePricingWithImages', $name, $description, $category, $cost_price, $images);
        }
        return $this->generatePricingSuggestion($name, $description, $category, $cost_price);
    }

    public function generateCostSuggestionWithImages($name, $description, $category, $images = [])
    {
        if ($this->currentModelSupportsImages() && !empty($images)) {
            return $this->runWithFallback('generateCostWithImages', $name, $description, $category, $images);
        }
        return $this->generateCostSuggestion($name, $description, $category);
    }

    public function extractMarketingInsightsFromImages($imageAnalysisData, $name, $category)
    {
        if (empty($imageAnalysisData) || !is_array($imageAnalysisData))
            return '';
        $insights = [];
        foreach ($imageAnalysisData as $imageData) {
            if (isset($imageData['description']))
                $insights[] = $imageData['description'];
        }
        if (empty($insights))
            return '';
        return "Visual analysis reveals: " . implode(" Additionally, ", $insights) . ". Based on item images for '{$name}' in {$category}.";
    }

    public function getAvailableModels($provider)
    {
        // This could be moved to providers or kept here as a registry
        $p = $this->getProviderInstance($provider);
        return method_exists($p, 'getModels') ? $p->getModels() : [];
    }

    protected function getProviderInstance($type)
    {
        switch ($type) {
            case WF_Constants::AI_PROVIDER_OPENAI:
                return new OpenAIProvider($this->settings);
            case WF_Constants::AI_PROVIDER_ANTHROPIC:
                return new AnthropicProvider($this->settings);
            case WF_Constants::AI_PROVIDER_GOOGLE:
                return new GoogleProvider($this->settings);
            case WF_Constants::AI_PROVIDER_META:
                return new MetaProvider($this->settings);
            case WF_Constants::AI_PROVIDER_JONS_AI:
            default:
                return $this->localProvider;
        }
    }

    public function testProvider($provider)
    {
        $resolvedModel = $this->settings['openai_model']
            ?? $this->settings['anthropic_model']
            ?? $this->settings['google_model']
            ?? $this->settings['meta_model']
            ?? 'unknown';

        $results = [
            'text_test' => ['success' => false, 'message' => 'Not tested'],
            'image_test' => ['success' => false, 'message' => 'Not tested'],
            'model' => $resolvedModel
        ];

        try {
            $p = $this->getProviderInstance($provider);

            // Test 1: Basic text generation
            try {
                $res = $p->generateMarketing('Test Item', 'A test product for API verification', 'Test Category', '', '');
                if ($res && (isset($res['title']) || isset($res['description']))) {
                    $results['text_test'] = ['success' => true, 'message' => 'Text generation works'];
                } else {
                    $results['text_test'] = ['success' => false, 'message' => 'Text generation returned empty result'];
                }
            } catch (Exception $e) {
                $results['text_test'] = ['success' => false, 'message' => 'Text test failed: ' . $e->getMessage()];
            }

            // Test 2: Image analysis (if supported)
            if ($p->supportsImages()) {
                try {
                    // Prefer real product images with provider-supported mime types.
                    $candidateImages = [
                        __DIR__ . '/../images/items/WF-GEN-001A.png',
                        __DIR__ . '/../images/items/WF-GEN-001A.webp',
                        __DIR__ . '/../images/items/WF-GEN-001A.jpg',
                        __DIR__ . '/../images/items/WF-GEN-001A.jpeg'
                    ];

                    $testImagePath = '';
                    foreach ($candidateImages as $candidate) {
                        if (file_exists($candidate)) {
                            $testImagePath = $candidate;
                            break;
                        }
                    }

                    // Fallback: scan for any usable item image
                    if ($testImagePath === '') {
                        $globbed = array_merge(
                            glob(__DIR__ . '/../images/items/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [],
                            glob(__DIR__ . '/../images/backgrounds/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: []
                        );
                        if (!empty($globbed)) {
                            $testImagePath = $globbed[0];
                        }
                    }

                    if ($testImagePath !== '' && file_exists($testImagePath)) {
                        error_log("testProvider: Testing image analysis with: $testImagePath");
                        $imageRes = $p->analyzeItemImage($testImagePath, ['Test', 'Category']);
                        error_log("testProvider: Image analysis result: " . json_encode($imageRes));

                        if ($imageRes && (isset($imageRes['title']) || isset($imageRes['description']) || isset($imageRes['category']))) {
                            $results['image_test'] = ['success' => true, 'message' => 'Image analysis works', 'data' => $imageRes];
                        } else {
                            $results['image_test'] = ['success' => false, 'message' => 'Image analysis returned empty result'];
                        }
                    } else {
                        $results['image_test'] = ['success' => false, 'message' => 'No compatible test image found (.png/.jpg/.jpeg/.webp).'];
                    }
                } catch (Exception $e) {
                    $errMsg = $e->getMessage();
                    // Check for common errors and provide helpful feedback
                    if (strpos($errMsg, '404') !== false || strpos($errMsg, 'not_found') !== false) {
                        $results['image_test'] = ['success' => false, 'message' => 'Model not found (404) - check model ID format: ' . $this->settings['anthropic_model']];
                    } else if (strpos($errMsg, '401') !== false || strpos($errMsg, 'auth') !== false) {
                        $results['image_test'] = ['success' => false, 'message' => 'Authentication failed - check API key'];
                    } else {
                        $results['image_test'] = ['success' => false, 'message' => 'Image test failed: ' . $errMsg];
                    }
                }
            } else {
                $results['image_test'] = [
                    'success' => false,
                    'message' => 'Selected provider/model does not support image analysis. Choose a vision-capable model.'
                ];
            }

            // Overall success requires BOTH text and image tests.
            $overallSuccess = $results['text_test']['success'] && $results['image_test']['success'];
            if ($overallSuccess) {
                $this->markTestSuccess($provider);
            }

            $message = $overallSuccess
                ? '✅ Text + image analysis tests passed.'
                : (
                    !$results['image_test']['success']
                    ? '❌ Image analysis test failed: ' . ($results['image_test']['message'] ?? 'Unknown image analysis error')
                    : '❌ Text test failed: ' . $results['text_test']['message']
                );

            return [
                'success' => $overallSuccess,
                'message' => $message,
                'details' => $results
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Provider test failed: ' . $e->getMessage(), 'details' => $results];
        }
    }

    public function getAvailableProviders()
    {
        return [
            WF_Constants::AI_PROVIDER_JONS_AI => ['name' => "Jon's AI (Algorithm-based)", 'requires_api_key' => false],
            WF_Constants::AI_PROVIDER_OPENAI => ['name' => 'OpenAI (ChatGPT)', 'requires_api_key' => true],
            WF_Constants::AI_PROVIDER_ANTHROPIC => ['name' => 'Anthropic (Claude)', 'requires_api_key' => true],
            WF_Constants::AI_PROVIDER_GOOGLE => ['name' => 'Google AI (Gemini)', 'requires_api_key' => true],
            WF_Constants::AI_PROVIDER_META => ['name' => 'Meta AI (Llama)', 'requires_api_key' => true]
        ];
    }

    /**
     * Helper to load images for an item from the database
     */
    public static function getItemImages($sku, $limit = 3)
    {
        $images = [];
        if (empty($sku))
            return $images;

        try {
            $imageRows = Database::queryAll("SELECT image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC LIMIT ?", [$sku, $limit]);
            foreach ($imageRows as $row) {
                $abs = __DIR__ . '/../' . $row['image_path'];
                if (file_exists($abs)) {
                    $images[] = $abs;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to load images for item {$sku}: " . $e->getMessage());
        }
        return $images;
    }
}

// Global instance
$GLOBALS['aiProviders'] = new AIProviders();

// Helper functions
function getAIProviders()
{
    return $GLOBALS['aiProviders'];
}
function generateAIMarketingContent($name, $description, $category, $brandVoice = '', $contentTone = '')
{
    return $GLOBALS['aiProviders']->generateMarketingContent($name, $description, $category, $brandVoice, $contentTone);
}
function generateAIPricingSuggestion($name, $description, $category, $cost_price)
{
    return $GLOBALS['aiProviders']->generatePricingSuggestion($name, $description, $category, $cost_price);
}
function generateAICostSuggestion($name, $description, $category)
{
    return $GLOBALS['aiProviders']->generateCostSuggestion($name, $description, $category);
}
