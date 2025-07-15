<?php
// Business Settings API
// Handles comprehensive business configuration for website customization

require_once 'config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all_settings':
            getAllSettings($pdo);
            break;
            
        case 'get_setting':
            getSetting($pdo);
            break;
            
        case 'update_setting':
            updateSetting($pdo);
            break;
            
        case 'update_multiple_settings':
            updateMultipleSettings($pdo);
            break;
            
        case 'reset_to_defaults':
            resetToDefaults($pdo);
            break;
            
        case 'get_by_category':
            getByCategory($pdo);
            break;
            
        case 'get_sales_verbiage':
            try {
                $stmt = $pdo->prepare("
                    SELECT setting_key, setting_value 
                    FROM business_settings 
                    WHERE category = 'sales' 
                    ORDER BY display_order
                ");
                $stmt->execute();
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                Response::json([
                    'success' => true,
                    'verbiage' => $settings
                ]);
            } catch (Exception $e) {
                Response::json([
                    'success' => false,
                    'error' => 'Failed to load sales verbiage: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getAllSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM business_settings ORDER BY category, display_order, setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'count' => count($settings)
    ]);
}

function getSetting($pdo) {
    $key = $_GET['key'] ?? '';
    
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo json_encode(['success' => true, 'setting' => $setting]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Setting not found']);
    }
}

function updateSetting($pdo) {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
    $result = $stmt->execute([$value, $key]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting or setting not found']);
    }
}

function updateMultipleSettings($pdo) {
    $settingsJson = $_POST['settings'] ?? '';
    
    if (empty($settingsJson)) {
        echo json_encode(['success' => false, 'message' => 'Settings data is required']);
        return;
    }
    
    $settings = json_decode($settingsJson, true);
    if (!$settings) {
        echo json_encode(['success' => false, 'message' => 'Invalid settings data']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        $updatedCount = 0;
        
        foreach ($settings as $key => $value) {
            if ($stmt->execute([$value, $key])) {
                $updatedCount++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Updated {$updatedCount} settings successfully",
            'updated_count' => $updatedCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}
// resetToDefaults function moved to data_manager.php for centralization

function getByCategory($pdo) {
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE category = ? ORDER BY display_order, setting_key");
    $stmt->execute([$category]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'category' => $category,
        'count' => count($settings)
    ]);
}
?> 