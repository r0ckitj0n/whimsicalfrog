#!/usr/bin/env php
<?php
/**
 * Quick Database Utility
 * Simple command-line tool for database operations
 */

// Include database configuration
require_once __DIR__ . '/includes/database.php';

function connectLocal() {
    try {
        $dsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, 'root', 'Palz2516', $options);
    } catch (PDOException $e) {
        echo "❌ Local database connection failed: " . $e->getMessage() . "\n";
        return null;
    }
}

function testGlobalCSSRules() {
    echo "🔍 Testing Global CSS Rules\n";
    echo "==========================\n";
    
    $pdo = connectLocal();
    if (!$pdo) return;
    
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'global_css_rules'");
        if ($stmt->rowCount() === 0) {
            echo "❌ global_css_rules table does not exist\n";
            return;
        }
        
        // Count total rules
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM global_css_rules");
        $total = $stmt->fetch()['count'];
        echo "📊 Total CSS rules: {$total}\n";
        
        // Count by category
        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM global_css_rules GROUP BY category ORDER BY count DESC");
        $categories = $stmt->fetchAll();
        echo "\n📁 Rules by category:\n";
        foreach ($categories as $cat) {
            echo "  - {$cat['category']}: {$cat['count']} rules\n";
        }
        
        // Check for main_room rules
        $stmt = $pdo->query("SELECT * FROM global_css_rules WHERE category = 'main_room'");
        $mainRoomRules = $stmt->fetchAll();
        echo "\n🏠 Main room rules: " . count($mainRoomRules) . "\n";
        
        if (count($mainRoomRules) > 0) {
            foreach ($mainRoomRules as $rule) {
                echo "  - {$rule['rule_name']}: {$rule['css_value']}\n";
            }
        }
        
        // Look for border radius rules
        $stmt = $pdo->query("SELECT * FROM global_css_rules WHERE rule_name LIKE '%border_radius%'");
        $borderRules = $stmt->fetchAll();
        echo "\n🔘 Border radius rules: " . count($borderRules) . "\n";
        
        if (count($borderRules) > 0) {
            foreach ($borderRules as $rule) {
                echo "  - {$rule['rule_name']} ({$rule['category']}): {$rule['css_value']}\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function addMainRoomBorderRadius() {
    echo "➕ Adding Main Room Border Radius\n";
    echo "=================================\n";
    
    $pdo = connectLocal();
    if (!$pdo) return;
    
    try {
        // Check if rule already exists
        $stmt = $pdo->prepare("SELECT * FROM global_css_rules WHERE rule_name = ? AND category = ?");
        $stmt->execute(['main_room_section_border_radius', 'main_room']);
        
        if ($stmt->rowCount() > 0) {
            echo "⚠️  Rule already exists\n";
            $existing = $stmt->fetch();
            echo "Current value: {$existing['css_value']}\n";
            return;
        }
        
        // Add the rule
        $stmt = $pdo->prepare("
            INSERT INTO global_css_rules (rule_name, css_value, css_property, category, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            'main_room_section_border_radius',
            '15px',
            'border-radius',
            'main_room',
            1
        ]);
        
        echo "✅ Added main_room_section_border_radius rule\n";
        
        // Add overflow rule too
        $stmt = $pdo->prepare("
            INSERT INTO global_css_rules (rule_name, css_value, css_property, category, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            'main_room_section_overflow',
            'hidden',
            'overflow',
            'main_room',
            1
        ]);
        
        echo "✅ Added main_room_section_overflow rule\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function generateCSS() {
    echo "🎨 Generating CSS from Database\n";
    echo "==============================\n";
    
    $pdo = connectLocal();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->query("SELECT * FROM global_css_rules WHERE is_active = 1 ORDER BY category, rule_name");
        $rules = $stmt->fetchAll();
        
        $css = "/* Generated CSS from Database */\n\n";
        $currentCategory = '';
        
        foreach ($rules as $rule) {
            if ($rule['category'] !== $currentCategory) {
                $css .= "\n/* " . ucfirst(str_replace('_', ' ', $rule['category'])) . " */\n";
                $currentCategory = $rule['category'];
            }
            
            // CSS Variable
            $css .= ":root {\n";
            $css .= "    --{$rule['rule_name']}: {$rule['css_value']};\n";
            $css .= "}\n\n";
            
            // Utility Class
            $className = str_replace('_', '-', $rule['rule_name']);
            $css .= ".{$className} {\n";
            $css .= "    {$rule['css_property']}: {$rule['css_value']};\n";
            $css .= "}\n\n";
        }
        
        file_put_contents('generated_css_test.css', $css);
        echo "✅ CSS generated and saved to generated_css_test.css\n";
        echo "📊 Generated " . count($rules) . " CSS rules\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}

function quickQuery($sql) {
    echo "🔍 Quick Query\n";
    echo "=============\n";
    echo "SQL: {$sql}\n\n";
    
    $pdo = connectLocal();
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->query($sql);
        
        if ($stmt->columnCount() > 0) {
            $results = $stmt->fetchAll();
            
            if (empty($results)) {
                echo "No results found.\n";
                return;
            }
            
            // Show results
            foreach ($results as $i => $row) {
                echo "Row " . ($i + 1) . ":\n";
                foreach ($row as $key => $value) {
                    echo "  {$key}: {$value}\n";
                }
                echo "\n";
            }
            
            echo "Total rows: " . count($results) . "\n";
        } else {
            echo "✅ Query executed successfully.\n";
            echo "Affected rows: " . $stmt->rowCount() . "\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Query failed: " . $e->getMessage() . "\n";
    }
}

// Main execution
if ($argc < 2) {
    echo "🐸 WhimsicalFrog Quick Database Utility\n";
    echo "=======================================\n\n";
    echo "Usage: php db_quick.php [command]\n\n";
    echo "Commands:\n";
    echo "  test-css      - Test global CSS rules\n";
    echo "  add-border    - Add main room border radius rule\n";
    echo "  generate-css  - Generate CSS file from database\n";
    echo "  query \"SQL\"   - Execute quick SQL query\n\n";
    echo "Examples:\n";
    echo "  php db_quick.php test-css\n";
    echo "  php db_quick.php query \"SELECT COUNT(*) FROM items\"\n";
    exit;
}

$command = $argv[1];

switch ($command) {
    case 'test-css':
        testGlobalCSSRules();
        break;
        
    case 'add-border':
        addMainRoomBorderRadius();
        break;
        
    case 'generate-css':
        generateCSS();
        break;
        
    case 'query':
        if (!isset($argv[2])) {
            echo "❌ SQL query required\n";
            exit(1);
        }
        quickQuery($argv[2]);
        break;
        
    default:
        echo "❌ Unknown command: {$command}\n";
        exit(1);
} 