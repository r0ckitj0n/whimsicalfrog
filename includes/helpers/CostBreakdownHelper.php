<?php
/**
 * includes/helpers/CostBreakdownHelper.php
 * Helper class for cost breakdown operations
 */

require_once __DIR__ . '/../Constants.php';

class CostBreakdownHelper {
    /**
     * Get table name from cost type
     */
    public static function getTableName($type) {
        switch ($type) {
            case WF_Constants::COST_CATEGORY_MATERIALS: return 'inventory_materials';
            case WF_Constants::COST_CATEGORY_LABOR: return 'inventory_labors';
            case WF_Constants::COST_CATEGORY_ENERGY: return 'inventory_energies';
            case WF_Constants::COST_CATEGORY_EQUIPMENT: return 'inventory_equipments';
            default: return null;
        }
    }

    /**
     * Validate cost data
     */
    public static function validateCostData($data, $type) {
        if (!isset($data['cost']) || !is_numeric($data['cost']) || floatval($data['cost']) < 0) {
            return 'Cost must be a valid non-negative number';
        }

        switch ($type) {
            case WF_Constants::COST_CATEGORY_MATERIALS:
                if (!isset($data['name']) || empty(trim($data['name']))) {
                    return 'Material name is required';
                }
                break;
            case WF_Constants::COST_CATEGORY_LABOR:
            case WF_Constants::COST_CATEGORY_ENERGY:
            case WF_Constants::COST_CATEGORY_EQUIPMENT:
                if (!isset($data['description']) || empty(trim($data['description']))) {
                    return 'Description is required';
                }
                break;
            default:
                return 'Invalid cost type';
        }
        return true;
    }

    /**
     * Get summary of all costs for a SKU
     */
    public static function getSkuSummary($sku) {
        $materials = Database::queryAll("SELECT * FROM inventory_materials WHERE sku = ?", [$sku]);
        $labor = Database::queryAll("SELECT * FROM inventory_labors WHERE sku = ?", [$sku]);
        $energy = Database::queryAll("SELECT * FROM inventory_energies WHERE sku = ?", [$sku]);
        $equipment = Database::queryAll("SELECT * FROM inventory_equipments WHERE sku = ?", [$sku]);

        $materialTotal = array_sum(array_column($materials, 'cost'));
        $laborTotal = array_sum(array_column($labor, 'cost'));
        $energyTotal = array_sum(array_column($energy, 'cost'));
        $equipmentTotal = array_sum(array_column($equipment, 'cost'));

        $total = $materialTotal + $laborTotal + $energyTotal + $equipmentTotal;

        return [
            WF_Constants::COST_CATEGORY_MATERIALS => $materials,
            WF_Constants::COST_CATEGORY_LABOR => $labor,
            WF_Constants::COST_CATEGORY_ENERGY => $energy,
            WF_Constants::COST_CATEGORY_EQUIPMENT => $equipment,
            'totals' => [
                WF_Constants::COST_CATEGORY_MATERIALS => $materialTotal,
                WF_Constants::COST_CATEGORY_LABOR => $laborTotal,
                WF_Constants::COST_CATEGORY_ENERGY => $energyTotal,
                WF_Constants::COST_CATEGORY_EQUIPMENT => $equipmentTotal,
                'total' => $total
            ]
        ];
    }
}
