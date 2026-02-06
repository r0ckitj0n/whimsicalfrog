<?php
/**
 * AI Model Utilities for WhimsicalFrog
 * Handles model listing, fetching from external APIs, and caching.
 */

/**
 * Live model listing with 1-day DB caching; force bypass supported
 */
function ai_list_models(string $provider, bool $force = false): array
{
    $provider = strtolower(trim($provider));
    $cacheKey = 'ai_models_json_' . $provider;
    $cacheTsKey = $cacheKey . '_ts';
    $now = time();

    try {
        if (!$force) {
            $rowTs = Database::queryOne("SELECT setting_value FROM business_settings WHERE category='ai' AND setting_key=?", [$cacheTsKey]);
            $ts = $rowTs ? (int) $rowTs['setting_value'] : 0;
            if ($ts > 0 && ($now - $ts) < 86400) {
                $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE category='ai' AND setting_key=?", [$cacheKey]);
                if ($row && !empty($row['setting_value'])) {
                    $list = json_decode($row['setting_value'], true);
                    if (is_array($list))
                        return $list;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[ai_model_utils] model cache read failed: ' . $e->getMessage());
    }

    // Fetch fresh
    $models = [];
    if ($provider === 'openai') {
        $models = ai_fetch_openai_models();
    } elseif ($provider === 'google') {
        $models = ai_fetch_google_models();
    } elseif ($provider === 'meta') {
        $models = ai_fetch_openrouter_models('meta');
    } elseif ($provider === 'anthropic') {
        $models = ai_fallback_anthropic_models();
    }

    try {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value) VALUES ('ai', ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$cacheKey, json_encode($models)]);
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value) VALUES ('ai', ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$cacheTsKey, (string) $now]);
    } catch (\Throwable $e) {
        error_log('[ai_model_utils] model cache write failed: ' . $e->getMessage());
    }

    return $models;
}

function ai_fetch_openai_models(): array
{
    // Curated list of OpenAI models with vision capability indicators
    // Vision models can analyze images; non-vision models are text-only
    return [
        ['id' => 'gpt-5.2', 'name' => 'GPT-5.2 (Latest)', 'description' => 'Latest flagship model', 'supportsVision' => true],
        ['id' => 'gpt-4.5-preview', 'name' => 'GPT-4.5 Preview', 'description' => 'Advanced reasoning preview', 'supportsVision' => true],
        ['id' => 'gpt-4o', 'name' => 'GPT-4o', 'description' => 'Fast multimodal model', 'supportsVision' => true],
        ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'description' => 'Affordable multimodal', 'supportsVision' => true],
        ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'description' => 'Previous flagship with vision', 'supportsVision' => true],
        ['id' => 'o3', 'name' => 'o3', 'description' => 'Reasoning model (text only)', 'supportsVision' => false],
        ['id' => 'o3-mini', 'name' => 'o3 Mini', 'description' => 'Fast reasoning (text only)', 'supportsVision' => false],
        ['id' => 'o1', 'name' => 'o1', 'description' => 'Deep reasoning (text only)', 'supportsVision' => false],
        ['id' => 'o1-mini', 'name' => 'o1 Mini', 'description' => 'Efficient reasoning (text only)', 'supportsVision' => false],
        ['id' => 'gpt-4', 'name' => 'GPT-4 (Legacy)', 'description' => 'Original GPT-4', 'supportsVision' => false],
        ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo (Legacy)', 'description' => 'Fast and affordable', 'supportsVision' => false],
    ];
}

function ai_fetch_google_models(): array
{
    // Curated list of Google Gemini models with vision capability indicators
    // All Gemini models support multimodal (vision) input
    return [
        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Latest)', 'description' => 'Latest fast model', 'supportsVision' => true],
        ['id' => 'gemini-2.0-pro', 'name' => 'Gemini 2.0 Pro', 'description' => 'Latest pro model', 'supportsVision' => true],
        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'description' => '1M context window', 'supportsVision' => true],
        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'description' => 'Fast and efficient', 'supportsVision' => true],
        ['id' => 'gemini-pro', 'name' => 'Gemini Pro (Legacy)', 'description' => 'Original Gemini', 'supportsVision' => true],
    ];
}

