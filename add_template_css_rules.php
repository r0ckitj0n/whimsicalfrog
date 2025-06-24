<?php
/**
 * Add Complete Template CSS Rules to Global CSS System
 * Creates admin controls for all major template components
 */

require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Adding comprehensive template CSS rules to global CSS system...\n";
    
    $templateRules = [
        // Header/Navigation System
        ['header-bg-color', '--header-bg-color', '#ffffff', 'header', 'Header background color'],
        ['header-text-color', '--header-text-color', '#374151', 'header', 'Header text color'],
        ['header-logo-height', '--header-logo-height', '60px', 'header', 'Logo height in header'],
        ['header-padding-y', '--header-padding-y', '1rem', 'header', 'Header vertical padding'],
        ['header-shadow', '--header-shadow', '0 1px 3px rgba(0,0,0,0.1)', 'header', 'Header drop shadow'],
        ['nav-link-color', '--nav-link-color', '#374151', 'header', 'Navigation link color'],
        ['nav-link-hover-color', '--nav-link-hover-color', '#87ac3a', 'header', 'Navigation link hover color'],
        ['nav-link-active-color', '--nav-link-active-color', '#87ac3a', 'header', 'Active navigation link color'],
        ['search-bar-bg', '--search-bar-bg', 'rgba(255,255,255,0.9)', 'header', 'Search bar background'],
        ['search-bar-border', '--search-bar-border', '2px solid rgba(255,255,255,0.6)', 'header', 'Search bar border'],
        ['search-placeholder-color', '--search-placeholder-color', 'rgba(0,0,0,0.5)', 'header', 'Search placeholder text color'],
        
        // Button System
        ['btn-primary-bg', '--btn-primary-bg', '#87ac3a', 'buttons', 'Primary button background'],
        ['btn-primary-color', '--btn-primary-color', '#ffffff', 'buttons', 'Primary button text color'],
        ['btn-primary-hover-bg', '--btn-primary-hover-bg', '#6b8e23', 'buttons', 'Primary button hover background'],
        ['btn-secondary-bg', '--btn-secondary-bg', 'transparent', 'buttons', 'Secondary button background'],
        ['btn-secondary-color', '--btn-secondary-color', '#87ac3a', 'buttons', 'Secondary button text color'],
        ['btn-secondary-border', '--btn-secondary-border', '2px solid #87ac3a', 'buttons', 'Secondary button border'],
        ['btn-secondary-hover-bg', '--btn-secondary-hover-bg', '#87ac3a', 'buttons', 'Secondary button hover background'],
        ['btn-secondary-hover-color', '--btn-secondary-hover-color', '#ffffff', 'buttons', 'Secondary button hover text color'],
        ['btn-danger-bg', '--btn-danger-bg', '#dc2626', 'buttons', 'Danger button background'],
        ['btn-danger-color', '--btn-danger-color', '#ffffff', 'buttons', 'Danger button text color'],
        ['btn-danger-hover-bg', '--btn-danger-hover-bg', '#b91c1c', 'buttons', 'Danger button hover background'],
        ['btn-padding', '--btn-padding', '0.5rem 1rem', 'buttons', 'Button padding'],
        ['btn-border-radius', '--btn-border-radius', '0.375rem', 'buttons', 'Button border radius'],
        ['btn-font-weight', '--btn-font-weight', '600', 'buttons', 'Button font weight'],
        ['btn-transition', '--btn-transition', 'all 0.2s ease', 'buttons', 'Button transition effect'],
        
        // Form System
        ['form-input-bg', '--form-input-bg', '#ffffff', 'forms', 'Form input background'],
        ['form-input-border', '--form-input-border', '1px solid #d1d5db', 'forms', 'Form input border'],
        ['form-input-border-focus', '--form-input-border-focus', '2px solid #87ac3a', 'forms', 'Form input focus border'],
        ['form-input-color', '--form-input-color', '#374151', 'forms', 'Form input text color'],
        ['form-input-placeholder', '--form-input-placeholder', '#9ca3af', 'forms', 'Form input placeholder color'],
        ['form-input-padding', '--form-input-padding', '0.5rem 0.75rem', 'forms', 'Form input padding'],
        ['form-input-border-radius', '--form-input-border-radius', '0.375rem', 'forms', 'Form input border radius'],
        ['form-label-color', '--form-label-color', '#374151', 'forms', 'Form label color'],
        ['form-label-font-weight', '--form-label-font-weight', '500', 'forms', 'Form label font weight'],
        ['form-error-color', '--form-error-color', '#dc2626', 'forms', 'Form error text color'],
        ['form-success-color', '--form-success-color', '#059669', 'forms', 'Form success text color'],
        ['form-select-bg', '--form-select-bg', '#ffffff', 'forms', 'Form select background'],
        ['form-select-arrow', '--form-select-arrow', "url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIiIGhlaWdodD0iOCIgdmlld0JveD0iMCAwIDEyIDgiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik0xIDFMNiA2TDExIDEiIHN0cm9rZT0iIzZCNzI4MCIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+Cg==')", 'forms', 'Form select dropdown arrow'],
        
        // Card/Product System
        ['card-bg', '--card-bg', '#ffffff', 'cards', 'Card background color'],
        ['card-border', '--card-border', '1px solid #e5e7eb', 'cards', 'Card border'],
        ['card-border-radius', '--card-border-radius', '0.5rem', 'cards', 'Card border radius'],
        ['card-shadow', '--card-shadow', '0 1px 3px rgba(0,0,0,0.1)', 'cards', 'Card drop shadow'],
        ['card-shadow-hover', '--card-shadow-hover', '0 4px 6px rgba(0,0,0,0.1)', 'cards', 'Card hover shadow'],
        ['card-padding', '--card-padding', '1.5rem', 'cards', 'Card internal padding'],
        ['card-title-color', '--card-title-color', '#111827', 'cards', 'Card title color'],
        ['card-title-size', '--card-title-size', '1.125rem', 'cards', 'Card title font size'],
        ['card-text-color', '--card-text-color', '#6b7280', 'cards', 'Card body text color'],
        ['card-text-size', '--card-text-size', '0.875rem', 'cards', 'Card body text size'],
        ['product-price-color', '--product-price-color', '#87ac3a', 'cards', 'Product price color'],
        ['product-price-size', '--product-price-size', '1.25rem', 'cards', 'Product price font size'],
        ['product-price-weight', '--product-price-weight', '700', 'cards', 'Product price font weight'],
        ['product-image-border-radius', '--product-image-border-radius', '0.375rem', 'cards', 'Product image border radius'],
        
        // Typography System
        ['heading-font-family', '--heading-font-family', "'Merienda', cursive", 'typography', 'Heading font family'],
        ['body-font-family', '--body-font-family', "'Inter', sans-serif", 'typography', 'Body font family'],
        ['heading-color', '--heading-color', '#111827', 'typography', 'Heading text color'],
        ['body-color', '--body-color', '#374151', 'typography', 'Body text color'],
        ['heading-h1-size', '--heading-h1-size', '2.25rem', 'typography', 'H1 heading size'],
        ['heading-h2-size', '--heading-h2-size', '1.875rem', 'typography', 'H2 heading size'],
        ['heading-h3-size', '--heading-h3-size', '1.5rem', 'typography', 'H3 heading size'],
        ['heading-h4-size', '--heading-h4-size', '1.25rem', 'typography', 'H4 heading size'],
        ['body-text-size', '--body-text-size', '1rem', 'typography', 'Body text size'],
        ['small-text-size', '--small-text-size', '0.875rem', 'typography', 'Small text size'],
        ['line-height-tight', '--line-height-tight', '1.25', 'typography', 'Tight line height'],
        ['line-height-normal', '--line-height-normal', '1.5', 'typography', 'Normal line height'],
        ['line-height-relaxed', '--line-height-relaxed', '1.75', 'typography', 'Relaxed line height'],
        
        // Layout System
        ['container-max-width', '--container-max-width', '1200px', 'layout', 'Maximum container width'],
        ['container-padding', '--container-padding', '1rem', 'layout', 'Container horizontal padding'],
        ['section-padding-y', '--section-padding-y', '3rem', 'layout', 'Section vertical padding'],
        ['section-margin-y', '--section-margin-y', '2rem', 'layout', 'Section vertical margin'],
        ['grid-gap', '--grid-gap', '1.5rem', 'layout', 'Grid gap spacing'],
        ['border-radius-sm', '--border-radius-sm', '0.25rem', 'layout', 'Small border radius'],
        ['border-radius-md', '--border-radius-md', '0.375rem', 'layout', 'Medium border radius'],
        ['border-radius-lg', '--border-radius-lg', '0.5rem', 'layout', 'Large border radius'],
        ['border-radius-xl', '--border-radius-xl', '0.75rem', 'layout', 'Extra large border radius'],
        ['spacing-xs', '--spacing-xs', '0.25rem', 'layout', 'Extra small spacing'],
        ['spacing-sm', '--spacing-sm', '0.5rem', 'layout', 'Small spacing'],
        ['spacing-md', '--spacing-md', '1rem', 'layout', 'Medium spacing'],
        ['spacing-lg', '--spacing-lg', '1.5rem', 'layout', 'Large spacing'],
        ['spacing-xl', '--spacing-xl', '3rem', 'layout', 'Extra large spacing'],
        
        // Color System
        ['color-primary', '--color-primary', '#87ac3a', 'colors', 'Primary brand color'],
        ['color-primary-light', '--color-primary-light', '#a3cc4a', 'colors', 'Light primary color'],
        ['color-primary-dark', '--color-primary-dark', '#6b8e23', 'colors', 'Dark primary color'],
        ['color-secondary', '--color-secondary', '#6b7280', 'colors', 'Secondary color'],
        ['color-accent', '--color-accent', '#f59e0b', 'colors', 'Accent color'],
        ['color-success', '--color-success', '#10b981', 'colors', 'Success color'],
        ['color-warning', '--color-warning', '#f59e0b', 'colors', 'Warning color'],
        ['color-error', '--color-error', '#ef4444', 'colors', 'Error color'],
        ['color-info', '--color-info', '#3b82f6', 'colors', 'Info color'],
        ['color-gray-50', '--color-gray-50', '#f9fafb', 'colors', 'Gray 50'],
        ['color-gray-100', '--color-gray-100', '#f3f4f6', 'colors', 'Gray 100'],
        ['color-gray-200', '--color-gray-200', '#e5e7eb', 'colors', 'Gray 200'],
        ['color-gray-300', '--color-gray-300', '#d1d5db', 'colors', 'Gray 300'],
        ['color-gray-400', '--color-gray-400', '#9ca3af', 'colors', 'Gray 400'],
        ['color-gray-500', '--color-gray-500', '#6b7280', 'colors', 'Gray 500'],
        ['color-gray-600', '--color-gray-600', '#4b5563', 'colors', 'Gray 600'],
        ['color-gray-700', '--color-gray-700', '#374151', 'colors', 'Gray 700'],
        ['color-gray-800', '--color-gray-800', '#1f2937', 'colors', 'Gray 800'],
        ['color-gray-900', '--color-gray-900', '#111827', 'colors', 'Gray 900'],
        
        // Animation System
        ['transition-fast', '--transition-fast', '0.15s ease', 'animations', 'Fast transition'],
        ['transition-normal', '--transition-normal', '0.2s ease', 'animations', 'Normal transition'],
        ['transition-slow', '--transition-slow', '0.3s ease', 'animations', 'Slow transition'],
        ['animation-fade-in', '--animation-fade-in', 'fadeIn 0.3s ease-in-out', 'animations', 'Fade in animation'],
        ['animation-slide-up', '--animation-slide-up', 'slideUp 0.3s ease-out', 'animations', 'Slide up animation'],
        ['animation-bounce', '--animation-bounce', 'bounce 0.5s ease-in-out', 'animations', 'Bounce animation'],
        ['hover-scale', '--hover-scale', 'scale(1.05)', 'animations', 'Hover scale transform'],
        ['hover-lift', '--hover-lift', 'translateY(-2px)', 'animations', 'Hover lift transform'],
        
        // Admin Interface System
        ['admin-bg-color', '--admin-bg-color', '#f8fafc', 'admin', 'Admin interface background'],
        ['admin-sidebar-bg', '--admin-sidebar-bg', '#ffffff', 'admin', 'Admin sidebar background'],
        ['admin-header-bg', '--admin-header-bg', '#87ac3a', 'admin', 'Admin header background'],
        ['admin-header-color', '--admin-header-color', '#ffffff', 'admin', 'Admin header text color'],
        ['admin-nav-active-bg', '--admin-nav-active-bg', '#f3f4f6', 'admin', 'Active admin nav background'],
        ['admin-nav-hover-bg', '--admin-nav-hover-bg', '#e5e7eb', 'admin', 'Hover admin nav background'],
        ['admin-border-color', '--admin-border-color', '#e5e7eb', 'admin', 'Admin border color'],
        ['admin-text-primary', '--admin-text-primary', '#111827', 'admin', 'Primary admin text color'],
        ['admin-text-secondary', '--admin-text-secondary', '#6b7280', 'admin', 'Secondary admin text color'],
        ['admin-success-bg', '--admin-success-bg', '#d1fae5', 'admin', 'Admin success background'],
        ['admin-error-bg', '--admin-error-bg', '#fee2e2', 'admin', 'Admin error background'],
        ['admin-warning-bg', '--admin-warning-bg', '#fef3c7', 'admin', 'Admin warning background'],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO global_css_rules (rule_name, css_property, css_value, category, description, is_active) 
        VALUES (?, ?, ?, ?, ?, 1) 
        ON DUPLICATE KEY UPDATE 
        css_value = VALUES(css_value), 
        description = VALUES(description)
    ");
    
    $added = 0;
    foreach ($templateRules as $rule) {
        $stmt->execute($rule);
        $added++;
        echo "Added: {$rule[0]} ({$rule[1]}) = {$rule[2]} [{$rule[3]}]\n";
    }
    
    echo "\n✅ Successfully added {$added} template CSS rules!\n";
    echo "These can now be managed through Admin Settings > Global CSS Rules\n";
    echo "\nCategories added:\n";
    echo "- Header (11 rules) - Navigation and header styling\n";
    echo "- Buttons (15 rules) - All button types and states\n";
    echo "- Forms (13 rules) - Input fields, labels, validation\n";
    echo "- Cards (13 rules) - Product cards and content cards\n";
    echo "- Typography (13 rules) - Fonts, sizes, line heights\n";
    echo "- Layout (15 rules) - Spacing, containers, grids\n";
    echo "- Colors (20 rules) - Complete color palette\n";
    echo "- Animations (8 rules) - Transitions and effects\n";
    echo "- Admin (12 rules) - Admin interface styling\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 