#!/usr/bin/env python3
"""
Targeted Missing Styles Extractor for WhimsicalFrog
Extracts specific missing CSS components from backup files
"""

import re
from pathlib import Path

def extract_header_variables(backup_css_content):
    """Extract header-specific CSS variables that are missing"""
    header_vars = {}
    
    # Look for header-related CSS variables
    header_var_pattern = r'(--header-[^:]*|--nav-[^:]*|--transition-[^:]*)\s*:\s*([^;]+);'
    matches = re.findall(header_var_pattern, backup_css_content)
    
    for var_name, var_value in matches:
        header_vars[var_name.strip()] = var_value.strip()
    
    return header_vars

def extract_modal_styles(backup_css_content):
    """Extract modal-related styles"""
    modal_styles = []
    
    # Extract complete modal CSS rules
    modal_pattern = r'(\.[^{]*modal[^{]*)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
    matches = re.findall(modal_pattern, backup_css_content, re.IGNORECASE | re.MULTILINE)
    
    for selector, declarations in matches:
        if selector.strip() and declarations.strip():
            modal_styles.append(f"{selector.strip()} {{\n{declarations.strip()}\n}}\n")
    
    return modal_styles

def extract_admin_styles(backup_css_content):
    """Extract admin interface styles"""
    admin_styles = []
    
    # Extract admin-related CSS rules
    admin_pattern = r'(\.[^{]*admin[^{]*)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
    matches = re.findall(admin_pattern, backup_css_content, re.IGNORECASE | re.MULTILINE)
    
    for selector, declarations in matches:
        if selector.strip() and declarations.strip():
            admin_styles.append(f"{selector.strip()} {{\n{declarations.strip()}\n}}\n")
    
    return admin_styles

def extract_notification_styles(backup_css_content):
    """Extract notification system styles"""
    notification_styles = []
    
    # Extract notification-related CSS rules
    notif_pattern = r'(\.[^{]*(?:notification|alert|toast)[^{]*)\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}'
    matches = re.findall(notif_pattern, backup_css_content, re.IGNORECASE | re.MULTILINE)
    
    for selector, declarations in matches:
        if selector.strip() and declarations.strip():
            notification_styles.append(f"{selector.strip()} {{\n{declarations.strip()}\n}}\n")
    
    return notification_styles

def extract_z_index_rules(backup_css_content):
    """Extract z-index related rules and create hierarchy"""
    z_index_rules = []
    z_values = set()
    
    # Find all z-index declarations
    z_pattern = r'([^{]+)\{[^{}]*z-index\s*:\s*([^;]+);[^{}]*\}'
    matches = re.findall(z_pattern, backup_css_content, re.MULTILINE)
    
    for selector, z_value in matches:
        selector = selector.strip()
        z_value = z_value.strip()
        
        try:
            z_int = int(z_value)
            z_values.add(z_int)
            z_index_rules.append(f"/* z-index: {z_value} */\n{selector} {{\n  z-index: {z_value};\n}}\n")
        except ValueError:
            # Handle CSS variables or calc() values
            z_index_rules.append(f"/* z-index: {z_value} */\n{selector} {{\n  z-index: {z_value};\n}}\n")
    
    return z_index_rules, sorted(z_values)

