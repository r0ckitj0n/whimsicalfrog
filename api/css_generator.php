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
    
    // First pass: CSS variables and regular properties
    foreach ($rules as $rule) {
        if ($rule['category'] !== $currentCategory) {
            $currentCategory = $rule['category'];
            $css .= "\n/* === " . ucwords(str_replace('_', ' ', $currentCategory)) . " === */\n";
        }
        
        // Check if this is a utility class (contains full CSS block)
        if (strpos($rule['rule_name'], '_utility_class') !== false && strpos($rule['css_value'], '{') !== false) {
            // This is a utility class - store it for later processing
            $utilityClasses[] = $rule;
            continue;
        }
        
        // Generate CSS variable
        $css .= ":root {\n";
        $css .= "    --{$rule['rule_name']}: {$rule['css_value']};\n";
        $css .= "}\n\n";
    }
    
    // Second pass: Utility classes
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