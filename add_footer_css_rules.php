<?php
/**
 * Add Footer CSS Rules to Global CSS System
 */

require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Adding footer CSS rules to global CSS system...\n";
    
    $footerRules = [
        ['footer-bg-color', '--footer-bg-color', '#2d3748', 'footer', 'Footer background color'],
        ['footer-text-color', '--footer-text-color', '#ffffff', 'footer', 'Footer text color'],
        ['footer-link-color', '--footer-link-color', '#87ac3a', 'footer', 'Footer link color'],
        ['footer-link-hover-color', '--footer-link-hover-color', '#a3cc4a', 'footer', 'Footer link hover color'],
        ['footer-border-color', '--footer-border-color', '#4a5568', 'footer', 'Footer border/divider color'],
        ['footer-padding-top', '--footer-padding-top', '40px', 'footer', 'Footer top padding'],
        ['footer-padding-bottom', '--footer-padding-bottom', '40px', 'footer', 'Footer bottom padding'],
        ['footer-font-size', '--footer-font-size', '14px', 'footer', 'Footer text font size'],
        ['footer-heading-color', '--footer-heading-color', '#87ac3a', 'footer', 'Footer heading color'],
        ['footer-heading-size', '--footer-heading-size', '18px', 'footer', 'Footer heading font size'],
        ['footer-copyright-color', '--footer-copyright-color', '#a0aec0', 'footer', 'Copyright text color'],
        ['footer-copyright-size', '--footer-copyright-size', '12px', 'footer', 'Copyright text font size'],
        ['footer-social-icon-color', '--footer-social-icon-color', '#87ac3a', 'footer', 'Social media icon color'],
        ['footer-social-icon-hover', '--footer-social-icon-hover', '#a3cc4a', 'footer', 'Social media icon hover color'],
        ['footer-divider-style', '--footer-divider-style', '1px solid #4a5568', 'footer', 'Footer section divider style']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO global_css_rules (rule_name, css_property, css_value, category, description, is_active) 
        VALUES (?, ?, ?, ?, ?, 1) 
        ON DUPLICATE KEY UPDATE 
        css_value = VALUES(css_value), 
        description = VALUES(description)
    ");
    
    $added = 0;
    foreach ($footerRules as $rule) {
        $stmt->execute($rule);
        $added++;
        echo "Added: {$rule[0]} ({$rule[1]}) = {$rule[2]}\n";
    }
    
    echo "\n✅ Successfully added {$added} footer CSS rules!\n";
    echo "These can now be managed through Admin Settings > Global CSS Rules > Footer tab\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 