function ai_fetch_openrouter_models(string $filterProvider = ''): array
{
    // For Meta provider, use a curated list of Llama models with vision flags
    if (strtolower(trim($filterProvider)) === 'meta') {
        return [
            ['id' => 'meta-llama/llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B Instruct', 'description' => 'Latest Llama model', 'supportsVision' => false],
            ['id' => 'meta-llama/llama-3.2-90b-vision-instruct', 'name' => 'Llama 3.2 90B Vision', 'description' => 'Multimodal with vision', 'supportsVision' => true],
            ['id' => 'meta-llama/llama-3.2-11b-vision-instruct', 'name' => 'Llama 3.2 11B Vision', 'description' => 'Efficient multimodal', 'supportsVision' => true],
            ['id' => 'meta-llama/llama-3.1-405b-instruct', 'name' => 'Llama 3.1 405B Instruct', 'description' => 'Largest Llama model', 'supportsVision' => false],
            ['id' => 'meta-llama/llama-3.1-70b-instruct', 'name' => 'Llama 3.1 70B Instruct', 'description' => 'Balanced performance', 'supportsVision' => false],
            ['id' => 'meta-llama/llama-3.1-8b-instruct', 'name' => 'Llama 3.1 8B Instruct', 'description' => 'Fast and efficient', 'supportsVision' => false],
        ];
    }

    // Fallback to fetching from OpenRouter API for other providers
    $url = 'https://openrouter.ai/api/v1/models';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)
    if ($code !== 200 || !$res)
        return [];
    $j = json_decode($res, true);
    $out = [];
    if (isset($j['data']) && is_array($j['data'])) {
        foreach ($j['data'] as $m) {
            $id = $m['id'] ?? '';
            if (!$id)
                continue;
            if ($filterProvider) {
                $f = strtolower($filterProvider);
                $sid = strtolower($id);
                if ($f === 'meta') {
                    if (strpos($sid, 'meta-llama') === false)
                        continue;
                } elseif ($f === 'openai') {
                    if (!(strpos($sid, 'openai/') === 0 || strpos($sid, 'gpt-') !== false || strpos($sid, 'o3') !== false))
                        continue;
                } elseif ($f === 'google') {
                    if (!(strpos($sid, 'google/') === 0 || strpos($sid, 'gemini') !== false))
                        continue;
                } elseif ($f === 'anthropic') {
                    if (!(strpos($sid, 'anthropic/') === 0 || strpos($sid, 'claude-') !== false))
                        continue;
                }
            }
            $label = $m['name'] ?? $id;
            $desc = $m['description'] ?? '';
            // Check if model name contains 'vision' to infer vision support
            $supportsVision = stripos($id, 'vision') !== false || stripos($label, 'vision') !== false;
            $out[] = ['id' => $id, 'name' => $label, 'description' => $desc, 'supportsVision' => $supportsVision];
        }
    }
    return $out;
}

function ai_fallback_anthropic_models(): array
{
    return [
        ['id' => 'claude-sonnet-5-20260203', 'name' => 'Claude Sonnet 5 (Fennec)', 'description' => 'Latest flagship - 1M context, 82% SWE-Bench', 'supportsVision' => true],
        ['id' => 'claude-opus-4.5-20260115', 'name' => 'Claude Opus 4.5', 'description' => 'Highest capability - advanced reasoning', 'supportsVision' => true],
        ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'description' => 'Previous best model - stable', 'supportsVision' => true],
        ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'description' => 'Fastest model (text only)', 'supportsVision' => false],
        ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus (Legacy)', 'description' => 'Legacy - may be deprecated', 'supportsVision' => true],
        ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'description' => 'Fast and affordable (text only)', 'supportsVision' => false],
    ];
}

function ai_list_models_openrouter(string $provider, bool $force = false): array
{
    $provider = strtolower(trim($provider));
    $cacheKey = 'ai_models_or_json_' . $provider;
    $cacheTsKey = $cacheKey . '_ts';
    $now = time();

    try {
        if (!$force) {
            $rowTs = Database::queryOne("SELECT setting_value FROM business_settings WHERE category='ai' AND setting_key=?", [$cacheTsKey]);
            $ts = $rowTs ? (int) $rowTs['setting_value'] : 0;
            if ($ts > 0 && ($now - $ts) < 86400) {
                $row = Database::queryOne("SELECT setting_value FROM business_settings WHERE category='ai' AND setting_key=?", [$cacheKey]);
                if ($row && !empty($row['setting_value'])) {
                    $list = json_decode($row['setting_value'], true);
                    if (is_array($list))
                        return $list;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('[ai_model_utils] openrouter cache read failed: ' . $e->getMessage());
    }

    $models = ai_fetch_openrouter_models($provider);

    try {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value) VALUES ('ai', ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$cacheKey, json_encode($models)]);
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value) VALUES ('ai', ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)", [$cacheTsKey, (string) $now]);
    } catch (\Throwable $e) {
        error_log('[ai_model_utils] openrouter cache write failed: ' . $e->getMessage());
    }

    return $models;
}
