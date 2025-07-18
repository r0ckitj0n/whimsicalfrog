<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check session authentication first
require_once __DIR__ . '/../includes/auth.php';
if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only GET is allowed.']);
    exit;
}

// Get SKU parameter
$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU parameter is required.']);
    exit;
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Get the most recent cost suggestion for this SKU
    $stmt = $pdo->prepare("
        SELECT 
            suggested_cost,
            reasoning,
            confidence,
            breakdown,
            detected_materials,
            detected_features,
            size_analysis,
            complexity_score,
            production_time_estimate,
            skill_level_required,
            market_positioning,
            eco_friendly_score,
            material_cost_factors,
            labor_complexity_factors,
            energy_usage_factors,
            equipment_requirements,
            material_confidence,
            labor_confidence,
            energy_confidence,
            equipment_confidence,
            materials_cost_amount,
            labor_cost_amount,
            energy_cost_amount,
            equipment_cost_amount,
            created_at
        FROM cost_suggestions 
        WHERE sku = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$sku]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Parse breakdown and create components
        $breakdown = json_decode($result['breakdown'] ?? '{}', true);
        $components = createCostComponents($breakdown, $result);

        echo json_encode([
            'success' => true,
            'suggestedCost' => floatval($result['suggested_cost']),
            'reasoning' => $result['reasoning'],
            'confidence' => $result['confidence'],
            'breakdown' => $breakdown,
            'components' => $components,
            'analysis' => [
                'detected_materials' => json_decode($result['detected_materials'] ?? '[]', true),
                'detected_features' => json_decode($result['detected_features'] ?? '[]', true),
                'size_analysis' => json_decode($result['size_analysis'] ?? '[]', true),
                'complexity_score' => floatval($result['complexity_score'] ?? 0),
                'production_time_estimate' => intval($result['production_time_estimate'] ?? 0),
                'skill_level_required' => $result['skill_level_required'],
                'market_positioning' => $result['market_positioning'],
                'eco_friendly_score' => floatval($result['eco_friendly_score'] ?? 0),
                'material_cost_factors' => json_decode($result['material_cost_factors'] ?? '[]', true),
                'labor_complexity_factors' => json_decode($result['labor_complexity_factors'] ?? '[]', true),
                'energy_usage_factors' => json_decode($result['energy_usage_factors'] ?? '[]', true),
                'equipment_requirements' => json_decode($result['equipment_requirements'] ?? '[]', true),
                'material_confidence' => floatval($result['material_confidence'] ?? 0),
                'labor_confidence' => floatval($result['labor_confidence'] ?? 0),
                'energy_confidence' => floatval($result['energy_confidence'] ?? 0),
                'equipment_confidence' => floatval($result['equipment_confidence'] ?? 0)
            ],
            'createdAt' => $result['created_at']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No cost suggestion found for this SKU'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in get_cost_suggestion.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function createCostComponents($breakdown, $dbData)
{
    $components = [];

    // Materials component
    if (isset($breakdown['materials']) && $breakdown['materials'] > 0) {
        $materialFactors = json_decode($dbData['material_cost_factors'] ?? '[]', true);
        $components[] = [
            'type' => 'materials',
            'label' => 'Materials Cost',
            'amount' => floatval($breakdown['materials']),
            'confidence' => floatval($dbData['material_confidence'] ?? 0.5),
            'factors' => $materialFactors,
            'explanation' => createMaterialsExplanation($materialFactors, $dbData)
        ];
    }

    // Labor component
    if (isset($breakdown['labor']) && $breakdown['labor'] > 0) {
        $laborFactors = json_decode($dbData['labor_complexity_factors'] ?? '[]', true);
        $components[] = [
            'type' => 'labor',
            'label' => 'Labor Cost',
            'amount' => floatval($breakdown['labor']),
            'confidence' => floatval($dbData['labor_confidence'] ?? 0.5),
            'factors' => $laborFactors,
            'explanation' => createLaborExplanation($laborFactors, $dbData)
        ];
    }

    // Energy component
    if (isset($breakdown['energy']) && $breakdown['energy'] > 0) {
        $energyFactors = json_decode($dbData['energy_usage_factors'] ?? '[]', true);
        $components[] = [
            'type' => 'energy',
            'label' => 'Energy Cost',
            'amount' => floatval($breakdown['energy']),
            'confidence' => floatval($dbData['energy_confidence'] ?? 0.5),
            'factors' => $energyFactors,
            'explanation' => createEnergyExplanation($energyFactors, $dbData)
        ];
    }

    // Equipment component
    if (isset($breakdown['equipment']) && $breakdown['equipment'] > 0) {
        $equipmentReqs = json_decode($dbData['equipment_requirements'] ?? '[]', true);
        $components[] = [
            'type' => 'equipment',
            'label' => 'Equipment Cost',
            'amount' => floatval($breakdown['equipment']),
            'confidence' => floatval($dbData['equipment_confidence'] ?? 0.5),
            'factors' => $equipmentReqs,
            'explanation' => createEquipmentExplanation($equipmentReqs, $dbData)
        ];
    }

    return $components;
}

function createMaterialsExplanation($factors, $dbData)
{
    $materials = json_decode($dbData['detected_materials'] ?? '[]', true);
    $explanation = 'Material costs based on detected materials and complexity. ';

    if (!empty($materials)) {
        $explanation .= 'Detected materials: ' . implode(', ', array_slice($materials, 0, 3));
        if (count($materials) > 3) {
            $explanation .= ' and ' . (count($materials) - 3) . ' more';
        }
        $explanation .= '. ';
    }

    if (!empty($factors)) {
        $explanation .= 'Cost factors include: ' . implode(', ', array_slice($factors, 0, 2)) . '.';
    }

    return $explanation;
}

function createLaborExplanation($factors, $dbData)
{
    $skillLevel = $dbData['skill_level_required'] ?? 'intermediate';
    $productionTime = intval($dbData['production_time_estimate'] ?? 0);

    $explanation = "Labor costs based on $skillLevel skill level required";

    if ($productionTime > 0) {
        $explanation .= " and estimated $productionTime minutes production time";
    }

    $explanation .= '. ';

    if (!empty($factors)) {
        $explanation .= 'Complexity factors: ' . implode(', ', array_slice($factors, 0, 2)) . '.';
    }

    return $explanation;
}

function createEnergyExplanation($factors, $dbData)
{
    $explanation = 'Energy costs for equipment operation and production processes. ';

    if (!empty($factors)) {
        $explanation .= 'Usage factors: ' . implode(', ', array_slice($factors, 0, 2)) . '.';
    }

    $complexityScore = floatval($dbData['complexity_score'] ?? 0);
    if ($complexityScore > 0.7) {
        $explanation .= ' Higher energy usage due to complex production requirements.';
    } elseif ($complexityScore < 0.3) {
        $explanation .= ' Lower energy usage for simple production process.';
    }

    return $explanation;
}

function createEquipmentExplanation($requirements, $dbData)
{
    $explanation = 'Equipment costs including depreciation, maintenance, and specialized tools. ';

    if (!empty($requirements)) {
        $explanation .= 'Required equipment: ' . implode(', ', array_slice($requirements, 0, 2));
        if (count($requirements) > 2) {
            $explanation .= ' and ' . (count($requirements) - 2) . ' more';
        }
        $explanation .= '. ';
    }

    $marketPositioning = $dbData['market_positioning'] ?? 'standard';
    if ($marketPositioning === 'premium') {
        $explanation .= 'Premium positioning requires specialized equipment.';
    }

    return $explanation;
}
?> 