def main():
    backup_dir = Path("/Users/jongraves/Documents/Websites/WhimsicalFrog - Backups")
    base_dir = Path("/Users/jongraves/Documents/Websites/WhimsicalFrog")
    
    # Read backup bundle.css
    backup_bundle = backup_dir / "css" / "bundle.css"
    if not backup_bundle.exists():
        print(f"❌ Backup bundle.css not found at {backup_bundle}")
        return
    
    print(f"📖 Reading backup bundle.css ({backup_bundle.stat().st_size // 1024}KB)")
    
    with open(backup_bundle, 'r', encoding='utf-8') as f:
        backup_content = f.read()
    
    # Extract specific missing components
    print("🔍 Extracting missing components...")
    
    header_vars = extract_header_variables(backup_content)
    modal_styles = extract_modal_styles(backup_content)
    admin_styles = extract_admin_styles(backup_content)
    notification_styles = extract_notification_styles(backup_content)
    z_index_rules, z_values = extract_z_index_rules(backup_content)
    
    print(f"✅ Found {len(header_vars)} header variables")
    print(f"✅ Found {len(modal_styles)} modal styles")
    print(f"✅ Found {len(admin_styles)} admin styles")
    print(f"✅ Found {len(notification_styles)} notification styles")
    print(f"✅ Found {len(z_index_rules)} z-index rules")
    
    # Generate missing header variables file
    if header_vars:
        header_output = base_dir / "css" / "missing-header-vars.css"
        with open(header_output, 'w') as f:
            f.write("/* Missing Header Variables - Extracted from Backup */\n\n")
            f.write(":root {\n")
            for var_name, var_value in sorted(header_vars.items()):
                f.write(f"  {var_name}: {var_value};\n")
            f.write("}\n")
        print(f"📄 Generated: {header_output}")
    
    # Generate missing modal styles file
    if modal_styles:
        modal_output = base_dir / "css" / "missing-modals.css"
        with open(modal_output, 'w') as f:
            f.write("/* Missing Modal Styles - Extracted from Backup */\n\n")
            for style in modal_styles[:50]:  # Limit to first 50 to avoid overwhelming
                f.write(style + "\n")
        print(f"📄 Generated: {modal_output}")
    
    # Generate missing admin styles file
    if admin_styles:
        admin_output = base_dir / "css" / "missing-admin.css"
        with open(admin_output, 'w') as f:
            f.write("/* Missing Admin Styles - Extracted from Backup */\n\n")
            for style in admin_styles[:30]:  # Limit to first 30
                f.write(style + "\n")
        print(f"📄 Generated: {admin_output}")
    
    # Generate z-index management file
    if z_index_rules:
        z_output = base_dir / "css" / "missing-z-index.css"
        with open(z_output, 'w') as f:
            f.write("/* Missing Z-Index Rules - Extracted from Backup */\n\n")
            f.write("/* Z-Index Hierarchy Found: */\n")
            for z_val in z_values:
                f.write(f"/* Level {z_val} */\n")
            f.write("\n")
            
            # Add organized z-index CSS variables
            f.write(":root {\n")
            f.write("  /* Z-Index Hierarchy */\n")
            f.write("  --z-tooltip: 10000;\n")
            f.write("  --z-modal: 9000;\n")
            f.write("  --z-dropdown: 8000;\n")
            f.write("  --z-header: 1000;\n")
            f.write("  --z-content: 1;\n")
            f.write("}\n\n")
            
            for rule in z_index_rules[:20]:  # Limit to first 20
                f.write(rule + "\n")
        print(f"📄 Generated: {z_output}")
    
    # Generate consolidated missing styles file
    consolidated_output = base_dir / "css" / "backup-missing-consolidated.css"
    with open(consolidated_output, 'w') as f:
        f.write("/* Consolidated Missing Styles - Import this file to restore missing functionality */\n\n")
        
        if header_vars:
            f.write("/* === MISSING HEADER VARIABLES === */\n")
            f.write(":root {\n")
            for var_name, var_value in sorted(header_vars.items()):
                f.write(f"  {var_name}: {var_value};\n")
            f.write("}\n\n")
        
        if modal_styles:
            f.write("/* === CRITICAL MODAL STYLES === */\n")
            for style in modal_styles[:10]:  # Top 10 most important
                f.write(style + "\n")
        
        if admin_styles:
            f.write("/* === CRITICAL ADMIN STYLES === */\n")
            for style in admin_styles[:10]:  # Top 10 most important
                f.write(style + "\n")
                
        f.write("\n/* To import all extracted styles, use: */\n")
        f.write("/* @import 'missing-header-vars.css'; */\n")
        f.write("/* @import 'missing-modals.css'; */\n")
        f.write("/* @import 'missing-admin.css'; */\n")
        f.write("/* @import 'missing-z-index.css'; */\n")
    
    print(f"📄 Generated consolidated file: {consolidated_output}")
    print("\n🎉 Extraction complete! Import the consolidated file or individual files as needed.")

if __name__ == "__main__":
    main()
