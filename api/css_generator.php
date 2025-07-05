<?php
// CSS Generator for Global CSS Rules
// This endpoint generates actual CSS content for linking in HTML

require_once __DIR__ . '/../includes/database.php';

// Set proper CSS content type
header('Content-Type: text/css');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

try {
    $pdo = Database::getInstance();
    
    // Get category filter if provided
    $category = $_GET['category'] ?? '';
    
    // Build query
    $query = "SELECT rule_name, css_property, css_value, category FROM global_css_rules WHERE is_active = 1";
    $params = [];
    
    if (!empty($category)) {
        $query .= " AND category = ?";
        $params[] = $category;
    }
    
    $query .= " ORDER BY category, rule_name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSS content
    echo generateCSSContent($rules);
    
} catch (Exception $e) {
    echo "/* Error generating CSS: " . $e->getMessage() . " */\n";
}

function generateCSSContent($rules) {
    $css = "/* Global CSS Rules - Generated from Database */\n";
    $css .= "/* Generated on: " . date('Y-m-d H:i:s') . " */\n";
    $css .= "/* WhimsicalFrog Global CSS System */\n\n";
    
    $currentCategory = '';
    $utilityClasses = [];
    $cssRules = [];
    
    // First pass: Group rules by selector and collect utility classes
    foreach ($rules as $rule) {
        if ($rule['category'] !== $currentCategory) {
            $currentCategory = $rule['category'];
        }
        
        // Check if this is a utility class (contains full CSS block)
        if (strpos($rule['rule_name'], '_utility_class') !== false && strpos($rule['css_value'], '{') !== false) {
            $utilityClasses[] = $rule;
            continue;
        }
        
        // Check if this is a CSS selector (starts with . # or is an element name)
        if (preg_match('/^(\.|#|[a-zA-Z]|\[|:)/', $rule['rule_name'])) {
            // This is a CSS selector - group properties by selector
            if (!isset($cssRules[$rule['category']])) {
                $cssRules[$rule['category']] = [];
            }
            if (!isset($cssRules[$rule['category']][$rule['rule_name']])) {
                $cssRules[$rule['category']][$rule['rule_name']] = [];
            }
            $cssRules[$rule['category']][$rule['rule_name']][] = [
                'property' => $rule['css_property'],
                'value' => $rule['css_value']
            ];
        } else {
            // This is a CSS variable
            $css .= ":root {\n";
            $css .= "    --{$rule['rule_name']}: {$rule['css_value']};\n";
            $css .= "}\n\n";
        }
    }
    
    // Second pass: Generate CSS rules for selectors
    foreach ($cssRules as $category => $selectors) {
        $css .= "\n/* === " . ucwords(str_replace('_', ' ', $category)) . " === */\n";
        
        foreach ($selectors as $selector => $properties) {
            $css .= "{$selector} {\n";
            foreach ($properties as $prop) {
                $css .= "    {$prop['property']}: {$prop['value']};\n";
            }
            $css .= "}\n\n";
        }
    }
    
    // Third pass: Utility classes
    if (!empty($utilityClasses)) {
        $css .= "\n/* === Utility Classes === */\n";
        foreach ($utilityClasses as $utilityRule) {
            $css .= "/* " . ucwords(str_replace('_', ' ', $utilityRule['rule_name'])) . " */\n";
            $css .= $utilityRule['css_value'] . "\n\n";
        }
    }
    
    return $css;
}
?> 