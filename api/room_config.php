<?php
/**
 * Room Configuration API
 * Manages database-driven room settings for centralized configuration
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check authentication for write operations
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_room_config';
$readOnlyActions = ['get_room_config', 'get_modal_settings', 'get_all_configs'];

if (!in_array($action, $readOnlyActions)) {
    checkApiAuth(true); // Require admin for write operations
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_room_config';
    $roomNumber = $_GET['room'] ?? $_POST['room'] ?? null;
    
    switch ($action) {
        case 'get_room_config':
            getRoomConfig($pdo, $roomNumber);
            break;
            
        case 'get_all_configs':
            getAllRoomConfigs($pdo);
            break;
            
        case 'update_config':
            updateRoomConfig($pdo);
            break;
            
        case 'get_modal_settings':
            getModalSettings($pdo);
            break;
            
        case 'update_modal_settings':
            updateModalSettings($pdo);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getRoomConfig($pdo, $roomNumber) {
    if (!$roomNumber) {
        throw new Exception('Room number required');
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM room_config 
        WHERE room_number = ? AND is_active = 1
    ");
    $stmt->execute([$roomNumber]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        // Return default config if none exists
        $config = getDefaultRoomConfig($roomNumber);
    }
    
    echo json_encode($config);
}

function getAllRoomConfigs($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM room_config 
        WHERE is_active = 1 
        ORDER BY room_number
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($configs);
}

function updateRoomConfig($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['room_number'])) {
        throw new Exception('Room number required');
    }
    
    $roomNumber = $input['room_number'];
    $config = $input['config'] ?? [];
    
    // Check if config exists
    $stmt = $pdo->prepare("SELECT id FROM room_config WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing config
        $stmt = $pdo->prepare("
            UPDATE room_config SET
                popup_settings = ?,
                modal_settings = ?,
                interaction_settings = ?,
                visual_settings = ?,
                updated_at = NOW()
            WHERE room_number = ?
        ");
        $stmt->execute([
            json_encode($config['popup_settings'] ?? []),
            json_encode($config['modal_settings'] ?? []),
            json_encode($config['interaction_settings'] ?? []),
            json_encode($config['visual_settings'] ?? []),
            $roomNumber
        ]);
    } else {
        // Insert new config
        $stmt = $pdo->prepare("
            INSERT INTO room_config 
            (room_number, popup_settings, modal_settings, interaction_settings, visual_settings, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $roomNumber,
            json_encode($config['popup_settings'] ?? []),
            json_encode($config['modal_settings'] ?? []),
            json_encode($config['interaction_settings'] ?? []),
            json_encode($config['visual_settings'] ?? [])
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Room config updated successfully']);
}

function getModalSettings($pdo) {
    $stmt = $pdo->query("
        SELECT * FROM modal_config 
        WHERE is_active = 1 
        ORDER BY config_name
    ");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($settings);
}

function updateModalSettings($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['config_name'])) {
        throw new Exception('Config name required');
    }
    
    $configName = $input['config_name'];
    $settings = $input['settings'] ?? [];
    
    // Check if config exists
    $stmt = $pdo->prepare("SELECT id FROM modal_config WHERE config_name = ?");
    $stmt->execute([$configName]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing config
        $stmt = $pdo->prepare("
            UPDATE modal_config SET
                settings = ?,
                updated_at = NOW()
            WHERE config_name = ?
        ");
        $stmt->execute([
            json_encode($settings),
            $configName
        ]);
    } else {
        // Insert new config
        $stmt = $pdo->prepare("
            INSERT INTO modal_config 
            (config_name, settings, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $configName,
            json_encode($settings)
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Modal settings updated successfully']);
}

function getDefaultRoomConfig($roomNumber) {
    return [
        'room_number' => $roomNumber,
        'popup_settings' => [
            'show_delay' => 50,
            'hide_delay' => 150,
            'position_offset' => 10,
            'max_width' => 450,
            'min_width' => 280,
            'enable_sales_check' => true,
            'show_category' => true,
            'show_description' => true,
            'enable_image_fallback' => true
        ],
        'modal_settings' => [
            'enable_colors' => true,
            'enable_sizes' => true,
            'enable_quantity_limits' => true,
            'max_quantity' => 999,
            'min_quantity' => 1,
            'show_unit_price' => true,
            'show_total_calculation' => true,
            'enable_stock_checking' => true
        ],
        'interaction_settings' => [
            'click_to_details' => true,
            'hover_to_popup' => true,
            'popup_add_to_cart' => true,
            'enable_touch_events' => true,
            'debounce_time' => 50
        ],
        'visual_settings' => [
            'popup_animation' => 'fade',
            'modal_animation' => 'scale',
            'button_style' => 'brand',
            'color_theme' => 'whimsical'
        ]
    ];
}

// Initialize database tables if they don't exist
function initializeRoomConfigTables($pdo) {
    // Room config table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS room_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number INT NOT NULL,
            popup_settings JSON,
            modal_settings JSON,
            interaction_settings JSON,
            visual_settings JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room (room_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Modal config table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS modal_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_name VARCHAR(100) NOT NULL,
            settings JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_config (config_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default modal configurations
    $pdo->exec("
        INSERT IGNORE INTO modal_config (config_name, settings) VALUES
        ('quantity_modal', '{
            \"enable_colors\": true,
            \"enable_sizes\": true,
            \"color_display_type\": \"dropdown_with_swatches\",
            \"size_display_type\": \"dropdown\",
            \"show_stock_levels\": true,
            \"enable_price_adjustments\": true,
            \"quantity_controls\": \"input_with_buttons\",
            \"max_quantity\": 999,
            \"validation_rules\": {
                \"require_color_selection\": false,
                \"require_size_selection\": false,
                \"check_stock_availability\": true
            }
        }'),
        ('detail_modal', '{
            \"show_image_carousel\": true,
            \"show_detailed_description\": true,
            \"enable_zoom\": true,
            \"show_specifications\": true,
            \"enable_reviews\": false,
            \"show_related_items\": false
        }'),
        ('popup_display', '{
            \"max_description_length\": 150,
            \"show_truncated_description\": true,
            \"enable_dynamic_sizing\": true,
            \"position_strategy\": \"smart\",
            \"animation_type\": \"fade\",
            \"animation_duration\": 200
        }')
    ");
}
?> 