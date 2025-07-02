/**
 * WhimsicalFrog Form Validation and Input Handling
 * Centralized JavaScript functions to eliminate duplication
 * Generated: 2025-07-01 23:31:50
 */

// Form Validation Dependencies
// Requires: ui-manager.js for error display

    
    /**
     * Validate required fields in input data
     * @param array $data
     * @param array $requiredFields
     * @return bool
     */
    public static function validateRequired($data, $requiredFields) {
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            self::validationError(['missing_fields' => $missing]);
            return false;
        }
        
        return true;
    }

