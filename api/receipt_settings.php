<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_providers.php';

header('Content-Type: application/json');

function handleReceiptSettings()
{
    global $dsn, $user, $pass, $options;

    try {
        try {
            $pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
        // Attributes handled by Database helper

        $action = $_GET['action'] ?? $_POST['action'] ?? 'get_settings';

        switch ($action) {
            case 'get_settings':
                return getReceiptSettings($pdo);
            case 'update_settings':
                return updateReceiptSettings($pdo);
            case 'generate_ai_message':
                return generateAIReceiptMessage($pdo);
            case 'init_defaults':
                return initializeDefaultSettings($pdo);
            case 'delete':
                return deleteReceiptSetting($pdo);
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log("Receipt Settings Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getReceiptSettings($pdo)
{
    // Create table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS receipt_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_type ENUM('shipping_method', 'item_count', 'item_category', 'default') NOT NULL,
            condition_key VARCHAR(100) NOT NULL,
            condition_value VARCHAR(255) NOT NULL,
            message_title VARCHAR(255) NOT NULL,
            message_content TEXT NOT NULL,
            ai_generated BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_condition (setting_type, condition_key, condition_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    Database::execute($createTableSQL);

    // Get all settings
    $settings = Database::queryAll("SELECT * FROM receipt_settings ORDER BY setting_type, condition_key, condition_value");

    // Group by setting type
    $grouped = [
        'shipping_method' => [],
        'item_count' => [],
        'item_category' => [],
        'default' => []
    ];

    foreach ($settings as $setting) {
        $grouped[$setting['setting_type']][] = $setting;
    }

    return ['success' => true, 'settings' => $grouped];
}

function updateReceiptSettings($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Begin transaction
    Database::beginTransaction();

    try {
        foreach ($input['settings'] as $setting) {
            if (isset($setting['id']) && $setting['id'] > 0) {
                // Update existing
                Database::execute("
                    UPDATE receipt_settings 
                    SET condition_key = ?, condition_value = ?, message_title = ?, message_content = ?, ai_generated = ?
                    WHERE id = ?
                ", [
                    $setting['condition_key'],
                    $setting['condition_value'],
                    $setting['message_title'],
                    $setting['message_content'],
                    $setting['ai_generated'] ?? false,
                    $setting['id']
                ]);
            } else {
                // Insert new
                Database::execute("
                    INSERT INTO receipt_settings (setting_type, condition_key, condition_value, message_title, message_content, ai_generated)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [
                    $setting['setting_type'],
                    $setting['condition_key'],
                    $setting['condition_value'],
                    $setting['message_title'],
                    $setting['message_content'],
                    $setting['ai_generated'] ?? false
                ]);
            }
        }

        Database::commit();
        return ['success' => true, 'message' => 'Receipt settings updated successfully'];

    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}

function generateAIReceiptMessage($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['context'])) {
        throw new Exception('Context required for AI generation');
    }

    $context = $input['context'];
    $settingType = $context['setting_type'] ?? 'default';
    $conditionKey = $context['condition_key'] ?? '';
    $conditionValue = $context['condition_value'] ?? '';

    // Get AI settings for tone and style
    $aiProvider = new AIProviders();
    $aiSettings = getAISettings($pdo);

    // Build context-specific prompt
    $prompt = buildReceiptMessagePrompt($settingType, $conditionKey, $conditionValue, $aiSettings);

    try {
        // Generate message using AI
        $result = $aiProvider->generateReceiptMessage($prompt, $aiSettings);

        return [
            'success' => true,
            'message' => $result,
            'ai_generated' => true
        ];

    } catch (Exception $e) {
        // Fallback to template-based generation
        $fallback = generateFallbackMessage($settingType, $conditionKey, $conditionValue, $aiSettings);

        return [
            'success' => true,
            'message' => $fallback,
            'ai_generated' => false,
            'note' => 'AI unavailable, using template: ' . $e->getMessage()
        ];
    }
}

function buildReceiptMessagePrompt($settingType, $conditionKey, $conditionValue, $aiSettings)
{
    $brandVoice = $aiSettings['ai_brand_voice'] ?? 'friendly';
    $contentTone = $aiSettings['ai_content_tone'] ?? 'professional';

    $contextDescription = '';

    switch ($settingType) {
        case 'shipping_method':
            $contextDescription = "The customer chose '{$conditionValue}' as their shipping method.";
            break;
        case 'item_count':
            $contextDescription = "The customer purchased {$conditionValue} items.";
            break;
        case 'item_category':
            $contextDescription = "The customer purchased items from the '{$conditionValue}' category.";
            break;
        case 'default':
            $contextDescription = "This is the default message for completed orders.";
            break;
    }

    return "You are creating a personalized order receipt message for WhimsicalFrog Crafts, a custom craft business specializing in personalized items like t-shirts, tumblers, artwork, sublimation products, and window wraps.

Context: {$contextDescription}

Brand Voice: {$brandVoice}
Content Tone: {$contentTone}

Create a warm, personalized message that includes:
1. A title (short, friendly acknowledgment)
2. Main message content (2-3 sentences that feel personal and match the context)

The message should:
- Reflect the chosen brand voice and tone strongly
- Be specific to the context provided
- Feel personal and genuine, not generic
- Include appropriate next steps or expectations
- Maintain WhimsicalFrog's friendly, crafty personality

Return as JSON with 'title' and 'content' fields only.";
}

function generateFallbackMessage($settingType, $conditionKey, $conditionValue, $aiSettings)
{
    $tone = $aiSettings['ai_content_tone'] ?? 'professional';

    $templates = [
        'shipping_method' => [
            'Customer Pickup' => [
                'title' => 'Ready for Pickup!',
                'content' => 'Your custom items are being prepared with care. We\'ll notify you as soon as they\'re ready for pickup at our location.'
            ],
            'Local Delivery' => [
                'title' => 'We\'ll Deliver to You!',
                'content' => 'Your order is being crafted with attention to detail. We\'ll coordinate delivery to your location once everything is ready.'
            ],
            'USPS' => [
                'title' => 'Shipping via USPS',
                'content' => 'Your custom items are being prepared for shipment. You\'ll receive USPS tracking information once your package is on its way.'
            ],
            'FedEx' => [
                'title' => 'FedEx Delivery',
                'content' => 'Your order is being carefully prepared. FedEx tracking details will be provided once your items ship.'
            ],
            'UPS' => [
                'title' => 'UPS Shipping',
                'content' => 'We\'re preparing your custom items with care. UPS tracking information will be sent once your order ships.'
            ]
        ],
        'item_count' => [
            '1' => [
                'title' => 'Your Custom Item',
                'content' => 'Your personalized item is being crafted with special attention to detail. Thank you for choosing WhimsicalFrog Crafts!'
            ],
            'multiple' => [
                'title' => 'Your Custom Order',
                'content' => 'Your collection of personalized items is being prepared with care. Each piece will be crafted to meet our quality standards.'
            ]
        ],
        'default' => [
            'title' => 'Order Confirmed',
            'content' => 'Your order is being processed with care. You\'ll receive updates as your custom items are prepared.'
        ]
    ];

    // Apply tone modifications
    if ($tone === 'casual') {
        $templates['default']['content'] = 'Thanks for your order! We\'re getting started on your custom items and will keep you posted.';
    } elseif ($tone === 'energetic') {
        $templates['default']['content'] = 'Exciting! Your custom order is underway and we can\'t wait for you to see the results!';
    }

    // Select appropriate template
    if ($settingType === 'shipping_method' && isset($templates['shipping_method'][$conditionValue])) {
        return $templates['shipping_method'][$conditionValue];
    } elseif ($settingType === 'item_count') {
        return $conditionValue === '1' ? $templates['item_count']['1'] : $templates['item_count']['multiple'];
    }

    return $templates['default'];
}

function getAISettings($pdo)
{
    $defaults = [
        'ai_brand_voice' => 'friendly',
        'ai_content_tone' => 'professional'
    ];

    try {
        $results = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai' AND setting_key IN ('ai_brand_voice', 'ai_content_tone')");

        foreach ($results as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        error_log("Error loading AI settings for receipt: " . $e->getMessage());
    }

    return $defaults;
}

function initializeDefaultSettings($pdo)
{
    // Check if settings already exist
    $result = Database::queryOne("SELECT COUNT(*) as count FROM receipt_settings");

    if ($result['count'] > 0) {
        return ['success' => true, 'message' => 'Settings already initialized'];
    }

    $defaultSettings = [
        // Shipping method messages
        ['shipping_method', 'method', 'Customer Pickup', 'Ready for Pickup!', 'Your custom items are being prepared with care. We\'ll notify you as soon as they\'re ready for pickup at our location.'],
        ['shipping_method', 'method', 'Local Delivery', 'We\'ll Deliver to You!', 'Your order is being crafted with attention to detail. We\'ll coordinate delivery to your location once everything is ready.'],
        ['shipping_method', 'method', 'USPS', 'Shipping via USPS', 'Your custom items are being prepared for shipment. You\'ll receive USPS tracking information once your package is on its way.'],
        ['shipping_method', 'method', 'FedEx', 'FedEx Delivery', 'Your order is being carefully prepared. FedEx tracking details will be provided once your items ship.'],
        ['shipping_method', 'method', 'UPS', 'UPS Shipping', 'We\'re preparing your custom items with care. UPS tracking information will be sent once your order ships.'],

        // Item count messages
        ['item_count', 'count', '1', 'Your Custom Item', 'Your personalized item is being crafted with special attention to detail. Thank you for choosing WhimsicalFrog Crafts!'],
        ['item_count', 'count', 'multiple', 'Your Custom Collection', 'Your collection of personalized items is being prepared with care. Each piece will be crafted to meet our quality standards.'],

        // Category-specific messages
        ['item_category', 'category', 'T-Shirts', 'Custom Apparel Order', 'Your custom t-shirts are being prepared with quality materials and attention to detail. Get ready to wear something uniquely yours!'],
        ['item_category', 'category', 'Tumblers', 'Custom Drinkware Order', 'Your personalized tumblers are being crafted to keep your drinks at the perfect temperature while showing off your style.'],
        ['item_category', 'category', 'Artwork', 'Custom Art Order', 'Your personalized artwork is being created with care and creativity. Each piece will be a unique expression of your vision.'],
        ['item_category', 'category', 'Sublimation', 'Custom Sublimation Order', 'Your sublimation items are being prepared using high-quality materials and vibrant, long-lasting prints.'],
        ['item_category', 'category', 'Window Wraps', 'Custom Window Graphics', 'Your custom window wraps are being prepared with precision and quality materials for lasting impact.'],

        // Default message
        ['default', 'status', 'completed', 'Payment Received', 'Your order is being processed with care. You\'ll receive updates as your custom items are prepared and shipped.']
    ];

    foreach ($defaultSettings as $setting) {
        Database::execute("
            INSERT INTO receipt_settings (setting_type, condition_key, condition_value, message_title, message_content)
            VALUES (?, ?, ?, ?, ?)
        ", $setting);
    }

    return ['success' => true, 'message' => 'Default receipt settings initialized'];
}

function deleteReceiptSetting($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? ($_POST['id'] ?? $_GET['id'] ?? null);
    if (!$id) {
        throw new Exception('Missing id');
    }
    $id = (int)$id;
    if ($id <= 0) {
        throw new Exception('Invalid id');
    }
    Database::execute("DELETE FROM receipt_settings WHERE id = ?", [$id]);
    return ['success' => true];
}

// Handle the request
echo json_encode(handleReceiptSettings());
?> 